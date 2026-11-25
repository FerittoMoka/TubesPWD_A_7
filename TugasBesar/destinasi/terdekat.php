<?php
require_once __DIR__ . "/../config/koneksi.php";
$judul = "Terdekat";
$menu_aktif = "terdekat";
require_once __DIR__ . "/../includes/header.php";

$lat = isset($_GET["lat"]) && $_GET["lat"] !== "" ? (float) $_GET["lat"] : null;
$lng = isset($_GET["lng"]) && $_GET["lng"] !== "" ? (float) $_GET["lng"] : null;
$maks_km = isset($_GET["radius"]) && $_GET["radius"] !== "" ? (float) $_GET["radius"] : 0;
$valid = ($lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180);
$hasil = array();

if ($valid) {
    $sql = "SELECT d.id, d.nama, d.lokasi, d.harga_tiket, d.harga_maks, d.foto, d.latitude, d.longitude, c.nama AS kategori,
        (6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(d.latitude)) * COS(RADIANS(d.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(d.latitude))))) AS jarak
        FROM destinations d JOIN categories c ON c.id = d.category_id
        WHERE d.latitude IS NOT NULL AND d.longitude IS NOT NULL";

    if ($maks_km > 0) {
        $sql .= " HAVING jarak <= ? ORDER BY jarak ASC LIMIT 20";
        $st = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($st, "dddd", $lat, $lng, $lat, $maks_km);
    } else {
        $sql .= " ORDER BY jarak ASC LIMIT 15";
        $st = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($st, "ddd", $lat, $lng, $lat);
    }

    mysqli_stmt_execute($st);
    $hasil = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
    mysqli_stmt_close($st);
}
?>
<div class="wadah">
    <div class="halaman-isi">
        <p class="eyebrow">Geolocation Wisata</p>
        <h2>Destinasi terdekat</h2>
        <p class="muted">Izinkan akses lokasi browser untuk menemukan destinasi wisata Yogyakarta yang paling dekat dengan posisi Anda saat ini.</p>

        <div class="terdekat-kontrol panel">
            <div class="terdekat-aksi-baris">
                <button type="button" class="tombol" id="btnLokasiSaya" onclick="cariTerdekat();">Gunakan lokasi saya</button>
                <button type="button" class="tombol tombol-garis" id="btnSimulasiJogja" onclick="gunakanTitikAcuan(-7.782889, 110.367083, 'Tugu Yogyakarta');">Uji pakai Tugu Jogja</button>

                <label for="pilihRadius" class="terdekat-radius-label">Radius:</label>
                <select id="pilihRadius" onchange="ubahRadius(this.value);">
                    <option value="0"<?php if ($maks_km == 0) { echo ' selected="selected"'; } ?>>Semua jarak</option>
                    <option value="5"<?php if ($maks_km == 5) { echo ' selected="selected"'; } ?>>Maks. 5 km</option>
                    <option value="10"<?php if ($maks_km == 10) { echo ' selected="selected"'; } ?>>Maks. 10 km</option>
                    <option value="20"<?php if ($maks_km == 20) { echo ' selected="selected"'; } ?>>Maks. 20 km</option>
                </select>
            </div>
            <p id="status-lokasi" class="kecil terdekat-status">
                <?php if ($valid) { ?>
                    Koordinat aktif: <strong><?php echo e(number_format($lat, 5)); ?>, <?php echo e(number_format($lng, 5)); ?></strong>
                <?php } else { ?>
                    Belum ada koordinat lokasi yang dipilih. Klik tombol lokasi atau uji coba titik Tugu Jogja.
                <?php } ?>
            </p>
        </div>

        <?php if ($valid) { ?>
            <p class="eyebrow">Hasil Perhitungan Jarak</p>
            <h3><?php echo count($hasil); ?> destinasi ditemukan</h3>

            <?php if (count($hasil) === 0) { ?>
                <p class="kosong">Tidak ada destinasi dalam radius yang dipilih. Coba perlebar radius jarak.</p>
            <?php } else { ?>
                <div class="daftar">
                    <?php foreach ($hasil as $d) {
                        $jarak_km = (float) $d["jarak"];
                        $estimasi_menit = max(1, (int) round(($jarak_km / 30) * 60));
                    ?>
                        <div class="baris kartu-klik">
                            <?php echo tampil_foto($d["foto"], $d["kategori"], $d["nama"]); ?>
                            <div class="baris-info">
                                <h3><?php echo e($d["nama"]); ?></h3>
                                <p class="baris-meta"><?php echo e($d["kategori"]); ?> &middot; <?php echo e($d["lokasi"]); ?></p>
                                <div class="terdekat-badges">
                                    <span class="badge-jarak"><?php echo e(number_format($jarak_km, 1, ",", ".")); ?> km</span>
                                    <span class="kecil">&plusmn; <?php echo $estimasi_menit; ?> mnt berkendara</span>
                                </div>
                            </div>
                            <div class="baris-harga"><?php echo e(rentang_harga($d["harga_tiket"], $d["harga_maks"])); ?></div>
                            <a class="tombol tombol-garis" href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $d["id"]; ?>">Detail</a>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>

<script>
(function () {
    var statusEl = document.getElementById("status-lokasi");

    function setStatus(teks) {
        if (statusEl) {
            statusEl.innerHTML = teks;
        }
    }

    function redirectKoordinat(lat, lng, label) {
        var radiusSelect = document.getElementById("pilihRadius");
        var radiusVal = radiusSelect ? radiusSelect.value : "0";
        var url = new URL(window.location.href);
        url.searchParams.set("lat", lat.toFixed(6));
        url.searchParams.set("lng", lng.toFixed(6));
        url.searchParams.set("radius", radiusVal);
        setStatus("Mengambil destinasi terdekat dari " + label + "...");
        window.location.href = url.toString();
    }

    window.gunakanTitikAcuan = function (lat, lng, label) {
        redirectKoordinat(lat, lng, label);
    };

    window.ubahRadius = function (val) {
        var url = new URL(window.location.href);
        url.searchParams.set("radius", val);
        if (url.searchParams.has("lat") && url.searchParams.has("lng")) {
            window.location.href = url.toString();
        }
    };

    window.cariTerdekat = function () {
        if (!navigator.geolocation) {
            setStatus("Browser Anda tidak mendukung fitur Geolocation. Gunakan tombol uji coba titik acuan.");
            return;
        }
        setStatus("Mendeteksi lokasi GPS/jaringan Anda...");
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                redirectKoordinat(pos.coords.latitude, pos.coords.longitude, "lokasi Anda");
            },
            function (err) {
                var pesan = "Gagal mengambil lokasi.";
                if (err.code === 1) pesan = "Izin lokasi ditolak. Aktifkan izin lokasi di browser atau gunakan tombol Tugu Jogja.";
                else if (err.code === 2) pesan = "Lokasi tidak tersedia saat ini.";
                else if (err.code === 3) pesan = "Waktu permintaan lokasi habis.";
                setStatus(pesan);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    };
})();
</script>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>