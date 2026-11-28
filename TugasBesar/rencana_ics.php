<?php
// Ekspor jadwal Rencana Dolan ke kalender (.ics).
// Bisa lewat tautan berbagi (?token=...) atau langsung oleh pemilik yang sudah masuk.
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/rencana_jadwal.php";
require_once __DIR__ . "/includes/rencana_bagi.php";

pastikan_rencana_hari($conn);
pastikan_rencana_bagi($conn);

function ics_tolak($kode, $pesan) {
    http_response_code($kode);
    header("Content-Type: text/plain; charset=UTF-8");
    header("Cache-Control: no-store");
    echo $pesan;
    exit;
}

$token = trim((string) ($_GET["token"] ?? ""));

if ($token !== "") {
    $uid = cari_pemilik_token($conn, $token);
    if ($uid === 0) { ics_tolak(403, "Tautan berbagi tidak valid atau sudah dimatikan."); }
} elseif (sudah_login()) {
    $uid = (int) $_SESSION["user_id"];
} else {
    ics_tolak(403, "Masuk ke Dolan Jogja atau gunakan tautan berbagi untuk mengunduh kalender.");
}

$data = muat_rencana_pengguna($conn, $uid);

if (count($data["hari"]) === 0) {
    ics_tolak(404, "Rencana ini masih kosong, belum ada jadwal yang bisa diekspor.");
}
if ($data["tanggal_mulai"] === "") {
    ics_tolak(400, "Isi tanggal mulai di halaman Rencana Dolan agar jadwal bisa diekspor ke kalender.");
}

kirim_ics_rencana($data);
