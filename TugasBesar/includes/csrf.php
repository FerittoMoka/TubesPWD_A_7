<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Dibungkus function_exists agar tidak bentrok dengan e() di includes/fungsi.php
if (!function_exists('e')) {
    function e($text) {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}

// Mengeluarkan input hidden token CSRF (nama field: csrf_token)
if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . e(generate_csrf_token()) . '" />';
    }
}
?>