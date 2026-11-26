<?php
/* GET api/cek_user.php?username=..&email=..  ->  JSON status ketersediaan (untuk form registrasi) */
require_once __DIR__ . "/../includes/init.php";
header("Content-Type: application/json; charset=UTF-8");

$hasil = array();

if (isset($_GET["username"])) {
    $u = trim($_GET["username"]);
    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $u)) {
        $hasil["username"] = "format";
    } else {
        $s = $conn->prepare("SELECT id FROM users WHERE username=?");
        $s->bind_param("s", $u);
        $s->execute();
        $hasil["username"] = $s->get_result()->num_rows ? "dipakai" : "tersedia";
    }
}

if (isset($_GET["email"])) {
    $m = trim($_GET["email"]);
    if (!filter_var($m, FILTER_VALIDATE_EMAIL) || strlen($m) > 100) {
        $hasil["email"] = "format";
    } else {
        $s = $conn->prepare("SELECT id FROM users WHERE email=?");
        $s->bind_param("s", $m);
        $s->execute();
        $hasil["email"] = $s->get_result()->num_rows ? "dipakai" : "tersedia";
    }
}

echo json_encode($hasil);
