<?php
// Tautan berbagi hanya-baca untuk Rencana Dolan dan ekspor jadwal ke kalender (.ics).
require_once __DIR__ . "/rencana_jadwal.php";

function pastikan_rencana_bagi($conn) {
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rencana_bagi (
        user_id INT NOT NULL PRIMARY KEY,
        token CHAR(32) NOT NULL,
        dibuat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        kedaluwarsa DATE DEFAULT NULL,
        kode_hash VARCHAR(255) NOT NULL DEFAULT '',
        tanpa_biaya TINYINT(1) NOT NULL DEFAULT 0,
        jumlah_buka INT NOT NULL DEFAULT 0,
        terakhir_diakses DATETIME DEFAULT NULL,
        UNIQUE KEY unik_token_bagi (token),
        CONSTRAINT fk_bagi_pengguna FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* Database lama: kolom baru ditambahkan tanpa menghapus data yang sudah ada. */
    $kolom = array(
        "kedaluwarsa"      => "DATE DEFAULT NULL",
        "kode_hash"        => "VARCHAR(255) NOT NULL DEFAULT ''",
        "tanpa_biaya"      => "TINYINT(1) NOT NULL DEFAULT 0",
        "jumlah_buka"      => "INT NOT NULL DEFAULT 0",
        "terakhir_diakses" => "DATETIME DEFAULT NULL",
    );
    foreach ($kolom as $nama => $def) {
        @mysqli_query($conn, "ALTER TABLE rencana_bagi ADD COLUMN IF NOT EXISTS " . $nama . " " . $def);
    }
}

/* ---------- Masa berlaku dan kode akses ---------- */

function masa_berlaku_pilihan() {
    return array(
        "rencana"   => "Sampai rencana selesai + 7 hari",
        "7"         => "7 hari",
        "30"        => "30 hari",
        "90"        => "90 hari",
        "selamanya" => "Tanpa batas waktu",
    );
}

/* Kode akses: 4-6 angka; selain itu dianggap tidak valid. */
function kode_akses_valid($kode) {
    return preg_match('/^[0-9]{4,6}$/', (string) $kode) === 1;
}

/* Tanggal (Y-m-d) terakhir tautan masih berlaku; null = tanpa batas waktu. */
function hitung_kedaluwarsa_bagi($conn, $uid, $masa) {
    $masa = (string) $masa;
    if ($masa === "selamanya") { return null; }

    if ($masa === "rencana") {
        $mulai = ambil_tanggal_mulai($conn, $uid);
        $hari_terakhir = 0;
        $st = @mysqli_prepare($conn, "SELECT COALESCE(MAX(hari_ke), 0) AS n FROM wishlists WHERE user_id = ?");
        if ($st) {
            mysqli_stmt_bind_param($st, "i", $uid);
            mysqli_stmt_execute($st);
            $hari_terakhir = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($st))["n"] ?? 0);
            mysqli_stmt_close($st);
        }
        if ($mulai !== "" && $hari_terakhir > 0) {
            $t = DateTime::createFromFormat("!Y-m-d", $mulai);
            if ($t) {
                $t->modify("+" . ($hari_terakhir - 1) . " day");
                $t->modify("+7 day");
                return $t->format("Y-m-d");
            }
        }
        $masa = "30";
    }

    $hari = max(1, min(365, (int) $masa));
    return date("Y-m-d", strtotime("+" . $hari . " day"));
}

