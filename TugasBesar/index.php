<?php
$judul = "Beranda";
$menu_aktif = "beranda";
require_once "config/koneksi.php";
require_once "includes/header.php";
require_once "includes/jam_buka.php";

$cari = trim($_GET["cari"] ?? "");
$kategori = (int) ($_GET["kategori"] ?? 0);
$harga = $_GET["harga"] ?? "";
if (!in_array($harga, array("gratis", "25000", "50000"), true)) { $harga = ""; }
$ada_filter = ($cari !== "" || $kategori > 0 || $harga !== "");

$jml_dest   = hitung_baris($conn, "destinations");
$jml_kat    = hitung_baris($conn, "categories");
$jml_ulasan = hitung_baris($conn, "reviews");

$daftar_kat = [];
$q = mysqli_query($conn, "SELECT c.id, c.nama, COUNT(d.id) AS jumlah FROM categories c LEFT JOIN destinations d ON d.category_id = c.id GROUP BY c.id, c.nama ORDER BY c.nama");
while ($k = mysqli_fetch_assoc($q)) {
    $daftar_kat[] = $k;
}

/* ---------- Daftar destinasi (dengan filter) ---------- */
$like  = "%" . $cari . "%";
$where = array("(d.nama LIKE ? OR d.lokasi LIKE ?)");
$tipe  = "ss";
$par   = array($like, $like);
if ($kategori > 0) { $where[] = "d.category_id = ?"; $tipe .= "i"; $par[] = $kategori; }
if ($harga === "gratis") {
    $where[] = "d.harga_tiket = 0";
} elseif ($harga !== "") {
    $where[] = "d.harga_tiket <= ?"; $tipe .= "i"; $par[] = (int) $harga;
}

$sql = "SELECT d.id, d.nama, d.lokasi, d.harga_tiket, d.harga_maks, d.jam_buka, d.foto, c.nama AS kategori,
        (SELECT AVG(r.rating) FROM reviews r WHERE r.destination_id = d.id) AS rata,
        (SELECT COUNT(*) FROM reviews r WHERE r.destination_id = d.id) AS jml_ulasan
        FROM destinations d JOIN categories c ON d.category_id = c.id
        WHERE " . implode(" AND ", $where) . " ORDER BY d.created_at DESC, d.id DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $tipe, ...$par);
mysqli_stmt_execute($stmt);
$semua = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

/* ---------- Terfavorit: rating tertinggi (hanya tanpa filter) ---------- */
$favorit = array();
if (!$ada_filter && count($semua) >= 4) {
    $urut = $semua;
    usort($urut, function ($a, $b) {
        $ra = (float) $a["rata"];
        $rb = (float) $b["rata"];
        if ($ra == $rb) { return $b["jml_ulasan"] - $a["jml_ulasan"]; }
        return $rb <=> $ra;
    });
    $favorit = array_slice($urut, 0, 4);
}

