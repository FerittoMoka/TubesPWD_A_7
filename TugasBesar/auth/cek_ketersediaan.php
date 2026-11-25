<?php
require_once __DIR__ . "/../config/koneksi.php";
header("Content-Type: application/json; charset=UTF-8");

$tipe = $_GET["tipe"] ?? "";
$nilai = trim($_GET["nilai"] ?? "");

if ($nilai === "" || !in_array($tipe, array("username", "email"), true)) {
    echo json_encode(array("tersedia" => false, "pesan" => ""));
    exit;
}

if ($tipe === "username") {
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $nilai)) {
        echo json_encode(array(
            "tersedia" => false,
            "pesan" => "Username hanya boleh huruf, angka, titik, garis bawah, atau strip."
        ));
        exit;
    }
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? LIMIT 1");
} else {
    if (!filter_var($nilai, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(array(
            "tersedia" => false,
            "pesan" => "Format email belum valid."
        ));
        exit;
    }
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
}

mysqli_stmt_bind_param($stmt, "s", $nilai);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$terpakai = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if ($terpakai) {
    echo json_encode(array(
        "tersedia" => false,
        "pesan" => ($tipe === "username" ? "Username" : "Email") . " sudah digunakan."
    ));
} else {
    echo json_encode(array(
        "tersedia" => true,
        "pesan" => ($tipe === "username" ? "Username" : "Email") . " tersedia."
    ));
}