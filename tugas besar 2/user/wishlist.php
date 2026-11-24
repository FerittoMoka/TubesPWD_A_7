<?php
require_once __DIR__ . "/../includes/init.php";
wajib_login();
$uid = (int) $USER["id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cek_csrf();
    if (($_POST["aksi"] ?? "") === "hapus") {
        $id = (int) ($_POST["id"] ?? 0);
        $s = $conn->prepare("DELETE FROM wishlists WHERE user_id=? AND destination_id=?");
        $s->bind_param("ii", $uid, $id);
        $s->execute();
        flash("success", "Destinasi dihapus dari favorit.");
    }
    redirect("user/wishlist.php");
}

$s = $conn->prepare("SELECT d.id, d.nama, d.lokasi, d.harga_tiket, d.harga_maks, c.nama AS kategori
                     FROM wishlists w
                     JOIN destinations d ON d.id=w.destination_id
                     JOIN categories c ON c.id=d.category_id
                     WHERE w.user_id=? ORDER BY w.id DESC");
$s->bind_param("i", $uid);
$s->execute();
$rows = $s->get_result();

header_web("Favorit Saya");
?>
<div class="page-heading">
  <span class="eyebrow">Rencana perjalanan</span>
  <h1>Destinasi favorit</h1>
  <p>Kumpulan tempat yang ingin kamu kunjungi.</p>
</div>
<div class="simple-list">
  <?php if (!$rows->num_rows) { ?>
  <div class="panel empty">Belum ada destinasi favorit. Jelajahi destinasi dan simpan tempat yang kamu suka.</div>
  <?php } ?>
  <?php while ($d = $rows->fetch_assoc()) { ?>
  <div class="panel simple-item">
    <div>
      <span class="tag"><?php echo e($d["kategori"]); ?></span>
      <h3><?php echo e($d["nama"]); ?></h3>
      <p class="muted"><?php echo e($d["lokasi"]); ?></p>
      <strong><?php echo harga($d["harga_tiket"], $d["harga_maks"]); ?></strong>
    </div>
    <div class="actions">
      <a class="text-link" href="<?php echo url("destinasi/detail.php?id=" . (int) $d["id"]); ?>">Detail</a>
      <?php echo form_aksi(url("user/wishlist.php"), "hapus", $d["id"], "Hapus", "Hapus dari favorit?"); ?>
    </div>
  </div>
  <?php } ?>
</div>
<?php footer_web(); ?>
