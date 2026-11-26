<?php
require_once __DIR__ . "/../includes/init.php";
require_once __DIR__ . "/../includes/wilayah.php";
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
    } elseif (($_POST["aksi"] ?? "") === "arsip") {
        $id = (int) ($_POST["id"] ?? 0);
        $s = $conn->prepare("UPDATE destinations SET status = IF(status = 'aktif', 'arsip', 'aktif') WHERE id = ?");
        $s->bind_param("i", $id);
        $s->execute();
        flash("success", "Status destinasi berhasil diubah.");
    }
    redirect("admin/index.php");
}

function hitung($conn, $tabel) { return (int) $conn->query("SELECT COUNT(*) n FROM " . $tabel)->fetch_assoc()["n"]; }
$n_aktif = (int) $conn->query("SELECT COUNT(*) n FROM destinations WHERE status='aktif'")->fetch_assoc()["n"];
$n_arsip = (int) $conn->query("SELECT COUNT(*) n FROM destinations WHERE status='arsip'")->fetch_assoc()["n"];
$stat = array(
    "Pengguna"  => hitung($conn, "users"),
    "Destinasi aktif" => $n_aktif,
    "Diarsipkan" => $n_arsip,
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
// Pencarian & filter kategori untuk tabel "Data destinasi"
$cari_admin = trim($_GET["q"] ?? "");
$kat_admin = (int) ($_GET["kategori"] ?? 0);
$wil_admin = $_GET["wilayah"] ?? "";
if (!in_array($wil_admin, daftar_wilayah(), true)) { $wil_admin = ""; }
$daftar_status_admin = daftar_status();
$status_admin = $_GET["status"] ?? "";
if (!isset($daftar_status_admin[$status_admin])) { $status_admin = ""; }
$ada_filter_admin = ($cari_admin !== "" || $kat_admin > 0 || $wil_admin !== "" || $status_admin !== "");
$daftar_kat = $conn->query("SELECT id, nama FROM categories ORDER BY nama")->fetch_all(MYSQLI_ASSOC);

$sql_dest = "SELECT d.id, d.nama, d.lokasi, d.wilayah, d.status, d.harga_tiket, d.harga_maks, c.nama AS kategori
        FROM destinations d JOIN categories c ON c.id=d.category_id
        WHERE (d.nama LIKE ? OR d.lokasi LIKE ? OR COALESCE(d.wilayah,'') LIKE ?)";
$like_admin = "%" . $cari_admin . "%";
$tipe_admin = "sss";
$param_admin = array($like_admin, $like_admin, $like_admin);
if ($kat_admin > 0) {
    $sql_dest .= " AND d.category_id = ?";
    $tipe_admin .= "i";
    $param_admin[] = $kat_admin;
}
if ($wil_admin !== "") {
    $sql_dest .= " AND d.wilayah = ?";
    $tipe_admin .= "s";
    $param_admin[] = $wil_admin;
}
if ($status_admin !== "") {
    $sql_dest .= " AND d.status = ?";
    $tipe_admin .= "s";
    $param_admin[] = $status_admin;
}
$sql_dest .= " ORDER BY d.id DESC";
$st_dest = $conn->prepare($sql_dest);
$st_dest->bind_param($tipe_admin, ...$param_admin);
$st_dest->execute();
$rows = $st_dest->get_result();

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
<div class="panel table-wrap" id="data-destinasi">
  <h2>Data destinasi</h2>
  <form method="get" action="<?php echo url("admin/index.php"); ?>#data-destinasi" class="form-isi">
    <div class="filter-admin">
      <input type="text" name="q" value="<?php echo e($cari_admin); ?>" placeholder="Cari nama atau lokasi" />
      <select name="kategori">
        <option value="0">Semua kategori</option>
        <?php foreach ($daftar_kat as $k) { ?>
        <option value="<?php echo (int) $k["id"]; ?>"<?php if ($kat_admin === (int) $k["id"]) { echo ' selected="selected"'; } ?>><?php echo e($k["nama"]); ?></option>
        <?php } ?>
      </select>
      <select name="wilayah">
        <option value="">Semua wilayah</option>
        <?php foreach (daftar_wilayah() as $w) { ?>
        <option value="<?php echo e($w); ?>"<?php if ($wil_admin === $w) { echo ' selected="selected"'; } ?>><?php echo e($w); ?></option>
        <?php } ?>
      </select>
      <select name="status">
        <option value="">Semua status</option>
        <?php foreach ($daftar_status_admin as $ks => $ls) { ?>
        <option value="<?php echo e($ks); ?>"<?php if ($status_admin === $ks) { echo ' selected="selected"'; } ?>><?php echo e(ucfirst($ks)); ?></option>
        <?php } ?>
      </select>
      <input type="submit" value="Cari" />
      <?php if ($ada_filter_admin) { ?><a href="<?php echo url("admin/index.php"); ?>#data-destinasi">Reset</a><?php } ?>
    </div>
  </form>
  <p class="kecil"><?php echo (int) $rows->num_rows; ?> destinasi<?php echo $ada_filter_admin ? " cocok dengan filter" : ""; ?></p>
  <table>
    <thead><tr><th>Nama destinasi</th><th>Kategori</th><th>Lokasi</th><th>Wilayah</th><th>Tiket</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php if ($rows->num_rows === 0) { ?>
      <tr><td colspan="7">Tidak ada destinasi yang cocok.</td></tr>
      <?php } ?>
      <?php while ($d = $rows->fetch_assoc()) { ?>
      <tr<?php echo $d["status"] === "arsip" ? ' class="baris-arsip"' : ""; ?>>
        <td><?php echo e($d["nama"]); ?></td>
        <td><?php echo e($d["kategori"]); ?></td>
        <td><?php echo e($d["lokasi"]); ?></td>
        <td><?php echo e($d["wilayah"] ?? "-"); ?></td>
        <td><?php echo harga($d["harga_tiket"], $d["harga_maks"]); ?></td>
        <td><span class="tag tag-<?php echo e($d["status"]); ?>"><?php echo $d["status"] === "aktif" ? "Aktif" : "Arsip"; ?></span></td>
        <td class="aksi">
          <a href="<?php echo url("admin/destinasi.php?id=" . (int) $d["id"]); ?>">Edit</a>
          <?php echo form_aksi(url("admin/index.php"), "arsip", $d["id"], $d["status"] === "aktif" ? "Arsipkan" : "Aktifkan", $d["status"] === "aktif" ? "Sembunyikan destinasi ini dari situs?" : ""); ?>
          <?php echo form_aksi(url("admin/index.php"), "hapus", $d["id"], "Hapus", "Hapus destinasi ini beserta ulasannya?"); ?>
        </td>
      </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
<?php footer_web(); ?>