<?php
require_once "includes/init.php";

$cari     = trim($_GET["cari"] ?? "");
$kategori = (int) ($_GET["kategori"] ?? 0);
$ada_filter = ($cari !== "" || $kategori > 0);

$jml_dest   = (int) $conn->query("SELECT COUNT(*) n FROM destinations")->fetch_assoc()["n"];
$jml_ulasan = (int) $conn->query("SELECT COUNT(*) n FROM reviews")->fetch_assoc()["n"];

$daftar_kat = $conn->query("SELECT c.id, c.nama, COUNT(d.id) AS jumlah FROM categories c
        LEFT JOIN destinations d ON d.category_id=c.id GROUP BY c.id, c.nama ORDER BY c.nama")->fetch_all(MYSQLI_ASSOC);
$jml_kat = count($daftar_kat);

$sql = "SELECT d.id, d.nama, d.lokasi, d.harga_tiket, d.harga_maks, d.foto, c.nama AS kategori,
        (SELECT AVG(r.rating) FROM reviews r WHERE r.destination_id=d.id) AS rata,
        (SELECT COUNT(*) FROM reviews r WHERE r.destination_id=d.id) AS jml_ulasan
        FROM destinations d JOIN categories c ON d.category_id=c.id
        WHERE (d.nama LIKE ? OR d.lokasi LIKE ?)";
$like = "%" . $cari . "%";
if ($kategori > 0) {
    $sql .= " AND d.category_id=? ORDER BY d.created_at DESC, d.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $like, $like, $kategori);
} else {
    $sql .= " ORDER BY d.created_at DESC, d.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $like, $like);
}
$stmt->execute();
$semua = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Tiga destinasi dengan rating tertinggi menjadi "unggulan" (hanya tanpa filter) */
$unggulan = array();
$lainnya = $semua;
if (!$ada_filter && count($semua) >= 3) {
    $urut = $semua;
    usort($urut, function ($a, $b) {
        $ra = (float) $a["rata"]; $rb = (float) $b["rata"];
        if ($ra == $rb) { return $b["jml_ulasan"] - $a["jml_ulasan"]; }
        return $rb <=> $ra;
    });
    $unggulan = array_slice($urut, 0, 3);
    $id_unggulan = array_column($unggulan, "id");
    $lainnya = array_values(array_filter($semua, function ($x) use ($id_unggulan) { return !in_array($x["id"], $id_unggulan); }));
}

header_web("Beranda", "beranda", false);
?>
<div class="hero">
  <div class="hero-isi">
    <p class="atas">Informasi Pariwisata Yogyakarta</p>
    <h1>Jogja lebih seru kalau tahu mau ke mana.</h1>
    <p class="pengantar">Cari destinasi, cek harga tiket dan jam buka, lalu baca ulasan pengunjung sebelum berangkat.</p>

    <form method="get" action="<?php echo url("index.php#destinasi"); ?>" class="form-cari">
      <div>
        <input type="text" name="cari" value="<?php echo e($cari); ?>" title="Cari nama atau lokasi" />
        <select name="kategori" title="Kategori">
          <option value="0">Semua kategori</option>
          <?php foreach ($daftar_kat as $k) { ?>
          <option value="<?php echo (int) $k["id"]; ?>"<?php echo $kategori === (int) $k["id"] ? ' selected="selected"' : ""; ?>><?php echo e($k["nama"]); ?></option>
          <?php } ?>
        </select>
        <button type="submit" class="tombol">Cari</button>
      </div>
    </form>

    <div class="hero-angka">
      <div><strong><?php echo $jml_dest; ?></strong><span>destinasi</span></div>
      <div><strong><?php echo $jml_kat; ?></strong><span>kategori</span></div>
      <div><strong><?php echo $jml_ulasan; ?></strong><span>ulasan</span></div>
    </div>
  </div>
  <div class="gelombang"></div>
</div>

<div class="wadah">
  <?php tampil_flash(); ?>
  <div class="bagian" id="destinasi">
    <p class="eyebrow">Mulai dari sini</p>
    <h2>Mau cari yang seperti apa?</h2>
    <div class="chip-baris">
      <a class="chip<?php echo $kategori === 0 ? " aktif" : ""; ?>" href="<?php echo url("index.php#destinasi"); ?>">Semua</a>
      <?php foreach ($daftar_kat as $k) { ?>
      <a class="chip<?php echo $kategori === (int) $k["id"] ? " aktif" : ""; ?>" href="<?php echo url("index.php?kategori=" . (int) $k["id"] . "#destinasi"); ?>"><?php echo e($k["nama"]); ?><small><?php echo (int) $k["jumlah"]; ?></small></a>
      <?php } ?>
    </div>
  </div>

  <?php if (count($unggulan) === 3) { $besar = $unggulan[0]; ?>
  <div class="bagian">
    <p class="eyebrow">Paling disukai</p>
    <h2>Pilihan pengunjung</h2>
    <div class="unggulan">
      <div class="unggulan-besar kartu-klik">
        <?php echo tampil_foto($besar["foto"], $besar["kategori"], $besar["nama"]); ?>
        <div class="tutup"></div>
        <div class="unggulan-teks">
          <span class="label"><?php echo e($besar["kategori"]); ?></span>
          <h3><?php echo e($besar["nama"]); ?></h3>
          <p><?php echo e($besar["lokasi"]); ?></p>
          <p class="meta-baris"><?php echo tampil_rating($besar["rata"], $besar["jml_ulasan"]); ?><span><?php echo harga($besar["harga_tiket"], $besar["harga_maks"]); ?></span></p>
          <a class="tombol" href="<?php echo url("destinasi/detail.php?id=" . (int) $besar["id"]); ?>">Lihat detail</a>
        </div>
      </div>
      <div class="unggulan-samping">
        <?php foreach (array_slice($unggulan, 1) as $s) { ?>
        <div class="baris-kecil kartu-klik">
          <?php echo tampil_foto($s["foto"], $s["kategori"], $s["nama"]); ?>
          <div>
            <span class="label"><?php echo e($s["kategori"]); ?></span>
            <h3><?php echo e($s["nama"]); ?></h3>
            <p class="baris-meta"><?php echo e($s["lokasi"]); ?></p>
            <p class="meta-baris"><?php echo tampil_rating($s["rata"], $s["jml_ulasan"]); ?><span><?php echo harga($s["harga_tiket"], $s["harga_maks"]); ?></span></p>
            <a href="<?php echo url("destinasi/detail.php?id=" . (int) $s["id"]); ?>">Lihat detail</a>
          </div>
        </div>
        <?php } ?>
      </div>
    </div>
  </div>
  <?php } ?>

  <div class="bagian">
    <?php if ($ada_filter) { ?>
    <p class="eyebrow">Hasil pencarian</p>
    <h2><?php echo count($semua); ?> destinasi ditemukan</h2>
    <?php } else { ?>
    <p class="eyebrow">Jelajahi</p>
    <h2>Semua destinasi</h2>
    <?php } ?>

    <div id="daftar-hasil">
      <?php if (count($lainnya) === 0) { ?>
      <p class="kosong">Belum ada destinasi yang cocok. <a href="<?php echo url("index.php#destinasi"); ?>">Tampilkan semua</a></p>
      <?php } else { ?>
      <div class="daftar">
        <?php foreach ($lainnya as $d) { ?>
        <div class="baris kartu-klik">
          <?php echo tampil_foto($d["foto"], $d["kategori"], $d["nama"]); ?>
          <div class="baris-info">
            <h3><?php echo e($d["nama"]); ?></h3>
            <p class="baris-meta"><?php echo e($d["kategori"]); ?> &#183; <?php echo e($d["lokasi"]); ?></p>
            <?php echo tampil_rating($d["rata"], $d["jml_ulasan"]); ?>
          </div>
          <div class="baris-harga"><?php echo harga($d["harga_tiket"], $d["harga_maks"]); ?></div>
          <a class="tombol tombol-garis" href="<?php echo url("destinasi/detail.php?id=" . (int) $d["id"]); ?>">Detail</a>
        </div>
        <?php } ?>
      </div>
      <?php } ?>
    </div>
  </div>

  <div class="ajakan">
    <div>
      <h2>Lagi di Jogja sekarang?</h2>
      <p>Izinkan lokasimu, lalu lihat destinasi yang paling dekat.</p>
    </div>
    <a class="tombol" href="<?php echo url("destinasi/terdekat.php"); ?>">Cari yang terdekat</a>
  </div>
</div>
<?php footer_web(); ?>
