<?php
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/jam_buka.php";

$judul = "Peta Destinasi";
$menu_aktif = "peta";

$sql = "SELECT d.id, d.nama, d.lokasi, d.harga_tiket, d.harga_maks, d.jam_buka, d.latitude, d.longitude,
        c.id AS category_id, c.nama AS kategori,
        (SELECT AVG(r.rating) FROM reviews r WHERE r.destination_id = d.id) AS rata,
        (SELECT COUNT(*) FROM reviews r WHERE r.destination_id = d.id) AS jml_ulasan
        FROM destinations d
        JOIN categories c ON d.category_id = c.id
        WHERE d.latitude IS NOT NULL AND d.longitude IS NOT NULL
        ORDER BY d.nama ASC";
$res = mysqli_query($conn, $sql);
$titik = array();
while ($row = mysqli_fetch_assoc($res)) {
    $titik[] = array(
        "id"       => (int) $row["id"],
        "nama"     => $row["nama"],
        "lokasi"   => $row["lokasi"],
        "kategori" => $row["kategori"],
        "kat_id"   => (int) $row["category_id"],
        "harga"    => rentang_harga($row["harga_tiket"], $row["harga_maks"]),
        "status"   => status_buka($row["jam_buka"]),
        "rating"   => $row["jml_ulasan"] > 0 ? number_format((float) $row["rata"], 1) . " (" . (int) $row["jml_ulasan"] . ")" : "Belum ada ulasan",
        "lat"      => (float) $row["latitude"],
        "lng"      => (float) $row["longitude"],
        "url"      => BASE_URL . "destinasi/detail.php?id=" . (int) $row["id"],
    );
}

require_once __DIR__ . "/includes/header.php";
?>
<link rel="stylesheet" type="text/css" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="wadah">
    <div class="halaman-isi">
        <p class="eyebrow">Jelajah Peta</p>
        <h2>Peta Interaktif Wisata Yogyakarta</h2>
        <p class="muted">Klik penanda pada peta untuk melihat info singkat, rating, harga tiket, dan tautan detail destinasi.</p>

        <div class="panel">
            <div id="peta-jogja" style="width:100%;height:500px;border-radius:12px;"></div>
            <p class="kecil" style="margin-top:10px;margin-bottom:0;">Menampilkan <?php echo count($titik); ?> destinasi berkoordinat. Membutuhkan koneksi internet untuk memuat ubin peta OpenStreetMap.</p>
        </div>
    </div>
</div>

<script type="text/javascript" src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script type="text/javascript">
//<![CDATA[
(function () {
    var data = <?php echo json_encode($titik, JSON_UNESCAPED_UNICODE); ?>;
    var petaEl = document.getElementById("peta-jogja");
    if (!petaEl || typeof L === "undefined") { return; }

    var peta = L.map("peta-jogja").setView([-7.8014, 110.3648], 10);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 18,
        attribution: "&copy; OpenStreetMap"
    }).addTo(peta);

    var warnaKat = ["#493126", "#263B50", "#3F6B3A", "#C5A15A", "#79513B", "#B88962"];
    var grup = [];

    function esc(s) {
        return String(s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    data.forEach(function (d) {
        var warna = warnaKat[d.kat_id % warnaKat.length];
        var marker = L.circleMarker([d.lat, d.lng], {
            radius: 9,
            fillColor: warna,
            color: "#FFFCF6",
            weight: 2,
            opacity: 1,
            fillOpacity: 0.92
        }).addTo(peta);

        var badge = d.status === "buka" ? " · Buka sekarang" : (d.status === "tutup" ? " · Tutup" : "");
        var html = '<div style="min-width:190px;font-family:inherit;">' +
            '<span style="font-size:11px;font-weight:700;text-transform:uppercase;color:#79513B;">' + esc(d.kategori) + esc(badge) + '</span><br />' +
            '<strong style="font-size:15px;color:#493126;">' + esc(d.nama) + '</strong><br />' +
            '<span style="font-size:12px;color:#79513B;">' + esc(d.lokasi) + '</span><br />' +
            '<div style="margin:6px 0;font-size:12px;">&#9733; ' + esc(d.rating) + ' &middot; <strong>' + esc(d.harga) + '</strong></div>' +
            '<a href="' + esc(d.url) + '" style="font-weight:600;color:#493126;">Lihat detail &rarr;</a>' +
            '</div>';

        marker.bindPopup(html);
        grup.push(marker);
    });

    if (grup.length > 0) {
        peta.fitBounds(L.featureGroup(grup).getBounds().pad(0.12));
    }
})();
//]]>
</script>
<?php require_once __DIR__ . "/includes/footer.php"; ?>