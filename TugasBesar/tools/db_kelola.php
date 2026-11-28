<?php
/**
 * Dolan Jogja - pengelola database dari terminal.
 *
 * Perintah:
 *   php tools/db_kelola.php backup
 *       Membuat dump database hidup ke folder backups/ (tanpa mengubah data).
 *
 *   php tools/db_kelola.php daftar
 *       Menampilkan berkas backup yang tersedia.
 *
 *   php tools/db_kelola.php restore backups/NAMA.sql [--ya]
 *       Mengembalikan database dari berkas backup. Isi berkas diimpor ke database
 *       sementara lebih dulu; database hidup baru disentuh setelah hasilnya valid.
 *
 *   php tools/db_kelola.php reset [--ya]
 *       Menghapus data hidup dan mengisinya ulang dari database.sql. Backup otomatis
 *       dibuat lebih dulu, dan seed diimpor ke database sementara untuk divalidasi.
 *
 * Pengaman:
 *   - Berkas SQL dari database.sql / dump disaring: baris CREATE DATABASE, DROP DATABASE,
 *     dan USE dibuang, sehingga impor tidak pernah "nyasar" ke database lain.
 *   - Aksi yang menimpa data meminta konfirmasi (ketik RESET/RESTORE) kecuali --ya.
 *   - Jika impor ke database hidup gagal, backup otomatis dipulihkan kembali.
 *   - Satu proses pada satu waktu (lock file), dan setiap aksi dicatat di backups/riwayat.log.
 *
 * Konfigurasi koneksi dibaca dari config/koneksi.php. Variabel lingkungan DB_HOST, DB_USER,
 * DB_PASS, DB_NAME, MYSQL_BIN, dan MYSQLDUMP_BIN boleh dipakai untuk menimpanya.
 */

if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit("Alat ini hanya bisa dijalankan dari terminal (CLI).\n");
}

$AKAR = dirname(__DIR__);
/* BACKUP_DIR boleh diarahkan ke luar webroot agar dump tidak bisa diunduh lewat browser. */
$FOLDER_BACKUP = getenv("BACKUP_DIR") ?: ($AKAR . DIRECTORY_SEPARATOR . "backups");
$BERKAS_SEED = $AKAR . DIRECTORY_SEPARATOR . "database.sql";
$BERKAS_LOG = $FOLDER_BACKUP . DIRECTORY_SEPARATOR . "riwayat.log";

function baris($teks = "") { echo $teks . "\n"; }
function berhenti($pesan, $kode = 1) {
    fwrite(STDERR, "\nGAGAL: " . $pesan . "\n");
    exit($kode);
}

/* ---------- Konfigurasi ---------- */

function baca_konfigurasi($berkas) {
    $isi = @file_get_contents($berkas);
    if ($isi === false) {
        berhenti("config/koneksi.php tidak ditemukan. Jalankan perintah dari folder proyek.");
    }
    $ambil = function ($nama) use ($isi) {
        if (preg_match('/\$' . $nama . '\s*=\s*"([^"]*)"/', $isi, $m)) { return $m[1]; }
        if (preg_match("/\\\$" . $nama . "\s*=\s*'([^']*)'/", $isi, $m)) { return $m[1]; }
        return null;
    };
    $cfg = array(
        "host" => getenv("DB_HOST") ?: ($ambil("host") ?: "localhost"),
        "user" => getenv("DB_USER") ?: ($ambil("user") ?: "root"),
        "pass" => getenv("DB_PASS") !== false ? getenv("DB_PASS") : (string) $ambil("pass"),
        "db"   => getenv("DB_NAME") ?: ($ambil("db") ?: "db_wisata"),
    );
    if (!preg_match('/^[A-Za-z0-9_]+$/', $cfg["db"])) {
        berhenti("Nama database \"" . $cfg["db"] . "\" tidak valid.");
    }
    return $cfg;
}

function cari_biner($nama) {
    $env = getenv(strtoupper($nama) . "_BIN");
    if ($env && is_file($env)) { return $env; }
    $xampp = dirname(dirname(PHP_BINARY)) . DIRECTORY_SEPARATOR . "mysql"
        . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . $nama . ".exe";
    if (is_file($xampp)) { return $xampp; }
    return $nama;
}

/* ---------- Proses & MySQL ---------- */

