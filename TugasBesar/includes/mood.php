<?php
require_once __DIR__ . "/wilayah.php";

// Daftar mood & waktu kunjungan. Kunci = nilai yang disimpan di kolom destinations.tag.
// Dipakai bersama oleh index.php (filter beranda) dan admin/destinasi.php (form tag).
if (!function_exists("daftar_mood")) {
    function daftar_mood() {
        return array(
            "sunset"     => "Cocok untuk Sunset",
            "sunrise"    => "Berburu Sunrise",
            "keluarga"   => "Wisata Keluarga & Edukasi",
            "hidden-gem" => "Hidden Gem Alam",
            "malam"      => "Seru di Malam Hari",
        );
    }
}
