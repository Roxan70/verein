<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

$db = get_db();

if (!isset($_SESSION['import_state']) || !is_array($_SESSION['import_state'])) {
    $_SESSION['import_state'] = [];
}

function detect_separator(string $line): string
{
    $candidates = [',', ';', "\t"];
    $best = ',';
    $bestCount = -1;

    foreach ($candidates as $sep) {
        $count = substr_count($line, $sep);
        if ($count > $bestCount) {
            $bestCount = $count;
            $best = $sep;
        }
    }

    return $best;
}

function parse_preview_rows(string $filePath, string $separator, int $maxRows = 10): array
{
    $rows = [];
    $fh = fopen($filePath, 'rb');
    if ($fh === false) {
        return $rows;
    }

    $rowCount = 0;
    while (($row = fgetcsv($fh, 0, $separator)) !== false) {
        $rows[] = $row;
        $rowCount++;
        if ($rowCount >= $maxRows) {
            break;
        }
    }

    fclose($fh);
    return $rows;
}

function normalize_header(string $text): string
{
    $text = strtolower(trim($text));
    $text = str_replace([' ', '-'], '_', $text);
    return preg_replace('/[^a-z0-9_]/', '', $text) ?? '';
}

function auto_map_headers(array $headers, array $allowedFields): array
{
    $mapping = [];
    foreach ($allowedFields as $field) {
        $mapping[$field] = '';
    }

    foreach ($headers as $index => $header) {
        $normalized = normalize_header((string)$header);
        foreach ($allowedFields as $field) {
            if ($normalized === $field) {
                $mapping[$field] = (string)$index;
                continue 2;
            }
        }

        $aliases = [
            'name' => ['fullname', 'kontaktname'],
            'company' => ['firma'],
            'notes' => ['note', 'notiz'],
            'due_date' => ['due', 'faelligkeit', 'deadline'],
            'last_contact' => ['lastcontact', 'letzter_kontakt'],
            'description' => ['desc', 'details'],
        ];

        foreach ($aliases as $field => $aliasList) {
            if (in_array($normalized, $aliasList, true) && isset($mapping[$field])) {
                $mapping[$field] = (string)$index;
                continue 2;
            }
        }
    }

    return $mapping;
}

$tabs = ['tasks', 'contacts'];
$tab = get_string('tab', 20);
if (!in_array($tab, $tabs, true)) {
    $tab = 'tasks';
}

$report = null;
$messages = [];

