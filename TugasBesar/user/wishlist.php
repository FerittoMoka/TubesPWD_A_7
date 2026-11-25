<?php
// Menambah / menghapus destinasi dari wishlist (toggle). Dipanggil dari destinasi/detail.php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";

cek_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$id  = (int) ($_POST["destination_id"] ?? 0);
$uid = (int) $_SESSION["user_id"];
$kembali = BASE_URL . "destinasi/detail.php?id=" . $id . "&wl=";

if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
    header("Location: " . $kembali . "gagal");
    exit;
}

// Pastikan destinasinya ada
$st = mysqli_prepare($conn, "SELECT id FROM destinations WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($st, "i", $id);
mysqli_stmt_execute($st);
if (!mysqli_fetch_assoc(mysqli_stmt_get_result($st))) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}
mysqli_stmt_close($st);

$st = mysqli_prepare($conn, "SELECT id FROM wishlists WHERE user_id = ? AND destination_id = ? LIMIT 1");
mysqli_stmt_bind_param($st, "ii", $uid, $id);
mysqli_stmt_execute($st);
$ada = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if ($ada) {
    $q = mysqli_prepare($conn, "DELETE FROM wishlists WHERE user_id = ? AND destination_id = ?");
    $hasil = "dihapus";
} else {
    // Destinasi baru masuk di akhir Hari ke-1 pada Rencana Dolan
    $q = mysqli_prepare($conn, "INSERT INTO wishlists (user_id, destination_id, hari_ke, urutan)
        SELECT ?, ?, 1, COALESCE(MAX(urutan), 0) + 1 FROM wishlists WHERE user_id = ? AND hari_ke = 1");
    $hasil = "tersimpan";
}
if ($ada) {
    mysqli_stmt_bind_param($q, "ii", $uid, $id);
} else {
    mysqli_stmt_bind_param($q, "iii", $uid, $id, $uid);
}
mysqli_stmt_execute($q);
mysqli_stmt_close($q);

header("Location: " . $kembali . $hasil);
exit;