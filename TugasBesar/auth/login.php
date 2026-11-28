<?php
require_once "../config/koneksi.php";
require_once "../includes/auth.php";
require_once "../includes/csrf.php";
require_once "../includes/keamanan.php";

$pesan = "";

if (sudah_login()) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $pesan = "Token keamanan tidak valid. Silakan muat ulang halaman.";
    } else {
        $login = trim($_POST["login"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($login === "" || $password === "") {
            $pesan = "Username/email dan password wajib diisi.";
        } elseif (($tunggu = batas_percobaan_login($conn, $login)) > 0) {
            $pesan = "Terlalu banyak percobaan masuk. " . teks_tunggu($tunggu);
        } else {
            $stmt = mysqli_prepare($conn, "SELECT id, username, email, password, nama_lengkap, role FROM users WHERE username = ? OR email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "ss", $login, $login);
            mysqli_stmt_execute($stmt);
            $hasil = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($hasil);
            mysqli_stmt_close($stmt);

            if ($user && password_verify($password, $user["password"])) {
                reset_percobaan_login($conn, $login);
                session_regenerate_id(true);
                $_SESSION["user_id"] = (int) $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];
                header("Location: " . BASE_URL . "index.php");
                exit;
            } else {
                catat_gagal_login($conn, $login);
                $pesan = "Username/email atau password salah.";
            }
        }
    }
}

$csrf_token = generate_csrf_token();
$judul = "Masuk";
require_once "../includes/header.php";
?>
<div class="wadah">
    <div class="kotak">
        <h1>Masuk</h1>
        <?php if ($pesan !== "") { ?><p class="error"><?php echo e($pesan); ?></p><?php } ?>
        <form method="post" action="login.php" class="form-isi">
            <div>
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>" />
            <p>
                <label for="login">Username atau Email</label>
                <input type="text" id="login" name="login" value="<?php echo e($_POST["login"] ?? ""); ?>" required="required" />
            </p>
            <p>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required="required" />
            </p>
            <input type="submit" value="Masuk" />
            </div>
        </form>
        <p>Belum punya akun? <a href="<?php echo BASE_URL; ?>auth/register.php">Daftar</a></p>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>