$definitions = [
    'tasks' => [
        'label' => 'Tasks importieren',
        'fields' => ['title', 'description', 'status', 'priority', 'due_date'],
    ],
    'contacts' => [
        'label' => 'Kontakte importieren',
        'fields' => ['name', 'company', 'email', 'phone', 'notes', 'last_contact'],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $action = post_string('action', 30);
    $type = post_string('type', 20);

    if (!isset($definitions[$type])) {
        $messages[] = 'Ungültiger Import-Typ.';
    } elseif ($action === 'upload_preview') {
        $tab = $type;
        $sepChoice = post_string('separator', 5);

        if (!isset($_FILES['csv_file']) || (int)$_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $messages[] = 'Bitte eine gültige CSV-Datei hochladen.';
        } else {
            $tmpPath = (string)$_FILES['csv_file']['tmp_name'];
            $firstLine = (string)file_get_contents($tmpPath, false, null, 0, 2048);
            $detected = detect_separator($firstLine);
            if ($sepChoice === 'tab') {
                $sepChoice = "\t";
            }
            $separator = $sepChoice !== '' ? $sepChoice : $detected;

            $sessionFile = sys_get_temp_dir() . '/pod_import_' . bin2hex(random_bytes(8)) . '.csv';
            if (!move_uploaded_file($tmpPath, $sessionFile)) {
                $messages[] = 'Datei konnte nicht verarbeitet werden.';
            } else {
                $previewRows = parse_preview_rows($sessionFile, $separator, 10);
                if ($previewRows === []) {
                    $messages[] = 'CSV enthält keine lesbaren Daten.';
                } else {
                    $hasHeader = isset($_POST['has_header']) && $_POST['has_header'] === '1';
                    $headers = $hasHeader ? $previewRows[0] : [];
                    $mapping = $hasHeader
                        ? auto_map_headers($headers, $definitions[$type]['fields'])
                        : array_fill_keys($definitions[$type]['fields'], '');

                    $_SESSION['import_state'][$type] = [
                        'file' => $sessionFile,
                        'separator' => $separator,
                        'has_header' => $hasHeader,
                        'headers' => $headers,
                        'preview' => $previewRows,
                        'mapping' => $mapping,
                    ];
                    $messages[] = 'Vorschau erstellt. Mapping prüfen und Import starten.';
                }
            }
        }
    } elseif ($action === 'run_import') {
        $tab = $type;
        $state = $_SESSION['import_state'][$type] ?? null;
        if (!is_array($state) || empty($state['file']) || !file_exists((string)$state['file'])) {
            $messages[] = 'Keine Import-Datei vorhanden. Bitte erneut hochladen.';
        } else {
            $mapping = [];
            foreach ($definitions[$type]['fields'] as $field) {
                $idx = trim((string)($_POST['map_' . $field] ?? ''));
                $mapping[$field] = $idx;
            }

            $imported = 0;
            $skipped = 0;
            $errors = [];

            $filePath = (string)$state['file'];
            $separator = (string)$state['separator'];
            $hasHeader = (bool)$state['has_header'];

            $fh = fopen($filePath, 'rb');
            if ($fh === false) {
                $messages[] = 'Datei konnte nicht gelesen werden.';
            } else {
                if ($type === 'tasks') {
                    $insertStmt = $db->prepare('INSERT INTO tasks (title, description, status, priority, due_date) VALUES (?, ?, ?, ?, ?)');
                } else {
                    $insertStmt = $db->prepare('INSERT INTO contacts (name, company, email, phone, notes, last_contact) VALUES (?, ?, ?, ?, ?, ?)');
                }

                $rowNumber = 0;
                while (($row = fgetcsv($fh, 0, $separator)) !== false) {
                    $rowNumber++;
                    if ($hasHeader && $rowNumber === 1) {
                        continue;
                    }

                    $get = static function (array $arr, string $idx): string {
                        if ($idx === '' || !ctype_digit($idx)) {
                            return '';
                        }
                        $pos = (int)$idx;
                        return isset($arr[$pos]) ? trim((string)$arr[$pos]) : '';
                    };

                    if ($type === 'tasks') {
                        $titleVal = $get($row, $mapping['title']);
                        $descriptionVal = $get($row, $mapping['description']);
                        $statusVal = $get($row, $mapping['status']);
                        $priorityVal = $get($row, $mapping['priority']);
                        $dueVal = $get($row, $mapping['due_date']);

                        if ($titleVal === '') {
                            $skipped++;
                            if (count($errors) < 50) {
                                $errors[] = 'Zeile ' . $rowNumber . ': Titel fehlt.';
                            }
                            continue;
                        }
                        if (!in_array($statusVal, ['Backlog', 'Doing', 'Done'], true)) {
                            $skipped++;
                            if (count($errors) < 50) {
                                $errors[] = 'Zeile ' . $rowNumber . ': ungültiger Status.';
                            }
                            continue;
                        }
                        if (!in_array($priorityVal, ['1', '2', '3'], true)) {
                            $skipped++;
                            if (count($errors) < 50) {
                                $errors[] = 'Zeile ' . $rowNumber . ': ungültige Priorität.';
                            }
                            continue;
                        }

                        $dueClean = valid_date_or_null($dueVal);
                        if ($dueVal !== '' && $dueClean === null) {
                            $dueClean = null;
                        }

                        $priorityInt = (int)$priorityVal;
                        if ($insertStmt) {
                            $insertStmt->bind_param('sssis', $titleVal, $descriptionVal, $statusVal, $priorityInt, $dueClean);
                            if ($insertStmt->execute()) {
                                $imported++;
                            } else {
                                $skipped++;
                                if (count($errors) < 50) {
                                    $errors[] = 'Zeile ' . $rowNumber . ': DB-Fehler beim Speichern.';
                                }
                            }
                        }
                    } else {
                        $nameVal = $get($row, $mapping['name']);
                        $companyVal = $get($row, $mapping['company']);
                        $emailVal = $get($row, $mapping['email']);
                        $phoneVal = $get($row, $mapping['phone']);
                        $notesVal = $get($row, $mapping['notes']);
                        $lastVal = $get($row, $mapping['last_contact']);

                        if ($nameVal === '') {
                            $skipped++;
                            if (count($errors) < 50) {
                                $errors[] = 'Zeile ' . $rowNumber . ': Name fehlt.';
                            }
                            continue;
                        }

                        if ($emailVal !== '' && !filter_var($emailVal, FILTER_VALIDATE_EMAIL)) {
                            $skipped++;
                            if (count($errors) < 50) {
                                $errors[] = 'Zeile ' . $rowNumber . ': ungültige E-Mail.';
                            }
                            continue;
                        }

                        $lastClean = valid_date_or_null($lastVal);
                        if ($lastVal !== '' && $lastClean === null) {
                            $lastClean = null;
                        }

                        if ($insertStmt) {
                            $insertStmt->bind_param('ssssss', $nameVal, $companyVal, $emailVal, $phoneVal, $notesVal, $lastClean);
                            if ($insertStmt->execute()) {
                                $imported++;
                            } else {
                                $skipped++;
                                if (count($errors) < 50) {
                                    $errors[] = 'Zeile ' . $rowNumber . ': DB-Fehler beim Speichern.';
                                }
                            }
                        }
                    }
                }

                if ($insertStmt) {
                    $insertStmt->close();
                }
                fclose($fh);

                $report = [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ];
            }
        }
    }
}

$title = 'CSV Import';
$currentPage = 'import';
require __DIR__ . '/inc/header.php';
?>
<section>
    <h2>CSV-Import</h2>

    <?php foreach ($messages as $message): ?>
        <p class="alert"><?= e($message) ?></p>
    <?php endforeach; ?>

    <?php if (is_array($report)): ?>
        <div class="card">
            <h3>Import-Report</h3>
            <p>Importiert: <strong><?= (int)$report['imported'] ?></strong></p>
            <p>Übersprungen: <strong><?= (int)$report['skipped'] ?></strong></p>
            <?php if (!empty($report['errors'])): ?>
                <ul>
                    <?php foreach ($report['errors'] as $error): ?>
                        <li><?= e((string)$error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="tab-buttons">
        <a class="button<?= $tab === 'tasks' ? '' : ' button-muted' ?>" href="<?= e(build_url('import.php', ['tab' => 'tasks'])) ?>">Tasks importieren</a>
        <a class="button<?= $tab === 'contacts' ? '' : ' button-muted' ?>" href="<?= e(build_url('import.php', ['tab' => 'contacts'])) ?>">Kontakte importieren</a>
    </div>

    <?php
    $state = $_SESSION['import_state'][$tab] ?? null;
    $fields = $definitions[$tab]['fields'];
    $previewRows = is_array($state['preview'] ?? null) ? $state['preview'] : [];
    $maxCols = 0;
    foreach ($previewRows as $row) {
        if (is_array($row) && count($row) > $maxCols) {
            $maxCols = count($row);
        }
    }
    ?>

    <div class="card">
        <h3><?= e($definitions[$tab]['label']) ?></h3>
        <form method="post" enctype="multipart/form-data" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="upload_preview">
            <input type="hidden" name="type" value="<?= e($tab) ?>">

            <label for="csv_file">CSV-Datei (UTF-8)</label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required>

            <label for="separator">Separator (leer = automatisch)</label>
            <select name="separator" id="separator">
                <option value="">Automatisch</option>
                <option value=",">Komma (,)</option>
                <option value=";">Semikolon (;)</option>
                <option value="tab">Tab (\t)</option>
            </select>

            <label><input type="checkbox" name="has_header" value="1" checked> Erste Zeile ist Header</label>
            <button type="submit">Vorschau laden</button>
        </form>
    </div>

    <?php if (is_array($state) && $previewRows !== []): ?>
        <div class="card">
            <h3>Vorschau (erste 10 Zeilen)</h3>
            <div class="table-wrap">
                <table>
                    <tbody>
                    <?php foreach ($previewRows as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?= e((string)$cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3>Mapping</h3>
            <form method="post" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="run_import">
                <input type="hidden" name="type" value="<?= e($tab) ?>">

                <?php foreach ($fields as $field): ?>
                    <label for="map_<?= e($field) ?>"><?= e($field) ?></label>
                    <select name="map_<?= e($field) ?>" id="map_<?= e($field) ?>">
                        <option value="">-- nicht zuordnen --</option>
                        <?php for ($i = 0; $i < $maxCols; $i++): ?>
                            <?php
                            $headerText = isset($state['headers'][$i]) ? (string)$state['headers'][$i] : ('Spalte ' . ($i + 1));
                            $selected = ((string)($state['mapping'][$field] ?? '') === (string)$i) ? 'selected' : '';
                            ?>
                            <option value="<?= $i ?>" <?= $selected ?>><?= e($headerText) ?> (<?= $i ?>)</option>
                        <?php endfor; ?>
                    </select>
                <?php endforeach; ?>

                <button type="submit">Import starten</button>
            </form>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/inc/footer.php';
