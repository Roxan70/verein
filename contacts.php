<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

$db = get_db();
$config = require __DIR__ . '/inc/config.php';
$itemsPerPage = (int)($config['items_per_page'] ?? 25);
if ($itemsPerPage < 1) {
    $itemsPerPage = 25;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf_or_die();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmtDelete = $db->prepare('DELETE FROM contacts WHERE id = ?');
        if ($stmtDelete) {
            $stmtDelete->bind_param('i', $id);
            $stmtDelete->execute();
            $stmtDelete->close();
        }
    }
    redirect_to('contacts.php');
}

$search = get_string('q', 100);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $itemsPerPage;

$where = ' WHERE 1=1 ';
$types = '';
$params = [];

if ($search !== '') {
    $where .= ' AND (name LIKE ? OR company LIKE ? OR notes LIKE ?) ';
    $searchLike = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$total = 0;
$stmtCount = $db->prepare('SELECT COUNT(*) FROM contacts' . $where);
if ($stmtCount) {
    if ($types !== '') {
        $stmtCount->bind_param($types, ...$params);
    }
    $stmtCount->execute();
    $stmtCount->bind_result($totalRows);
    if ($stmtCount->fetch()) {
        $total = (int)$totalRows;
    }
    $stmtCount->close();
}

$totalPages = max(1, (int)ceil($total / $itemsPerPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $itemsPerPage;
}

$stmtList = $db->prepare(
    'SELECT id, name, company, email, phone, notes, last_contact, created_at, updated_at FROM contacts'
    . $where
    . ' ORDER BY created_at DESC LIMIT ? OFFSET ?'
);

$listTypes = $types . 'ii';
$listParams = $params;
$listParams[] = $itemsPerPage;
$listParams[] = $offset;

if ($stmtList) {
    $stmtList->bind_param($listTypes, ...$listParams);
    $stmtList->execute();
    $stmtList->bind_result($id, $nameCol, $companyCol, $emailCol, $phoneCol, $notesCol, $lastContactCol, $createdAtCol, $updatedAtCol);
}

$title = 'Kontakte';
$currentPage = 'contacts';
require __DIR__ . '/inc/header.php';
?>
<section>
    <div class="section-head">
        <h2>Kontakte</h2>
        <a class="button" href="<?= e(build_url('contact_edit.php')) ?>">+ Neuer Kontakt</a>
    </div>

    <form method="get" class="grid-form">
        <div>
            <label for="q">Suche</label>
            <input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="Name, Firma, Notizen">
        </div>
        <div class="actions-row">
            <button type="submit">Suchen</button>
            <a class="button button-muted" href="<?= e(build_url('contacts.php')) ?>">Reset</a>
        </div>
    </form>

    <p class="muted">Treffer: <?= $total ?></p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Firma</th>
                    <th>Kontakt</th>
                    <th>Letzter Kontakt</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($stmtList) {
                    while ($stmtList->fetch()) {
                        echo '<tr>';
                        echo '<td><strong>' . e((string)$nameCol) . '</strong><br><span class="muted">' . e((string)$notesCol) . '</span></td>';
                        echo '<td>' . e((string)$companyCol) . '</td>';
                        echo '<td>' . e((string)$emailCol) . '<br>' . e((string)$phoneCol) . '</td>';
                        echo '<td>' . e((string)($lastContactCol ?? '-')) . '</td>';
                        echo '<td><a class="button button-small" href="' . e(build_url('contact_edit.php', ['id' => (int)$id])) . '">Bearbeiten</a> '
                            . '<form method="post" class="inline-form" onsubmit="return confirm(\'Wirklich löschen?\');">'
                            . '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
                            . '<input type="hidden" name="action" value="delete">'
                            . '<input type="hidden" name="id" value="' . (int)$id . '">'
                            . '<button type="submit" class="button button-small button-danger">Löschen</button>'
                            . '</form></td>';
                        echo '</tr>';
                    }
                    $stmtList->close();
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php
        $baseParams = ['q' => $search];
        if ($page > 1) {
            echo '<a class="button button-small" href="' . e(build_url('contacts.php', $baseParams + ['page' => $page - 1])) . '">← Zurück</a>';
        }
        echo '<span>Seite ' . $page . ' von ' . $totalPages . '</span>';
        if ($page < $totalPages) {
            echo '<a class="button button-small" href="' . e(build_url('contacts.php', $baseParams + ['page' => $page + 1])) . '">Weiter →</a>';
        }
        ?>
    </div>
</section>
<?php require __DIR__ . '/inc/footer.php';
