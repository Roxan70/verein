<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

$db = get_db();

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$data = [
    'name' => '',
    'company' => '',
    'email' => '',
    'phone' => '',
    'notes' => '',
    'last_contact' => '',
];
$errors = [];

if ($isEdit) {
    $stmt = $db->prepare('SELECT id, name, company, email, phone, notes, last_contact, created_at, updated_at FROM contacts WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($cid, $nameDb, $companyDb, $emailDb, $phoneDb, $notesDb, $lastContactDb, $createdAtDb, $updatedAtDb);
        if ($stmt->fetch()) {
            $data['name'] = (string)$nameDb;
            $data['company'] = (string)$companyDb;
            $data['email'] = (string)$emailDb;
            $data['phone'] = (string)$phoneDb;
            $data['notes'] = (string)$notesDb;
            $data['last_contact'] = (string)($lastContactDb ?? '');
        } else {
            $errors[] = 'Kontakt nicht gefunden.';
            $isEdit = false;
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $data['name'] = post_string('name', 150);
    $data['company'] = post_string('company', 150);
    $data['email'] = post_string('email', 190);
    $data['phone'] = post_string('phone', 60);
    $data['notes'] = post_string('notes', 2000);
    $lastRaw = post_string('last_contact', 10);
    $validLast = valid_date_or_null($lastRaw);
    $data['last_contact'] = $validLast ?? '';

    if ($data['name'] === '') {
        $errors[] = 'Name ist erforderlich.';
    }
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-Mail ist ungültig.';
    }
    if ($lastRaw !== '' && $validLast === null) {
        $errors[] = 'Datum muss im Format YYYY-MM-DD sein.';
    }

    if ($errors === []) {
        $lastParam = $data['last_contact'] !== '' ? $data['last_contact'] : null;

        if ($isEdit) {
            $stmtUpdate = $db->prepare('UPDATE contacts SET name = ?, company = ?, email = ?, phone = ?, notes = ?, last_contact = ?, updated_at = NOW() WHERE id = ?');
            if ($stmtUpdate) {
                $stmtUpdate->bind_param(
                    'ssssssi',
                    $data['name'],
                    $data['company'],
                    $data['email'],
                    $data['phone'],
                    $data['notes'],
                    $lastParam,
                    $id
                );
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        } else {
            $stmtInsert = $db->prepare('INSERT INTO contacts (name, company, email, phone, notes, last_contact) VALUES (?, ?, ?, ?, ?, ?)');
            if ($stmtInsert) {
                $stmtInsert->bind_param(
                    'ssssss',
                    $data['name'],
                    $data['company'],
                    $data['email'],
                    $data['phone'],
                    $data['notes'],
                    $lastParam
                );
                $stmtInsert->execute();
                $stmtInsert->close();
            }
        }
        redirect_to('contacts.php');
    }
}

$title = $isEdit ? 'Kontakt bearbeiten' : 'Kontakt erstellen';
$currentPage = 'contacts';
require __DIR__ . '/inc/header.php';
?>
<section>
    <h2><?= e($title) ?></h2>

    <?php foreach ($errors as $error): ?>
        <p class="alert"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="post" class="stack-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <label for="name">Name *</label>
        <input type="text" id="name" name="name" maxlength="150" required value="<?= e((string)$data['name']) ?>">

        <label for="company">Firma</label>
        <input type="text" id="company" name="company" maxlength="150" value="<?= e((string)$data['company']) ?>">

        <label for="email">E-Mail</label>
        <input type="email" id="email" name="email" maxlength="190" value="<?= e((string)$data['email']) ?>">

        <label for="phone">Telefon</label>
        <input type="text" id="phone" name="phone" maxlength="60" value="<?= e((string)$data['phone']) ?>">

        <label for="notes">Notizen</label>
        <textarea id="notes" name="notes" rows="6" maxlength="2000"><?= e((string)$data['notes']) ?></textarea>

        <label for="last_contact">Letzter Kontakt (optional)</label>
        <input type="date" id="last_contact" name="last_contact" value="<?= e((string)$data['last_contact']) ?>">

        <div class="actions-row">
            <button type="submit">Speichern</button>
            <a class="button button-muted" href="<?= e(build_url('contacts.php')) ?>">Abbrechen</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/inc/footer.php';
