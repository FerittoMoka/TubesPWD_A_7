<?php
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/jam_buka.php";

$judul = "Peta Destinasi";
$menu_aktif = "peta";

$sql = "SELECT d.id, d.nama, d.lokasi, d.harga_tiket, d.harga_maks, d.jam_buka, d.foto, d.latitude, d.longitude,
        c.id AS category_id, c.nama AS kategori,
        (SELECT AVG(r.rating) FROM reviews r WHERE r.destination_id = d.id) AS rata,
        (SELECT COUNT(*) FROM reviews r WHERE r.destination_id = d.id) AS jml_ulasan
        FROM destinations d
        JOIN categories c ON d.category_id = c.id
        WHERE d.latitude IS NOT NULL AND d.longitude IS NOT NULL
        ORDER BY d.nama ASC";
$res = mysqli_query($conn, $sql);
$titik = array();
$kategori_map = array();
while ($row = mysqli_fetch_assoc($res)) {
    $kat_id = (int) $row["category_id"];
    $kategori_map[$kat_id] = $row["kategori"];
    $foto_url = "";
    if (!empty($row["foto"]) && is_file(__DIR__ . "/assets/uploads/destinasi/" . $row["foto"])) {
        $foto_url = BASE_URL . "assets/uploads/destinasi/" . $row["foto"];
    }
    $titik[] = array(
        "id"       => (int) $row["id"],
        "nama"     => $row["nama"],
        "lokasi"   => $row["lokasi"],
        "kategori" => $row["kategori"],
        "kat_id"   => $kat_id,
        "harga"    => rentang_harga($row["harga_tiket"], $row["harga_maks"]),
        "jam"      => $row["jam_buka"] ?: "Jam buka belum tersedia",
        "status"   => status_buka($row["jam_buka"]),
        "skor"     => $row["jml_ulasan"] > 0 ? number_format((float) $row["rata"], 1) : "-",
        "rating"   => $row["jml_ulasan"] > 0 ? number_format((float) $row["rata"], 1) . " (" . (int) $row["jml_ulasan"] . ")" : "Belum ada ulasan",
        "foto"     => $foto_url,
        "lat"      => (float) $row["latitude"],
        "lng"      => (float) $row["longitude"],
        "url"      => BASE_URL . "destinasi/detail.php?id=" . (int) $row["id"],
    );
}

/* Sorotan dari halaman detail: peta.php?fokus=12 */
$fokus = (int) ($_GET["fokus"] ?? 0);
$fokus_nama = "";
foreach ($titik as $t) {
    if ($t["id"] === $fokus) { $fokus_nama = $t["nama"]; }
}

/* Tampilkan rute rencana sejak awal: peta.php?rute=1 */
$rute_awal = (isset($_GET["rute"]) && $_GET["rute"] !== "0");

// Ambil urutan rute rencana user jika sedang login
$rute_user = array();
$id_tersimpan = array();
if (sudah_login()) {
    $uid = (int) $_SESSION["user_id"];
    $st_r = mysqli_prepare(
        $conn,
        "SELECT d.id, d.nama, d.latitude, d.longitude, w.hari_ke
         FROM wishlists w
         JOIN destinations d ON d.id = w.destination_id
         WHERE w.user_id = ? AND d.latitude IS NOT NULL AND d.longitude IS NOT NULL
         ORDER BY w.hari_ke ASC, w.urutan ASC, w.id ASC"
    );
    if ($st_r) {
        mysqli_stmt_bind_param($st_r, "i", $uid);
        mysqli_stmt_execute($st_r);
        $res_r = mysqli_stmt_get_result($st_r);
        while ($rr = mysqli_fetch_assoc($res_r)) {
            $rute_user[] = array(
                "id"   => (int) $rr["id"],
                "nama" => $rr["nama"],
                "hari" => (int) $rr["hari_ke"],
                "lat"  => (float) $rr["latitude"],
                "lng"  => (float) $rr["longitude"],
            );
        }
        mysqli_stmt_close($st_r);
    }

    $st_t = mysqli_prepare($conn, "SELECT destination_id FROM wishlists WHERE user_id = ?");
    if ($st_t) {
        mysqli_stmt_bind_param($st_t, "i", $uid);
        mysqli_stmt_execute($st_t);
        foreach (mysqli_fetch_all(mysqli_stmt_get_result($st_t), MYSQLI_ASSOC) as $b_t) {
            $id_tersimpan[] = (int) $b_t["destination_id"];
        }
        mysqli_stmt_close($st_t);
    }
}
$csrf_peta = sudah_login() ? generate_csrf_token() : "";

