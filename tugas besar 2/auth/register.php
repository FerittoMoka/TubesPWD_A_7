<?php
require_once __DIR__ . "/../includes/init.php";
if (login()) { redirect("index.php"); }

$error = "";
$val = array("nama" => "", "username" => "", "email" => "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cek_csrf();
    $val["nama"]     = trim($_POST["nama"] ?? "");
    $val["username"] = trim($_POST["username"] ?? "");
    $val["email"]    = trim($_POST["email"] ?? "");
    $pass  = $_POST["password"] ?? "";
    $pass2 = $_POST["password2"] ?? "";

    if ($val["nama"] === "" || mb_strlen($val["nama"]) > 100) {
        $error = "Nama lengkap wajib diisi (maksimal 100 karakter).";
    } elseif (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $val["username"])) {
        $error = "Username 3-30 karakter: huruf, angka, titik, atau underscore.";
    } elseif (!filter_var($val["email"], FILTER_VALIDATE_EMAIL) || strlen($val["email"]) > 100) {
        $error = "Format email tidak valid.";
    } elseif (strlen($pass) < 8) {
        $error = "Password minimal 8 karakter.";
    } elseif ($pass !== $pass2) {
        $error = "Konfirmasi password tidak sama.";
    } else {
        $s = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $s->bind_param("ss", $val["username"], $val["email"]);
        $s->execute();
        if ($s->get_result()->num_rows) {
            $error = "Username atau email sudah digunakan.";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);   // enkripsi (hash bcrypt) password
            try {
                $s = $conn->prepare("INSERT INTO users (username,email,password,nama_lengkap) VALUES (?,?,?,?)");
                $s->bind_param("ssss", $val["username"], $val["email"], $hash, $val["nama"]);
                $s->execute();
                flash("success", "Akun berhasil dibuat. Silakan masuk.");
                redirect("auth/login.php");
            } catch (mysqli_sql_exception $ex) {
                $error = "Username atau email sudah digunakan.";
            }
        }
    }
}

header_web("Daftar Akun");
?>
<div class="auth-wrap">
  <div class="auth-intro">
    <span class="eyebrow">Mulai petualangan</span>
    <h1>Buat akun Dolan Jogja.</h1>
    <p>Simpan destinasi favorit dan bagikan pengalaman wisatamu.</p>
  </div>
  <form id="form-register" class="panel auth-form" method="post" action="<?php echo url("auth/register.php"); ?>" onsubmit="return validasiRegister();">
    <div>
      <h2>Daftar akun</h2>
      <?php if ($error) { echo '<div class="alert alert-error">' . e($error) . '</div>'; } ?>
      <div id="pesan-form"></div>
      <?php echo csrf_field(); ?>
      <input type="hidden" id="api-cek" value="<?php echo url("api/cek_user.php"); ?>" />

      <label for="nama">Nama lengkap</label>
      <input type="text" id="nama" name="nama" maxlength="100" value="<?php echo e($val["nama"]); ?>" />

      <label for="username">Username</label>
      <input type="text" id="username" name="username" maxlength="30" value="<?php echo e($val["username"]); ?>" />
      <span id="status-username" class="status"></span>

      <label for="email">Email</label>
      <input type="text" id="email" name="email" maxlength="100" value="<?php echo e($val["email"]); ?>" />
      <span id="status-email" class="status"></span>

      <label for="password">Password (minimal 8 karakter)</label>
      <input type="password" id="password" name="password" />

      <label for="password2">Ulangi password</label>
      <input type="password" id="password2" name="password2" />
      <span id="status-password" class="status"></span>

      <button class="btn full" type="submit">Buat akun</button>
      <p class="form-foot">Sudah punya akun? <a href="<?php echo url("auth/login.php"); ?>">Masuk</a></p>
    </div>
  </form>
</div>
<?php footer_web(); ?>
