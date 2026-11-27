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

/* Cek keberadaan file foto di folder upload (bukan hanya nama di database). */
function foto_ada($foto) {
    if ($foto === null || $foto === "") { return false; }
    $berkas = basename((string) $foto);
    if ($berkas === "" || $berkas === "." || $berkas === "..") { return false; }
    return is_file(__DIR__ . "/../assets/uploads/destinasi/" . $berkas);
}

/* Kartu monogram tipografi: dipakai saat foto belum diunggah atau file hilang. */
function tampil_monogram($kategori, $nama) {
    $kelas = "ph-" . strtolower(preg_replace("/[^a-zA-Z0-9]/", "", (string) $kategori));
    $huruf = mb_strtoupper(mb_substr((string) $nama, 0, 1, "UTF-8"), "UTF-8");
    return '<div class="foto-bingkai"><div class="ph ' . $kelas . '"><span>' . e($huruf) . '</span></div></div>';
}

function tampil_foto($foto, $kategori, $nama) {
    if (!foto_ada($foto)) {
        return tampil_monogram($kategori, $nama);
    }
    return '<div class="foto-bingkai"><img src="' . BASE_URL . 'assets/uploads/destinasi/' . rawurlencode(basename((string) $foto)) . '" alt="Foto ' . e($nama) . '" width="600" height="400" /></div>';
}

/*
 * Daftar foto galeri satu destinasi.
 * Konvensi folder: assets/uploads/destinasi/galeri/<id>/ (jpg, jpeg, png, webp),
 * diurutkan menurut nama file sehingga 1.jpg tampil lebih dulu.
 */
function galeri_destinasi($id) {
    $id = (int) $id;
    $hasil = array();
    if ($id <= 0) { return $hasil; }

    $folder = __DIR__ . "/../assets/uploads/destinasi/galeri/" . $id;
    if (!is_dir($folder)) { return $hasil; }

    $berkas = glob($folder . "/*");
    if (!$berkas) { return $hasil; }
    sort($berkas, SORT_NATURAL);

    $izin = array("jpg", "jpeg", "png", "webp");
    foreach ($berkas as $jalur) {
        if (!is_file($jalur)) { continue; }
        $ext = strtolower(pathinfo($jalur, PATHINFO_EXTENSION));
        if (!in_array($ext, $izin, true)) { continue; }
        $nama_file = basename($jalur);
        $hasil[] = array(
            "berkas" => $nama_file,
            "url"    => BASE_URL . "assets/uploads/destinasi/galeri/" . $id . "/" . rawurlencode($nama_file),
        );
    }
    return $hasil;
}

/* Jarak dua koordinat (km) dengan rumus Haversine. */
function jarak_km($lat1, $lng1, $lat2, $lng2) {
    if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) { return 0.0; }
    $r = 6371.0;
    $d_lat = deg2rad((float) $lat2 - (float) $lat1);
    $d_lng = deg2rad((float) $lng2 - (float) $lng1);
    $a = sin($d_lat / 2) * sin($d_lat / 2)
       + cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2)) * sin($d_lng / 2) * sin($d_lng / 2);
    $a = min(1.0, max(0.0, $a));
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
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