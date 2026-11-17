<?php
require_once 'koneksi.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username     = trim($_POST['username']);
    $email        = trim($_POST['email']);
    $password     = $_POST['password'];
    $confirm      = $_POST['confirm_password'];

    if (empty($nama_lengkap) || empty($username) || empty($email) || empty($password)) {
        echo "<script>alert('Semua field wajib diisi!'); window.location='register.php';</script>";
        exit;
    }

    if ($password !== $confirm) {
        echo "<script>alert('Konfirmasi password tidak sesuai!'); window.location='register.php';</script>";
        exit;
    }

    $cekStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
    mysqli_stmt_bind_param($cekStmt, "ss", $username, $email);
    mysqli_stmt_execute($cekStmt);
    mysqli_stmt_store_result($cekStmt);

    if (mysqli_stmt_num_rows($cekStmt) > 0) {
        echo "<script>alert('Username atau Email sudah digunakan!'); window.location='register.php';</script>";
        mysqli_stmt_close($cekStmt);
        exit;
    }
    mysqli_stmt_close($cekStmt);

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, email, password) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($insertStmt, "ssss", $nama_lengkap, $username, $email, $hashed_password);

    if (mysqli_stmt_execute($insertStmt)) {
        echo "<script>alert('Registrasi berhasil! Silakan login.'); window.location='login.php';</script>";
    } else {
        echo "<script>alert('Registrasi gagal: " . mysqli_error($conn) . "'); window.location='register.php';</script>";
    }

    mysqli_stmt_close($insertStmt);
    mysqli_close($conn);
} else {
    header("Location: register.php");
    exit;
}
?>