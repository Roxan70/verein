<?php
require_once __DIR__ . '/config.php';

function lang_allowed($lang)
{
    $arr = explode(',', APP_LANGS);
    return in_array($lang, $arr, true);
}

function current_lang($mysqli = null)
{
    if (isset($_GET['lang']) && lang_allowed($_GET['lang'])) {
        $_SESSION['lang'] = $_GET['lang'];
        if (!empty($_SESSION['user_id']) && $mysqli instanceof mysqli) {
            $stmt = $mysqli->prepare('UPDATE users SET preferred_lang=? WHERE user_id=?');
            $stmt->bind_param('si', $_GET['lang'], $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    if (!empty($_SESSION['lang']) && lang_allowed($_SESSION['lang'])) {
        return $_SESSION['lang'];
    }

    if (!empty($_SESSION['user_id']) && $mysqli instanceof mysqli) {
        $stmt = $mysqli->prepare('SELECT preferred_lang FROM users WHERE user_id=? LIMIT 1');
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($pref);
        if ($stmt->fetch() && lang_allowed($pref)) {
            $_SESSION['lang'] = $pref;
            $stmt->close();
            return $pref;
        }
        $stmt->close();
    }
    return APP_DEFAULT_LANG;
}

function t($key)
{
    global $I18N;
    if (isset($I18N[$key])) {
        return $I18N[$key];
    }
    return $key;
}

function load_lang($lang)
{
    $file = __DIR__ . '/../lang/' . $lang . '.php';
    if (!file_exists($file)) {
        $file = __DIR__ . '/../lang/de.php';
    }
    return require $file;
}
?>
