<?php
// Satu sumber daftar wilayah (kabupaten/kota DIY) untuk seluruh halaman.
// Kunci = slug untuk URL/atribut data, nilai = isi kolom destinations.wilayah.
if (!function_exists('daftar_wilayah')) {
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

// Daftar nama wilayah saja (dipakai formulir yang menyimpan nama, bukan slug).
if (!function_exists('label_wilayah')) {
    function label_wilayah() {
        return array_values(daftar_wilayah());
    }
}

if (!function_exists('slug_wilayah')) {
    function slug_wilayah($label) {
        $s = array_search((string) $label, daftar_wilayah(), true);
        return $s === false ? "" : $s;
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
