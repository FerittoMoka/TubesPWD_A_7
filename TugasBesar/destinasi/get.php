<?php
// GET destinasi/get.php?id=1  ->  JSON detail destinasi + rating + destinasi terkait
header('Content-Type: application/json; charset=utf-8');

// Sesuaikan path jika koneksi.php ada di lokasi lain
require_once __DIR__ . '/../config/koneksi.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'ID tidak valid']);
    exit;
}

$sql = "SELECT d.id, d.category_id, d.nama, d.lokasi, d.deskripsi, d.harga_tiket, d.harga_maks,
               d.jam_buka, d.latitude, d.longitude, d.foto, c.nama AS kategori,
               (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.destination_id = d.id) AS rating,
               (SELECT COUNT(*) FROM reviews r WHERE r.destination_id = d.id) AS jumlah_ulasan
        FROM destinations d
        JOIN categories c ON c.id = d.category_id
        WHERE d.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$destinasi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$destinasi) {
    http_response_code(404);
    echo json_encode(['error' => 'Destinasi tidak ditemukan']);
    exit;
}

// Terkait: kategori yang sama dulu, lalu sisanya (maks. 3)
$sql2 = "SELECT d.id, d.nama, d.foto, c.nama AS kategori
         FROM destinations d
         JOIN categories c ON c.id = d.category_id
         WHERE d.id <> ?
         ORDER BY (d.category_id = ?) DESC, d.id
         LIMIT 3";
$stmt2 = mysqli_prepare($conn, $sql2);
mysqli_stmt_bind_param($stmt2, 'ii', $id, $destinasi['category_id']);
mysqli_stmt_execute($stmt2);
$terkait = mysqli_fetch_all(mysqli_stmt_get_result($stmt2), MYSQLI_ASSOC);

echo json_encode(['destinasi' => $destinasi, 'terkait' => $terkait], JSON_UNESCAPED_UNICODE);
