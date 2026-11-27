<?php
// Simpan / perbarui ulasan. Dipanggil dari destinasi/detail.php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";
require_once __DIR__ . "/../includes/pesan.php";

cek_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$id      = (int) ($_POST["destination_id"] ?? 0);
$uid     = (int) $_SESSION["user_id"];
$rating  = (int) ($_POST["rating"] ?? 0);
$komentar = (string) ($_POST["komentar"] ?? "");
$kembali = BASE_URL . "destinasi/detail.php?id=" . $id . "#ulasan";

if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
    set_pesan("error", "Token keamanan tidak valid. Silakan coba lagi.");
    header("Location: " . $kembali);
    exit;
}

// Pastikan destinasi ada
$st = mysqli_prepare($conn, "SELECT id FROM destinations WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($st, "i", $id);
mysqli_stmt_execute($st);
if (!mysqli_fetch_assoc(mysqli_stmt_get_result($st))) {
    mysqli_stmt_close($st);
    header("Location: " . BASE_URL . "index.php");
    exit;
}
mysqli_stmt_close($st);

/* Normalisasi teks: samakan akhir baris, buang spasi di ujung baris,
   dan rapatkan baris kosong berlebih (maksimal satu baris kosong). */
$komentar = str_replace(array("\r\n", "\r"), "\n", $komentar);
$komentar = preg_replace("/[ \t]+\n/u", "\n", $komentar);
$komentar = preg_replace("/\n{3,}/u", "\n\n", trim($komentar));
$komentar = trim((string) $komentar);
$panjang_komentar = mb_strlen($komentar, "UTF-8");
$batas_komentar = 600;

if ($rating < 1 || $rating > 5) {
    set_pesan("error", "Pilih rating 1 sampai 5.");
} elseif ($panjang_komentar > $batas_komentar) {
    set_pesan("error", "Komentar maksimal " . $batas_komentar . " karakter (saat ini " . $panjang_komentar . ").");
} else {
    $st = mysqli_prepare($conn, "SELECT id FROM reviews WHERE user_id = ? AND destination_id = ? LIMIT 1");
    mysqli_stmt_bind_param($st, "ii", $uid, $id);
    mysqli_stmt_execute($st);
    $lama = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);

    if ($lama) {
        $q = mysqli_prepare($conn, "UPDATE reviews SET rating = ?, komentar = ? WHERE id = ?");
        mysqli_stmt_bind_param($q, "isi", $rating, $komentar, $lama["id"]);
    } else {
        $q = mysqli_prepare($conn, "INSERT INTO reviews (destination_id, user_id, rating, komentar) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($q, "iiis", $id, $uid, $rating, $komentar);
    }
    mysqli_stmt_execute($q);
    mysqli_stmt_close($q);
    set_pesan("sukses", $lama ? "Ulasan Anda diperbarui." : "Ulasan Anda tersimpan.");
}

header("Location: " . $kembali);
exit;
