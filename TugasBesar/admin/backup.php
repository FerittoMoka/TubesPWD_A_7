<?php
// Unduh backup database dari panel admin, memakai mysqldump yang sama dengan alat terminal.
// Berkas dibuat di folder sementara lalu langsung dikirim ke browser; tidak disimpan di webroot.
require_once __DIR__ . "/../includes/init.php";
wajib_admin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("admin/index.php");
}
cek_csrf();

/* Cari mysqldump: variabel lingkungan MYSQLDUMP_BIN, lalu folder MySQL bawaan XAMPP. */
function biner_dump($nama) {
    $env = getenv(strtoupper($nama) . "_BIN");
    if ($env && is_file($env)) { return $env; }
    $xampp = dirname(dirname(PHP_BINARY)) . DIRECTORY_SEPARATOR . "mysql"
        . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . $nama . ".exe";
    if (is_file($xampp)) { return $xampp; }
    return $nama;
}

$nama_berkas = $db . "_" . date("Ymd-His") . ".sql";
$tujuan = tempnam(sys_get_temp_dir(), "dolan_backup");
$kredensial = tempnam(sys_get_temp_dir(), "dolan_my");

if ($tujuan === false || $kredensial === false) {
    flash("error", "Berkas sementara untuk backup tidak bisa dibuat.");
    redirect("admin/index.php");
}

/* Kredensial lewat berkas 0600 agar tidak terlihat di daftar proses. */
file_put_contents($kredensial, "[client]\nhost=" . $host . "\nuser=" . $user . "\npassword=" . $pass . "\n");
@chmod($kredensial, 0600);

$perintah = array(
    biner_dump("mysqldump"),
    "--defaults-extra-file=" . $kredensial,
    "--single-transaction",
    "--quick",
    "--routines",
    "--triggers",
    "--default-character-set=utf8mb4",
    $db,
);
$deskriptor = array(
    0 => array("pipe", "r"),
    1 => array("file", $tujuan, "w"),
    2 => array("pipe", "w"),
);
$pipa = array();
$proses = @proc_open($perintah, $deskriptor, $pipa);
if (!is_resource($proses)) {
    @unlink($tujuan);
    @unlink($kredensial);
    flash("error", "mysqldump tidak bisa dijalankan. Periksa folder MySQL pada XAMPP.");
    redirect("admin/index.php");
}
fclose($pipa[0]);
$galat = trim((string) stream_get_contents($pipa[2]));
fclose($pipa[2]);
$kode = proc_close($proses);
@unlink($kredensial);

if ($kode !== 0 || !is_file($tujuan) || filesize($tujuan) < 512) {
    error_log("Dolan Jogja (admin/backup.php): mysqldump kode " . $kode . " " . $galat);
    @unlink($tujuan);
    flash("error", "Backup gagal dibuat. Coba lagi, atau pakai tools/db_kelola.php backup dari terminal.");
    redirect("admin/index.php");
}

catat_log_admin($conn, (int) $USER["id"], "backup_database", $nama_berkas);

header("Content-Type: application/sql; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"" . $nama_berkas . "\"");
header("Content-Length: " . filesize($tujuan));
header("Cache-Control: private, no-store, max-age=0");
header("X-Content-Type-Options: nosniff");
header("X-Robots-Tag: noindex, nofollow");
readfile($tujuan);
@unlink($tujuan);
exit;