/* ---------- Ulasan terbaru untuk bagian testimoni ---------- */
$testimoni = array();
if (!$ada_filter) {
    $qt = mysqli_query($conn, "SELECT r.rating, r.komentar, u.nama_lengkap, d.nama AS destinasi
        FROM reviews r JOIN users u ON u.id = r.user_id JOIN destinations d ON d.id = r.destination_id
        WHERE r.komentar IS NOT NULL AND r.komentar <> ''
        ORDER BY r.created_at DESC, r.id DESC LIMIT 3");
    $testimoni = mysqli_fetch_all($qt, MYSQLI_ASSOC);
}

/* ---------- Komponen kartu destinasi ---------- */
function kartu_dest($d, $overlay = false) {
    $url = BASE_URL . "destinasi/detail.php?id=" . (int) $d["id"];
    ?>
    <div class="kartu-dest<?php echo $overlay ? ' kartu-overlay' : ''; ?> kartu-klik">
        <div class="kartu-foto">
            <?php echo tampil_foto($d["foto"], $d["kategori"], $d["nama"]); ?>
            <span class="kartu-chip"><?php echo e($d["kategori"]); ?></span>
            <?php if ($overlay) { ?>
                <div class="kartu-tutup"></div>
                <div class="kartu-teks">
                    <h3><a href="<?php echo $url; ?>"><?php echo e($d["nama"]); ?></a></h3>
                    <p class="kartu-lokasi"><?php echo e($d["lokasi"]); ?></p>
                    <div class="kartu-bawah">
                        <?php echo tampil_rating($d["rata"], $d["jml_ulasan"]); ?>
                        <span class="kartu-harga"><?php echo rentang_harga($d["harga_tiket"], $d["harga_maks"]); ?></span>
                    </div>
                </div>
            <?php } ?>
        </div>
        <?php if (!$overlay) { ?>
            <div class="kartu-isi">
                <h3><a href="<?php echo $url; ?>"><?php echo e($d["nama"]); ?></a></h3>
                <p class="kartu-lokasi"><?php echo e($d["lokasi"]); ?><?php echo badge_buka($d["jam_buka"]); ?></p>
                <div class="kartu-bawah">
                    <?php echo tampil_rating($d["rata"], $d["jml_ulasan"]); ?>
                    <span class="kartu-harga"><?php echo rentang_harga($d["harga_tiket"], $d["harga_maks"]); ?></span>
                </div>
            </div>
        <?php } ?>
    </div>
    <?php
}
?>

<div class="hero hero-foto">
    <div class="hero-isi">
        <span class="hero-chip">Jelajahi <?php echo $jml_dest; ?> destinasi di Yogyakarta</span>
        <h1>Petualangan Jogja-mu<br /><span class="sorot">Dimulai dari Sini</span></h1>
        <p class="pengantar">Cari destinasi, cek harga tiket dan jam buka, lalu baca ulasan pengunjung sebelum berangkat.</p>

        <div class="hero-fitur">
            <div class="fitur-item"><span class="fitur-ikon">Rp</span><div><strong>Harga tiket jelas</strong><span>Rentang harga tiap destinasi</span></div></div>
            <div class="fitur-item"><span class="fitur-ikon">24</span><div><strong>Status buka</strong><span>Lihat yang sedang buka sekarang</span></div></div>
            <div class="fitur-item"><span class="fitur-ikon">&#9733;</span><div><strong>Ulasan pengunjung</strong><span>Rating dan komentar asli</span></div></div>
        </div>

        <form method="get" action="index.php#destinasi" class="cari-kartu">
            <div class="cari-baris">
                <div class="cari-kolom">
                    <label for="cari">Mau ke mana?</label>
                    <input type="text" id="cari" name="cari" value="<?php echo e($cari); ?>" placeholder="Nama atau lokasi destinasi" />
                </div>
                <div class="cari-kolom">
                    <label for="kategori">Kategori</label>
                    <select id="kategori" name="kategori">
                        <option value="0">Semua kategori</option>
                        <?php foreach ($daftar_kat as $k) { ?>
                            <option value="<?php echo $k["id"]; ?>"<?php if ($kategori === (int) $k["id"]) { echo ' selected="selected"'; } ?>><?php echo e($k["nama"]); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="cari-kolom">
                    <label for="harga">Harga tiket</label>
                    <select id="harga" name="harga">
                        <option value="">Semua harga</option>
                        <option value="gratis"<?php if ($harga === "gratis") { echo ' selected="selected"'; } ?>>Gratis</option>
                        <option value="25000"<?php if ($harga === "25000") { echo ' selected="selected"'; } ?>>Sampai Rp 25.000</option>
                        <option value="50000"<?php if ($harga === "50000") { echo ' selected="selected"'; } ?>>Sampai Rp 50.000</option>
                    </select>
                </div>
                <button type="submit" class="tombol">Cari</button>
            </div>
        </form>
    </div>
</div>

<?php if (count($favorit) === 4) { ?>
<div class="wadah">
    <div class="bagian">
        <div class="judul-baris">
            <div>
                <p class="eyebrow">Destinasi populer</p>
                <h2>Paling disukai pengunjung</h2>
            </div>
            <a class="tautan-semua" href="index.php#destinasi">Lihat semua &rarr;</a>
        </div>
        <div class="grid-favorit">
            <?php foreach ($favorit as $f) { kartu_dest($f, true); } ?>
        </div>
    </div>
</div>

<div class="band-kenapa">
    <div class="wadah">
        <div class="kenapa">
            <div>
                <p class="eyebrow">Kenapa Dolan Jogja</p>
                <h2>Lebih dari sekadar daftar tempat wisata</h2>
                <p>Semua informasi untuk merencanakan perjalanan ada di satu tempat: harga tiket, jam buka, ulasan, dan rencana per hari.</p>
                <p><a class="tombol" href="<?php echo BASE_URL . (sudah_login() ? 'user/rencana.php' : 'auth/login.php'); ?>">Mulai merencanakan</a></p>
                <div class="kenapa-angka">
                    <div><strong><?php echo $jml_dest; ?></strong><span>destinasi</span></div>
                    <div><strong><?php echo $jml_kat; ?></strong><span>kategori</span></div>
                    <div><strong><?php echo $jml_ulasan; ?></strong><span>ulasan</span></div>
                </div>
            </div>
            <div class="kenapa-grid">
                <div class="kenapa-tile"><span class="ikon">Rp</span><h3>Harga tiket jelas</h3><p>Rentang harga tiap destinasi, termasuk yang gratis.</p></div>
                <div class="kenapa-tile"><span class="ikon">24</span><h3>Status buka</h3><p>Lihat langsung apakah tempatnya sedang buka atau tutup.</p></div>
                <div class="kenapa-tile"><span class="ikon">&#9733;</span><h3>Ulasan pengunjung</h3><p>Rating dan komentar dari orang yang sudah berkunjung.</p></div>
                <div class="kenapa-tile"><span class="ikon">+</span><h3>Rencana perjalanan</h3><p>Susun per hari dan hitung estimasi total tiket.</p></div>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<div class="wadah">
    <div class="bagian" id="destinasi">
        <div class="judul-baris">
            <div>
                <?php if ($ada_filter) { ?>
                    <p class="eyebrow">Hasil pencarian</p>
                    <h2><?php echo count($semua); ?> destinasi ditemukan</h2>
                <?php } else { ?>
                    <p class="eyebrow">Jelajahi</p>
                    <h2>Semua destinasi</h2>
                <?php } ?>
            </div>
        </div>
        <div class="chip-baris">
            <a class="chip<?php echo ($kategori === 0) ? ' aktif' : ''; ?>" href="index.php#destinasi">Semua</a>
            <?php foreach ($daftar_kat as $k) { ?>
                <a class="chip<?php echo $kategori === (int) $k["id"] ? ' aktif' : ''; ?>" href="index.php?kategori=<?php echo $k["id"]; ?>#destinasi"><?php echo e($k["nama"]); ?><small><?php echo (int) $k["jumlah"]; ?></small></a>
            <?php } ?>
        </div>

        <div id="daftar-hasil">
        <?php if (count($semua) === 0) { ?>
            <p class="kosong">Belum ada destinasi yang cocok. <a href="index.php#destinasi">Tampilkan semua</a></p>
        <?php } else { ?>
            <div class="grid-kartu">
                <?php foreach ($semua as $d) { kartu_dest($d); } ?>
            </div>
        <?php } ?>
        </div>
    </div>

    <?php if (count($testimoni) > 0) { ?>
    <div class="bagian">
        <p class="eyebrow">Testimoni</p>
        <h2>Kata para pengunjung</h2>
        <div class="grid-testi">
            <?php foreach ($testimoni as $t) { ?>
                <div class="testi">
                    <p class="kutipan">&ldquo;<?php echo e($t["komentar"]); ?>&rdquo;</p>
                    <div class="testi-orang">
                        <span class="avatar"><?php echo e(mb_strtoupper(mb_substr($t["nama_lengkap"], 0, 1))); ?></span>
                        <div>
                            <strong><?php echo e($t["nama_lengkap"]); ?></strong>
                            <span class="kecil"><?php echo e($t["destinasi"]); ?> &middot; <?php echo (int) $t["rating"]; ?>/5</span>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

    <div class="ajakan ajakan-banner">
        <div>
            <h2>Lagi di Jogja sekarang?</h2>
            <p>Izinkan lokasimu, lalu lihat destinasi yang paling dekat.</p>
        </div>
        <a class="tombol" href="<?php echo BASE_URL; ?>destinasi/terdekat.php">Cari yang terdekat</a>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
