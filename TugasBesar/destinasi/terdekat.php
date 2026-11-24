<?php
require_once __DIR__ . "/../config/koneksi.php";
$judul = "Terdekat";
$menu_aktif = "terdekat";
require_once __DIR__ . "/../includes/header.php";

$lat = isset($_GET["lat"]) ? (float) $_GET["lat"] : null;
$lng = isset($_GET["lng"]) ? (float) $_GET["lng"] : null;
$valid = ($lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180);
$hasil = array();

if ($valid) {
    // Rumus haversine, jarak dalam km.
    $st = mysqli_prepare($conn, "SELECT d.id, d.nama, d.lokasi, d.harga_tiket, d.harga_maks, d.foto, c.nama AS kategori,
        (6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(d.latitude)) * COS(RADIANS(d.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(d.latitude))))) AS jarak
        FROM destinations d JOIN categories c ON c.id = d.category_id
        WHERE d.latitude IS NOT NULL AND d.longitude IS NOT NULL
        ORDER BY jarak ASC LIMIT 10");
    mysqli_stmt_bind_param($st, "ddd", $lat, $lng, $lat);
    mysqli_stmt_execute($st);
    $hasil = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
}
?>
<div class="wadah"><div class="halaman-isi">
    <h2>Destinasi terdekat</h2>
    <p>Izinkan akses lokasi untuk melihat destinasi yang paling dekat denganmu.</p>
    <p><button type="button" class="tombol" onclick="cariTerdekat();">Gunakan lokasi saya</button></p>
    <p id="status-lokasi" class="kecil"></p>

    <?php if ($valid) { ?>
        <div class="daftar">
            <?php foreach ($hasil as $d) { ?>
                <div class="baris kartu-klik">
                    <?php echo tampil_foto($d["foto"], $d["kategori"], $d["nama"]); ?>
                    <div class="baris-info">
                        <h3><?php echo e($d["nama"]); ?></h3>
                        <p class="baris-meta"><?php echo e($d["kategori"]); ?> &middot; <?php echo e($d["lokasi"]); ?></p>
                        <span class="rating"><?php echo number_format($d["jarak"], 1, ",", "."); ?> km dari lokasimu</span>
                    </div>
                    <div class="baris-harga"><?php echo e(rentang_harga($d["harga_tiket"], $d["harga_maks"])); ?></div>
                    <a class="tombol tombol-garis" href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $d["id"]; ?>">Detail</a>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div></div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
