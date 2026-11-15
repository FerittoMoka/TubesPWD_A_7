<?php
$judul = "Daftar";
require_once "../includes/auth.php";
require_once "../config/koneksi.php";

if (sudah_login()) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$error = "";
$sukses = false;
$nama = "";
$username = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama = trim($_POST["nama_lengkap"]);
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $konfirmasi = $_POST["konfirmasi"];

    if ($nama === "" || $username === "" || $email === "" || $password === "") {
        $error = "Semua kolom wajib diisi.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = "Username 3-30 karakter, hanya huruf, angka, dan garis bawah.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";
    } elseif ($password !== $konfirmasi) {
        $error = "Konfirmasi password tidak sama.";
    } else {
        $cek = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($cek, "ss", $username, $email);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            $error = "Username atau email sudah dipakai.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $simpan = mysqli_prepare($conn, "INSERT INTO users (username, email, password, nama_lengkap) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($simpan, "ssss", $username, $email, $hash, $nama);
            if (mysqli_stmt_execute($simpan)) {
                $sukses = true;
            } else {
                $error = "Pendaftaran gagal, coba lagi.";
            }
        }
    }
}

require_once "../includes/header.php";
?>
<div class="kotak">
    <h2>Daftar</h2>

    <?php if ($sukses) { ?>
        <p class="sukses">Pendaftaran berhasil. Silakan masuk dengan akunmu.</p>
        <p><a class="tombol" href="<?php echo BASE_URL; ?>auth/login.php">Masuk</a></p>
    <?php } else { ?>
        <?php if ($error !== "") { ?>
            <p class="error"><?php echo e($error); ?></p>
        <?php } ?>

        <form method="post" action="register.php" class="form-isi">
            <p><label for="nama_lengkap">Nama lengkap</label>
            <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo e($nama); ?>" /></p>

            <p><label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?php echo e($username); ?>" /></p>

            <p><label for="email">Email</label>
            <input type="text" id="email" name="email" value="<?php echo e($email); ?>" /></p>

            <p><label for="password">Password</label>
            <input type="password" id="password" name="password" /></p>

            <p><label for="konfirmasi">Konfirmasi password</label>
            <input type="password" id="konfirmasi" name="konfirmasi" /></p>

            <p><button type="submit" class="tombol">Daftar</button></p>
        </form>
        <p class="kecil">Sudah punya akun? <a href="<?php echo BASE_URL; ?>auth/login.php">Masuk</a></p>
    <?php } ?>
</div>
<?php require_once "../includes/footer.php"; ?>
