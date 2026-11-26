<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";

if (!sudah_login()) {
    http_response_code(401);
    echo json_encode(array("ok" => false, "pesan" => "Silakan masuk terlebih dahulu."));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !verify_csrf_token($_POST["csrf_token"] ?? "")) {
    http_response_code(403);
    echo json_encode(array("ok" => false, "pesan" => "Permintaan tidak valid."));
    exit;
}

$id  = (int) ($_POST["destination_id"] ?? 0);
$uid = (int) $_SESSION["user_id"];

$st = mysqli_prepare($conn, "SELECT id FROM destinations WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($st, "i", $id);
mysqli_stmt_execute($st);
$ada_dest = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if (!$ada_dest) {
    http_response_code(404);
    echo json_encode(array("ok" => false, "pesan" => "Destinasi tidak ditemukan."));
    exit;
}

$st = mysqli_prepare($conn, "SELECT id FROM favorites WHERE user_id = ? AND destination_id = ? LIMIT 1");
mysqli_stmt_bind_param($st, "ii", $uid, $id);
mysqli_stmt_execute($st);
$ada = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if ($ada) {
    $q = mysqli_prepare($conn, "DELETE FROM favorites WHERE user_id = ? AND destination_id = ?");
    $status = "removed";
} else {
    $q = mysqli_prepare($conn, "INSERT INTO favorites (user_id, destination_id) VALUES (?, ?)");
    $status = "added";
}
mysqli_stmt_bind_param($q, "ii", $uid, $id);
mysqli_stmt_execute($q);
mysqli_stmt_close($q);

$st = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM favorites WHERE destination_id = ?");
mysqli_stmt_bind_param($st, "i", $id);
mysqli_stmt_execute($st);
$total_favorit = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($st))["n"];
mysqli_stmt_close($st);

echo json_encode(array("ok" => true, "status" => $status, "total_favorit" => $total_favorit));