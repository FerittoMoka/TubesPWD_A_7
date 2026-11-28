<?php
require_once __DIR__ . "/../includes/init.php";
require_once __DIR__ . "/../includes/galeri.php";
wajib_admin();
pastikan_galeri_destinasi($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cek_csrf();
    $aksi_post = $_POST["aksi"] ?? "";
    if ($aksi_post === "arsip" || $aksi_post === "aktifkan") {
        $id = (int) ($_POST["id"] ?? 0);
        $status_baru = ($aksi_post === "arsip") ? "arsip" : "aktif";
        $s = $conn->prepare("UPDATE destinations SET status=? WHERE id=?");
        $s->bind_param("si", $status_baru, $id);
        $s->execute();
        catat_log_admin($conn, (int) $USER["id"], $status_baru === "arsip" ? "arsip_destinasi" : "aktifkan_destinasi", "destinasi #" . $id);
        flash("success", $status_baru === "arsip" ? "Destinasi diarsipkan dan disembunyikan dari pengunjung." : "Destinasi diaktifkan kembali.");
    }
    if (($_POST["aksi"] ?? "") === "hapus") {
        $id = (int) ($_POST["id"] ?? 0);
        $s = $conn->prepare("SELECT nama, foto FROM destinations WHERE id=?");
        $s->bind_param("i", $id);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        if ($row) {
            /* Hapus destinasi + seluruh data terkait secara atomik.
               Relasi ON DELETE CASCADE sudah ada, tetapi pembersihan eksplisit ini
               menjaga data yatim bila foreign key belum aktif di instalasi lain. */
            mysqli_begin_transaction($conn);
            try {
                foreach (array("reviews", "wishlists", "favorites") as $tabel_terkait) {
                    $q = $conn->prepare("DELETE FROM " . $tabel_terkait . " WHERE destination_id = ?");
                    $q->bind_param("i", $id);
                    $q->execute();
                }
                $q = $conn->prepare("DELETE FROM destinations WHERE id = ?");
                $q->bind_param("i", $id);
                $q->execute();

                mysqli_commit($conn);
                hapus_gambar("destinasi", $row["foto"]);
                catat_log_admin($conn, (int) $USER["id"], "hapus_destinasi", "destinasi #" . $id . " " . $row["nama"]);
                flash("success", "Destinasi berhasil dihapus.");
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                error_log("Dolan Jogja (admin/index.php hapus): " . $e->getMessage());
                flash("error", "Destinasi gagal dihapus. Tidak ada data yang berubah, silakan coba lagi.");
            }
        }
    }
    redirect("admin/index.php");
}

