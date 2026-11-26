<?php
// Daftar wilayah (kabupaten/kota di DIY) dan status publikasi destinasi.
// Dipakai form admin, filter beranda, dan halaman lain agar pilihannya selalu seragam.
if (!function_exists('daftar_wilayah')) {
    function daftar_wilayah() {
        return array("Kota Yogyakarta", "Sleman", "Bantul", "Gunungkidul", "Kulon Progo");
    }
}
if (!function_exists('daftar_status')) {
    function daftar_status() {
        return array(
            "aktif" => "Aktif (tampil di situs)",
            "arsip" => "Arsip (disembunyikan, data tetap tersimpan)",
        );
    }
}