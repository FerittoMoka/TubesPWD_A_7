<?php

function e($teks) {
    return htmlspecialchars((string) $teks, ENT_QUOTES, "UTF-8");
}

function rupiah($angka) {
    return ((int) $angka === 0) ? "Gratis" : "Rp " . number_format($angka, 0, ",", ".");
}

function rentang_harga($min, $maks) {
    if ((int) $min === 0 && ($maks === null || (int) $maks === 0)) {
        return "Gratis";
    }
    $teks = "Rp " . number_format($min, 0, ",", ".");
    if ($maks !== null && (int) $maks > (int) $min) {
        $teks .= " - " . number_format($maks, 0, ",", ".");
    }
    return $teks;
}

function hitung_baris($conn, $tabel) {
    $r = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM " . $tabel));
    return (int) $r[0];
}

function aktif($nama, $sekarang) {
    return $nama === $sekarang ? ' class="aktif"' : "";
}

function tampil_foto($foto, $kategori, $nama) {
    if ($foto) {
        return '<div class="foto-bingkai"><img src="' . BASE_URL . 'assets/uploads/destinasi/' . e($foto) . '" alt="' . e($nama) . '" /></div>';
    }
    $kelas = "ph-" . strtolower(preg_replace("/[^a-zA-Z0-9]/", "", $kategori));
    $huruf = strtoupper(mb_substr($nama, 0, 1));
    return '<div class="foto-bingkai"><div class="ph ' . $kelas . '"><span>' . e($huruf) . '</span></div></div>';
}

function tampil_rating($rata, $jumlah) {
    if ((int) $jumlah === 0) {
        return '<span class="rating redup">Belum ada ulasan</span>';
    }
    return '<span class="rating"><span class="bintang"></span>' . number_format($rata, 1) . ' <span class="redup">(' . (int) $jumlah . ')</span></span>';
}

if (!function_exists('set_pesan')) {
    function set_pesan($jenis, $teks) {
        $_SESSION['pesan'] = [$jenis, $teks];
    }
}

if (!function_exists('tampil_pesan')) {
    function tampil_pesan() {
        if (empty($_SESSION['pesan'])) { return; }
        list($jenis, $teks) = $_SESSION['pesan'];
        unset($_SESSION['pesan']);
        $kelas = ($jenis === 'error') ? 'error' : 'sukses';
        echo '<p class="' . $kelas . '">' . e($teks) . '</p>';
    }
}