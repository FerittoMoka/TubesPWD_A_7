<?php
require_once "../config/koneksi.php";
require_once "../includes/auth.php";
require_once "../includes/csrf.php";
cek_login();

$pesan = "";
$jenis_pesan = "";
$user_id = (int) $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $pesan = "Token keamanan tidak valid.";
        $jenis_pesan = "error";
    } else {
        $nama_lengkap = trim($_POST["nama_lengkap"] ?? "");
        $username = trim($_POST["username"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $no_hp = trim($_POST["no_hp"] ?? "");
        $password_baru = $_POST["password_baru"] ?? "";

        if ($nama_lengkap === "" || $username === "" || $email === "") {
            $pesan = "Nama lengkap, username, dan email wajib diisi.";
            $jenis_pesan = "error";
        } elseif ($password_baru !== "" && strlen($password_baru) < 6) {
            $pesan = "Password baru minimal 6 karakter.";
            $jenis_pesan = "error";
        } else {
            $cek = mysqli_prepare($conn, "SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1");
            mysqli_stmt_bind_param($cek, "ssi", $username, $email, $user_id);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);

            if (mysqli_stmt_num_rows($cek) > 0) {
                $pesan = "Username atau email sudah digunakan pengguna lain.";
                $jenis_pesan = "error";
            } else {
                mysqli_stmt_close($cek);

                $foto_baru = null;
                if (isset($_FILES["foto"]) && $_FILES["foto"]["error"] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
                        $pesan = "Foto gagal diunggah.";
                        $jenis_pesan = "error";
                    } else {
                        $size = (int) $_FILES["foto"]["size"];
                        $tipe = mime_content_type($_FILES["foto"]["tmp_name"]);
                        $tipe_valid = array("image/jpeg", "image/png", "image/webp");
                        if ($size > 2097152 || !in_array($tipe, $tipe_valid, true)) {
                            $pesan = "Foto harus JPG, PNG, atau WEBP maksimal 2 MB.";
                            $jenis_pesan = "error";
                        } else {
                            $ext = pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION);
                            $foto_baru = "user_" . $user_id . "_" . time() . "." . strtolower($ext);
                            $folder = "../assets/uploads/profil";
                            if (!is_dir($folder)) { mkdir($folder, 0777, true); }
                            if (!move_uploaded_file($_FILES["foto"]["tmp_name"], $folder . "/" . $foto_baru)) {
                                $foto_baru = null;
                                $pesan = "Foto gagal disimpan.";
                                $jenis_pesan = "error";
                            }
                        }
                    }
                }

                if ($jenis_pesan !== "error") {
                    if ($password_baru !== "") {
                        $hash = password_hash($password_baru, PASSWORD_DEFAULT);
                        if ($foto_baru !== null) {
                            $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap=?, username=?, email=?, no_hp=?, password=?, foto=? WHERE id=?");
                            mysqli_stmt_bind_param($stmt, "ssssssi", $nama_lengkap, $username, $email, $no_hp, $hash, $foto_baru, $user_id);
                        } else {
                            $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap=?, username=?, email=?, no_hp=?, password=? WHERE id=?");
                            mysqli_stmt_bind_param($stmt, "sssssi", $nama_lengkap, $username, $email, $no_hp, $hash, $user_id);
                        }
                    } elseif ($foto_baru !== null) {
                        $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap=?, username=?, email=?, no_hp=?, foto=? WHERE id=?");
                        mysqli_stmt_bind_param($stmt, "sssssi", $nama_lengkap, $username, $email, $no_hp, $foto_baru, $user_id);
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap=?, username=?, email=?, no_hp=? WHERE id=?");
                        mysqli_stmt_bind_param($stmt, "sssi", $nama_lengkap, $username, $email, $no_hp, $user_id);
                    }

                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION["username"] = $username;
                        $pesan = "Profil berhasil diperbarui.";
                        $jenis_pesan = "sukses";
                    } else {
                        $pesan = "Profil gagal diperbarui.";
                        $jenis_pesan = "error";
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

$stmt = mysqli_prepare($conn, "SELECT username, email, nama_lengkap, no_hp, foto, role, created_at FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$csrf_token = generate_csrf_token();
$judul = "Profil";
$menu_aktif = "profil";
require_once "../includes/header.php";
?>
<div class="wadah">
    <div class="halaman-isi">
        <h1>Profil Pengguna</h1>
        <?php if ($pesan !== "") { ?><p class="<?php echo $jenis_pesan; ?>"><?php echo e($pesan); ?></p><?php } ?>
        <div class="panel">
            <form method="post" action="profil.php" enctype="multipart/form-data" class="form-isi">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>" />
                <p><label for="nama_lengkap">Nama lengkap</label><input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo e($user["nama_lengkap"]); ?>" required="required" /></p>
                <p><label for="username">Username</label><input type="text" id="username" name="username" value="<?php echo e($user["username"]); ?>" required="required" /></p>
                <p><label for="email">Email</label><input type="email" id="email" name="email" value="<?php echo e($user["email"]); ?>" required="required" /></p>
                <p><label for="no_hp">Nomor HP</label><input type="text" id="no_hp" name="no_hp" value="<?php echo e($user["no_hp"] ?? ""); ?>" /></p>
                <p><label for="password_baru">Password baru <span class="kecil">(kosongkan jika tidak ingin mengganti)</span></label><input type="password" id="password_baru" name="password_baru" /></p>
                <p><label for="foto">Foto profil</label><input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" /></p>
                <?php if (!empty($user["foto"])) { ?><p class="kecil">Foto saat ini: <?php echo e($user["foto"]); ?></p><?php } ?>
                <p><input type="submit" value="Simpan Perubahan" /></p>
            </form>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
