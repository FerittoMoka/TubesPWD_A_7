<?php
/*
 * Koneksi database Dolan Jogja.
 *
 * Jika MySQL belum berjalan, database belum dibuat, atau database.sql belum diimpor,
 * halaman ini menampilkan panduan instalasi ringkas (tanpa membocorkan jalur folder server)
 * alih-alih Fatal Error mysqli_sql_exception.
 */

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_wisata";

/* Endpoint yang menjawab JSON: panduan dikirim sebagai JSON, bukan halaman HTML. */
if (!function_exists("dolan_minta_json")) {
    function dolan_minta_json() {
        $terima = $_SERVER["HTTP_ACCEPT"] ?? "";
        $skrip  = $_SERVER["SCRIPT_NAME"] ?? "";
        $ajax   = strcasecmp((string) ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? ""), "XMLHttpRequest") === 0;
        return stripos($terima, "application/json") !== false
            || $ajax
            || preg_match("~/(toggle_[a-z_]+|get|me|cek_ketersediaan)\.php$~i", $skrip) === 1;
    }
}

/* Halaman panduan instalasi. $alasan = kalimat singkat, $rincian = petunjuk tambahan (opsional). */
if (!function_exists("tampil_panduan_db")) {
    function tampil_panduan_db($alasan, $rincian = "") {
        if (!headers_sent()) { http_response_code(503); }

        if (dolan_minta_json()) {
            if (!headers_sent()) { header("Content-Type: application/json; charset=utf-8"); }
            echo json_encode(array(
                "ok"      => false,
                "pesan"   => trim($alasan . " " . $rincian),
                "panduan" => "Jalankan Apache dan MySQL di XAMPP, lalu impor database.sql lewat phpMyAdmin.",
            ), JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!headers_sent()) { header("Content-Type: text/html; charset=utf-8"); }
        $alasan_aman  = htmlspecialchars((string) $alasan, ENT_QUOTES, "UTF-8");
        $rincian_aman = htmlspecialchars((string) $rincian, ENT_QUOTES, "UTF-8");
        $dokumen  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $dokumen .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">' . "\n";
        $dokumen .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="id" lang="id"><head>'
            . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />'
            . '<title>Database belum siap - Dolan Jogja</title>'
            . '<style type="text/css">'
            . 'body{margin:0;padding:32px 18px;background:#F7F2E8;color:#38271F;font:16px/1.6 "Segoe UI",Arial,sans-serif;}'
            . '.kotak{max-width:720px;margin:0 auto;background:#FFFCF6;border:1px solid #E6D4B8;border-radius:16px;padding:28px 30px;box-shadow:0 12px 30px rgba(56,39,31,.08);}'
            . 'h1{font-size:26px;margin:0 0 6px;color:#493126;}'
            . 'h2{font-size:18px;margin:22px 0 8px;color:#493126;}'
            . '.eyebrow{letter-spacing:.12em;text-transform:uppercase;font-size:12px;font-weight:700;color:#79513B;margin:0 0 6px;}'
            . 'ol{padding-left:22px;}li{margin-bottom:10px;}'
            . 'code{background:#EFE7D8;border-radius:6px;padding:2px 7px;font-size:14px;}'
            . '.catatan{margin-top:18px;padding:12px 14px;border-radius:10px;background:#F3DEDA;color:#9B2C2C;font-size:14px;}'
            . '.kaki{margin-top:18px;font-size:13px;color:#79513B;}'
            . '</style></head><body><div class="kotak">'
            . '<p class="eyebrow">Dolan Jogja</p>'
            . '<h1>Database belum siap dipakai</h1>'
            . '<p>' . $alasan_aman . '</p>';
        if ($rincian_aman !== "") {
            $dokumen .= '<div class="catatan">' . $rincian_aman . '</div>';
        }
        $dokumen .= '<h2>Langkah singkat perbaikan</h2><ol>'
            . '<li>Buka XAMPP Control Panel, lalu nyalakan <strong>Apache</strong> dan <strong>MySQL</strong>.</li>'
            . '<li>Buka <code>http://localhost/phpmyadmin</code>, pilih tab <strong>Import</strong>.</li>'
            . '<li>Impor file <code>database.sql</code> dari folder proyek. File ini membuat database <code>db_wisata</code> beserta tabel dan data contohnya.</li>'
            . '<li>Pastikan nama database di <code>config/koneksi.php</code> sama dengan yang diimpor (bawaan: <code>db_wisata</code>, user <code>root</code> tanpa password).</li>'
            . '<li>Muat ulang halaman ini.</li>'
            . '</ol>'
            . '<p class="kaki">Pesan teknis lengkap sudah dicatat di log PHP (<code>error_log</code>) dan tidak ditampilkan di halaman ini.</p>'
            . '</div></body></html>';
        echo $dokumen;
        exit;
    }
}

$conn = null;

try {
    $conn = @mysqli_connect($host, $user, $pass, $db);
    if (!$conn) {
        throw new mysqli_sql_exception("Koneksi database gagal: " . mysqli_connect_error());
    }
    mysqli_set_charset($conn, "utf8mb4");

    /* Pastikan tabel inti sudah diimpor (satu query ringan, tidak membaca data pengguna). */
    $wajib = array("users", "categories", "destinations", "reviews", "wishlists", "favorites");
    $q_ada = mysqli_query($conn, "SELECT table_name AS nama FROM information_schema.tables WHERE table_schema = DATABASE()");
    if ($q_ada) {
        $ada = array();
        while ($r_ada = mysqli_fetch_assoc($q_ada)) {
            $ada[] = strtolower((string) $r_ada["nama"]);
        }
        mysqli_free_result($q_ada);
        $hilang = array_values(array_diff($wajib, $ada));
        if (count($hilang) > 0) {
            tampil_panduan_db(
                "Database \"" . $db . "\" sudah terhubung, tetapi tabel yang dibutuhkan belum lengkap.",
                "Tabel yang belum ada: " . implode(", ", $hilang) . "."
            );
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Dolan Jogja (config/koneksi.php): " . $e->getMessage());
    tampil_panduan_db(
        "Aplikasi belum dapat terhubung ke database \"" . $db . "\".",
        "Periksa apakah MySQL sudah berjalan dan database sudah dibuat."
    );
}
