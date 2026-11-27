<?php
// Simpan / perbarui ulasan. Dipanggil dari destinasi/detail.php
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

    $rid = $lama ? (int) $lama["id"] : (int) mysqli_insert_id($conn);
    $pesan_ok = $lama ? "Ulasan Anda diperbarui." : "Ulasan Anda tersimpan.";
    $galat_foto = "";

    /* Foto kunjungan opsional: satu foto per ulasan, bisa dihapus/diganti. */
    if ($rid > 0) {
        if (!empty($_POST["hapus_foto"]) && hapus_foto_ulasan($conn, $rid, $id)) {
            $pesan_ok .= " Foto kunjungan dihapus.";
        }
        $unggah = simpan_foto_ulasan($conn, $rid, $id, $_FILES["foto_ulasan"] ?? array());
        if ($unggah["ok"]) { $pesan_ok .= " Foto kunjungan tersimpan."; }
        if ($unggah["pesan"] !== "") { $galat_foto = $unggah["pesan"]; }
    }

    if ($galat_foto !== "") {
        set_pesan("error", $pesan_ok . " Namun foto gagal diproses: " . $galat_foto);
    } else {
        set_pesan("sukses", $pesan_ok);
    }
}

header("Location: " . $kembali);
exit;
