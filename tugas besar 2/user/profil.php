<?php
require_once __DIR__ . "/../includes/init.php";
wajib_login();
$uid = (int) $USER["id"];
$self = url("user/profil.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cek_csrf();
    $aksi = $_POST["aksi"] ?? "";

    if ($aksi === "info") {
        $nama  = trim($_POST["nama"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $hp    = trim($_POST["no_hp"] ?? "");
        if ($nama === "" || mb_strlen($nama) > 100) {
            flash("error", "Nama lengkap wajib diisi (maksimal 100 karakter).");
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            flash("error", "Format email tidak valid.");
        } elseif ($hp !== "" && !preg_match('/^[0-9+\- ]{8,20}$/', $hp)) {
            flash("error", "Nomor HP hanya boleh berisi angka, spasi, + dan - (8-20 karakter).");
        } else {
            $s = $conn->prepare("SELECT id FROM users WHERE email=? AND id<>?");
            $s->bind_param("si", $email, $uid);
            $s->execute();
            if ($s->get_result()->num_rows) {
                flash("error", "Email sudah digunakan pengguna lain.");
            } else {
                $hp_db = $hp === "" ? null : $hp;
                $s = $conn->prepare("UPDATE users SET nama_lengkap=?, email=?, no_hp=? WHERE id=?");
                $s->bind_param("sssi", $nama, $email, $hp_db, $uid);
                $s->execute();
                flash("success", "Profil berhasil diperbarui.");
            }
        }
    } elseif ($aksi === "foto") {
        list($nama_file, $err) = simpan_gambar($_FILES["foto"] ?? array(), "profil");
        if ($err) {
            flash("error", $err);
        } elseif ($nama_file === null) {
            flash("error", "Pilih file foto terlebih dahulu.");
        } else {
            hapus_gambar("profil", $USER["foto"]);
            $s = $conn->prepare("UPDATE users SET foto=? WHERE id=?");
            $s->bind_param("si", $nama_file, $uid);
            $s->execute();
            flash("success", "Foto profil berhasil diperbarui.");
        }
    } elseif ($aksi === "hapus_foto") {
        hapus_gambar("profil", $USER["foto"]);
        $s = $conn->prepare("UPDATE users SET foto=NULL WHERE id=?");
        $s->bind_param("i", $uid);
        $s->execute();
        flash("success", "Foto profil dihapus.");
    } elseif ($aksi === "password") {
        $lama = $_POST["password_lama"] ?? "";
        $baru = $_POST["password_baru"] ?? "";
        $ulang = $_POST["password_ulang"] ?? "";
        $s = $conn->prepare("SELECT password FROM users WHERE id=?");
        $s->bind_param("i", $uid);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        if (!password_verify($lama, $row["password"])) {
            flash("error", "Password lama tidak sesuai.");
        } elseif (strlen($baru) < 8) {
            flash("error", "Password baru minimal 8 karakter.");
        } elseif ($baru !== $ulang) {
            flash("error", "Konfirmasi password baru tidak sama.");
        } else {
            $hash = password_hash($baru, PASSWORD_DEFAULT);
            $s = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $s->bind_param("si", $hash, $uid);
            $s->execute();
            flash("success", "Password berhasil diubah.");
        }
    }
    redirect("user/profil.php");
}

$n_ulasan = $conn->query("SELECT COUNT(*) n FROM reviews WHERE user_id=" . $uid)->fetch_assoc()["n"];
$n_fav    = $conn->query("SELECT COUNT(*) n FROM wishlists WHERE user_id=" . $uid)->fetch_assoc()["n"];

header_web("Profil Saya");
?>
<div class="page-heading">
  <span class="eyebrow">Akun pengguna</span>
  <h1>Profil saya</h1>
  <p>Lihat dan perbarui informasi akun Dolan Jogja.</p>
</div>

<div class="profile-layout">
  <div>
    <div class="panel profile-card">
      <?php echo avatar($USER, "avatar avatar-lg"); ?>
      <h2><?php echo e($USER["nama_lengkap"]); ?></h2>
      <p class="muted">@<?php echo e($USER["username"]); ?> &#183; <?php echo e($USER["role"]); ?></p>
      <p class="muted">Bergabung <?php echo e(tanggal($USER["created_at"])); ?></p>
      <div class="mini-stats">
        <span><b><?php echo (int) $n_ulasan; ?></b> ulasan</span>
        <span><b><?php echo (int) $n_fav; ?></b> favorit</span>
      </div>
      <a class="text-link" href="<?php echo url("user/wishlist.php"); ?>">Lihat destinasi favorit &#8594;</a>
    </div>

    <form class="panel" method="post" action="<?php echo $self; ?>" enctype="multipart/form-data">
      <div>
        <h2>Foto profil</h2>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="aksi" value="foto" />
        <img id="preview-foto" class="preview hidden" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" alt="Pratinjau foto baru" />
        <label for="input-foto">Pilih foto (JPG/PNG/WEBP, maks. 2 MB)</label>
        <input type="file" id="input-foto" name="foto" />
        <span id="status-foto" class="status"></span>
        <button class="btn" type="submit">Unggah foto</button>
      </div>
    </form>
    <?php if (!empty($USER["foto"])) { ?>
    <div class="panel"><?php echo form_aksi($self, "hapus_foto", 0, "Hapus foto profil", "Hapus foto profil?"); ?></div>
    <?php } ?>
  </div>

  <div>
    <form class="panel" method="post" action="<?php echo $self; ?>">
      <div>
        <h2>Informasi pribadi</h2>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="aksi" value="info" />
        <label for="nama">Nama lengkap</label>
        <input type="text" id="nama" name="nama" maxlength="100" value="<?php echo e($USER["nama_lengkap"]); ?>" />
        <label for="username">Username (tidak dapat diubah)</label>
        <input type="text" id="username" value="<?php echo e($USER["username"]); ?>" readonly="readonly" />
        <label for="email">Email</label>
        <input type="text" id="email" name="email" maxlength="100" value="<?php echo e($USER["email"]); ?>" />
        <label for="no_hp">Nomor HP</label>
        <input type="text" id="no_hp" name="no_hp" maxlength="20" value="<?php echo e($USER["no_hp"]); ?>" />
        <button class="btn" type="submit">Simpan perubahan</button>
      </div>
    </form>

    <form class="panel" method="post" action="<?php echo $self; ?>">
      <div>
        <h2>Ubah password</h2>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="aksi" value="password" />
        <label for="password_lama">Password lama</label>
        <input type="password" id="password_lama" name="password_lama" />
        <label for="password_baru">Password baru (minimal 8 karakter)</label>
        <input type="password" id="password_baru" name="password_baru" />
        <label for="password_ulang">Ulangi password baru</label>
        <input type="password" id="password_ulang" name="password_ulang" />
        <button class="btn" type="submit">Ubah password</button>
      </div>
    </form>
  </div>
</div>
<?php footer_web(); ?>
