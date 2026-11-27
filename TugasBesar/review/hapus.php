<?php
// Hapus ulasan: hanya pemilik ulasan atau admin
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";
require_once __DIR__ . "/../includes/pesan.php";
require_once __DIR__ . "/../includes/foto_ulasan.php";

cek_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$rid = (int) ($_POST["id"] ?? 0);

if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
    set_pesan("error", "Token keamanan tidak valid. Silakan coba lagi.");
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$st = mysqli_prepare($conn, "SELECT user_id, destination_id FROM reviews WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($st, "i", $rid);
mysqli_stmt_execute($st);
$r = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if (!$r) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$boleh = ((int) $r["user_id"] === (int) $_SESSION["user_id"]) || ($_SESSION["role"] === "admin");
$kembali = BASE_URL . "destinasi/detail.php?id=" . (int) $r["destination_id"] . "#ulasan";

if (!$boleh) {
    set_pesan("error", "Anda tidak berhak menghapus ulasan ini.");
} else {
    /* Hapus juga foto kunjungan yang dilampirkan pada ulasan ini. */
    hapus_foto_ulasan($conn, $rid, (int) $r["destination_id"]);

    $q = mysqli_prepare($conn, "DELETE FROM reviews WHERE id = ?");
    mysqli_stmt_bind_param($q, "i", $rid);
    mysqli_stmt_execute($q);
    mysqli_stmt_close($q);
    set_pesan("sukses", "Ulasan dihapus.");
}

header("Location: " . $kembali);
exit;
