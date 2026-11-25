<?php
/* GET api/terdekat.php?lat=..&lng=..  ->  JSON daftar destinasi terdekat (rumus Haversine) */
require_once __DIR__ . "/../includes/init.php";
header("Content-Type: application/json; charset=UTF-8");

$lat = isset($_GET["lat"]) && is_numeric($_GET["lat"]) ? (float) $_GET["lat"] : null;
$lng = isset($_GET["lng"]) && is_numeric($_GET["lng"]) ? (float) $_GET["lng"] : null;
if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(array("error" => "Koordinat tidak valid."));
    exit;
}

$sql = "SELECT d.id, d.nama, d.lokasi, d.harga_tiket, d.harga_maks, c.nama AS kategori,
        (SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.destination_id=d.id) AS rating,
        (6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(d.latitude)) * COS(RADIANS(d.longitude) - RADIANS(?))
                         + SIN(RADIANS(?)) * SIN(RADIANS(d.latitude))))) AS jarak
        FROM destinations d JOIN categories c ON c.id=d.category_id
        WHERE d.latitude IS NOT NULL AND d.longitude IS NOT NULL
        ORDER BY jarak ASC LIMIT 6";
$s = $conn->prepare($sql);
$s->bind_param("ddd", $lat, $lng, $lat);
$s->execute();
$hasil = array();
foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $hasil[] = array(
        "id"       => (int) $r["id"],
        "nama"     => $r["nama"],
        "lokasi"   => $r["lokasi"],
        "kategori" => $r["kategori"],
        "harga"    => harga($r["harga_tiket"], $r["harga_maks"]),
        "rating"   => $r["rating"] !== null ? (float) $r["rating"] : null,
        "jarak"    => round((float) $r["jarak"], 1),
    );
}
echo json_encode($hasil);
