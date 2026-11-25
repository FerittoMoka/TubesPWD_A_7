<?php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";

$id = (int) ($_GET["id"] ?? 0);
$st = mysqli_prepare($conn, "SELECT d.*, c.nama AS kategori,
    (SELECT AVG(rating) FROM reviews WHERE destination_id = d.id) AS rata,
    (SELECT COUNT(*) FROM reviews WHERE destination_id = d.id) AS jml_ulasan
    FROM destinations d JOIN categories c ON c.id = d.category_id WHERE d.id = ?");
mysqli_stmt_bind_param($st, "i", $id);
mysqli_stmt_execute($st);
$d = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
if (!$d) {
    http_response_code(404);
    $judul = "Tidak ditemukan";
    require_once __DIR__ . "/../includes/header.php";
    echo '<div class="wadah"><div class="halaman-isi"><p class="kosong">Destinasi tidak ditemukan. <a href="' . BASE_URL . 'index.php">Kembali ke beranda</a></p></div></div>';
    require_once __DIR__ . "/../includes/footer.php";
    exit;
}

$st = mysqli_prepare($conn, "SELECT r.id, r.user_id, r.rating, r.komentar, r.created_at, u.username FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.destination_id = ? ORDER BY r.created_at DESC");
mysqli_stmt_bind_param($st, "i", $id);
mysqli_stmt_execute($st);
$ulasan = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);

$milik = null;
if (sudah_login()) {
    foreach ($ulasan as $r) {
        if ((int) $r["user_id"] === (int) $_SESSION["user_id"]) { $milik = $r; }
    }
}

$tersimpan = false;
if (sudah_login()) {
    $st = mysqli_prepare($conn, "SELECT id FROM wishlists WHERE user_id = ? AND destination_id = ? LIMIT 1");
    mysqli_stmt_bind_param($st, "ii", $_SESSION["user_id"], $id);
    mysqli_stmt_execute($st);
    $tersimpan = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($st));
}

// Terkait: kategori yang sama dulu, lalu sisanya (maks. 3)
$st = mysqli_prepare($conn, "SELECT d.id, d.nama, d.foto, d.harga_tiket, d.harga_maks, c.nama AS kategori,
    (SELECT AVG(rating) FROM reviews WHERE destination_id = d.id) AS rata,
    (SELECT COUNT(*) FROM reviews WHERE destination_id = d.id) AS jml_ulasan
    FROM destinations d JOIN categories c ON c.id = d.category_id
    WHERE d.id <> ? ORDER BY (d.category_id = ?) DESC, d.id LIMIT 3");
mysqli_stmt_bind_param($st, "ii", $id, $d["category_id"]);
mysqli_stmt_execute($st);
$terkait = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);

$wl = $_GET["wl"] ?? "";

