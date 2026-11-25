<?php
require_once __DIR__ . "/../includes/init.php";

$id = (int) ($_GET["id"] ?? 0);
$s = $conn->prepare("SELECT d.*, c.nama AS kategori,
        (SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.destination_id=d.id) AS rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.destination_id=d.id) AS total_review
        FROM destinations d JOIN categories c ON c.id=d.category_id WHERE d.id=?");
$s->bind_param("i", $id);
$s->execute();
$d = $s->get_result()->fetch_assoc();

if (!$d) {
    http_response_code(404);
    header_web("Tidak ditemukan");
    echo '<div class="page-heading"><h1>Destinasi tidak ditemukan</h1><p><a class="text-link" href="' . url("index.php") . '">Kembali ke beranda</a></p></div>';
    footer_web();
    exit;
}

$self = "destinasi/detail.php?id=" . $id;

/* ---------- Aksi POST: ulasan (create/update/delete) dan favorit ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    wajib_login();
    cek_csrf();
    $aksi = $_POST["aksi"] ?? "";
    $uid  = (int) $USER["id"];

    if ($aksi === "simpan_ulasan") {
        $rating   = (int) ($_POST["rating"] ?? 0);
        $komentar = trim($_POST["komentar"] ?? "");
        if ($rating < 1 || $rating > 5) {
            flash("error", "Pilih rating 1 sampai 5.");
        } elseif (mb_strlen($komentar) > 1000) {
            flash("error", "Komentar maksimal 1000 karakter.");
        } else {
            $s = $conn->prepare("SELECT id FROM reviews WHERE destination_id=? AND user_id=?");
            $s->bind_param("ii", $id, $uid);
            $s->execute();
            $ada = $s->get_result()->fetch_assoc();
            if ($ada) {
                $s = $conn->prepare("UPDATE reviews SET rating=?, komentar=? WHERE id=?");
                $s->bind_param("isi", $rating, $komentar, $ada["id"]);
                $s->execute();
                flash("success", "Ulasan berhasil diperbarui.");
            } else {
                $s = $conn->prepare("INSERT INTO reviews (destination_id,user_id,rating,komentar) VALUES (?,?,?,?)");
                $s->bind_param("iiis", $id, $uid, $rating, $komentar);
                $s->execute();
                flash("success", "Ulasan berhasil ditambahkan.");
            }
        }
    } elseif ($aksi === "hapus_ulasan") {
        $rid = (int) ($_POST["id"] ?? 0);
        if (admin()) {
            $s = $conn->prepare("DELETE FROM reviews WHERE id=? AND destination_id=?");
            $s->bind_param("ii", $rid, $id);
        } else {
            $s = $conn->prepare("DELETE FROM reviews WHERE id=? AND destination_id=? AND user_id=?");
            $s->bind_param("iii", $rid, $id, $uid);
        }
        $s->execute();
        flash("success", "Ulasan dihapus.");
    } elseif ($aksi === "favorit") {
        $s = $conn->prepare("INSERT IGNORE INTO wishlists (user_id,destination_id) VALUES (?,?)");
        $s->bind_param("ii", $uid, $id);
        $s->execute();
        flash("success", "Destinasi disimpan ke favorit.");
    } elseif ($aksi === "hapus_favorit") {
        $s = $conn->prepare("DELETE FROM wishlists WHERE user_id=? AND destination_id=?");
        $s->bind_param("ii", $uid, $id);
        $s->execute();
        flash("success", "Destinasi dihapus dari favorit.");
    }
    redirect($self);
}

/* ---------- Data tampilan ---------- */
$s = $conn->prepare("SELECT r.id, r.user_id, r.rating, r.komentar, r.created_at, u.nama_lengkap, u.username
                     FROM reviews r JOIN users u ON u.id=r.user_id
                     WHERE r.destination_id=? ORDER BY r.created_at DESC, r.id DESC");
$s->bind_param("i", $id);
$s->execute();
$reviews = $s->get_result();

$ulasan_saya = null;
$favorit = false;
if (login()) {
    $uid = (int) $USER["id"];
    $s = $conn->prepare("SELECT id, rating, komentar FROM reviews WHERE destination_id=? AND user_id=?");
    $s->bind_param("ii", $id, $uid);
    $s->execute();
    $ulasan_saya = $s->get_result()->fetch_assoc();
    $s = $conn->prepare("SELECT id FROM wishlists WHERE destination_id=? AND user_id=?");
    $s->bind_param("ii", $id, $uid);
    $s->execute();
    $favorit = $s->get_result()->num_rows > 0;
}
$punya_koordinat = $d["latitude"] !== null && $d["longitude"] !== null;
$rating_pilihan = array(5 => "5 - Sangat baik", 4 => "4 - Baik", 3 => "3 - Cukup", 2 => "2 - Kurang", 1 => "1 - Buruk");
$rating_terpilih = $ulasan_saya ? (int) $ulasan_saya["rating"] : 5;

header_web($d["nama"]);
?>
<div class="detail-hero">
  <div>
    <span class="eyebrow"><?php echo e($d["kategori"]); ?> &#183; Wisata Yogyakarta</span>
    <h1><?php echo e($d["nama"]); ?></h1>
    <p><?php echo e($d["lokasi"]); ?></p>
    <p class="rating-line"><?php echo $d["rating"] ? "&#9733; " . e($d["rating"]) . " dari " . (int) $d["total_review"] . " ulasan" : "Belum ada ulasan"; ?></p>
  </div>
  <?php if (!empty($d["foto"])) { ?>
  <img class="detail-foto" src="<?php echo foto_src("destinasi", $d["foto"]); ?>" alt="<?php echo e($d["nama"]); ?>" />
  <?php } else { ?>
  <div class="detail-symbol"><?php echo e(mb_substr($d["nama"], 0, 1)); ?></div>
  <?php } ?>
</div>

<div class="detail-grid">
  <div class="panel">
    <span class="eyebrow">Tentang destinasi</span>
    <h2>Kenali tempatnya</h2>
    <p><?php echo nl2br(e($d["deskripsi"])); ?></p>
    <div class="info-grid">
      <div><small>Harga tiket (perkiraan)</small><strong><?php echo harga($d["harga_tiket"], $d["harga_maks"]); ?></strong></div>
      <div><small>Jam buka</small><strong><?php echo e($d["jam_buka"] ? $d["jam_buka"] : "Belum tersedia"); ?></strong></div>
      <div><small>Koordinat</small><strong><?php echo $punya_koordinat ? e($d["latitude"]) . ", " . e($d["longitude"]) : "-"; ?></strong></div>
    </div>

    <?php if ($punya_koordinat) { ?>
    <div class="geo-box">
      <span id="dest-lat" class="hidden"><?php echo e($d["latitude"]); ?></span>
      <span id="dest-lng" class="hidden"><?php echo e($d["longitude"]); ?></span>
      <button id="btn-jarak" class="btn btn-light" type="button">Hitung jarak dari lokasiku</button>
      <a class="btn btn-light" href="<?php echo e("https://www.google.com/maps/search/?api=1&query=" . $d["latitude"] . "," . $d["longitude"]); ?>">Buka di peta</a>
      <span id="hasil-jarak" class="status"></span>
    </div>
    <?php } ?>

    <?php if (login()) { ?>
    <form class="inline-form" method="post" action="<?php echo url($self); ?>">
      <div>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="aksi" value="<?php echo $favorit ? "hapus_favorit" : "favorit"; ?>" />
        <button class="btn btn-light" type="submit"><?php echo $favorit ? "Hapus dari favorit" : "Simpan ke favorit"; ?></button>
      </div>
    </form>
    <?php } else { ?>
    <p><a class="btn btn-light" href="<?php echo url("auth/login.php"); ?>">Masuk untuk menyimpan</a></p>
    <?php } ?>
  </div>

  <div class="panel">
    <span class="eyebrow">Bagikan pengalaman</span>
    <h2><?php echo $ulasan_saya ? "Ulasan saya" : "Tulis ulasan"; ?></h2>
    <?php if (login()) { ?>
    <form method="post" action="<?php echo url($self); ?>">
      <div>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="aksi" value="simpan_ulasan" />
        <label for="rating">Rating</label>
        <select id="rating" name="rating">
          <?php foreach ($rating_pilihan as $nilai => $label) { ?>
          <option value="<?php echo $nilai; ?>"<?php echo $nilai === $rating_terpilih ? ' selected="selected"' : ""; ?>><?php echo e($label); ?></option>
          <?php } ?>
        </select>
        <label for="komentar">Komentar</label>
        <textarea id="komentar" name="komentar" rows="4" cols="40"><?php echo e($ulasan_saya["komentar"] ?? ""); ?></textarea>
        <button class="btn full" type="submit"><?php echo $ulasan_saya ? "Perbarui ulasan" : "Kirim ulasan"; ?></button>
      </div>
    </form>
    <?php } else { ?>
    <p>Masuk untuk memberikan rating dan ulasan.</p>
    <a href="<?php echo url("auth/login.php"); ?>" class="text-link">Masuk sekarang &#8594;</a>
    <?php } ?>
  </div>
</div>

<div class="section">
  <h2>Ulasan pengunjung</h2>
  <div class="review-list">
    <?php if (!$reviews->num_rows) { ?><p class="empty">Belum ada ulasan untuk destinasi ini.</p><?php } ?>
    <?php while ($r = $reviews->fetch_assoc()) { ?>
    <div class="review">
      <div class="review-head">
        <strong><?php echo e($r["nama_lengkap"]); ?></strong>
        <span class="stars"><?php echo str_repeat("&#9733;", (int) $r["rating"]) . str_repeat("&#9734;", 5 - (int) $r["rating"]); ?></span>
      </div>
      <p><?php echo nl2br(e($r["komentar"])); ?></p>
      <small><?php echo e(tanggal($r["created_at"])); ?></small>
      <?php if (login() && (admin() || (int) $r["user_id"] === (int) $USER["id"])) { ?>
      <div class="actions"><?php echo form_aksi(url($self), "hapus_ulasan", $r["id"], "Hapus ulasan", "Hapus ulasan ini?"); ?></div>
      <?php } ?>
    </div>
    <?php } ?>
  </div>
</div>
<?php footer_web(); ?>
