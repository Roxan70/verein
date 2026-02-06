<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/i18n.php';
$lang = current_lang($mysqli);
$I18N = load_lang($lang);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = isset($_POST['username']) ? trim($_POST['username']) : '';
    $p = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $stmt = $mysqli->prepare('SELECT user_id, password_hash, role, preferred_lang FROM users WHERE username=? LIMIT 1');
    $stmt->bind_param('s', $u);
    $stmt->execute();
    $stmt->bind_result($id, $hash, $role, $pref);
    if ($stmt->fetch() && password_verify($p, $hash)) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = $u;
        $_SESSION['role'] = $role;
        $_SESSION['lang'] = $pref;
        $stmt->close();
        header('Location: dashboard.php');
        exit;
    }
    $stmt->close();
    $error = 'Invalid login';
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/styles.css"></head><body><main class="container"><div class="card"><h1><?php echo e(t('login')); ?></h1><?php if ($error): ?><p><?php echo e($error); ?></p><?php endif; ?><form method="post"><label><?php echo e(t('username')); ?><input name="username" required></label><label><?php echo e(t('password')); ?><input type="password" name="password" required></label><button><?php echo e(t('login')); ?></button></form></div></main></body></html>
