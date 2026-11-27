<?php
// Tandai / batalkan tanda "Membantu" pada ulasan orang lain. Dipanggil dari destinasi/detail.php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";
require_once __DIR__ . "/../includes/pesan.php";
require_once __DIR__ . "/../includes/ulasan_bantuan.php";

cek_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$rid     = (int) ($_POST["id"] ?? 0);
$did     = (int) ($_POST["destination_id"] ?? 0);
$urut    = (string) ($_POST["urut"] ?? "terbaru");
$bintang = (int) ($_POST["bintang"] ?? 0);
$uid     = (int) $_SESSION["user_id"];

if (!in_array($urut, array("terbaru", "membantu", "tertinggi", "terendah"), true)) {
    $urut = "terbaru";
}
if ($bintang < 1 || $bintang > 5) { $bintang = 0; }

$kembali = BASE_URL . "destinasi/detail.php?id=" . $did
         . (in_array($urut, array("terbaru", "tertinggi", "terendah", "membantu"), true) ? "&urut=" . $urut : "")
         . ($bintang > 0 ? "&bintang=" . $bintang : "")
         . "#ulasan";

if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
    set_pesan("error", "Token keamanan tidak valid. Silakan coba lagi.");
    header("Location: " . $kembali);
    exit;
}

if (!pastikan_bantuan_ulasan($conn)) {
    set_pesan("error", "Fitur ulasan terbantu belum siap. Coba muat ulang halaman.");
    header("Location: " . $kembali);
    exit;
}

$st = mysqli_prepare($conn, "SELECT id, destination_id, user_id FROM reviews WHERE id = ? AND destination_id = ? LIMIT 1");
mysqli_stmt_bind_param($st, "ii", $rid, $did);
mysqli_stmt_execute($st);
$r = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if (!$r) {
    set_pesan("error", "Ulasan tidak ditemukan.");
    header("Location: " . BASE_URL . "index.php");
    exit;
}

if ((int) $r["user_id"] === $uid) {
    set_pesan("error", "Ulasan Anda sendiri tidak perlu ditandai membantu.");
    header("Location: " . $kembali);
    exit;
}

$st = mysqli_prepare($conn, "SELECT id FROM review_helps WHERE review_id = ? AND user_id = ? LIMIT 1");
mysqli_stmt_bind_param($st, "ii", $rid, $uid);
mysqli_stmt_execute($st);
$ada = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if ($ada) {
    $q = mysqli_prepare($conn, "DELETE FROM review_helps WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($q, "ii", $ada["id"], $uid);
    mysqli_stmt_execute($q);
    mysqli_stmt_close($q);
    set_pesan("sukses", "Tanda membantu dibatalkan.");
} else {
    $q = mysqli_prepare($conn, "INSERT INTO review_helps (review_id, user_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($q, "ii", $rid, $uid);
    mysqli_stmt_execute($q);
    mysqli_stmt_close($q);
    set_pesan("sukses", "Terima kasih! Ulasan ditandai membantu.");
}

header("Location: " . $kembali);
exit;
