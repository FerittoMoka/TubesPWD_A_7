<?php
require_once __DIR__ . "/../includes/init.php";
require_once __DIR__ . "/../includes/mood.php";
require_once __DIR__ . "/../includes/jam_buka.php";
require_once __DIR__ . "/../includes/galeri.php";
wajib_admin();
pastikan_galeri_destinasi($conn);

$id = (int) ($_GET["id"] ?? 0);
$d = array("nama" => "", "category_id" => 0, "lokasi" => "", "deskripsi" => "", "harga_tiket" => 0,
           "harga_maks" => "", "jam_buka" => "", "hari_tutup" => "", "tag" => "", "wilayah" => "", "status" => "aktif", "latitude" => "", "longitude" => "", "foto" => null);
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
    $wil_s = trim($_POST["wilayah"] ?? "");
    $wilayah_valid = ($wil_s === "" || in_array($wil_s, daftar_wilayah(), true));
    // Hari tutup: hanya hari yang dikenal yang diterima.
    $hari_sah = array();
    foreach (daftar_hari_pekan() as $n_hari => $label_hari) { $hari_sah[] = slug_hari_pekan($n_hari); }
    $tutup_pilih = array_values(array_intersect($hari_sah, (array) ($_POST["hari_tutup"] ?? array())));
    $tutup_str = count($tutup_pilih) > 0 ? implode(",", $tutup_pilih) : null;
    $status_s = (($_POST["status"] ?? "aktif") === "arsip") ? "arsip" : "aktif";

    /* tampilkan kembali input jika ada error */
    $d = array_merge($d, array("nama" => $nama, "lokasi" => $lokasi, "deskripsi" => $desc, "category_id" => $cat,
        "jam_buka" => $jam, "hari_tutup" => $tutup_str, "harga_tiket" => $tiket_s, "harga_maks" => $maks_s, "latitude" => $lat_s, "longitude" => $lng_s, "tag" => $tag_str, "wilayah" => $wil_s, "status" => $status_s));

    $kategori_valid = false;
    foreach ($daftar_kategori as $k) { if ((int) $k["id"] === $cat) { $kategori_valid = true; } }

    if ($nama === "" || $lokasi === "" || mb_strlen($nama) > 100 || mb_strlen($lokasi) > 200) {
        $error = "Nama dan lokasi wajib diisi (nama maks. 100, lokasi maks. 200 karakter).";
    } elseif (!$kategori_valid) {
        $error = "Pilih kategori yang valid.";
    } elseif (!$wilayah_valid) {
        $error = "Pilih wilayah yang valid.";
    } elseif (!ctype_digit($tiket_s) || ($maks_s !== "" && !ctype_digit($maks_s))) {
        $error = "Harga tiket harus berupa angka bulat (Rupiah).";
    } elseif (($lat_s !== "" && (!is_numeric($lat_s) || $lat_s < -90 || $lat_s > 90))
           || ($lng_s !== "" && (!is_numeric($lng_s) || $lng_s < -180 || $lng_s > 180))
           || (($lat_s === "") !== ($lng_s === ""))) {
        $error = "Latitude/longitude harus angka yang valid dan diisi berpasangan.";
    } elseif (($kembar = destinasi_kembar($conn, $nama, $id)) !== null) {
        $error = "Destinasi serupa sudah ada: \"" . $kembar["nama"] . "\" (id " . (int) $kembar["id"]
            . "). Sunting data itu atau ubah namanya agar tidak dobel.";
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
            $wilayah = $wil_s === "" ? null : $wil_s;
            $status  = $status_s;
            $foto_lama = $d["foto"];
            if (!empty($_POST["hapus_foto"]) && $foto_baru === null) { hapus_gambar("destinasi", $foto_lama); $foto_final = null; }
            elseif ($foto_baru !== null) { hapus_gambar("destinasi", $foto_lama); $foto_final = $foto_baru; }
            else { $foto_final = $foto_lama; }

            if ($id) {
                $s = $conn->prepare("UPDATE destinations SET category_id=?, nama=?, lokasi=?, wilayah=?, deskripsi=?, harga_tiket=?, harga_maks=?, jam_buka=?, hari_tutup=?, tag=?, status=?, latitude=?, longitude=?, foto=? WHERE id=?");
                $s->bind_param("issssiissssddsi", $cat, $nama, $lokasi, $wilayah, $desc, $tiket, $maks, $jam, $tutup_str, $tag, $status, $lat, $lng, $foto_final, $id);
            } else {
                $s = $conn->prepare("INSERT INTO destinations (category_id,nama,lokasi,wilayah,deskripsi,harga_tiket,harga_maks,jam_buka,hari_tutup,tag,status,latitude,longitude,foto) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $s->bind_param("issssiissssdds", $cat, $nama, $lokasi, $wilayah, $desc, $tiket, $maks, $jam, $tutup_str, $tag, $status, $lat, $lng, $foto_final);
            }
            if (!$s->execute()) {
                /* Kunci unik nama di database adalah jaring pengaman terakhir. */
                $error = ((int) $s->errno === 1062)
                    ? "Destinasi dengan nama itu sudah ada. Sunting data yang sudah tersimpan."
                    : "Destinasi gagal disimpan. Silakan coba lagi.";
            } else {
            $id_dest = $id ? $id : (int) mysqli_insert_id($conn);
            catat_log_admin($conn, (int) $USER["id"], $id ? "ubah_destinasi" : "tambah_destinasi", "destinasi #" . $id_dest . " " . $nama);

            /* Galeri: hapus foto terpilih, simpan keterangan, lalu unggah foto baru. */
            foreach ((array) ($_POST["hapus_galeri"] ?? array()) as $foto_hapus) {
                hapus_foto_galeri($conn, (int) $foto_hapus, $id_dest);
            }
            /* Foto lama yang hanya ada di folder (belum tercatat di database). */
            foreach ((array) ($_POST["hapus_berkas"] ?? array()) as $berkas_hapus) {
                $berkas_hapus = basename((string) $berkas_hapus);
                if ($berkas_hapus === "" || $berkas_hapus === "." || $berkas_hapus === "..") { continue; }
                $st_cek = mysqli_prepare($conn, "SELECT id FROM destination_photos WHERE destination_id = ? AND nama_file = ? LIMIT 1");
                $masih_tercatat = false;
                if ($st_cek) {
                    mysqli_stmt_bind_param($st_cek, "is", $id_dest, $berkas_hapus);
                    mysqli_stmt_execute($st_cek);
                    $masih_tercatat = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($st_cek));
                    mysqli_stmt_close($st_cek);
                }
                $jalur_hapus = __DIR__ . "/../assets/uploads/destinasi/galeri/" . $id_dest . "/" . $berkas_hapus;
                if (!$masih_tercatat && is_file($jalur_hapus)) { @unlink($jalur_hapus); }
            }
            perbarui_keterangan_galeri($conn, $id_dest, $_POST["keterangan_foto"] ?? array());
            $hasil_galeri = simpan_galeri_upload($conn, $id_dest, $_FILES["galeri_foto"] ?? array(), $_POST["galeri_keterangan"] ?? "");

            $pesan_galeri = "";
            if ($hasil_galeri["jumlah"] > 0) {
                $pesan_galeri = " " . (int) $hasil_galeri["jumlah"] . " foto galeri ditambahkan.";
            }
            foreach ($hasil_galeri["galat"] as $galat_foto) {
                $pesan_galeri .= " " . $galat_foto;
            }
            flash("success", ($id ? "Destinasi berhasil diperbarui." : "Destinasi berhasil ditambahkan.") . $pesan_galeri);
            redirect("admin/index.php");
            }
        }
    }
}

