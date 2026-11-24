<?php
require_once __DIR__ . "/../includes/init.php";
wajib_admin();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cek_csrf();
    if (($_POST["aksi"] ?? "") === "hapus") {
        $id = (int) ($_POST["id"] ?? 0);
        $s = $conn->prepare("SELECT foto FROM destinations WHERE id=?");
        $s->bind_param("i", $id);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        if ($row) {
            $s = $conn->prepare("DELETE FROM destinations WHERE id=?");
            $s->bind_param("i", $id);
            $s->execute();
            hapus_gambar("destinasi", $row["foto"]);
            flash("success", "Destinasi berhasil dihapus.");
        }
    }
    redirect("admin/index.php");
}

function hitung($conn, $tabel) { return (int) $conn->query("SELECT COUNT(*) n FROM " . $tabel)->fetch_assoc()["n"]; }
$stat = array(
    "Pengguna"  => hitung($conn, "users"),
    "Destinasi" => hitung($conn, "destinations"),
    "Kategori"  => hitung($conn, "categories"),
    "Ulasan"    => hitung($conn, "reviews"),
    "Favorit"   => hitung($conn, "wishlists"),
);

$populer = $conn->query("SELECT d.id, d.nama,
        COUNT(DISTINCT r.id) AS n_ulasan, ROUND(AVG(r.rating),1) AS rating,
        (SELECT COUNT(*) FROM wishlists w WHERE w.destination_id=d.id) AS n_fav
        FROM destinations d LEFT JOIN reviews r ON r.destination_id=d.id
        GROUP BY d.id, d.nama ORDER BY n_ulasan DESC, n_fav DESC, d.nama ASC LIMIT 5");
$ulasan_baru = $conn->query("SELECT r.rating, r.komentar, r.created_at, u.username, d.nama AS destinasi
        FROM reviews r JOIN users u ON u.id=r.user_id JOIN destinations d ON d.id=r.destination_id
        ORDER BY r.created_at DESC, r.id DESC LIMIT 5");
$rows = $conn->query("SELECT d.id, d.nama, d.lokasi, d.harga_tiket, d.harga_maks, c.nama AS kategori
        FROM destinations d JOIN categories c ON c.id=d.category_id ORDER BY d.id DESC");

header_web("Panel Admin");
?>
<div class="page-heading">
  <span class="eyebrow">Pengelolaan konten</span>
  <h1>Panel administrator</h1>
  <p>Ringkasan data dan pengelolaan destinasi Dolan Jogja.</p>
</div>
<?php subnav_admin("index"); ?>

<div class="stat-grid">
  <?php foreach ($stat as $label => $nilai) { ?>
  <div class="panel stat-card"><b><?php echo $nilai; ?></b><span><?php echo e($label); ?></span></div>
  <?php } ?>
</div>

<div class="two-panels">
  <div class="panel table-wrap">
    <h2>Destinasi terpopuler</h2>
    <table>
      <thead><tr><th>Destinasi</th><th>Rating</th><th>Ulasan</th><th>Favorit</th></tr></thead>
      <tbody>
        <?php while ($p = $populer->fetch_assoc()) { ?>
        <tr><td><?php echo e($p["nama"]); ?></td><td><?php echo $p["rating"] !== null ? "&#9733; " . e($p["rating"]) : "-"; ?></td><td><?php echo (int) $p["n_ulasan"]; ?></td><td><?php echo (int) $p["n_fav"]; ?></td></tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
  <div class="panel table-wrap">
    <h2>Ulasan terbaru</h2>
    <table>
      <thead><tr><th>Destinasi</th><th>Oleh</th><th>Rating</th><th>Tanggal</th></tr></thead>
      <tbody>
        <?php while ($u = $ulasan_baru->fetch_assoc()) { ?>
        <tr><td><?php echo e($u["destinasi"]); ?></td><td><?php echo e($u["username"]); ?></td><td><?php echo (int) $u["rating"]; ?>/5</td><td><?php echo e(tanggal($u["created_at"])); ?></td></tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</div>

<div class="admin-actions"><a class="btn" href="<?php echo url("admin/destinasi.php"); ?>">+ Tambah destinasi</a></div>
<div class="panel table-wrap">
  <h2>Data destinasi</h2>
  <table>
    <thead><tr><th>Nama destinasi</th><th>Kategori</th><th>Lokasi</th><th>Tiket</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php while ($d = $rows->fetch_assoc()) { ?>
      <tr>
        <td><?php echo e($d["nama"]); ?></td>
        <td><?php echo e($d["kategori"]); ?></td>
        <td><?php echo e($d["lokasi"]); ?></td>
        <td><?php echo harga($d["harga_tiket"], $d["harga_maks"]); ?></td>
        <td class="aksi">
          <a href="<?php echo url("admin/destinasi.php?id=" . (int) $d["id"]); ?>">Edit</a>
          <?php echo form_aksi(url("admin/index.php"), "hapus", $d["id"], "Hapus", "Hapus destinasi ini beserta ulasannya?"); ?>
        </td>
      </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
<?php footer_web(); ?>
