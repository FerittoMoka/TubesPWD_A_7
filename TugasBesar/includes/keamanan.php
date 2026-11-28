<?php
// Pembatas percobaan masuk/daftar dan jejak aktivitas administrator.
// Tabel dibuat saat pertama dipakai (CREATE TABLE IF NOT EXISTS) agar database lama tetap jalan.

function pastikan_keamanan_tabel($conn) {
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS percobaan_akses (
        kunci VARCHAR(160) NOT NULL PRIMARY KEY,
        jumlah INT NOT NULL DEFAULT 0,
        mulai DATETIME NOT NULL,
        terakhir DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS log_admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        aksi VARCHAR(40) NOT NULL,
        detail VARCHAR(255) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        dibuat DATETIME NOT NULL,
        KEY kunci_log_admin (dibuat)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ip_pengunjung() {
    return substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45);
}

/* ---------- Pembatas percobaan (jendela bergulir sederhana) ---------- */

/* Umur jendela dihitung MySQL (TIMESTAMPDIFF) agar tidak terpengaruh beda zona waktu PHP dan MySQL. */
function baris_percobaan($conn, $kunci) {
    $st = @mysqli_prepare($conn, "SELECT jumlah, TIMESTAMPDIFF(SECOND, mulai, NOW()) AS umur FROM percobaan_akses WHERE kunci = ? LIMIT 1");
    if (!$st) { return null; }
    mysqli_stmt_bind_param($st, "s", $kunci);
    mysqli_stmt_execute($st);
    $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    return $baris ?: null;
}

/* Sudah melewati batas? Jendela yang sudah lewat dianggap kosong. */
function terlalu_banyak_percobaan($conn, $kunci, $batas, $jendela) {
    pastikan_keamanan_tabel($conn);
    $baris = baris_percobaan($conn, $kunci);
    if ($baris === null) { return false; }
    if ((int) $baris["umur"] > $jendela) { return false; }
    return (int) $baris["jumlah"] >= $batas;
}

/* Sisa detik sebelum jendela dibuka lagi; 0 bila tidak terkunci. */
function sisa_kunci_percobaan($conn, $kunci, $batas, $jendela) {
    $baris = baris_percobaan($conn, $kunci);
    if ($baris === null || (int) $baris["jumlah"] < $batas) { return 0; }
    $sisa = $jendela - (int) $baris["umur"];
    return $sisa > 0 ? $sisa : 0;
}

function catat_percobaan($conn, $kunci, $jendela) {
    pastikan_keamanan_tabel($conn);
    $baris = baris_percobaan($conn, $kunci);

    /* Jendela masih berjalan: tambah hitungan. Jendela lewat: mulai jendela baru dari 1. */
    if ($baris !== null && (int) $baris["umur"] <= $jendela) {
        $st = @mysqli_prepare($conn, "UPDATE percobaan_akses SET jumlah = jumlah + 1, terakhir = NOW() WHERE kunci = ?");
        if (!$st) { return false; }
        mysqli_stmt_bind_param($st, "s", $kunci);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        return (bool) $ok;
    }

    $st = @mysqli_prepare($conn, "INSERT INTO percobaan_akses (kunci, jumlah, mulai, terakhir) VALUES (?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE jumlah = 1, mulai = NOW(), terakhir = NOW()");
    if (!$st) { return false; }
    mysqli_stmt_bind_param($st, "s", $kunci);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return (bool) $ok;
}

function hapus_percobaan($conn, $kunci) {
    $st = @mysqli_prepare($conn, "DELETE FROM percobaan_akses WHERE kunci = ?");
    if (!$st) { return false; }
    mysqli_stmt_bind_param($st, "s", $kunci);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return (bool) $ok;
}

/* ---------- Masuk & daftar ---------- */

/* Maksimal 6 percobaan per akun dan 20 per alamat IP dalam 15 menit. */
function kunci_percobaan_login($login) {
    $akun = mb_strtolower(trim((string) $login), "UTF-8");
    return array("masuk-akun:" . substr($akun, 0, 80), "masuk-ip:" . ip_pengunjung());
}

function batas_percobaan_login($conn, $login) {
    list($akun, $ip) = kunci_percobaan_login($login);
    if (terlalu_banyak_percobaan($conn, $akun, 6, 900)) {
        return sisa_kunci_percobaan($conn, $akun, 6, 900);
    }
    if (terlalu_banyak_percobaan($conn, $ip, 20, 900)) {
        return sisa_kunci_percobaan($conn, $ip, 20, 900);
    }
    return 0;
}

function catat_gagal_login($conn, $login) {
    list($akun, $ip) = kunci_percobaan_login($login);
    catat_percobaan($conn, $akun, 900);
    catat_percobaan($conn, $ip, 900);
}

function reset_percobaan_login($conn, $login) {
    list($akun, $ip) = kunci_percobaan_login($login);
    hapus_percobaan($conn, $akun);
    hapus_percobaan($conn, $ip);
}

/* Maksimal 5 pendaftaran per alamat IP dalam satu jam. */
function batas_percobaan_daftar($conn) {
    return terlalu_banyak_percobaan($conn, "daftar-ip:" . ip_pengunjung(), 5, 3600);
}

function catat_percobaan_daftar($conn) {
    catat_percobaan($conn, "daftar-ip:" . ip_pengunjung(), 3600);
}

/* Teks bantuan untuk pesan terkunci, mis. "Coba lagi dalam 12 menit." */
function teks_tunggu($detik) {
    $menit = max(1, (int) ceil(((int) $detik) / 60));
    return "Coba lagi dalam " . $menit . " menit.";
}

/* ---------- Jejak audit administrator ---------- */

function catat_log_admin($conn, $uid, $aksi, $detail = "") {
    pastikan_keamanan_tabel($conn);
    $st = @mysqli_prepare($conn, "INSERT INTO log_admin (user_id, aksi, detail, ip, dibuat) VALUES (?, ?, ?, ?, NOW())");
    if (!$st) { return false; }
    $aksi = substr((string) $aksi, 0, 40);
    $detail = mb_substr((string) $detail, 0, 255, "UTF-8");
    $ip = ip_pengunjung();
    mysqli_stmt_bind_param($st, "isss", $uid, $aksi, $detail, $ip);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return (bool) $ok;
}

function ambil_log_admin($conn, $batas = 8) {
    pastikan_keamanan_tabel($conn);
    $batas = max(1, min(50, (int) $batas));
    $hasil = array();
    $q = @mysqli_query($conn, "SELECT l.aksi, l.detail, l.dibuat, u.username
        FROM log_admin l LEFT JOIN users u ON u.id = l.user_id
        ORDER BY l.id DESC LIMIT " . $batas);
    while ($q && ($b = mysqli_fetch_assoc($q))) { $hasil[] = $b; }
    return $hasil;
}