$tag_aktif = ($d["tag"] ?? "") === "" ? array() : explode(",", $d["tag"]);
$tutup_aktif = hari_tutup_slug($d["hari_tutup"] ?? "");

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

    <label for="wilayah">Wilayah</label>
    <select id="wilayah" name="wilayah">
      <option value="">- Pilih wilayah -</option>
      <?php foreach (daftar_wilayah() as $w) { ?>
      <option value="<?php echo e($w); ?>"<?php echo ($d["wilayah"] ?? "") === $w ? ' selected="selected"' : ""; ?>><?php echo e($w); ?></option>
      <?php } ?>
    </select>

    <label for="deskripsi">Deskripsi</label>
    <textarea id="deskripsi" name="deskripsi" rows="5" cols="40"><?php echo e($d["deskripsi"]); ?></textarea>

    <div class="two-col">
      <div><label for="harga_tiket">Harga tiket minimum (Rp)</label><input type="text" id="harga_tiket" name="harga_tiket" value="<?php echo e($d["harga_tiket"]); ?>" /></div>
      <div><label for="harga_maks">Harga maksimum (opsional)</label><input type="text" id="harga_maks" name="harga_maks" value="<?php echo e($d["harga_maks"]); ?>" /></div>
      <div><label for="jam_buka">Jam buka</label><input type="text" id="jam_buka" name="jam_buka" maxlength="100" value="<?php echo e($d["jam_buka"]); ?>" /></div>
      <div><label for="latitude">Latitude</label><input type="text" id="latitude" name="latitude" value="<?php echo e($d["latitude"]); ?>" /></div>
      <div><label for="longitude">Longitude</label><input type="text" id="longitude" name="longitude" value="<?php echo e($d["longitude"]); ?>" /></div>
    </div>

    <label>Hari tutup</label>
    <div class="tag-pilihan">
      <?php foreach (daftar_hari_pekan() as $n_hari => $label_hari) { $slug_hari = slug_hari_pekan($n_hari); ?>
      <label class="cek"><input type="checkbox" name="hari_tutup[]" value="<?php echo e($slug_hari); ?>"<?php echo in_array($slug_hari, $tutup_aktif, true) ? ' checked="checked"' : ""; ?> /> <?php echo e($label_hari); ?></label>
      <?php } ?>
    </div>
    <p class="kecil">Kosongkan bila destinasi buka setiap hari. Hari tutup dipakai untuk peringatan di Rencana Dolan.</p>

    <label>Cocok untuk (mood &amp; waktu kunjungan)</label>
    <div class="tag-pilihan">
      <?php foreach (daftar_mood() as $kunci => $label) { ?>
      <label class="cek"><input type="checkbox" name="tag[]" value="<?php echo e($kunci); ?>"<?php echo in_array($kunci, $tag_aktif, true) ? ' checked="checked"' : ""; ?> /> <?php echo e($label); ?></label>
      <?php } ?>
    </div>

    <label for="status">Status tampil</label>
    <select id="status" name="status">
      <option value="aktif"<?php echo ($d["status"] ?? "aktif") !== "arsip" ? ' selected="selected"' : ""; ?>>Aktif (tampil ke pengunjung)</option>
      <option value="arsip"<?php echo ($d["status"] ?? "aktif") === "arsip" ? ' selected="selected"' : ""; ?>>Arsip (disembunyikan)</option>
    </select>

    <label>Galeri foto destinasi<?php if ($id) { echo " (" . count(galeri_destinasi($id)) . " foto)"; } ?></label>
    <p class="kecil">Foto di bawah tampil sebagai slide di halaman detail. Isi keterangan bila perlu, centang "Hapus" untuk membuang foto saat menyimpan.</p>
    <?php if ($id && count(galeri_destinasi($id)) > 0) { ?>
    <div class="galeri-admin-grid">
      <?php foreach (galeri_destinasi($id) as $f_galeri) { ?>
      <div class="galeri-admin-item">
        <img src="<?php echo e($f_galeri["url"]); ?>" alt="Foto galeri" width="170" height="110" />
        <?php if ($f_galeri["id"] !== null) { ?>
        <input type="text" name="keterangan_foto[<?php echo (int) $f_galeri["id"]; ?>]" value="<?php echo e($f_galeri["keterangan"]); ?>" maxlength="150" placeholder="Keterangan foto" />
        <label class="cek"><input type="checkbox" name="hapus_galeri[]" value="<?php echo (int) $f_galeri["id"]; ?>" /> Hapus foto ini</label>
        <?php } else { ?>
        <span class="kecil">Foto dari folder galeri (tanpa keterangan)</span>
        <label class="cek"><input type="checkbox" name="hapus_berkas[]" value="<?php echo e($f_galeri["berkas"]); ?>" /> Hapus foto ini</label>
        <?php } ?>
      </div>
      <?php } ?>
    </div>
    <?php } else { ?>
    <p class="muted kecil">Belum ada foto galeri untuk destinasi ini.</p>
    <?php } ?>
    <label for="galeri_foto">Tambah foto galeri (bisa banyak sekaligus, JPG/PNG/WEBP, maks. 5 MB per foto)</label>
    <input type="file" id="galeri_foto" name="galeri_foto[]" multiple="multiple" accept="image/jpeg,image/png,image/webp" />
    <label for="galeri_keterangan">Keterangan untuk foto baru (opsional)</label>
    <input type="text" id="galeri_keterangan" name="galeri_keterangan" maxlength="150" placeholder="mis. Area pelataran utama" />

    <label for="input-foto">Foto destinasi (JPG/PNG/WEBP, maks. 2 MB)</label>
    <?php if (!empty($d["foto"])) { ?>
    <img class="preview" src="<?php echo foto_src("destinasi", $d["foto"]); ?>" alt="Foto saat ini" width="220" height="150" />
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