function jalankan(array $perintah, $masukan = null, $keluaran = null) {
    $deskriptor = array(
        0 => $masukan ? array("file", $masukan, "r") : array("pipe", "r"),
        1 => $keluaran ? array("file", $keluaran, "w") : array("pipe", "w"),
        2 => array("pipe", "w"),
    );
    $pipa = array();
    $proses = @proc_open($perintah, $deskriptor, $pipa);
    if (!is_resource($proses)) {
        return array(1, "", "Tidak bisa menjalankan proses. Periksa path MySQL.");
    }
    if (!$masukan && isset($pipa[0])) { fclose($pipa[0]); }
    $teks = $keluaran ? "" : stream_get_contents($pipa[1]);
    $galat = stream_get_contents($pipa[2]);
    if (!$keluaran && isset($pipa[1])) { fclose($pipa[1]); }
    if (isset($pipa[2])) { fclose($pipa[2]); }
    $kode = proc_close($proses);
    return array($kode, $teks, $galat);
}

function file_kredensial(array $cfg) {
    $berkas = tempnam(sys_get_temp_dir(), "dolanmy");
    if ($berkas === false) { berhenti("Tidak bisa membuat berkas kredensial sementara."); }
    $isi = "[client]\nhost=" . $cfg["host"] . "\nuser=" . $cfg["user"]
        . "\npassword=" . $cfg["pass"] . "\n";
    file_put_contents($berkas, $isi);
    @chmod($berkas, 0600);
    return $berkas;
}

function argumen_mysql($biner, $kredensial, $db) {
    $arg = array($biner, "--defaults-extra-file=" . $kredensial, "--default-character-set=utf8mb4");
    if ($db !== null) { $arg[] = "--database=" . $db; }
    return $arg;
}

function koneksi(array $cfg, $db = null) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $c = @mysqli_connect($cfg["host"], $cfg["user"], $cfg["pass"], $db);
    if ($c) { mysqli_set_charset($c, "utf8mb4"); }
    return $c;
}

function buat_database(array $cfg, $nama) {
    $c = koneksi($cfg);
    if (!$c) { return false; }
    $ok = mysqli_query($c, "CREATE DATABASE `" . $nama . "` CHARACTER SET utf8mb4");
    mysqli_close($c);
    return (bool) $ok;
}

function hapus_database(array $cfg, $nama) {
    $c = koneksi($cfg);
    if (!$c) { return; }
    @mysqli_query($c, "DROP DATABASE IF EXISTS `" . $nama . "`");
    mysqli_close($c);
}

/* ---------- Berkas SQL ---------- */

/* Buang perintah yang bisa mengalihkan impor ke database lain. */
function saring_sql($sumber, $tujuan) {
    $masuk = @fopen($sumber, "r");
    if (!$masuk) { return false; }
    $keluar = @fopen($tujuan, "w");
    if (!$keluar) { fclose($masuk); return false; }
    while (($baris = fgets($masuk)) !== false) {
        if (preg_match('/^\s*(CREATE\s+DATABASE|DROP\s+DATABASE|USE\s)/i', $baris)) { continue; }
        fwrite($keluar, $baris);
    }
    fclose($masuk);
    fclose($keluar);
    return true;
}

function sql_valid($berkas) {
    if (!is_file($berkas) || filesize($berkas) < 512) { return false; }
    $isi = @file_get_contents($berkas);
    return $isi !== false && stripos($isi, "CREATE TABLE") !== false;
}

function validasi_database(array $cfg, $nama) {
    $c = koneksi($cfg, $nama);
    if (!$c) { return "Database sementara tidak bisa dibuka."; }

    $wajib = array("users", "categories", "destinations", "reviews", "wishlists", "favorites");
    $ada = array();
    $q = mysqli_query($c, "SELECT table_name AS nama FROM information_schema.tables WHERE table_schema = DATABASE()");
    while ($q && $r = mysqli_fetch_assoc($q)) { $ada[] = strtolower((string) $r["nama"]); }
    $hilang = array_values(array_diff($wajib, $ada));
    if (count($hilang) > 0) {
        mysqli_close($c);
        return "Tabel wajib tidak ada: " . implode(", ", $hilang) . ".";
    }

    $nd = (int) mysqli_fetch_row(mysqli_query($c, "SELECT COUNT(*) FROM destinations"))[0];
    $nu = (int) mysqli_fetch_row(mysqli_query($c, "SELECT COUNT(*) FROM users"))[0];
    mysqli_close($c);
    if ($nd < 1) { return "Tidak ada destinasi pada hasil impor."; }
    if ($nu < 1) { return "Tidak ada akun pengguna pada hasil impor; login tidak akan bisa dipakai."; }
    return true;
}

