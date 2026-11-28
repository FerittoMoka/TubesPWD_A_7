<?php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";

/*
 * Titik keberangkatan populer. Dipakai tombol pintasan supaya wisatawan
 * dari luar kota bisa langsung menghitung jarak dari stasiun/bandara.
 */
$titik_populer = array(
    array("label" => "Stasiun Tugu / Malioboro", "lat" => -7.789214, "lng" => 110.363483),
    array("label" => "Bandara YIA Kulon Progo", "lat" => -7.900694, "lng" => 110.056667),
    array("label" => "Candi Prambanan", "lat" => -7.752021, "lng" => 110.491467),
    array("label" => "Kampus UAJY Babarsari", "lat" => -7.780168, "lng" => 110.414328),
);

/* Ambil destinasi terdekat memakai rumus Haversine di MySQL. */
function terdekat_cari($conn, $lat, $lng, $maks_km) {
    $sql = "SELECT d.id, d.nama, d.lokasi, d.wilayah, d.harga_tiket, d.harga_maks, d.foto, d.latitude, d.longitude, c.nama AS kategori,
        (6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(d.latitude)) * COS(RADIANS(d.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(d.latitude))))) AS jarak
        FROM destinations d JOIN categories c ON c.id = d.category_id
        WHERE d.latitude IS NOT NULL AND d.longitude IS NOT NULL AND d.status = 'aktif'";

    if ($maks_km > 0) {
        $sql .= " HAVING jarak <= ? ORDER BY jarak ASC LIMIT 20";
        $st = mysqli_prepare($conn, $sql);
        if (!$st) { return array(); }
        mysqli_stmt_bind_param($st, "dddd", $lat, $lng, $lat, $maks_km);
    } else {
        $sql .= " ORDER BY jarak ASC LIMIT 15";
        $st = mysqli_prepare($conn, $sql);
        if (!$st) { return array(); }
        mysqli_stmt_bind_param($st, "ddd", $lat, $lng, $lat);
    }

    mysqli_stmt_execute($st);
    $hasil = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
    mysqli_stmt_close($st);
    return $hasil;
}

/* HTML daftar hasil: dipakai saat muat pertama maupun jawaban AJAX. */
function terdekat_html($hasil) {
    if (count($hasil) === 0) {
        return '<p class="kosong">Tidak ada destinasi pada radius ini. Coba perlebar radius atau pilih titik keberangkatan lain.</p>';
    }
    $keluar = '<div class="daftar">';
    foreach ($hasil as $d) {
        $jarak_km = (float) $d["jarak"];
        $estimasi_menit = max(1, (int) round(($jarak_km / 30) * 60));
        $keluar .= '<div class="baris kartu-klik">'
            . tampil_foto($d["foto"], $d["kategori"], $d["nama"])
            . '<div class="baris-info">'
            . '<h3>' . e($d["nama"]) . '</h3>'
            . '<p class="baris-meta">' . e($d["kategori"]) . ' &middot; ' . e($d["lokasi"]) . '</p>'
            . '<div class="terdekat-badges">'
            . '<span class="badge-jarak">' . e(number_format($jarak_km, 1, ",", ".")) . ' km</span>'
            . '<span class="kecil">&plusmn; ' . $estimasi_menit . ' mnt berkendara</span>'
            . (!empty($d["wilayah"]) ? '<span class="kecil">&middot; ' . e($d["wilayah"]) . '</span>' : '')
            . '</div></div>'
            . '<div class="baris-harga">' . e(rentang_harga($d["harga_tiket"], $d["harga_maks"])) . '</div>'
            . '<a class="tombol tombol-garis" href="' . BASE_URL . 'destinasi/detail.php?id=' . (int) $d["id"] . '">Detail</a>'
            . '</div>';
    }
    return $keluar . '</div>';
}

$lat = isset($_GET["lat"]) && is_numeric($_GET["lat"]) ? (float) $_GET["lat"] : null;
$lng = isset($_GET["lng"]) && is_numeric($_GET["lng"]) ? (float) $_GET["lng"] : null;
$maks_km = isset($_GET["radius"]) && $_GET["radius"] !== "" ? (float) $_GET["radius"] : 0;
if ($maks_km < 0 || $maks_km > 500) { $maks_km = 0; }
$valid = ($lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180);

$hasil = array();
if ($valid) {
    $_SESSION["lokasi_user"] = array("lat" => $lat, "lng" => $lng);
    $hasil = terdekat_cari($conn, $lat, $lng, $maks_km);
}

