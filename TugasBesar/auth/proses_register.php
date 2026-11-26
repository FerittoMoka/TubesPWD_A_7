<?php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";
require_once __DIR__ . "/../includes/pesan.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . BASE_URL . "auth/register.php");
    exit;
}

if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
    set_pesan("error", "Token keamanan tidak valid. Silakan muat ulang halaman.");
    header("Location: " . BASE_URL . "auth/register.php");
    exit;
}

$nama_lengkap = trim($_POST["nama_lengkap"] ?? "");
$username     = trim($_POST["username"] ?? "");
$email        = trim($_POST["email"] ?? "");
$password     = $_POST["password"] ?? "";

if ($nama_lengkap === "" || $username === "" || $email === "" || $password === "") {
    set_pesan("error", "Semua kolom wajib diisi.");
    header("Location: " . BASE_URL . "auth/register.php");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_pesan("error", "Format email tidak valid.");
    header("Location: " . BASE_URL . "auth/register.php");
    exit;
}

if (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
    set_pesan("error", "Username hanya boleh huruf, angka, titik, garis bawah, atau strip.");
    header("Location: " . BASE_URL . "auth/register.php");
    exit;
}

if (strlen($password) < 6) {
    set_pesan("error", "Password minimal 6 karakter.");
    header("Location: " . BASE_URL . "auth/register.php");
    exit;
}

$cekStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
mysqli_stmt_bind_param($cekStmt, "ss", $username, $email);
mysqli_stmt_execute($cekStmt);
mysqli_stmt_store_result($cekStmt);

if (mysqli_stmt_num_rows($cekStmt) > 0) {
    mysqli_stmt_close($cekStmt);
    set_pesan("error", "Username atau email sudah terdaftar.");
    header("Location: " . BASE_URL . "auth/register.php");
    exit;
}
mysqli_stmt_close($cekStmt);

$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$insertStmt = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, email, password) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($insertStmt, "ssss", $nama_lengkap, $username, $email, $hashed_password);

if (mysqli_stmt_execute($insertStmt)) {
    $new_user_id = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($insertStmt);
    mysqli_close($conn);

    session_regenerate_id(true);
    $_SESSION["user_id"]  = $new_user_id;
    $_SESSION["username"] = $username;
    $_SESSION["role"]     = "user";

    set_pesan("sukses", "Sugeng rawuh, " . $nama_lengkap . "! Akunmu sudah aktif dan siap dipakai.");
    header("Location: " . BASE_URL . "index.php");
    exit;
}

mysqli_stmt_close($insertStmt);
mysqli_close($conn);
set_pesan("error", "Registrasi gagal. Silakan coba lagi.");
header("Location: " . BASE_URL . "auth/register.php");
exit;