function impor_sql(array $cfg, $mysql, $db, $berkas) {
    $kredensial = file_kredensial($cfg);
    list($kode, , $galat) = jalankan(argumen_mysql($mysql, $kredensial, $db), $berkas);
    @unlink($kredensial);
    return $kode === 0 ? true : trim($galat);
}

function buat_backup(array $cfg, $folder, $mysqldump) {
    $nama = $cfg["db"] . "_" . date("Ymd-His") . "_" . bin2hex(random_bytes(3)) . ".sql";
    $tujuan = $folder . DIRECTORY_SEPARATOR . $nama;
    $kredensial = file_kredensial($cfg);
    $perintah = array(
        $mysqldump,
        "--defaults-extra-file=" . $kredensial,
        "--single-transaction",
        "--quick",
        "--routines",
        "--triggers",
        "--default-character-set=utf8mb4",
        $cfg["db"],
    );
    list($kode, , $galat) = jalankan($perintah, null, $tujuan);
    @unlink($kredensial);
    if ($kode !== 0 || !sql_valid($tujuan)) {
        @unlink($tujuan);
        return array(false, "", trim($galat) !== "" ? trim($galat) : "Hasil dump kosong atau tidak valid.");
    }
    return array(true, $tujuan, "");
}

/* ---------- Utilitas ---------- */

function siapkan_folder($folder) {
    if (!is_dir($folder) && !@mkdir($folder, 0775, true)) {
        berhenti("Tidak bisa membuat folder backups/. Periksa izin folder proyek.");
    }
    $htaccess = $folder . DIRECTORY_SEPARATOR . ".htaccess";
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    $gitignore = $folder . DIRECTORY_SEPARATOR . ".gitignore";
    if (!is_file($gitignore)) {
        file_put_contents($gitignore, "*.sql\n*.log\n.kunci\n");
    }
}

function catat($berkas, $aksi, $status, $detail) {
    $baris = "[" . date("Y-m-d H:i:s") . "] " . str_pad($aksi, 8) . " " . str_pad($status, 8) . " " . $detail . "\n";
    @file_put_contents($berkas, $baris, FILE_APPEND);
}

function ukuran_manusia($bita) {
    if ($bita >= 1048576) { return number_format($bita / 1048576, 1, ",", ".") . " MB"; }
    if ($bita >= 1024) { return number_format($bita / 1024, 1, ",", ".") . " KB"; }
    return $bita . " B";
}

function konfirmasi($kata, $rincian, $tanpa_tanya) {
    if ($tanpa_tanya) { return true; }
    baris($rincian);
    baris();
    if (!function_exists("stream_isatty") || !@stream_isatty(STDIN)) {
        baris("Dibatalkan: terminal tidak interaktif. Tambahkan opsi --ya bila sudah yakin.");
        return false;
    }
    echo "Ketik " . $kata . " untuk melanjutkan, atau Enter untuk membatalkan: ";
    $jawab = trim((string) fgets(STDIN));
    if ($jawab !== $kata) {
        baris("Dibatalkan.");
        return false;
    }
    return true;
}

function daftar_backup($folder) {
    $berkas = glob($folder . DIRECTORY_SEPARATOR . "*.sql") ?: array();
    usort($berkas, function ($a, $b) { return filemtime($b) - filemtime($a); });
    return $berkas;
}

/* ---------- Perintah ---------- */

$cfg = baca_konfigurasi($AKAR . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "koneksi.php");
$mysql = cari_biner("mysql");
$mysqldump = cari_biner("mysqldump");
$perintah = $argv[1] ?? "bantuan";
$tanpa_tanya = in_array("--ya", $argv, true);

if (in_array($perintah, array("backup", "restore", "reset"), true)) {
    siapkan_folder($FOLDER_BACKUP);
    $kunci = @fopen($FOLDER_BACKUP . DIRECTORY_SEPARATOR . ".kunci", "c");
    if (!$kunci || !flock($kunci, LOCK_EX | LOCK_NB)) {
        berhenti("Proses lain sedang memakai backup/reset. Tunggu hingga selesai.");
    }
}

