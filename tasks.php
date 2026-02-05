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
        $stmtDelete = $db->prepare('DELETE FROM tasks WHERE id = ?');
        if ($stmtDelete) {
            $stmtDelete->bind_param('i', $id);
            $stmtDelete->execute();
            $stmtDelete->close();
        }
    }
    redirect_to('tasks.php');
}

$allowedStatus = ['', 'Backlog', 'Doing', 'Done'];
$status = get_string('status', 20);
if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

$search = get_string('q', 100);

$allowedSort = [
    'due_date' => 'due_date IS NULL, due_date ASC',
    'priority' => 'priority ASC, created_at DESC',
    'created_at' => 'created_at DESC',
];
$sort = get_string('sort', 20);
if (!isset($allowedSort[$sort])) {
    $sort = 'created_at';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $itemsPerPage;

$where = ' WHERE 1=1 ';
$types = '';
$params = [];

if ($status !== '') {
    $where .= ' AND status = ? ';
    $types .= 's';
    $params[] = $status;
}

if ($search !== '') {
    $where .= ' AND (title LIKE ? OR description LIKE ?) ';
    $searchLike = '%' . $search . '%';
    $types .= 'ss';
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$total = 0;
$sqlCount = 'SELECT COUNT(*) FROM tasks' . $where;
$stmtCount = $db->prepare($sqlCount);
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

$sqlList = 'SELECT id, title, description, status, priority, due_date, created_at, updated_at FROM tasks'
    . $where
    . ' ORDER BY ' . $allowedSort[$sort]
    . ' LIMIT ? OFFSET ?';

$stmtList = $db->prepare($sqlList);

$listTypes = $types . 'ii';
$listParams = $params;
$listParams[] = $itemsPerPage;
$listParams[] = $offset;

if ($stmtList) {
    $stmtList->bind_param($listTypes, ...$listParams);
    $stmtList->execute();
    $stmtList->bind_result($id, $titleCol, $descriptionCol, $statusCol, $priorityCol, $dueDateCol, $createdAtCol, $updatedAtCol);
}

$pageTitle = 'Tasks';
$currentPage = 'tasks';
$title = $pageTitle;
require __DIR__ . '/inc/header.php';
?>
<section>
    <div class="section-head">
        <h2>Aufgaben</h2>
        <a class="button" href="<?= e(build_url('task_edit.php')) ?>">+ Neue Aufgabe</a>
    </div>

    <form method="get" class="grid-form">
        <div>
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="">Alle</option>
                <option value="Backlog" <?= $status === 'Backlog' ? 'selected' : '' ?>>Backlog</option>
                <option value="Doing" <?= $status === 'Doing' ? 'selected' : '' ?>>Doing</option>
                <option value="Done" <?= $status === 'Done' ? 'selected' : '' ?>>Done</option>
            </select>
        </div>
        <div>
            <label for="q">Suche</label>
            <input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="Titel oder Beschreibung">
        </div>
        <div>
            <label for="sort">Sortierung</label>
            <select name="sort" id="sort">
                <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Neueste</option>
                <option value="due_date" <?= $sort === 'due_date' ? 'selected' : '' ?>>Fälligkeit</option>
                <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priorität</option>
            </select>
        </div>
        <div class="actions-row">
            <button type="submit">Filtern</button>
            <a class="button button-muted" href="<?= e(build_url('tasks.php')) ?>">Reset</a>
        </div>
    </form>

    <p class="muted">Treffer: <?= $total ?></p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Status</th>
                    <th>Priorität</th>
                    <th>Fällig</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($stmtList) {
                    while ($stmtList->fetch()) {
                        echo '<tr>';
                        echo '<td><strong>' . e((string)$titleCol) . '</strong><br><span class="muted">' . e((string)$descriptionCol) . '</span></td>';
                        echo '<td>' . e((string)$statusCol) . '</td>';
                        echo '<td>' . (int)$priorityCol . '</td>';
                        echo '<td>' . e((string)($dueDateCol ?? '-')) . '</td>';
                        echo '<td><a class="button button-small" href="' . e(build_url('task_edit.php', ['id' => (int)$id])) . '">Bearbeiten</a> '
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
        $baseParams = ['status' => $status, 'q' => $search, 'sort' => $sort];
        if ($page > 1) {
            echo '<a class="button button-small" href="' . e(build_url('tasks.php', $baseParams + ['page' => $page - 1])) . '">← Zurück</a>';
        }
        echo '<span>Seite ' . $page . ' von ' . $totalPages . '</span>';
        if ($page < $totalPages) {
            echo '<a class="button button-small" href="' . e(build_url('tasks.php', $baseParams + ['page' => $page + 1])) . '">Weiter →</a>';
        }
        ?>
    </div>
</section>
<?php require __DIR__ . '/inc/footer.php';