/* Mode AJAX: kembalikan JSON agar daftar bisa diperbarui tanpa memuat ulang halaman. */
if (isset($_GET["ajax"])) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(array(
        "ok"     => $valid,
        "lat"    => $valid ? $lat : null,
        "lng"    => $valid ? $lng : null,
        "radius" => $maks_km,
        "jumlah" => count($hasil),
        "html"   => $valid ? terdekat_html($hasil) : '<p class="kosong">Koordinat belum valid.</p>',
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

$judul = "Terdekat";
$menu_aktif = "terdekat";
require_once __DIR__ . "/../includes/header.php";
?>
<div class="wadah">
    <div class="halaman-isi">
        <p class="eyebrow">Geolocation Wisata</p>
        <h2>Destinasi terdekat</h2>
        <p class="muted">Izinkan akses lokasi browser untuk menemukan destinasi wisata Yogyakarta yang paling dekat dengan posisi Anda saat ini.</p>

        <div class="terdekat-kontrol panel">
            <div class="terdekat-aksi-baris">
                <button type="button" class="tombol" id="btnLokasiSaya">Gunakan lokasi saya</button>
                <button type="button" class="tombol tombol-garis" id="btnSimulasiJogja">Uji pakai Tugu Jogja</button>

                <label for="pilihRadius" class="terdekat-radius-label">Radius:</label>
                <select id="pilihRadius">
                    <option value="0"<?php if ($maks_km == 0) { echo ' selected="selected"'; } ?>>Semua jarak</option>
                    <option value="5"<?php if ($maks_km == 5) { echo ' selected="selected"'; } ?>>Maks. 5 km</option>
                    <option value="10"<?php if ($maks_km == 10) { echo ' selected="selected"'; } ?>>Maks. 10 km</option>
                    <option value="20"<?php if ($maks_km == 20) { echo ' selected="selected"'; } ?>>Maks. 20 km</option>
                    <option value="50"<?php if ($maks_km == 50) { echo ' selected="selected"'; } ?>>Maks. 50 km</option>
                </select>
            </div>

            <p class="muted kecil titik-cepat-judul">Titik keberangkatan populer:</p>
            <div class="titik-cepat-baris">
                <?php foreach ($titik_populer as $tp) { ?>
                    <button type="button" class="btn-titik-cepat"
                            data-lat="<?php echo e($tp["lat"]); ?>"
                            data-lng="<?php echo e($tp["lng"]); ?>"
                            data-label="<?php echo e($tp["label"]); ?>"><?php echo e($tp["label"]); ?></button>
                <?php } ?>
            </div>

            <form method="get" action="<?php echo BASE_URL; ?>destinasi/terdekat.php" class="terdekat-manual" id="form-lokasi-manual">
                <label for="manual-lat">Latitude</label>
                <input type="text" id="manual-lat" name="lat" inputmode="decimal" placeholder="-7.78289" value="<?php echo $valid ? e(number_format($lat, 6, ".", "")) : ""; ?>" />
                <label for="manual-lng">Longitude</label>
                <input type="text" id="manual-lng" name="lng" inputmode="decimal" placeholder="110.36708" value="<?php echo $valid ? e(number_format($lng, 6, ".", "")) : ""; ?>" />
                <input type="hidden" name="radius" id="manual-radius" value="<?php echo e((string) $maks_km); ?>" />
                <input type="submit" class="btn-kecil" value="Hitung dari koordinat" />
            </form>
            <p class="kecil">Tanpa GPS? Isi koordinat dari Google Maps (klik kanan lokasi &rarr; salin angka), atau <a href="<?php echo BASE_URL; ?>peta.php">pilih titik lewat peta</a>.</p>

            <p id="status-lokasi" class="kecil terdekat-status">
                <?php if ($valid) { ?>
                    Koordinat aktif: <strong><?php echo e(number_format($lat, 5)); ?>, <?php echo e(number_format($lng, 5)); ?></strong>
                <?php } else { ?>
                    Belum ada koordinat lokasi yang dipilih. Klik tombol lokasi, pilih titik populer, isi koordinat manual, atau uji coba titik Tugu Jogja.
                <?php } ?>
            </p>
        </div>

        <p class="eyebrow">Hasil Perhitungan Jarak</p>
        <h3 id="judul-terdekat"<?php echo $valid ? "" : ' class="sembunyi"'; ?>><?php echo count($hasil); ?> destinasi ditemukan</h3>
        <div id="wadah-terdekat" class="wadah-terdekat">
            <?php if ($valid) { ?>
                <?php echo terdekat_html($hasil); ?>
            <?php } else { ?>
                <p class="kosong" id="petunjuk-terdekat">Pilih titik lokasi lebih dulu untuk melihat daftar destinasi terdekat.</p>
            <?php } ?>
        </div>
    </div>
</div>

<script type="text/javascript">
//<![CDATA[
(function () {
    var wadah = document.getElementById("wadah-terdekat");
    var judul = document.getElementById("judul-terdekat");
    var statusEl = document.getElementById("status-lokasi");
    var radius = document.getElementById("pilihRadius");
    var btnLokasi = document.getElementById("btnLokasiSaya");
    var btnSimulasi = document.getElementById("btnSimulasiJogja");
    var formManual = document.getElementById("form-lokasi-manual");
    var inputLat = document.getElementById("manual-lat");
    var inputLng = document.getElementById("manual-lng");
    var inputRadius = document.getElementById("manual-radius");
    var titikAktif = null;

    function esc(s) {
        return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function setStatus(html, tipe) {
        if (!statusEl) { return; }
        statusEl.className = "kecil terdekat-status" + (tipe ? " status-" + tipe : "");
        statusEl.innerHTML = html;
    }

    function alamat(lat, lng, label) {
        var url = new URL(window.location.href);
        url.searchParams.set("lat", Number(lat).toFixed(6));
        url.searchParams.set("lng", Number(lng).toFixed(6));
        url.searchParams.set("radius", radius ? radius.value : "0");
        url.hash = "";
        return url;
    }

    function muat(lat, lng, label) {
        if (!wadah) { return; }
        titikAktif = { lat: Number(lat), lng: Number(lng), label: label };
        if (inputLat) { inputLat.value = titikAktif.lat.toFixed(6); }
        if (inputLng) { inputLng.value = titikAktif.lng.toFixed(6); }
        var url = alamat(titikAktif.lat, titikAktif.lng, label);
        var api = new URL(url.toString());
        api.searchParams.set("ajax", "1");

        setStatus("Menghitung destinasi terdekat dari <strong>" + esc(label) + "</strong>...", "info");
        wadah.className = "wadah-terdekat sedang-memuat";

        if (!window.fetch) {
            window.location.href = url.toString();
            return;
        }

        fetch(api.toString(), { headers: { "Accept": "application/json" } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                wadah.className = "wadah-terdekat";
                if (!data || !data.ok) {
                    setStatus("Koordinat tidak valid. Coba pilih titik lain.", "error");
                    return;
                }
                wadah.innerHTML = data.html;
                if (judul) {
                    judul.classList.remove("sembunyi");
                    judul.textContent = data.jumlah + " destinasi ditemukan";
                }
                var petunjuk = document.getElementById("petunjuk-terdekat");
                if (petunjuk) { petunjuk.className = "kosong sembunyi"; }
                setStatus("Koordinat aktif: <strong>" + titikAktif.lat.toFixed(5) + ", " + titikAktif.lng.toFixed(5) +
                    "</strong> &middot; titik acuan " + esc(label), "sukses");
                try { history.replaceState(null, "", url.toString()); } catch (e) {}
            })
            .catch(function () {
                window.location.href = url.toString();
            });
    }

    function pakaiLokasi() {
        if (!window.DolanLokasi) {
            setStatus("Modul geolocation belum termuat. Gunakan titik keberangkatan populer di atas.", "error");
            return;
        }
        window.DolanLokasi.cari({
            status: statusEl,
            tombol: btnLokasi,
            onSukses: function (lat, lng, sumber) {
                muat(lat, lng, sumber);
            }
        });
    }

    /* Koordinat manual: bisa diketik sendiri atau disalin dari Google Maps. */
    function angka(teks) {
        return parseFloat(String(teks).replace(",", ".").replace(/\s+/g, ""));
    }

    if (formManual) {
        formManual.addEventListener("submit", function (e) {
            var lat = angka(inputLat.value);
            var lng = angka(inputLng.value);
            if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                if (window.fetch) { e.preventDefault(); }
                setStatus("Koordinat tidak valid. Latitude -90 sampai 90, longitude -180 sampai 180.", "error");
                return;
            }
            if (window.fetch) {
                e.preventDefault();
                muat(lat, lng, "koordinat manual");
            }
        });
    }

    if (btnLokasi) { btnLokasi.addEventListener("click", pakaiLokasi); }
    if (btnSimulasi) {
        btnSimulasi.addEventListener("click", function () {
            muat(-7.782889, 110.367083, "Tugu Yogyakarta");
        });
    }

    if (radius) {
        radius.addEventListener("change", function () {
            if (inputRadius) { inputRadius.value = radius.value; }
            if (titikAktif) {
                muat(titikAktif.lat, titikAktif.lng, titikAktif.label);
            }
        });
    }

    /* Tombol titik keberangkatan populer + tombol fallback dari modul geolocation. */
    document.addEventListener("click", function (e) {
        var btn = e.target.closest ? e.target.closest("button[data-lat][data-lng]") : null;
        if (!btn) { return; }
        var lat = parseFloat(btn.getAttribute("data-lat"));
        var lng = parseFloat(btn.getAttribute("data-lng"));
        if (isNaN(lat) || isNaN(lng)) { return; }
        var label = btn.getAttribute("data-label") || "titik pilihan";
        muat(lat, lng, label);
    });
})();
//]]>
</script>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
