<?php
require_once __DIR__ . "/../includes/init.php";
if (login()) { redirect("index.php"); }

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cek_csrf();
    $u = trim($_POST["user"] ?? "");
    $p = $_POST["password"] ?? "";

    $s = $conn->prepare("SELECT id, password, role FROM users WHERE username=? OR email=? LIMIT 1");
    $s->bind_param("ss", $u, $u);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();

    if ($r && password_verify($p, $r["password"])) {
        session_regenerate_id(true);
        $_SESSION["user_id"] = (int) $r["id"];
        if (password_needs_rehash($r["password"], PASSWORD_DEFAULT)) {
            $baru = password_hash($p, PASSWORD_DEFAULT);
            $s = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $s->bind_param("si", $baru, $r["id"]);
            $s->execute();
        }
        redirect($r["role"] === "admin" ? "admin/index.php" : "index.php");
    }
    $error = "Username/email atau password tidak sesuai.";
}

header_web("Masuk");
?>
<div class="auth-wrap">
  <div class="auth-intro">
    <span class="eyebrow">Senang melihatmu kembali</span>
    <h1>Perjalanan berikutnya dimulai di sini.</h1>
    <p>Masuk untuk mengelola profil, favorit, dan ulasan destinasi.</p>
  </div>
  <form class="panel auth-form" method="post" action="<?php echo url("auth/login.php"); ?>">
    <div>
      <h2>Masuk</h2>
      <?php if ($error) { echo '<div class="alert alert-error">' . e($error) . '</div>'; } ?>
      <?php echo csrf_field(); ?>
      <label for="user">Username atau email</label>
      <input type="text" id="user" name="user" value="<?php echo e($_POST["user"] ?? ""); ?>" />
      <label for="password">Password</label>
      <input type="password" id="password" name="password" />
      <button class="btn full" type="submit">Masuk</button>
      <p class="form-foot">Belum punya akun? <a href="<?php echo url("auth/register.php"); ?>">Daftar sekarang</a></p>
    </div>
  </form>
</div>
<?php footer_web(); ?>
