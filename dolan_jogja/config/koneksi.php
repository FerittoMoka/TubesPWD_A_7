<?php
/* Konfigurasi koneksi database. Sesuaikan jika user/password MySQL berbeda. */
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "db_wisata";

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $ex) {
    http_response_code(500);
    exit("Koneksi database gagal. Pastikan MySQL aktif, database.sql sudah diimpor, dan config/koneksi.php benar.");
}