$judul = $d["nama"];
require_once __DIR__ . "/../includes/header.php";
?>
<div class="wadah"><div class="halaman-isi">
    <p class="breadcrumb">
        <a href="<?php echo BASE_URL; ?>index.php">Beranda</a> &gt;
        <a href="<?php echo BASE_URL; ?>index.php?kategori=<?php echo (int) $d["category_id"]; ?>#destinasi"><?php echo e($d["kategori"]); ?></a> &gt;
        <span class="sekarang"><?php echo e($d["nama"]); ?></span>
    </p>
    <?php if ($wl === "tersimpan") { ?><p class="sukses">Disimpan ke daftar favorit.</p><?php } ?>
    <?php if ($wl === "dihapus") { ?><p class="sukses">Dihapus dari daftar favorit.</p><?php } ?>
    <?php if ($wl === "gagal") { ?><p class="error">Gagal memproses favorit. Silakan coba lagi.</p><?php } ?>
    <div class="panel">
        <div style="height:280px;border-radius:12px;overflow:hidden;margin-bottom:16px;"><?php echo tampil_foto($d["foto"], $d["kategori"], $d["nama"]); ?></div>
        <span class="label"><?php echo e($d["kategori"]); ?></span>
        <h2><?php echo e($d["nama"]); ?></h2>
        <p><?php echo e($d["lokasi"]); ?></p>
        <p class="meta-baris"><?php echo tampil_rating($d["rata"], $d["jml_ulasan"]); ?></p>
        <p><strong>Harga tiket:</strong> <?php echo e(rentang_harga($d["harga_tiket"], $d["harga_maks"])); ?><br />
        <strong>Jam buka:</strong> <?php echo e($d["jam_buka"] ?: "Tidak tersedia"); ?></p>
        <p><?php echo nl2br(e($d["deskripsi"])); ?></p>
        <?php if ($d["latitude"] !== null && $d["longitude"] !== null) { ?>
            <p><a class="tombol tombol-garis" href="https://www.google.com/maps?q=<?php echo e($d["latitude"]); ?>,<?php echo e($d["longitude"]); ?>" target="_blank" rel="noopener noreferrer">Buka di Google Maps</a></p>
        <?php } ?>
        <?php if (sudah_login()) { ?>
            <form method="post" action="<?php echo BASE_URL; ?>user/wishlist.php" class="inline">
                <div class="inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="destination_id" value="<?php echo (int) $d["id"]; ?>" />
                    <button type="submit" class="tombol<?php echo $tersimpan ? ' tombol-garis' : ''; ?>"><?php echo $tersimpan ? "&#10003; Tersimpan (klik untuk hapus)" : "Simpan ke favorit"; ?></button>
                </div>
            </form>
        <?php } else { ?>
            <p><a class="tombol" href="<?php echo BASE_URL; ?>auth/login.php">Masuk untuk menyimpan ke favorit</a></p>
        <?php } ?>
    </div>

    <div class="panel" id="ulasan">
        <h3>Ulasan (<?php echo count($ulasan); ?>)</h3>
        <?php tampil_pesan(); ?>

        <?php if (sudah_login()) { ?>
            <form method="post" action="<?php echo BASE_URL; ?>review/simpan.php" class="form-isi">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="destination_id" value="<?php echo (int) $d["id"]; ?>" />
                <p><label for="rating"><?php echo $milik ? "Ubah ulasan Anda" : "Tulis ulasan"; ?></label>
                <select id="rating" name="rating">
                    <?php for ($i = 5; $i >= 1; $i--) { ?>
                        <option value="<?php echo $i; ?>"<?php if ($milik && (int) $milik["rating"] === $i) { echo ' selected="selected"'; } ?>><?php echo $i; ?> bintang</option>
                    <?php } ?>
                </select></p>
                <p><textarea name="komentar" rows="3" maxlength="1000" placeholder="Ceritakan pengalamanmu"><?php echo e($milik ? $milik["komentar"] : ""); ?></textarea></p>
                <p><input type="submit" value="<?php echo $milik ? "Perbarui ulasan" : "Kirim ulasan"; ?>" /></p>
            </form>
        <?php } else { ?>
            <p class="kecil"><a href="<?php echo BASE_URL; ?>auth/login.php">Masuk</a> untuk menulis ulasan.</p>
        <?php } ?>

        <?php if (count($ulasan) === 0) { ?>
            <p class="kosong">Belum ada ulasan.</p>
        <?php } ?>
        <?php foreach ($ulasan as $r) { ?>
            <div style="border-top:1px solid #E8DCC4;padding:12px 0;">
                <strong><?php echo e($r["username"]); ?></strong> &middot; <?php echo (int) $r["rating"]; ?>/5
                <span class="kecil"> &middot; <?php echo e(date("d M Y", strtotime($r["created_at"]))); ?></span>
                <p><?php echo nl2br(e($r["komentar"])); ?></p>
                <?php if (sudah_login() && ((int) $r["user_id"] === (int) $_SESSION["user_id"] || $_SESSION["role"] === "admin")) { ?>
                    <form method="post" action="<?php echo BASE_URL; ?>review/hapus.php" class="inline" onsubmit="return konfirmasiHapus('ulasan ini');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $r["id"]; ?>" />
                        <input type="hidden" name="kembali" value="detail" />
                        <button type="submit" class="btn-kecil btn-bahaya">Hapus</button>
                    </form>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <?php if (count($terkait) > 0) { ?>
    <div class="bagian">
        <p class="eyebrow">Masih penasaran?</p>
        <h2>Pilihan terkait lainnya</h2>
        <div class="terkait-grid">
            <?php foreach ($terkait as $t) { ?>
                <div class="baris-kecil kartu-klik">
                    <?php echo tampil_foto($t["foto"], $t["kategori"], $t["nama"]); ?>
                    <div>
                        <span class="label"><?php echo e($t["kategori"]); ?></span>
                        <h3><?php echo e($t["nama"]); ?></h3>
                        <p class="meta-baris"><?php echo tampil_rating($t["rata"], $t["jml_ulasan"]); ?><span><?php echo e(rentang_harga($t["harga_tiket"], $t["harga_maks"])); ?></span></p>
                        <a href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $t["id"]; ?>">Lihat detail</a>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div></div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>