<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';

$lang = current_lang($mysqli);
$I18N = load_lang($lang);
?>
<!doctype html>
<html lang="<?php echo e($lang); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo e(APP_NAME); ?></title>
<link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<header class="topbar">
  <div><strong><?php echo e(APP_NAME); ?></strong></div>
  <?php if (!empty($_SESSION['user_id'])): ?>
  <nav>
    <a href="dashboard.php"><?php echo e(t('dashboard')); ?></a>
    <a href="events.php"><?php echo e(t('events')); ?></a>
    <a href="entries.php"><?php echo e(t('entries')); ?></a>
    <a href="results.php"><?php echo e(t('results')); ?></a>
    <a href="logout.php"><?php echo e(t('logout')); ?></a>
  </nav>
  <?php endif; ?>
</header>
<main class="container">
