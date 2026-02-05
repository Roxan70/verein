<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

$db = get_db();

$counts = [
    'Backlog' => 0,
    'Doing' => 0,
    'Done' => 0,
    'contacts' => 0,
];

$stmt = $db->prepare('SELECT status, COUNT(*) AS cnt FROM tasks GROUP BY status');
if ($stmt) {
    $stmt->execute();
    $stmt->bind_result($status, $cnt);
    while ($stmt->fetch()) {
        if (isset($counts[$status])) {
            $counts[$status] = (int)$cnt;
        }
    }
    $stmt->close();
}

$stmtContacts = $db->prepare('SELECT COUNT(*) FROM contacts');
if ($stmtContacts) {
    $stmtContacts->execute();
    $stmtContacts->bind_result($contactsCnt);
    if ($stmtContacts->fetch()) {
        $counts['contacts'] = (int)$contactsCnt;
    }
    $stmtContacts->close();
}

$title = 'Dashboard';
$currentPage = 'dashboard';
require __DIR__ . '/inc/header.php';
?>
<section>
    <h2>Willkommen</h2>
    <p class="lead">Behalte Aufgaben und Kontakte an einem Ort im Blick.</p>
</section>
<section class="cards">
    <article class="card">
        <h3>Backlog</h3>
        <p class="number"><?= (int)$counts['Backlog'] ?></p>
    </article>
    <article class="card">
        <h3>Doing</h3>
        <p class="number"><?= (int)$counts['Doing'] ?></p>
    </article>
    <article class="card">
        <h3>Done</h3>
        <p class="number"><?= (int)$counts['Done'] ?></p>
    </article>
    <article class="card">
        <h3>Kontakte gesamt</h3>
        <p class="number"><?= (int)$counts['contacts'] ?></p>
    </article>
</section>
<?php require __DIR__ . '/inc/footer.php';
