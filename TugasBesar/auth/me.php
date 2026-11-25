<?php
require_once "../includes/auth.php";
header("Content-Type: application/json; charset=utf-8");

if (!sudah_login()) { echo json_encode(["login" => false]); exit; }

echo json_encode([
    "login" => true,
    "nama"  => $_SESSION["nama_lengkap"] ?? $_SESSION["username"],
    "role"  => $_SESSION["role"] ?? "user"
]);