switch ($perintah) {
    case "bantuan":
        baris("Pengelola database Dolan Jogja");
        baris();
        baris("  php tools/db_kelola.php backup");
        baris("  php tools/db_kelola.php daftar");
        baris("  php tools/db_kelola.php restore backups/NAMA.sql [--ya]");
        baris("  php tools/db_kelola.php reset [--ya]");
        baris();
        baris("Backup disimpan di backups/. Database: " . $cfg["db"] . " @ " . $cfg["host"] . ".");
        exit(0);

    case "backup":
        list($ok, $berkas, $galat) = buat_backup($cfg, $FOLDER_BACKUP, $mysqldump);
        if (!$ok) {
            catat($BERKAS_LOG, "backup", "gagal", $galat);
            berhenti("Backup gagal: " . $galat);
        }
        catat($BERKAS_LOG, "backup", "ok", basename($berkas) . " (" . ukuran_manusia(filesize($berkas)) . ")");
        baris("Backup dibuat: " . $berkas);
        baris("Ukuran: " . ukuran_manusia(filesize($berkas)));
        break;

    case "daftar":
        $daftar = daftar_backup($FOLDER_BACKUP);
        if (count($daftar) === 0) {
            baris("Belum ada backup. Jalankan: php tools/db_kelola.php backup");
            break;
        }
        baris("Backup di " . $FOLDER_BACKUP . ":");
        foreach ($daftar as $berkas) {
            baris("  " . basename($berkas) . "  " . date("d/m/Y H:i", filemtime($berkas)) . "  " . ukuran_manusia(filesize($berkas)));
        }
        break;

    case "restore":
    case "reset":
        if ($perintah === "reset") {
            if (!is_file($BERKAS_SEED)) { berhenti("database.sql tidak ditemukan di folder proyek."); }
            if (!konfirmasi("RESET", "PERINGATAN: reset demo akan MENGHAPUS seluruh data di database \""
                . $cfg["db"] . "\" (akun, ulasan, favorit, rencana) lalu mengisinya ulang dari database.sql."
                . "\nBackup otomatis dibuat lebih dulu, tetapi data yang masuk setelah backup tetap hilang.",
                $tanpa_tanya)) {
                exit(2);
            }
            $sumber = $BERKAS_SEED;
            $label = "reset";
        } else {
            $berkas_arg = null;
            foreach (array_slice($argv, 2) as $arg) {
                if (strpos($arg, "--") !== 0) { $berkas_arg = $arg; break; }
            }
            if ($berkas_arg === null) { berhenti("Sebutkan berkas backup, mis. restore backups/db_wisata_....sql"); }
            $kandidat = (strpos($berkas_arg, "/") === false && strpos($berkas_arg, "\\") === false)
                ? $FOLDER_BACKUP . DIRECTORY_SEPARATOR . $berkas_arg : $berkas_arg;
            $nyata = realpath($kandidat);
            $folder_nyata = realpath($FOLDER_BACKUP);
            if ($nyata === false || !is_file($nyata)) { berhenti("Berkas backup tidak ditemukan: " . $berkas_arg); }
            if ($folder_nyata === false || strpos($nyata, $folder_nyata . DIRECTORY_SEPARATOR) !== 0) {
                berhenti("Hanya berkas di dalam folder backups/ yang boleh dipulihkan.");
            }
            if (!sql_valid($nyata)) { berhenti("Berkas bukan dump SQL yang valid (tidak ada CREATE TABLE)."); }
            if (!konfirmasi("RESTORE", "PERINGATAN: restore akan MENGGANTI seluruh isi database \""
                . $cfg["db"] . "\" dengan data dari:\n  " . basename($nyata)
                . "\nBackup otomatis data saat ini dibuat lebih dulu.",
                $tanpa_tanya)) {
                exit(2);
            }
            $sumber = $nyata;
            $label = "restore";
        }

        /* 1. Backup data saat ini sebelum apa pun ditimpa. */
        list($ok_backup, $berkas_backup, $galat_backup) = buat_backup($cfg, $FOLDER_BACKUP, $mysqldump);
        if ($ok_backup) {
            baris("Backup otomatis: " . basename($berkas_backup));
            catat($BERKAS_LOG, $label, "backup", basename($berkas_backup));
        } elseif ($perintah === "reset") {
            catat($BERKAS_LOG, $label, "gagal", "backup otomatis gagal: " . $galat_backup);
            berhenti("Reset dibatalkan karena backup otomatis gagal: " . $galat_backup);
        } else {
            baris("PERINGATAN: backup otomatis gagal (" . $galat_backup . "). Restore tetap dilanjutkan.");
        }

        /* 2. Saring SQL agar tidak ada USE/CREATE DATABASE yang mengalihkan target. */
        $sql_bersih = tempnam(sys_get_temp_dir(), "dolan_sql");
        if (!saring_sql($sumber, $sql_bersih) || !sql_valid($sql_bersih)) {
            @unlink($sql_bersih);
            berhenti("Berkas SQL tidak bisa dibaca atau tidak valid.");
        }

        /* 3. Impor ke database sementara dan validasi. */
        $db_uji = $cfg["db"] . "_uji_" . date("YmdHis");
        if (!buat_database($cfg, $db_uji)) {
            @unlink($sql_bersih);
            berhenti("Tidak bisa membuat database sementara \"" . $db_uji . "\".");
        }
        baris("Menguji impor ke database sementara " . $db_uji . "...");
        $hasil_uji = impor_sql($cfg, $mysql, $db_uji, $sql_bersih);
        if ($hasil_uji !== true) {
            hapus_database($cfg, $db_uji);
            @unlink($sql_bersih);
            catat($BERKAS_LOG, $label, "gagal", "impor uji: " . $hasil_uji);
            berhenti("Impor ke database sementara gagal, database hidup tidak diubah.\n" . $hasil_uji);
        }
        $cek = validasi_database($cfg, $db_uji);
        if ($cek !== true) {
            hapus_database($cfg, $db_uji);
            @unlink($sql_bersih);
            catat($BERKAS_LOG, $label, "gagal", "validasi: " . $cek);
            berhenti("Hasil impor tidak lolos validasi, database hidup tidak diubah.\n" . $cek);
        }
        baris("Validasi database sementara: OK.");

        /* 4. Timpa database hidup, dengan pemulihan otomatis bila gagal. */
        baris("Menimpa database hidup \"" . $cfg["db"] . "\"...");
        $hasil_hidup = impor_sql($cfg, $mysql, $cfg["db"], $sql_bersih);
        $cek_hidup = $hasil_hidup === true ? validasi_database($cfg, $cfg["db"]) : $hasil_hidup;
        if ($cek_hidup !== true && $ok_backup) {
            baris("Impor ke database hidup gagal. Memulihkan backup otomatis...");
            $sql_pulih = tempnam(sys_get_temp_dir(), "dolan_pulih");
            if (saring_sql($berkas_backup, $sql_pulih) && impor_sql($cfg, $mysql, $cfg["db"], $sql_pulih) === true) {
                @unlink($sql_pulih);
                hapus_database($cfg, $db_uji);
                @unlink($sql_bersih);
                catat($BERKAS_LOG, $label, "gagal", "impor hidup gagal; backup dipulihkan");
                berhenti("Impor ke database hidup gagal, tetapi backup otomatis sudah dipulihkan. Data kembali seperti sebelum aksi.");
            }
            @unlink($sql_pulih);
            hapus_database($cfg, $db_uji);
            @unlink($sql_bersih);
            catat($BERKAS_LOG, $label, "gagal", "impor hidup dan pemulihan gagal: " . (string) $cek_hidup);
            berhenti("Impor ke database hidup gagal DAN pemulihan backup juga gagal. Berkas backup: " . $berkas_backup);
        }
        if ($cek_hidup !== true) {
            hapus_database($cfg, $db_uji);
            @unlink($sql_bersih);
            catat($BERKAS_LOG, $label, "gagal", "impor hidup: " . (string) $cek_hidup);
            berhenti("Impor ke database hidup gagal dan tidak ada backup otomatis.\n" . (string) $cek_hidup);
        }

        /* 5. Bersihkan. */
        hapus_database($cfg, $db_uji);
        @unlink($sql_bersih);
        catat($BERKAS_LOG, $label, "ok", basename($sumber) . ($ok_backup ? "; backup " . basename($berkas_backup) : ""));
        baris("Selesai. Database \"" . $cfg["db"] . "\" sekarang berisi data dari " . basename($sumber) . ".");
        if ($ok_backup) { baris("Backup data sebelumnya: " . $berkas_backup); }
        break;

    default:
        berhenti("Perintah tidak dikenal: " . $perintah . ". Jalankan tanpa argumen untuk melihat bantuan.");
}