function hitung($conn, $tabel) { return (int) $conn->query("SELECT COUNT(*) n FROM " . $tabel)->fetch_assoc()["n"]; }
/* Ringkasan tautan berbagi: memakai penghitung yang sudah dicatat halaman publik. */
require_once __DIR__ . "/../includes/rencana_bagi.php";
pastikan_rencana_bagi($conn);
$bagi_aktif = (int) $conn->query("SELECT COUNT(*) n FROM rencana_bagi WHERE kedaluwarsa IS NULL OR kedaluwarsa >= CURDATE()")->fetch_assoc()["n"];
$bagi_kunjungan = (int) $conn->query("SELECT COALESCE(SUM(jumlah_buka),0) n FROM rencana_bagi")->fetch_assoc()["n"];
$bagi_teratas = $conn->query("SELECT b.jumlah_buka, b.terakhir_diakses, b.kedaluwarsa, u.username, u.id AS uid
        FROM rencana_bagi b JOIN users u ON u.id = b.user_id
        ORDER BY b.jumlah_buka DESC, b.terakhir_diakses DESC LIMIT 5");
$log_admin = ambil_log_admin($conn, 8);

$stat = array(
    "Pengguna"  => hitung($conn, "users"),
    "Destinasi aktif" => (int) $conn->query("SELECT COUNT(*) n FROM destinations WHERE status='aktif'")->fetch_assoc()["n"],
    "Kategori"  => hitung($conn, "categories"),
    "Ulasan"    => hitung($conn, "reviews"),
    "Favorit"   => hitung($conn, "wishlists"),
    "Tautan aktif" => $bagi_aktif,
    "Kunjungan tautan" => $bagi_kunjungan,
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
$status_admin = $_GET["status"] ?? "";
if (!in_array($status_admin, array("aktif", "arsip"), true)) { $status_admin = ""; }
$ada_filter_admin = ($cari_admin !== "" || $kat_admin > 0 || $status_admin !== "");
$daftar_kat = $conn->query("SELECT id, nama FROM categories ORDER BY nama")->fetch_all(MYSQLI_ASSOC);

$sql_dest = "SELECT d.id, d.nama, d.lokasi, d.wilayah, d.status, d.harga_tiket, d.harga_maks, c.nama AS kategori
        FROM destinations d JOIN categories c ON c.id=d.category_id
        WHERE (d.nama LIKE ? OR d.lokasi LIKE ?)";
$like_admin = "%" . $cari_admin . "%";
$tipe_admin = "ss";
$param_admin = array($like_admin, $like_admin);
if ($kat_admin > 0) {
    $sql_dest .= " AND d.category_id = ?";
    $tipe_admin .= "i";
    $param_admin[] = $kat_admin;
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

<div class="two-panels">
  <div class="panel table-wrap">
    <h2>Tautan berbagi paling sering dibuka</h2>
    <table>
      <thead><tr><th>Pemilik</th><th>Dibuka</th><th>Terakhir</th><th>Berlaku sampai</th></tr></thead>
      <tbody>
        <?php if (!$bagi_teratas || $bagi_teratas->num_rows === 0) { ?>
        <tr><td colspan="4" class="muted">Belum ada tautan berbagi yang dibuat.</td></tr>
        <?php } ?>
        <?php while ($bagi_teratas && ($b = $bagi_teratas->fetch_assoc())) { ?>
        <tr>
          <td><?php echo e($b["username"]); ?></td>
          <td><?php echo (int) $b["jumlah_buka"]; ?> kali</td>
          <td><?php echo $b["terakhir_diakses"] ? e(tanggal($b["terakhir_diakses"])) : "-"; ?></td>
          <td><?php echo $b["kedaluwarsa"] ? e(tanggal($b["kedaluwarsa"])) : "Tanpa batas"; ?></td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
  <div class="panel table-wrap">
    <h2>Aktivitas admin terakhir</h2>
    <table>
      <thead><tr><th>Waktu</th><th>Admin</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (count($log_admin) === 0) { ?>
        <tr><td colspan="3" class="muted">Belum ada aktivitas yang tercatat.</td></tr>
        <?php } ?>
        <?php foreach ($log_admin as $log) { ?>
        <tr>
          <td><?php echo e(date("j M H:i", strtotime($log["dibuat"]))); ?></td>
          <td><?php echo e($log["username"] ?: "-"); ?></td>
          <td><?php echo e(str_replace("_", " ", $log["aksi"])); ?><?php if ($log["detail"] !== "") { ?> <span class="kecil"><?php echo e($log["detail"]); ?></span><?php } ?></td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</div>

<div class="admin-actions">
  <a class="btn" href="<?php echo url("admin/destinasi.php"); ?>">+ Tambah destinasi</a>
  <form method="post" action="<?php echo url("admin/backup.php"); ?>" class="inline" onsubmit="return confirm('Unduh salinan database sekarang? Berkas berisi data pengguna, jadi simpan di tempat aman.');">
    <?php echo csrf_field(); ?>
    <button type="submit" class="btn btn-sekunder">Unduh backup database (.sql)</button>
  </form>
</div>
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
      <select name="status">
        <option value="">Semua status</option>
        <option value="aktif"<?php if ($status_admin === "aktif") { echo ' selected="selected"'; } ?>>Aktif</option>
        <option value="arsip"<?php if ($status_admin === "arsip") { echo ' selected="selected"'; } ?>>Arsip</option>
      </select>
      <input type="submit" value="Cari" />
      <?php if ($ada_filter_admin) { ?><a href="<?php echo url("admin/index.php"); ?>#data-destinasi">Reset</a><?php } ?>
    </div>
  </form>
  <p class="kecil"><?php echo (int) $rows->num_rows; ?> destinasi<?php echo $ada_filter_admin ? " cocok dengan filter" : ""; ?></p>
  <table>
    <thead><tr><th>Nama destinasi</th><th>Kategori</th><th>Wilayah</th><th>Foto</th><th>Status</th><th>Lokasi</th><th>Tiket</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php if ($rows->num_rows === 0) { ?>
      <tr><td colspan="8">Tidak ada destinasi yang cocok.</td></tr>
      <?php } ?>
      <?php while ($d = $rows->fetch_assoc()) { ?>
      <tr>
        <td><?php echo e($d["nama"]); ?></td>
        <td><?php echo e($d["kategori"]); ?></td>
        <td><?php echo e($d["wilayah"] ?: "-"); ?></td>
        <?php $n_foto_admin = jumlah_foto_galeri((int) $d["id"]); ?>
        <td><span class="badge-foto <?php echo $n_foto_admin >= 3 ? "lengkap" : ($n_foto_admin > 0 ? "kurang" : "kosong"); ?>" title="<?php echo $n_foto_admin >= 3 ? "Galeri lengkap" : "Foto galeri masih kurang dari 3"; ?>"><?php echo (int) $n_foto_admin; ?> Foto</span></td>
        <td><span class="badge-status <?php echo $d["status"] === "arsip" ? "arsip" : "aktif"; ?>"><?php echo $d["status"] === "arsip" ? "Arsip" : "Aktif"; ?></span></td>
        <td><?php echo e($d["lokasi"]); ?></td>
        <td><?php echo harga($d["harga_tiket"], $d["harga_maks"]); ?></td>
        <td class="aksi">
          <a href="<?php echo url("admin/destinasi.php?id=" . (int) $d["id"]); ?>">Edit</a>
          <?php if ($d["status"] === "arsip") { echo form_aksi(url("admin/index.php"), "aktifkan", $d["id"], "Aktifkan"); } else { echo form_aksi(url("admin/index.php"), "arsip", $d["id"], "Arsipkan", "Arsipkan destinasi ini? Destinasi akan disembunyikan dari pengunjung, ulasan dan favorit tetap tersimpan."); } ?>
          <?php echo form_aksi(url("admin/index.php"), "hapus", $d["id"], "Hapus permanen", "Hapus destinasi ini beserta ulasannya secara permanen?"); ?>
        </td>
      </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
<?php footer_web(); ?>
