<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="id" lang="id">
<head>
    <title>Daftar - Dolan Jogja</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <style type="text/css">
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #3b2f2f; 
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .register-container {
            background-color: #fcf9f2; 
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            box-sizing: border-box;
        }
        .logo-text {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #3b2f2f;
            margin-bottom: 25px;
            letter-spacing: 0.5px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a3b3b;
            font-size: 14px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            box-sizing: border-box;
            border: 1px solid #d1c7bd;
            border-radius: 8px;
            background-color: #fff;
            font-size: 14px;
            color: #333;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #b8860b; 
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: #d4a373; 
            color: #fff;
            border: none;
            border-radius: 24px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 10px;
        }
        .btn-submit:hover {
            background-color: #bc6c25;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .login-link a {
            color: #bc6c25;
            text-decoration: none;
            font-weight: bold;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
    <script type="text/javascript">
        function validasiForm() {
            var nama = document.getElementById("nama_lengkap").value;
            var pass = document.getElementById("password").value;
            var konfirmasi = document.getElementById("confirm_password").value;

            if (nama.trim() === "") {
                alert("Nama lengkap tidak boleh kosong!");
                return false;
            }
            if (pass.length < 6) {
                alert("Password minimal harus 6 karakter!");
                return false;
            }
            if (pass !== konfirmasi) {
                alert("Konfirmasi password tidak cocok!");
                return false;
            }
            return true;
        }
    </script>
</head>
<body>
    <div class="register-container">
        <div class="logo-text">Dolan Jogja</div>
        <form action="proses_register.php" method="post" onsubmit="return validasiForm();">
            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" required="required" />
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required="required" />
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="text" id="email" name="email" required="required" />
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required="required" />
            </div>
            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required="required" />
            </div>
            <button type="submit" class="btn-submit" name="register">Daftar Akun</button>
        </form>
        <div class="login-link">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
    </div>
</body>
</html>

<?php require_once 'csrf.php'; $csrfToken = generate_csrf_token(); ?>
<form action="proses_register.php" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>" />
    <!-- field input lainnya -->
</form>