require_once __DIR__ . "/includes/header.php";
?>
<link rel="stylesheet" type="text/css" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="peta-split">
    <aside class="peta-panel-kiri">
        <div class="peta-kontrol">
            <p class="eyebrow">Jelajah Peta</p>
            <h2>Peta Wisata Jogja</h2>
            <?php if ($fokus_nama !== "") { ?>
                <p class="peta-info-fokus">Menyorot: <strong><?php echo e($fokus_nama); ?></strong> &middot;
                    <a href="<?php echo BASE_URL; ?>peta.php">Tampilkan semua</a></p>
            <?php } ?>
            <input type="text" id="peta-cari" placeholder="Cari nama atau lokasi..." />
            <div class="peta-filter-kat" id="peta-filter-kat">
                <button type="button" class="chip aktif" data-kat="0">Semua</button>
                <?php foreach ($kategori_map as $kid => $knama) { ?>
                    <button type="button" class="chip" data-kat="<?php echo (int) $kid; ?>"><?php echo e($knama); ?></button>
                <?php } ?>
            </div>
            <?php if (sudah_login() && count($rute_user) > 0) { ?>
                <button type="button" class="tombol tombol-garis btn-rute-peta" id="btn-toggle-rute">
                    Tampilkan Rute Rencana Saya (<?php echo count($rute_user); ?>)
                </button>
            <?php } ?>
            <p class="kecil" id="peta-hitung">Menampilkan <?php echo count($titik); ?> destinasi</p>
        </div>
        <div class="peta-daftar" id="peta-daftar"></div>
    </aside>

    <div class="peta-kanvas-bungkus">
        <div id="peta-jogja"></div>
    </div>
</div>

