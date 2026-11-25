<?php
// Pesan sekali tampil (flash). Dipakai bersama oleh detail.php, review/*, dan admin/*
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('set_pesan')) {
    function set_pesan($jenis, $teks) {
        $_SESSION['pesan'] = array($jenis === 'error' ? 'error' : 'sukses', $teks);
    }
}

if (!function_exists('tampil_pesan')) {
    function tampil_pesan() {
        if (empty($_SESSION['pesan'])) { return; }
        list($jenis, $teks) = $_SESSION['pesan'];
        unset($_SESSION['pesan']);
        $htmlTeks = htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
        echo '<p class="' . ($jenis === 'error' ? 'error' : 'sukses') . '">' . $htmlTeks . '</p>';
    }
}