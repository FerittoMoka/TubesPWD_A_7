<?php
require_once __DIR__ . "/../includes/init.php";
require_once __DIR__ . "/../includes/mood.php";
wajib_admin();

$id = (int) ($_GET["id"] ?? 0);
$d = array("nama" => "", "category_id" => 0, "lokasi" => "", "deskripsi" => "", "harga_tiket" => 0,
           "harga_maks" => "", "jam_buka" => "", "tag" => "", "latitude" => "", "longitude" => "", "foto" => null);
if ($id) {
    $s = $conn->prepare("SELECT * FROM destinations WHERE id=?");
    $s->bind_param("i", $id);
    $s->execute();
    $found = $s->get_result()->fetch_assoc();
    if (!$found) { flash("error", "Destinasi tidak ditemukan."); redirect("admin/index.php"); }
    $d = $found;
}
$daftar_kategori = $conn->query("SELECT id, nama FROM categories ORDER BY nama")->fetch_all(MYSQLI_ASSOC);
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cek_csrf();
    $nama   = trim($_POST["nama"] ?? "");
    $lokasi = trim($_POST["lokasi"] ?? "");
    $desc   = trim($_POST["deskripsi"] ?? "");
    $cat    = (int) ($_POST["category_id"] ?? 0);
    $jam    = trim($_POST["jam_buka"] ?? "");
    $tiket_s = trim($_POST["harga_tiket"] ?? "0");
    $maks_s  = trim($_POST["harga_maks"] ?? "");
    $lat_s   = trim($_POST["latitude"] ?? "");
    $lng_s   = trim($_POST["longitude"] ?? "");
    // Tag mood: hanya nilai yang ada di daftar_mood() yang diterima (whitelist)
    $tag_pilih = array_values(array_intersect(array_keys(daftar_mood()), (array) ($_POST["tag"] ?? array())));
    $tag_str = implode(",", $tag_pilih);

    /* tampilkan kembali input jika ada error */
    $d = array_merge($d, array("nama" => $nama, "lokasi" => $lokasi, "deskripsi" => $desc, "category_id" => $cat,
        "jam_buka" => $jam, "harga_tiket" => $tiket_s, "harga_maks" => $maks_s, "latitude" => $lat_s, "longitude" => $lng_s, "tag" => $tag_str));

    $kategori_valid = false;
    foreach ($daftar_kategori as $k) { if ((int) $k["id"] === $cat) { $kategori_valid = true; } }

    if ($nama === "" || $lokasi === "" || mb_strlen($nama) > 100 || mb_strlen($lokasi) > 200) {
        $error = "Nama dan lokasi wajib diisi (nama maks. 100, lokasi maks. 200 karakter).";
    } elseif (!$kategori_valid) {
        $error = "Pilih kategori yang valid.";
    } elseif (!ctype_digit($tiket_s) || ($maks_s !== "" && !ctype_digit($maks_s))) {
        $error = "Harga tiket harus berupa angka bulat (Rupiah).";
    } elseif (($lat_s !== "" && (!is_numeric($lat_s) || $lat_s < -90 || $lat_s > 90))
           || ($lng_s !== "" && (!is_numeric($lng_s) || $lng_s < -180 || $lng_s > 180))
           || (($lat_s === "") !== ($lng_s === ""))) {
        $error = "Latitude/longitude harus angka yang valid dan diisi berpasangan.";
    } else {
        list($foto_baru, $err_foto) = simpan_gambar($_FILES["foto"] ?? array(), "destinasi");
        if ($err_foto) {
            $error = $err_foto;
        } else {
            $tiket = (int) $tiket_s;
            $maks  = $maks_s === "" ? null : (int) $maks_s;
            $lat   = $lat_s === "" ? null : (float) $lat_s;
            $lng   = $lng_s === "" ? null : (float) $lng_s;
            $tag   = $tag_str === "" ? null : $tag_str;
            $foto_lama = $d["foto"];
            if (!empty($_POST["hapus_foto"]) && $foto_baru === null) { hapus_gambar("destinasi", $foto_lama); $foto_final = null; }
            elseif ($foto_baru !== null) { hapus_gambar("destinasi", $foto_lama); $foto_final = $foto_baru; }
            else { $foto_final = $foto_lama; }

            if ($id) {
                $s = $conn->prepare("UPDATE destinations SET category_id=?, nama=?, lokasi=?, deskripsi=?, harga_tiket=?, harga_maks=?, jam_buka=?, tag=?, latitude=?, longitude=?, foto=? WHERE id=?");
                $s->bind_param("isssiissddsi", $cat, $nama, $lokasi, $desc, $tiket, $maks, $jam, $tag, $lat, $lng, $foto_final, $id);
            } else {
                $s = $conn->prepare("INSERT INTO destinations (category_id,nama,lokasi,deskripsi,harga_tiket,harga_maks,jam_buka,tag,latitude,longitude,foto) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $s->bind_param("isssiissdds", $cat, $nama, $lokasi, $desc, $tiket, $maks, $jam, $tag, $lat, $lng, $foto_final);
            }
            $s->execute();
            flash("success", $id ? "Destinasi berhasil diperbarui." : "Destinasi berhasil ditambahkan.");
            redirect("admin/index.php");
        }
    }
}

