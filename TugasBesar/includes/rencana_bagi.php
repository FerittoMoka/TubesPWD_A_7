<?php
// Tautan berbagi hanya-baca untuk Rencana Dolan dan ekspor jadwal ke kalender (.ics).
require_once __DIR__ . "/rencana_jadwal.php";

function pastikan_rencana_bagi($conn) {
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rencana_bagi (
        user_id INT NOT NULL PRIMARY KEY,
        token CHAR(32) NOT NULL,
        dibuat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unik_token_bagi (token),
        CONSTRAINT fk_bagi_pengguna FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function token_bagi_format_valid($token) {
    return is_string($token) && preg_match('/^[a-f0-9]{32}$/', $token) === 1;
}

function ambil_token_bagi($conn, $uid) {
    $st = mysqli_prepare($conn, "SELECT token FROM rencana_bagi WHERE user_id = ? LIMIT 1");
    if (!$st) { return ""; }
    mysqli_stmt_bind_param($st, "i", $uid);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    return $row ? (string) $row["token"] : "";
}

/* Membuat token baru; token lama langsung tidak berlaku. */
function buat_token_bagi($conn, $uid) {
    for ($coba = 0; $coba < 5; $coba++) {
        $token = bin2hex(random_bytes(16));

        $st = mysqli_prepare($conn, "SELECT user_id FROM rencana_bagi WHERE token = ? LIMIT 1");
        mysqli_stmt_bind_param($st, "s", $token);
        mysqli_stmt_execute($st);
        $dipakai = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        if ($dipakai) { continue; }

        $st = mysqli_prepare($conn, "INSERT INTO rencana_bagi (user_id, token, dibuat) VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE token = VALUES(token), dibuat = NOW()");
        if (!$st) { continue; }
        mysqli_stmt_bind_param($st, "is", $uid, $token);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        if ($ok && ambil_token_bagi($conn, $uid) === $token) { return $token; }
    }
    return "";
}

function hapus_token_bagi($conn, $uid) {
    $st = mysqli_prepare($conn, "DELETE FROM rencana_bagi WHERE user_id = ?");
    mysqli_stmt_bind_param($st, "i", $uid);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return $ok;
}

function cari_pemilik_token($conn, $token) {
    if (!token_bagi_format_valid($token)) { return 0; }
    $st = mysqli_prepare($conn, "SELECT user_id FROM rencana_bagi WHERE token = ? LIMIT 1");
    if (!$st) { return 0; }
    mysqli_stmt_bind_param($st, "s", $token);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    return $row ? (int) $row["user_id"] : 0;
}

/* Alamat lengkap untuk dibagikan; memakai host permintaan saat tersedia. */
function url_rencana_publik($token) {
    $https = !empty($_SERVER["HTTPS"]) && strtolower((string) $_SERVER["HTTPS"]) !== "off";
    $host = (string) ($_SERVER["HTTP_HOST"] ?? "");
    $awal = $host !== "" ? (($https ? "https" : "http") . "://" . $host) : "";
    return $awal . BASE_URL . "rencana_publik.php?token=" . rawurlencode($token);
}

/* ---------- Kalender .ics ---------- */

function ics_escape($teks) {
    return strtr((string) $teks, array(
        "\\" => "\\\\",
        ";" => "\\;",
        "," => "\\,",
        "\r\n" => "\\n",
        "\n" => "\\n",
        "\r" => "\\n",
    ));
}

/* Lipat baris panjang sesuai RFC 5545 tanpa memotong karakter UTF-8. */
function ics_fold($baris) {
    $baris = (string) $baris;
    if ($baris === "") { return "\r\n"; }
    $keluar = "";
    $pertama = true;
    while (strlen($baris) > ($pertama ? 73 : 72)) {
        $batas = $pertama ? 73 : 72;
        while ($batas > 0 && (ord($baris[$batas]) & 0xC0) === 0x80) { $batas--; }
        if ($batas <= 0) { $batas = $pertama ? 73 : 72; }
        $keluar .= ($pertama ? "" : " ") . substr($baris, 0, $batas) . "\r\n";
        $baris = substr($baris, $batas);
        $pertama = false;
    }
    return $keluar . ($pertama ? "" : " ") . $baris . "\r\n";
}

/* Waktu WIB (Asia/Jakarta) diubah ke UTC agar tidak perlu VTIMEZONE. */
function ics_waktu_utc($tanggal, $menit) {
    $zona = new DateTimeZone("Asia/Jakarta");
    $d = new DateTimeImmutable($tanggal . " 00:00:00", $zona);
    $d = $d->modify("+" . max(0, (int) $menit) . " minutes");
    return $d->setTimezone(new DateTimeZone("UTC"))->format('Ymd\THis\Z');
}

function buat_ics_rencana($data, $kode) {
    $kode = preg_replace('/[^a-zA-Z0-9-]/', '', (string) $kode);
    $stamp = gmdate('Ymd\THis\Z');
    $baris = array(
        "BEGIN:VCALENDAR",
        "VERSION:2.0",
        "PRODID:-//Dolan Jogja//Rencana Dolan//ID",
        "CALSCALE:GREGORIAN",
        "METHOD:PUBLISH",
        "X-WR-CALNAME:Rencana Dolan Jogja",
        "X-WR-TIMEZONE:Asia/Jakarta",
    );

    foreach ($data["hari"] as $h => $blok) {
        $jad = $data["jadwal"][$h] ?? null;
        if (!$jad || $jad["tanggal"] === null) { continue; }
        foreach ($blok["item"] as $i => $r) {
            if (!isset($jad["item"][$i])) { continue; }
            $j = $jad["item"][$i];

            $ket = "Kategori: " . $r["kategori"] . "\nHarga tiket: " . rencana_harga($r["_min"], $r["_maks"]) . " /orang";
            if (!empty($r["jam_buka"])) { $ket .= "\nJam buka: " . $r["jam_buka"]; }
            foreach ($j["catatan"] as $c) { $ket .= "\n" . $c[1]; }

            $baris[] = "BEGIN:VEVENT";
            $baris[] = "UID:dolan-" . $kode . "-" . (int) $h . "-" . (int) $r["id"] . "@dolanjogja";
            $baris[] = "DTSTAMP:" . $stamp;
            $baris[] = "DTSTART:" . ics_waktu_utc($jad["tanggal"], $j["tiba"]);
            $baris[] = "DTEND:" . ics_waktu_utc($jad["tanggal"], $j["pulang"]);
            $baris[] = "SUMMARY:" . ics_escape($r["nama"]);
            $baris[] = "LOCATION:" . ics_escape($r["lokasi"]);
            $baris[] = "DESCRIPTION:" . ics_escape($ket);
            $baris[] = "END:VEVENT";
        }
    }
    $baris[] = "END:VCALENDAR";

    $keluar = "";
    foreach ($baris as $b) { $keluar .= ics_fold($b); }
    return $keluar;
}

function kirim_ics_rencana($data, $kode) {
    $nama = "rencana-dolan";
    if ($data["tanggal_mulai"] !== "") { $nama .= "-" . str_replace("-", "", $data["tanggal_mulai"]); }
    $nama .= ".ics";

    header("Content-Type: text/calendar; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"" . $nama . "\"");
    header("Cache-Control: private, no-store, max-age=0");
    header("X-Content-Type-Options: nosniff");
    echo buat_ics_rencana($data, $kode);
}
