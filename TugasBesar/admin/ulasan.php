<?php
require_once __DIR__ . "/../includes/init.php";
wajib_admin();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cek_csrf();
    if (($_POST["aksi"] ?? "") === "hapus") {
        $id = (int) ($_POST["id"] ?? 0);
        $s = $conn->prepare("DELETE FROM reviews WHERE id=?");
        $s->bind_param("i", $id);
        $s->execute();
        catat_log_admin($conn, (int) $USER["id"], "hapus_ulasan", "ulasan #" . $id);
        flash("success", "Ulasan dihapus.");
    }
    redirect("admin/ulasan.php");
}

$cari   = trim($_GET["cari"] ?? "");
$rating = (int) ($_GET["rating"] ?? 0);
$like   = "%" . $cari . "%";

$sql = "SELECT r.id, r.rating, r.komentar, r.created_at, u.username, d.nama AS destinasi
        FROM reviews r JOIN users u ON u.id=r.user_id JOIN destinations d ON d.id=r.destination_id
        WHERE (d.nama LIKE ? OR u.username LIKE ? OR r.komentar LIKE ?)";
$tipe  = "sss";
$param = array($like, $like, $like);
if ($rating >= 1 && $rating <= 5) {
    $sql .= " AND r.rating=?";
    $tipe .= "i";
    $param[] = $rating;
}
$sql .= " ORDER BY r.created_at DESC, r.id DESC LIMIT 100";
$s = $conn->prepare($sql);
$s->bind_param($tipe, ...$param);
$s->execute();
$rows = $s->get_result();
$self = url("admin/ulasan.php");

header_web("Moderasi Ulasan");
?>
<div class="page-heading">
  <span class="eyebrow">Administrator</span>
  <h1>Moderasi ulasan</h1>
  <p>Pantau ulasan pengunjung dan hapus yang tidak sesuai.</p>
</div>
<?php subnav_admin("ulasan"); ?>

<form class="panel" method="get" action="<?php echo $self; ?>">
  <div class="search">
    <input type="text" name="cari" value="<?php echo e($cari); ?>" placeholder="Cari destinasi, pengguna, atau isi komentar" />
    <select name="rating">
      <option value="0">Semua rating</option>
      <?php for ($i = 5; $i >= 1; $i--) { ?>
      <option value="<?php echo $i; ?>"<?php echo $rating === $i ? ' selected="selected"' : ""; ?>><?php echo $i; ?> bintang</option>
      <?php } ?>
    </select>
    <button class="btn" type="submit">Cari</button>
  </div>
</form>

<div class="panel table-wrap">
  <table>
    <thead><tr><th>Destinasi</th><th>Oleh</th><th>Rating</th><th>Komentar</th><th>Tanggal</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php if ($rows->num_rows === 0) { ?>
      <tr><td colspan="6" class="muted">Tidak ada ulasan yang cocok.</td></tr>
      <?php } ?>
      <?php while ($r = $rows->fetch_assoc()) { ?>
      <tr>
        <td><?php echo e($r["destinasi"]); ?></td>
        <td><?php echo e($r["username"]); ?></td>
        <td><?php echo (int) $r["rating"]; ?>/5</td>
        <td><?php echo e(mb_strimwidth((string) $r["komentar"], 0, 120, "...")); ?></td>
        <td><?php echo e(tanggal($r["created_at"])); ?></td>
        <td class="aksi"><?php echo form_aksi($self, "hapus", $r["id"], "Hapus", "Hapus ulasan ini?"); ?></td>
      </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
<?php footer_web(); ?>