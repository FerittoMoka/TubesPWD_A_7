<?php
require_once "../includes/csrf.php";

if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
    die("Token CSRF tidak valid! Silakan muat ulang halaman.");
}

require_once "../config/koneksi.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama_lengkap = trim($_POST["nama_lengkap"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    if ($nama_lengkap === "" || $username === "" || $email === "" || $password === "") {
        echo '<script type="text/javascript">alert("Semua field wajib diisi!"); window.location="register.php";</script>';
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo '<script type="text/javascript">alert("Format email tidak valid!"); window.location="register.php";</script>';
        exit;
    }

    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
        echo '<script type="text/javascript">alert("Username hanya boleh huruf, angka, titik, garis bawah, atau strip!"); window.location="register.php";</script>';
        exit;
    }

    if (strlen($password) < 6) {
        echo '<script type="text/javascript">alert("Password minimal 6 karakter!"); window.location="register.php";</script>';
        exit;
    }

    if ($password !== $confirm) {
        echo '<script type="text/javascript">alert("Konfirmasi password tidak sesuai!"); window.location="register.php";</script>';
        exit;
    }

    $cekStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    mysqli_stmt_bind_param($cekStmt, "ss", $username, $email);
    mysqli_stmt_execute($cekStmt);
    mysqli_stmt_store_result($cekStmt);

    if (mysqli_stmt_num_rows($cekStmt) > 0) {
        echo '<script type="text/javascript">alert("Username atau email sudah terdaftar!"); window.location="register.php";</script>';
        mysqli_stmt_close($cekStmt);
        exit;
    }
    mysqli_stmt_close($cekStmt);

    // Poin 4: password tidak pernah disimpan dalam bentuk teks biasa.
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $insertStmt = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, email, password) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($insertStmt, "ssss", $nama_lengkap, $username, $email, $hashed_password);

    if (mysqli_stmt_execute($insertStmt)) {
        echo '<script type="text/javascript">alert("Registrasi berhasil! Silakan masuk."); window.location="login.php";</script>';
    } else {
        echo '<script type="text/javascript">alert("Registrasi gagal."); window.location="register.php";</script>';
    }

    mysqli_stmt_close($insertStmt);
    mysqli_close($conn);
} else {
    header("Location: register.php");
    exit;
}
