<?php
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function get_page()
{
    $p = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    return $p > 0 ? $p : 1;
}

function get_offset($page, $pageSize)
{
    return ($page - 1) * $pageSize;
}

function require_role($roles)
{
    if (!isset($_SESSION['role'])) {
        header('Location: login.php');
        exit;
    }
    if (!is_array($roles)) {
        $roles = array($roles);
    }
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function parse_time_to_ms($str)
{
    $str = trim($str);
    if ($str === '') {
        return null;
    }
    if (!preg_match('/^([0-9]{1,2}):([0-9]{2})\.([0-9]{3})$/', $str, $m)) {
        return null;
    }
    return ((int)$m[1] * 60 * 1000) + ((int)$m[2] * 1000) + (int)$m[3];
}

function ms_to_time($ms)
{
    if ($ms === null || $ms === '') {
        return '';
    }
    $ms = (int)$ms;
    $min = floor($ms / 60000);
    $rest = $ms % 60000;
    $sec = floor($rest / 1000);
    $mil = $rest % 1000;
    return sprintf('%02d:%02d.%03d', $min, $sec, $mil);
}
?>