/* Baris tautan yang masih berlaku; null bila token salah, dimatikan, atau kedaluwarsa. */
function ambil_baris_bagi($conn, $token) {
    if (!is_string($token) || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) { return null; }
    $st = @mysqli_prepare($conn, "SELECT user_id, token, kode_hash, tanpa_biaya, jumlah_buka,
            DATE_FORMAT(kedaluwarsa, '%Y-%m-%d') AS kedaluwarsa,
            DATE_FORMAT(terakhir_diakses, '%Y-%m-%d %H:%i') AS terakhir_diakses
        FROM rencana_bagi
        WHERE token = ? AND (kedaluwarsa IS NULL OR kedaluwarsa >= CURDATE())
        LIMIT 1");
    if (!$st) { return null; }
    mysqli_stmt_bind_param($st, "s", $token);
    mysqli_stmt_execute($st);
    $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    return $baris ?: null;
}

function cari_pemilik_token($conn, $token) {
    $baris = ambil_baris_bagi($conn, $token);
    return $baris ? (int) $baris["user_id"] : 0;
}

/* Baris milik pemilik, termasuk yang sudah kedaluwarsa (untuk panel bagikan). */
function ambil_bagi_pemilik($conn, $uid) {
    $st = @mysqli_prepare($conn, "SELECT user_id, token, kode_hash, tanpa_biaya, jumlah_buka,
            DATE_FORMAT(kedaluwarsa, '%Y-%m-%d') AS kedaluwarsa,
            DATE_FORMAT(terakhir_diakses, '%Y-%m-%d %H:%i') AS terakhir_diakses
        FROM rencana_bagi WHERE user_id = ? LIMIT 1");
    if (!$st) { return null; }
    mysqli_stmt_bind_param($st, "i", $uid);
    mysqli_stmt_execute($st);
    $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    return $baris ?: null;
}

function bagi_kedaluwarsa($baris) {
    return $baris !== null && $baris["kedaluwarsa"] !== null && (string) $baris["kedaluwarsa"] < date("Y-m-d");
}

/* Token yang masih berlaku milik satu pengguna; "" bila belum ada atau sudah berakhir. */
function ambil_token_bagi($conn, $uid) {
    $baris = ambil_bagi_pemilik($conn, $uid);
    return ($baris !== null && !bagi_kedaluwarsa($baris)) ? (string) $baris["token"] : "";
}

/* Membuat token baru; token lama langsung tidak berlaku. */
function buat_token_bagi($conn, $uid, $masa = "rencana", $kode = "") {
    $kode_hash = ((string) $kode !== "") ? password_hash((string) $kode, PASSWORD_DEFAULT) : "";
    $kedaluwarsa = hitung_kedaluwarsa_bagi($conn, $uid, $masa);

    for ($coba = 0; $coba < 5; $coba++) {
        $token = bin2hex(random_bytes(16));

        $st = @mysqli_prepare($conn, "SELECT user_id FROM rencana_bagi WHERE token = ? LIMIT 1");
        if (!$st) { continue; }
        mysqli_stmt_bind_param($st, "s", $token);
        mysqli_stmt_execute($st);
        $dipakai = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        if ($dipakai) { continue; }

        $st = @mysqli_prepare($conn, "INSERT INTO rencana_bagi
                (user_id, token, dibuat, kedaluwarsa, kode_hash, tanpa_biaya, jumlah_buka, terakhir_diakses)
            VALUES (?, ?, NOW(), ?, ?, 0, 0, NULL)
            ON DUPLICATE KEY UPDATE token = VALUES(token), dibuat = NOW(), kedaluwarsa = VALUES(kedaluwarsa),
                kode_hash = VALUES(kode_hash), jumlah_buka = 0, terakhir_diakses = NULL");
        if (!$st) { continue; }
        mysqli_stmt_bind_param($st, "isss", $uid, $token, $kedaluwarsa, $kode_hash);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        if ($ok && ambil_token_bagi($conn, $uid) === $token) { return $token; }
    }
    return "";
}

/* Menyetel atau menghapus kode akses tautan yang sudah ada ($kode kosong = tanpa kode). */
function simpan_kode_akses_bagi($conn, $uid, $kode) {
    $kode_hash = ((string) $kode !== "") ? password_hash((string) $kode, PASSWORD_DEFAULT) : "";
    $st = @mysqli_prepare($conn, "UPDATE rencana_bagi SET kode_hash = ? WHERE user_id = ?");
    if (!$st) { return false; }
    mysqli_stmt_bind_param($st, "si", $kode_hash, $uid);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return (bool) $ok;
}

/* Sembunyikan perkiraan biaya di halaman publik dan di isi kalender. */
function simpan_tanpa_biaya_bagi($conn, $uid, $tanpa) {
    $nilai = $tanpa ? 1 : 0;
    $st = @mysqli_prepare($conn, "UPDATE rencana_bagi SET tanpa_biaya = ? WHERE user_id = ?");
    if (!$st) { return false; }
    mysqli_stmt_bind_param($st, "ii", $nilai, $uid);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return (bool) $ok;
}

/* Catat satu kunjungan tautan (dipakai halaman publik sekali per sesi). */
function catat_akses_bagi($conn, $token) {
    $st = @mysqli_prepare($conn, "UPDATE rencana_bagi SET jumlah_buka = jumlah_buka + 1, terakhir_diakses = NOW() WHERE token = ?");
    if (!$st) { return false; }
    mysqli_stmt_bind_param($st, "s", $token);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return (bool) $ok;
}

function hapus_token_bagi($conn, $uid) {
    $st = @mysqli_prepare($conn, "DELETE FROM rencana_bagi WHERE user_id = ?");
    mysqli_stmt_bind_param($st, "i", $uid);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return $ok;
}

/* ---------- Kode akses saat tautan dibuka ---------- */

function bagi_terkunci($baris) {
    return $baris !== null && (string) $baris["kode_hash"] !== "";
}

/* Sudah dibuka pada sesi ini? Penanda berisi hash kode, jadi ganti kode = akses lama batal. */
function bagi_sudah_terbuka($baris) {
    if (!bagi_terkunci($baris)) { return true; }
    $token = (string) $baris["token"];
    if (!isset($_SESSION["bagi_terbuka"][$token]) || !is_string($_SESSION["bagi_terbuka"][$token])) {
        return false;
    }
    return hash_equals((string) $baris["kode_hash"], $_SESSION["bagi_terbuka"][$token]);
}

function buka_bagi($baris) {
    $_SESSION["bagi_terbuka"][(string) $baris["token"]] = (string) $baris["kode_hash"];
}

/* Pembatas percobaan kode: maksimal 5 kali per menit per sesi. */
function bagi_terlalu_banyak_coba($token) {
    $kunci = "bagi_coba_" . $token;
    $batas = time() - 60;
    $daftar = array();
    foreach ((array) ($_SESSION[$kunci] ?? array()) as $waktu) {
        if ((int) $waktu > $batas) { $daftar[] = (int) $waktu; }
    }
    $_SESSION[$kunci] = $daftar;
    return count($daftar) >= 5;
}

function catat_coba_kode($token) {
    $kunci = "bagi_coba_" . $token;
    $daftar = (array) ($_SESSION[$kunci] ?? array());
    $daftar[] = time();
    $_SESSION[$kunci] = $daftar;
}

function reset_coba_kode($token) {
    unset($_SESSION["bagi_coba_" . (string) $token]);
}

/* ---------- Alamat tautan ---------- */

function url_absolut($relatif) {
    $https = !empty($_SERVER["HTTPS"]) && strtolower((string) $_SERVER["HTTPS"]) !== "off";
    $host = (string) ($_SERVER["HTTP_HOST"] ?? "");
    $awal = $host !== "" ? (($https ? "https" : "http") . "://" . $host) : "";
    return $awal . BASE_URL . ltrim((string) $relatif, "/");
}

/* Alamat lengkap untuk dibagikan; memakai host permintaan saat tersedia. */
function url_rencana_publik($token) {
    return url_absolut("rencana_publik.php") . "?token=" . rawurlencode((string) $token);
}

/* Alamat ekspor .ics; $langganan = true untuk URL langganan kalender. */
function url_rencana_ics($token, $langganan = false) {
    $url = url_absolut("rencana_ics.php") . "?token=" . rawurlencode((string) $token);
    return $langganan ? $url . "&langganan=1" : $url;
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

/* LAST-MODIFIED dalam UTC dari waktu perubahan rencana (disimpan sebagai WIB). */
function ics_waktu_ubah($diubah) {
    if ((string) $diubah === "") { return gmdate('Ymd\THis\Z'); }
    $d = DateTime::createFromFormat("!Y-m-d H:i:s", (string) $diubah, new DateTimeZone("Asia/Jakarta"));
    return $d ? $d->setTimezone(new DateTimeZone("UTC"))->format('Ymd\THis\Z') : gmdate('Ymd\THis\Z');
}

function buat_ics_rencana($data) {
    $stamp = gmdate('Ymd\THis\Z');
    /* SEQUENCE naik setiap rencana diubah, jadi impor ulang memperbarui event yang sama. */
    $urutan = max(0, (int) ($data["revisi"] ?? 0));
    $terakhir_ubah = ics_waktu_ubah($data["diubah"] ?? "");
    $tampil_biaya = empty($data["sembunyi_biaya"]);

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

            $ket = "Kategori: " . $r["kategori"];
            if ($tampil_biaya) {
                $ket .= "\nHarga tiket: " . rencana_harga($r["_min"], $r["_maks"]) . " /orang";
            }
            if (!empty($r["jam_buka"])) { $ket .= "\nJam buka: " . $r["jam_buka"]; }
            foreach ($j["catatan"] as $c) { $ket .= "\n" . $c[1]; }

            $baris[] = "BEGIN:VEVENT";
            $baris[] = "UID:dolan-item-" . (int) $r["id"] . "@dolanjogja";
            $baris[] = "DTSTAMP:" . $stamp;
            $baris[] = "LAST-MODIFIED:" . $terakhir_ubah;
            $baris[] = "SEQUENCE:" . $urutan;
            $baris[] = "DTSTART:" . ics_waktu_utc($jad["tanggal"], $j["tiba"]);
            $baris[] = "DTEND:" . ics_waktu_utc($jad["tanggal"], $j["pulang"]);
            $baris[] = "SUMMARY:" . ics_escape($r["nama"]);
            $baris[] = "LOCATION:" . ics_escape($r["lokasi"]);
            if ($r["latitude"] !== null && $r["longitude"] !== null) {
                $baris[] = "GEO:" . (float) $r["latitude"] . ";" . (float) $r["longitude"];
            }
            $baris[] = "URL:" . url_absolut("destinasi/detail.php") . "?id=" . (int) $r["did"];
            $baris[] = "DESCRIPTION:" . ics_escape($ket);
            $baris[] = "END:VEVENT";
        }
    }
    $baris[] = "END:VCALENDAR";

    $keluar = "";
    foreach ($baris as $b) { $keluar .= ics_fold($b); }
    return $keluar;
}

/* $langganan = true: berkas ditampilkan sebagai langganan kalender, bukan unduhan sekali. */
function kirim_ics_rencana($data, $langganan = false) {
    $nama = "rencana-dolan";
    if ($data["tanggal_mulai"] !== "") { $nama .= "-" . str_replace("-", "", $data["tanggal_mulai"]); }
    $nama .= ".ics";

    header("Content-Type: text/calendar; charset=UTF-8");
    if ($langganan) {
        header("Content-Disposition: inline; filename=\"" . $nama . "\"");
        header("Cache-Control: private, max-age=300");
        header("X-Published-TTL: PT1H");
    } else {
        header("Content-Disposition: attachment; filename=\"" . $nama . "\"");
        header("Cache-Control: private, no-store, max-age=0");
    }
    header("X-Content-Type-Options: nosniff");
    header("X-Robots-Tag: noindex, nofollow");
    header("Referrer-Policy: no-referrer");
    echo buat_ics_rencana($data);
}
