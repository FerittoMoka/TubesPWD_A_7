<?php
require_once "includes/init.php";

$q    = trim($_GET["q"] ?? "");
$cat  = (int) ($_GET["kategori"] ?? 0);
$urut = $_GET["urut"] ?? "terbaru";
$opsi_urut = array(
    "terbaru"  => array("Terbaru", "d.created_at DESC, d.id DESC"),
    "rating"   => array("Rating tertinggi", "rating DESC, total_review DESC, d.nama ASC"),
    "termurah" => array("Tiket termurah", "d.harga_tiket ASC, d.nama ASC"),
    "nama"     => array("Nama A-Z", "d.nama ASC"),
);
if (!isset($opsi_urut[$urut])) { $urut = "terbaru"; }

$cats = $conn->query("SELECT id, nama FROM categories ORDER BY nama");
$daftar_kategori = $cats->fetch_all(MYSQLI_ASSOC);

$sql = "SELECT d.*, c.nama AS kategori,
        (SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.destination_id=d.id) AS rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.destination_id=d.id) AS total_review
        FROM destinations d JOIN categories c ON c.id=d.category_id
        WHERE (d.nama LIKE ? OR d.lokasi LIKE ?)";
$like = "%" . $q . "%";
if ($cat) { $sql .= " AND d.category_id=?"; }
$sql .= " ORDER BY " . $opsi_urut[$urut][1];
$stmt = $conn->prepare($sql);
if ($cat) { $stmt->bind_param("ssi", $like, $like, $cat); } else { $stmt->bind_param("ss", $like, $like); }
$stmt->execute();
$items = $stmt->get_result();

$n_dest = $conn->query("SELECT COUNT(*) n FROM destinations")->fetch_assoc()["n"];
$n_kat  = count($daftar_kategori);
$n_rev  = $conn->query("SELECT COUNT(*) n FROM reviews")->fetch_assoc()["n"];

header_web("Jelajahi Wisata Yogyakarta");
?>
<div class="hero">
  <div class="hero-copy">
    <span class="eyebrow">Jelajahi keistimewaan Yogyakarta</span>
    <h1>Setiap sudut Jogja punya cerita.</h1>
    <p>Temukan tempat wisata, cek informasi tiket, dan baca pengalaman pengunjung sebelum memulai perjalananmu.</p>
    <form class="search" method="get" action="<?php echo url("index.php"); ?>">
      <div>
        <input type="text" id="q" name="q" value="<?php echo e($q); ?>" title="Cari destinasi atau lokasi" />
        <select id="kategori" name="kategori" title="Filter kategori">
          <option value="0">Semua kategori</option>
          <?php foreach ($daftar_kategori as $c) { ?>
          <option value="<?php echo (int) $c["id"]; ?>"<?php echo $cat === (int) $c["id"] ? ' selected="selected"' : ""; ?>><?php echo e($c["nama"]); ?></option>
          <?php } ?>
        </select>
        <button class="btn" type="submit">Cari</button>
      </div>
    </form>
    <div class="hero-stats">
      <span><b><?php echo (int) $n_dest; ?></b> destinasi</span>
      <span><b><?php echo (int) $n_kat; ?></b> kategori</span>
      <span><b><?php echo (int) $n_rev; ?></b> ulasan</span>
    </div>
  </div>
  <div class="hero-art"><div class="sun"></div><div class="art-caption">Yogyakarta<br />Istimewa</div></div>
</div>

<div id="terdekat" class="section">
  <span class="eyebrow">Berdasarkan lokasimu</span>
  <h2>Destinasi terdekat</h2>
  <p class="muted">Izinkan akses lokasi pada browser untuk melihat destinasi terdekat dari posisimu saat ini.</p>
  <p><a id="btn-terdekat" class="btn" href="<?php echo url("api/terdekat.php"); ?>">Cari destinasi terdekat</a> <span id="status-terdekat" class="status"></span></p>
  <div id="hasil-terdekat" class="cards"></div>
</div>

<div id="destinasi" class="section">
  <div class="section-head">
    <span class="eyebrow">Inspirasi perjalanan</span>
    <h2>Destinasi pilihan</h2>
    <p class="muted">Pilih tempat yang paling sesuai dengan rencana liburanmu.</p>
  </div>
  <div class="category-row">
    <a class="pill<?php echo !$cat ? " selected" : ""; ?>" href="<?php echo url("index.php?urut=" . $urut . "#destinasi"); ?>">Semua</a>
    <?php foreach ($daftar_kategori as $c) { ?>
    <a class="pill<?php echo $cat === (int) $c["id"] ? " selected" : ""; ?>" href="<?php echo url("index.php?kategori=" . (int) $c["id"] . "&urut=" . $urut . "#destinasi"); ?>"><?php echo e($c["nama"]); ?></a>
    <?php } ?>
  </div>
  <div class="sort-row">
    <span class="muted">Urutkan:</span>
    <?php foreach ($opsi_urut as $kode => $o) { ?>
    <a class="sort-link<?php echo $kode === $urut ? " selected" : ""; ?>" href="<?php echo url("index.php?q=" . urlencode($q) . "&kategori=" . $cat . "&urut=" . $kode . "#destinasi"); ?>"><?php echo e($o[0]); ?></a>
    <?php } ?>
  </div>
  <div class="cards">
    <?php if ($items->num_rows === 0) { ?><p class="empty">Destinasi tidak ditemukan. Coba kata kunci lain.</p><?php } ?>
    <?php while ($d = $items->fetch_assoc()) { echo kartu_destinasi($d); } ?>
  </div>
</div>
<?php footer_web(); ?>