$tag_aktif = ($d["tag"] ?? "") === "" ? array() : explode(",", $d["tag"]);

header_web($id ? "Edit Destinasi" : "Tambah Destinasi");
?>
<div class="page-heading">
  <span class="eyebrow">Administrator</span>
  <h1><?php echo $id ? "Edit destinasi" : "Tambah destinasi"; ?></h1>
</div>
<?php subnav_admin("destinasi"); ?>
<form class="panel editor" method="post" action="<?php echo url("admin/destinasi.php" . ($id ? "?id=" . $id : "")); ?>" enctype="multipart/form-data">
  <div>
    <?php if ($error) { echo '<div class="alert alert-error">' . e($error) . '</div>'; } ?>
    <?php echo csrf_field(); ?>

    <label for="nama">Nama destinasi</label>
    <input type="text" id="nama" name="nama" maxlength="100" value="<?php echo e($d["nama"]); ?>" />

    <label for="category_id">Kategori</label>
    <select id="category_id" name="category_id">
      <?php foreach ($daftar_kategori as $c) { ?>
      <option value="<?php echo (int) $c["id"]; ?>"<?php echo (int) $d["category_id"] === (int) $c["id"] ? ' selected="selected"' : ""; ?>><?php echo e($c["nama"]); ?></option>
      <?php } ?>
    </select>

    <label for="lokasi">Lokasi</label>
    <input type="text" id="lokasi" name="lokasi" maxlength="200" value="<?php echo e($d["lokasi"]); ?>" />

    <label for="deskripsi">Deskripsi</label>
    <textarea id="deskripsi" name="deskripsi" rows="5" cols="40"><?php echo e($d["deskripsi"]); ?></textarea>

    <div class="two-col">
      <div><label for="harga_tiket">Harga tiket minimum (Rp)</label><input type="text" id="harga_tiket" name="harga_tiket" value="<?php echo e($d["harga_tiket"]); ?>" /></div>
      <div><label for="harga_maks">Harga maksimum (opsional)</label><input type="text" id="harga_maks" name="harga_maks" value="<?php echo e($d["harga_maks"]); ?>" /></div>
      <div><label for="jam_buka">Jam buka</label><input type="text" id="jam_buka" name="jam_buka" maxlength="100" value="<?php echo e($d["jam_buka"]); ?>" /></div>
      <div><label for="latitude">Latitude</label><input type="text" id="latitude" name="latitude" value="<?php echo e($d["latitude"]); ?>" /></div>
      <div><label for="longitude">Longitude</label><input type="text" id="longitude" name="longitude" value="<?php echo e($d["longitude"]); ?>" /></div>
    </div>

    <label>Cocok untuk (mood &amp; waktu kunjungan)</label>
    <div class="tag-pilihan">
      <?php foreach (daftar_mood() as $kunci => $label) { ?>
      <label class="cek"><input type="checkbox" name="tag[]" value="<?php echo e($kunci); ?>"<?php echo in_array($kunci, $tag_aktif, true) ? ' checked="checked"' : ""; ?> /> <?php echo e($label); ?></label>
      <?php } ?>
    </div>

    <label for="input-foto">Foto destinasi (JPG/PNG/WEBP, maks. 2 MB)</label>
    <?php if (!empty($d["foto"])) { ?>
    <img class="preview" src="<?php echo foto_src("destinasi", $d["foto"]); ?>" alt="Foto saat ini" />
    <label class="cek"><input type="checkbox" name="hapus_foto" value="1" /> Hapus foto saat ini</label>
    <?php } ?>
    <img id="preview-foto" class="preview hidden" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" alt="Pratinjau foto baru" />
    <input type="file" id="input-foto" name="foto" />
    <span id="status-foto" class="status"></span>

    <button class="btn" type="submit">Simpan</button>
    <a class="text-link" href="<?php echo url("admin/index.php"); ?>">Batal</a>
  </div>
</form>
<?php footer_web(); ?>