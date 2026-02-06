<?php
require_once __DIR__ . '/inc/header.php';
require_login();
?>
<div class="card">
<h1><?php echo e(t('dashboard')); ?></h1>
<div class="row">
<a class="card" href="owners.php"><?php echo e(t('owners')); ?></a>
<a class="card" href="dogs.php"><?php echo e(t('dogs')); ?></a>
<a class="card" href="events.php"><?php echo e(t('events')); ?></a>
<a class="card" href="entries.php"><?php echo e(t('entries')); ?></a>
<a class="card" href="heats.php"><?php echo e(t('heats')); ?></a>
<a class="card" href="timing.php"><?php echo e(t('timing')); ?></a>
<a class="card" href="results.php"><?php echo e(t('results')); ?></a>
<a class="card" href="katalog.php"><?php echo e(t('catalog')); ?></a>
</div>
</div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
