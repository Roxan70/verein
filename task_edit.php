<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

$db = get_db();
$allowedStatus = ['Backlog', 'Doing', 'Done'];

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

$data = [
    'title' => '',
    'description' => '',
    'status' => 'Backlog',
    'priority' => 2,
    'due_date' => '',
];
$errors = [];

if ($isEdit) {
    $stmt = $db->prepare('SELECT id, title, description, status, priority, due_date, created_at, updated_at FROM tasks WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($taskId, $titleDb, $descriptionDb, $statusDb, $priorityDb, $dueDateDb, $createdAtDb, $updatedAtDb);
        if ($stmt->fetch()) {
            $data['title'] = (string)$titleDb;
            $data['description'] = (string)$descriptionDb;
            $data['status'] = (string)$statusDb;
            $data['priority'] = (int)$priorityDb;
            $data['due_date'] = (string)($dueDateDb ?? '');
        } else {
            $errors[] = 'Aufgabe nicht gefunden.';
            $isEdit = false;
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $data['title'] = post_string('title', 200);
    $data['description'] = post_string('description', 2000);
    $data['status'] = post_string('status', 20);
    $data['priority'] = (int)($_POST['priority'] ?? 0);
    $dueRaw = post_string('due_date', 10);
    $validatedDate = valid_date_or_null($dueRaw);
    $data['due_date'] = $validatedDate ?? '';

    if ($data['title'] === '') {
        $errors[] = 'Titel ist erforderlich.';
    }
    if (!in_array($data['status'], $allowedStatus, true)) {
        $errors[] = 'Ungültiger Status.';
    }
    if ($data['priority'] < 1 || $data['priority'] > 3) {
        $errors[] = 'Priorität muss zwischen 1 und 3 liegen.';
    }
    if ($dueRaw !== '' && $validatedDate === null) {
        $errors[] = 'Datum muss im Format YYYY-MM-DD sein.';
    }

    if ($errors === []) {
        $dueDateParam = $data['due_date'] !== '' ? $data['due_date'] : null;

        if ($isEdit) {
            $stmtUpdate = $db->prepare('UPDATE tasks SET title = ?, description = ?, status = ?, priority = ?, due_date = ?, updated_at = NOW() WHERE id = ?');
            if ($stmtUpdate) {
                $stmtUpdate->bind_param(
                    'sssisi',
                    $data['title'],
                    $data['description'],
                    $data['status'],
                    $data['priority'],
                    $dueDateParam,
                    $id
                );
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        } else {
            $stmtInsert = $db->prepare('INSERT INTO tasks (title, description, status, priority, due_date) VALUES (?, ?, ?, ?, ?)');
            if ($stmtInsert) {
                $stmtInsert->bind_param(
                    'sssis',
                    $data['title'],
                    $data['description'],
                    $data['status'],
                    $data['priority'],
                    $dueDateParam
                );
                $stmtInsert->execute();
                $stmtInsert->close();
            }
        }
        redirect_to('tasks.php');
    }
}

$title = $isEdit ? 'Aufgabe bearbeiten' : 'Aufgabe erstellen';
$currentPage = 'tasks';
require __DIR__ . '/inc/header.php';
?>
<section>
    <h2><?= e($title) ?></h2>

    <?php foreach ($errors as $error): ?>
        <p class="alert"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="post" class="stack-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <label for="title">Titel *</label>
        <input type="text" id="title" name="title" maxlength="200" required value="<?= e((string)$data['title']) ?>">

        <label for="description">Beschreibung</label>
        <textarea id="description" name="description" rows="6" maxlength="2000"><?= e((string)$data['description']) ?></textarea>

        <label for="status">Status *</label>
        <select name="status" id="status" required>
            <?php foreach ($allowedStatus as $status): ?>
                <option value="<?= e($status) ?>" <?= $data['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="priority">Priorität (1-3) *</label>
        <select name="priority" id="priority" required>
            <option value="1" <?= (int)$data['priority'] === 1 ? 'selected' : '' ?>>1 - Hoch</option>
            <option value="2" <?= (int)$data['priority'] === 2 ? 'selected' : '' ?>>2 - Mittel</option>
            <option value="3" <?= (int)$data['priority'] === 3 ? 'selected' : '' ?>>3 - Niedrig</option>
        </select>

        <label for="due_date">Fälligkeitsdatum (optional)</label>
        <input type="date" id="due_date" name="due_date" value="<?= e((string)$data['due_date']) ?>">

        <div class="actions-row">
            <button type="submit">Speichern</button>
            <a class="button button-muted" href="<?= e(build_url('tasks.php')) ?>">Abbrechen</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/inc/footer.php';
