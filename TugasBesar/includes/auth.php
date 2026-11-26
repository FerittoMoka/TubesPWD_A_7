<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * BASE_URL: alamat dasar proyek, mis. "/TugasBesar/".
 * Dihitung dari alamat skrip yang sedang berjalan (SCRIPT_NAME) dikurangi posisi skrip itu di dalam proyek,
 * sehingga tetap benar walaupun folder proyek berupa junction/symlink ke lokasi lain (mis. OneDrive).
 */
$root  = str_replace("\\", "/", (string) realpath(__DIR__ . "/.."));
$skrip = str_replace("\\", "/", (string) realpath($_SERVER["SCRIPT_FILENAME"] ?? ""));
$nama  = str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "");
$base  = "/";

if ($root !== "" && $skrip !== "" && stripos($skrip, $root) === 0) {
    $relatif = substr($skrip, strlen($root)); // mis. "/index.php" atau "/auth/login.php"
    if ($relatif !== "" && substr($nama, -strlen($relatif)) === $relatif) {
        $base = substr($nama, 0, strlen($nama) - strlen($relatif)) . "/";
    }
}
// Cadangan: cara lama (proyek berada langsung di dalam DOCUMENT_ROOT)
if ($base === "/") {
    $docroot = str_replace("\\", "/", (string) realpath($_SERVER["DOCUMENT_ROOT"] ?? ""));
    if ($docroot !== "" && stripos($root, $docroot) === 0) {
        $base = substr($root, strlen($docroot)) . "/";
    }
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