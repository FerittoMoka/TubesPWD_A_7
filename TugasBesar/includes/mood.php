<?php
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

// Wilayah (kabupaten/kota). Kunci = slug untuk URL dan atribut data, nilai = isi ENUM kolom destinations.wilayah.
if (!function_exists("daftar_wilayah")) {
    function daftar_wilayah() {
        return array(
            "kota-yogyakarta" => "Kota Yogyakarta",
            "sleman"          => "Sleman",
            "bantul"          => "Bantul",
            "gunungkidul"     => "Gunungkidul",
            "kulon-progo"     => "Kulon Progo",
        );
    }
}
if (!function_exists("slug_wilayah")) {
    function slug_wilayah($label) {
        $s = array_search((string) $label, daftar_wilayah(), true);
        return $s === false ? "" : $s;
    }
}