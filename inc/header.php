<?php

declare(strict_types=1);

require_once __DIR__ . '/security.php';

$title = $title ?? 'Personal Ops Dashboard';
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(build_url('assets/styles.css')) ?>">
</head>
<body>
<header class="topbar">
    <div class="container nav-wrap">
        <h1 class="logo">Personal Ops Dashboard</h1>
        <nav>
            <a class="nav-link<?= $currentPage === 'dashboard' ? ' active' : '' ?>" href="<?= e(build_url('index.php')) ?>">Dashboard</a>
            <a class="nav-link<?= $currentPage === 'tasks' ? ' active' : '' ?>" href="<?= e(build_url('tasks.php')) ?>">Tasks</a>
            <a class="nav-link<?= $currentPage === 'contacts' ? ' active' : '' ?>" href="<?= e(build_url('contacts.php')) ?>">Kontakte</a>
            <a class="nav-link<?= $currentPage === 'import' ? ' active' : '' ?>" href="<?= e(build_url('import.php')) ?>">Import</a>
        </nav>
    </div>
</header>
<main class="container">
