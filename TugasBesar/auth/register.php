<?php
require_once "../includes/auth.php";
require_once "../includes/csrf.php";

if (sudah_login()) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$csrfToken = generate_csrf_token();
?>
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="id" lang="id">
<head>
    <title>Daftar - Dolan Jogja</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel="stylesheet" type="text/css" href="<?php echo BASE_URL; ?>assets/css/style.css" />
</head>
<body>
    <div class="kotak">
        <h1>Dolan Jogja</h1>
        <form action="proses_register.php" method="post" onsubmit="return validasiForm();" class="form-isi">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>" />
            <p><label for="nama_lengkap">Nama Lengkap</label><input type="text" id="nama_lengkap" name="nama_lengkap" required="required" /></p>
            <p><label for="username">Username</label><input type="text" id="username" name="username" required="required" /></p>
            <p><label for="email">Email</label><input type="email" id="email" name="email" required="required" /></p>
            <p><label for="password">Password</label><input type="password" id="password" name="password" required="required" /></p>
            <p><label for="confirm_password">Konfirmasi Password</label><input type="password" id="confirm_password" name="confirm_password" required="required" /></p>
            <input type="submit" name="register" value="Daftar Akun" />
        </form>
        <p>Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
    </div>
    <script type="text/javascript">
        function validasiForm() {
            var nama = document.getElementById("nama_lengkap").value;
            var pass = document.getElementById("password").value;
            var konfirmasi = document.getElementById("confirm_password").value;
            if (nama.replace(/\s/g, "") === "") { alert("Nama lengkap tidak boleh kosong!"); return false; }
            if (pass.length < 6) { alert("Password minimal harus 6 karakter!"); return false; }
            if (pass !== konfirmasi) { alert("Konfirmasi password tidak cocok!"); return false; }
            return true;
        }
    </script>
</body>
</html>
