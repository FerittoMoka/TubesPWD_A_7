<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$root = str_replace("\\", "/", realpath(__DIR__ . "/.."));
$docroot = str_replace("\\", "/", (string) realpath($_SERVER["DOCUMENT_ROOT"]));
$base = "/";
if ($docroot !== "" && stripos($root, $docroot) === 0) {
    $base = substr($root, strlen($docroot)) . "/";
}
define("BASE_URL", $base);

function sudah_login() {
    return isset($_SESSION["user_id"]);
}

function cek_login() {
    if (!sudah_login()) {
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
}

function cek_admin() {
    cek_login();
    if ($_SESSION["role"] !== "admin") {
        header("Location: " . BASE_URL . "index.php");
        exit;
    }
}

require_once __DIR__ . "/fungsi.php";