<script type="text/javascript" src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script type="text/javascript">
//<![CDATA[
(function () {
    var data = <?php echo json_encode($titik, JSON_UNESCAPED_UNICODE); ?>;
    var ruteUser = <?php echo json_encode($rute_user, JSON_UNESCAPED_UNICODE); ?>;
    var idTersimpan = <?php echo json_encode($id_tersimpan, JSON_UNESCAPED_UNICODE); ?>;
    var login = <?php echo sudah_login() ? "true" : "false"; ?>;
    var csrfToken = <?php echo json_encode($csrf_peta, JSON_UNESCAPED_UNICODE); ?>;
    var endpointRencana = <?php echo json_encode(BASE_URL . "user/toggle_rencana.php", JSON_UNESCAPED_UNICODE); ?>;
    var fokusId = <?php echo (int) $fokus; ?>;
    var ruteAktif = <?php echo ($rute_awal && count($rute_user) > 0) ? "true" : "false"; ?>;

    var petaEl = document.getElementById("peta-jogja");
    if (!petaEl || typeof L === "undefined") { return; }

    var peta = L.map("peta-jogja").setView([-7.8014, 110.3648], 10);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 18,
        attribution: "&copy; OpenStreetMap"
    }).addTo(peta);

    var warnaKat = ["#493126", "#493126", "#263B50", "#3F6B3A", "#C5A15A", "#79513B", "#B88962"];
    var daftarEl = document.getElementById("peta-daftar");
    var hitungEl = document.getElementById("peta-hitung");
    var inputCari = document.getElementById("peta-cari");
    var filterKat = document.getElementById("peta-filter-kat");
    var btnRute = document.getElementById("btn-toggle-rute");

    var katAktif = 0;
    var markerMap = {};
    var layerPOI = L.layerGroup().addTo(peta);
    var layerRute = L.layerGroup().addTo(peta);

    function esc(s) {
        return String(s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function buatPopup(d) {
        var thumb = d.foto
            ? '<img class="poi-popup-foto" src="' + esc(d.foto) + '" alt="Foto ' + esc(d.nama) + '" width="310" height="135" />'
            : '<div class="poi-popup-foto poi-popup-inisial">' + esc(d.nama.charAt(0).toUpperCase()) + '</div>';
        var badge = d.status === "buka"
            ? '<span class="badge-buka">Buka</span>'
            : (d.status === "tutup" ? '<span class="badge-buka tutup">Tutup</span>' : "");
        var mapsUrl = "https://www.google.com/maps/dir/?api=1&destination=" + d.lat + "," + d.lng;

        var tombolRencana = "";
        if (login) {
            var sudah = idTersimpan.indexOf(d.id) !== -1;
            tombolRencana = '<button type="button" class="tombol btn-kecil-peta btn-rencana-toggle' + (sudah ? " tombol-garis" : "") + '"' +
                ' data-endpoint="' + esc(endpointRencana) + '" data-id="' + d.id + '" data-csrf="' + esc(csrfToken) + '">' +
                (sudah ? "&#10003; Tersimpan" : "+ Rencana") + "</button>";
        }

        return '<div class="poi-popup">' +
            thumb +
            '<div class="poi-popup-isi">' +
                '<div class="poi-popup-meta"><span class="label">' + esc(d.kategori) + '</span> ' + badge + '</div>' +
                '<h4>' + esc(d.nama) + '</h4>' +
                '<p class="kecil">' + esc(d.lokasi) + '</p>' +
                '<div class="poi-popup-info">&#9733; ' + esc(d.rating) + ' &middot; <strong>' + esc(d.harga) + '</strong><br /><span class="kecil">' + esc(d.jam) + '</span></div>' +
                '<div class="poi-popup-aksi">' +
                    '<a class="tombol btn-kecil-peta" href="' + esc(d.url) + '">Lihat Detail</a>' +
                    tombolRencana +
                    '<a class="tombol tombol-garis btn-kecil-peta" href="' + esc(mapsUrl) + '" target="_blank" rel="noopener noreferrer">Rute Maps</a>' +
                '</div>' +
            '</div>' +
        '</div>';
    }

    function render() {
        layerPOI.clearLayers();
        daftarEl.innerHTML = "";
        markerMap = {};
        var q = (inputCari ? inputCari.value : "").trim().toLowerCase();
        var grup = [];

        var urutanRute = {};
        if (ruteAktif) {
            ruteUser.forEach(function (r, idx) { urutanRute[r.id] = idx + 1; });
        }

        data.forEach(function (d) {
            if (katAktif > 0 && d.kat_id !== katAktif) { return; }
            if (q && (d.nama + " " + d.lokasi).toLowerCase().indexOf(q) === -1) { return; }

            var warna = warnaKat[d.kat_id % warnaKat.length];
            var nomorRute = urutanRute[d.id] ? '<span class="poi-urut">#' + urutanRute[d.id] + '</span>' : "";
            var ikon = L.divIcon({
                className: "poi-pin-wrap",
                html: '<div class="poi-pin" style="background:' + warna + ';">' +
                      nomorRute +
                      '<span class="poi-pin-huruf">' + esc(d.nama.charAt(0).toUpperCase()) + '</span>' +
                      '<span class="poi-pin-skor">&#9733; ' + esc(d.skor) + '</span>' +
                      '</div>',
                iconSize: [44, 44],
                iconAnchor: [22, 44],
                popupAnchor: [0, -40]
            });

            var marker = L.marker([d.lat, d.lng], { icon: ikon }).addTo(layerPOI);
            marker.bindPopup(function () { return buatPopup(d); }, { maxWidth: 330, minWidth: 290 });
            markerMap[d.id] = marker;
            grup.push(marker);

            var card = document.createElement("div");
            card.className = "peta-kartu";
            card.innerHTML = '<div class="peta-kartu-atas"><span class="label">' + esc(d.kategori) + '</span><span>&#9733; ' + esc(d.skor) + '</span></div>' +
                '<h3>' + esc(d.nama) + '</h3>' +
                '<p class="kecil">' + esc(d.lokasi) + '</p>' +
                '<p class="kecil"><strong>' + esc(d.harga) + '</strong></p>';
            card.addEventListener("click", function () {
                peta.flyTo([d.lat, d.lng], 14, { duration: 0.7 });
                marker.openPopup();
            });
            daftarEl.appendChild(card);
        });

        if (hitungEl) { hitungEl.textContent = "Menampilkan " + grup.length + " destinasi"; }
        if (grup.length > 0) {
            peta.fitBounds(L.featureGroup(grup).getBounds().pad(0.12));
        }
    }

    function renderRute() {
        layerRute.clearLayers();
        if (ruteAktif && ruteUser.length >= 2) {
            var coords = ruteUser.map(function (r) { return [r.lat, r.lng]; });
            L.polyline(coords, {
                color: "#C5A15A",
                weight: 4,
                dashArray: "8, 8",
                opacity: 0.9
            }).addTo(layerRute);
        }
        render();
    }

    if (inputCari) { inputCari.addEventListener("input", render); }
    if (filterKat) {
        filterKat.addEventListener("click", function (e) {
            var btn = e.target.closest ? e.target.closest("button[data-kat]") : null;
            if (!btn) { return; }
            katAktif = parseInt(btn.getAttribute("data-kat"), 10) || 0;
            Array.prototype.forEach.call(filterKat.querySelectorAll("button"), function (b) { b.classList.remove("aktif"); });
            btn.classList.add("aktif");
            render();
        });
    }

    function perbaruiTombolRute() {
        if (!btnRute) { return; }
        btnRute.classList.toggle("tombol-garis", !ruteAktif);
        btnRute.textContent = ruteAktif
            ? "Sembunyikan Rute Rencana"
            : "Tampilkan Rute Rencana Saya (" + ruteUser.length + ")";
    }

    if (btnRute) {
        btnRute.addEventListener("click", function () {
            ruteAktif = !ruteAktif;
            perbaruiTombolRute();
            renderRute();
        });
    }

    /* Sinkronkan penanda "+ Rencana" di popup setelah tombolnya diklik (main.js mengirim event). */
    document.addEventListener("dolan:rencana", function (e) {
        var info = e.detail || {};
        if (!info.id) { return; }
        var pos = idTersimpan.indexOf(info.id);
        if (info.saved && pos === -1) { idTersimpan.push(info.id); }
        if (!info.saved && pos !== -1) { idTersimpan.splice(pos, 1); }
    });

    if (ruteAktif) {
        perbaruiTombolRute();
        renderRute();
    } else {
        render();
    }

    /* Sorot satu destinasi saat datang dari halaman detail. */
    if (fokusId > 0) {
        var fokusData = null;
        for (var i = 0; i < data.length; i++) {
            if (data[i].id === fokusId) { fokusData = data[i]; }
        }
        if (fokusData && markerMap[fokusId]) {
            peta.setView([fokusData.lat, fokusData.lng], 15);
            markerMap[fokusId].openPopup();
        }
    }
})();
//]]>
</script>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
