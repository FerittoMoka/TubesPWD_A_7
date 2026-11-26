<?php
$judul = "Beranda";
$menu_aktif = "beranda";
require_once "config/koneksi.php";
require_once "includes/header.php";
require_once "includes/mood.php";
require_once "includes/jam_buka.php";

$cari = trim($_GET["cari"] ?? "");
$daftar_mood = daftar_mood();

// Dukung multi-filter dari URL (?kategori=1,2&mood=sunset,malam)
$kategori_pilih = array();
foreach (explode(",", (string) ($_GET["kategori"] ?? "")) as $kv) {
    $kid = (int) trim($kv);
    if ($kid > 0 && !in_array($kid, $kategori_pilih, true)) {
        $kategori_pilih[] = $kid;
    }
}

$mood_pilih = array();
foreach (explode(",", (string) ($_GET["mood"] ?? "")) as $mv) {
    $mk = trim($mv);
    if (isset($daftar_mood[$mk]) && !in_array($mk, $mood_pilih, true)) {
        $mood_pilih[] = $mk;
    }
}

$ada_filter = ($cari !== "" || count($kategori_pilih) > 0 || count($mood_pilih) > 0);

$jml_dest   = hitung_baris($conn, "destinations");
$jml_kat    = hitung_baris($conn, "categories");
$jml_ulasan = hitung_baris($conn, "reviews");

$daftar_kat = array();
$q = mysqli_query($conn, "SELECT c.id, c.nama, COUNT(d.id) AS jumlah FROM categories c LEFT JOIN destinations d ON d.category_id = c.id GROUP BY c.id, c.nama ORDER BY c.nama");
while ($k = mysqli_fetch_assoc($q)) {
    $daftar_kat[] = $k;
}

// Ambil seluruh destinasi agar filter sisi klien bekerja seketika tanpa reload
$sql = "SELECT d.id, d.category_id, d.nama, d.lokasi, d.harga_tiket, d.harga_maks, d.jam_buka, d.tag, d.latitude, d.longitude, d.foto, c.nama AS kategori,
        (SELECT AVG(r.rating) FROM reviews r WHERE r.destination_id = d.id) AS rata,
        (SELECT COUNT(*) FROM reviews r WHERE r.destination_id = d.id) AS jml_ulasan
        FROM destinations d JOIN categories c ON d.category_id = c.id
        ORDER BY d.created_at DESC, d.id DESC";
$hasil = mysqli_query($conn, $sql);

$semua = array();
while ($row = mysqli_fetch_assoc($hasil)) {
    $semua[] = $row;
}

// Pilihan pengunjung (3 destinasi rating/ulasan tertinggi)
$unggulan = array();
$id_unggulan = array();
if (count($semua) >= 3) {
    $urut = $semua;
    usort($urut, function ($a, $b) {
        $ra = (float) $a["rata"];
        $rb = (float) $b["rata"];
        if ($ra == $rb) {
            return $b["jml_ulasan"] - $a["jml_ulasan"];
        }
        return $rb <=> $ra;
    });
    $unggulan = array_slice($urut, 0, 3);
    $id_unggulan = array_column($unggulan, "id");
}

function beranda_jarak_km($lat1, $lng1, $lat2, $lng2) {
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2)
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
    return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$lokasi_user = $_SESSION["lokasi_user"] ?? null;
$urut_terdekat = (($_GET["urut"] ?? "") === "terdekat" && $lokasi_user !== null);
if ($urut_terdekat) {
    usort($semua, function ($a, $b) use ($lokasi_user) {
        $ja = ($a["latitude"] !== null && $a["longitude"] !== null)
            ? beranda_jarak_km($lokasi_user["lat"], $lokasi_user["lng"], (float) $a["latitude"], (float) $a["longitude"])
            : 99999;
        $jb = ($b["latitude"] !== null && $b["longitude"] !== null)
            ? beranda_jarak_km($lokasi_user["lat"], $lokasi_user["lng"], (float) $b["latitude"], (float) $b["longitude"])
            : 99999;
        return $ja <=> $jb;
    });
}

// Hitung kecocokan awal di server (untuk tampilan awal & fallback non-JS)
$cari_kecil = mb_strtolower($cari, "UTF-8");
$jumlah_cocok = 0;
$jumlah_terlihat = 0;
foreach ($semua as &$d) {
    $teks_cari = mb_strtolower($d["nama"] . " " . $d["lokasi"], "UTF-8");
    $cocok = true;
    if (count($kategori_pilih) > 0 && !in_array((int) $d["category_id"], $kategori_pilih, true)) {
        $cocok = false;
    }
    if ($cocok && count($mood_pilih) > 0) {
        $tags_row = ($d["tag"] ?? "") === "" ? array() : explode(",", $d["tag"]);
        $cocok = count(array_intersect($mood_pilih, $tags_row)) > 0;
    }
    if ($cocok && $cari_kecil !== "") {
        $cocok = (mb_strpos($teks_cari, $cari_kecil, 0, "UTF-8") !== false);
    }
    $adalah_unggulan = in_array($d["id"], $id_unggulan, true);
    $tampil = $cocok && ($ada_filter || !$adalah_unggulan);
    $d["_teks"] = $teks_cari;
    $d["_unggulan"] = $adalah_unggulan;
    $d["_tampil"] = $tampil;
        $d["_jarak"] = ($lokasi_user !== null && $d["latitude"] !== null && $d["longitude"] !== null)
        ? beranda_jarak_km($lokasi_user["lat"], $lokasi_user["lng"], (float) $d["latitude"], (float) $d["longitude"])
        : null;
    if ($cocok) { $jumlah_cocok++; }
    if ($tampil) { $jumlah_terlihat++; }
}
unset($d);

// Label filter aktif untuk judul hasil
$label_aktif = array();
foreach ($daftar_mood as $kunci => $label) {
    if (in_array($kunci, $mood_pilih, true)) { $label_aktif[] = $label; }
}
foreach ($daftar_kat as $k) {
    if (in_array((int) $k["id"], $kategori_pilih, true)) { $label_aktif[] = $k["nama"]; }
}
?>

<div class="hero">
    <div class="hero-isi">
        <p class="atas">Informasi Pariwisata Yogyakarta</p>
        <h1>Jogja lebih seru kalau tahu mau ke mana.</h1>
        <p class="pengantar">Cari destinasi, cek harga tiket dan jam buka, lalu baca ulasan pengunjung sebelum berangkat.</p>

        <form method="get" action="index.php#destinasi" class="form-cari" id="form-cari">
            <input type="text" id="cari-input" name="cari" value="<?php echo e($cari); ?>" placeholder="Cari nama atau lokasi" />
            <input type="hidden" id="in-mood" name="mood" value="<?php echo e(implode(",", $mood_pilih)); ?>" />
            <input type="hidden" id="in-kategori" name="kategori" value="<?php echo e(implode(",", $kategori_pilih)); ?>" />
            <button type="submit" class="tombol">Cari</button>
        </form>

        <div class="hero-angka">
            <div><strong><?php echo $jml_dest; ?></strong><span>destinasi</span></div>
            <div><strong><?php echo $jml_kat; ?></strong><span>kategori</span></div>
            <div><strong><?php echo $jml_ulasan; ?></strong><span>ulasan</span></div>
        </div>
    </div>
    <div class="gelombang"></div>
</div>

<div class="wadah">
    <div class="bagian" id="destinasi">
        <p class="eyebrow">Mulai dari sini</p>
        <h2>Mau cari yang seperti apa?</h2>
        <p class="muted">Berdasarkan suasana atau waktu kunjungan</p>
        <div class="chip-baris" id="mood-baris">
            <a class="chip<?php echo count($mood_pilih) === 0 ? ' aktif chip-terkunci' : ''; ?>" data-grup="mood" data-nilai="" href="index.php#destinasi">Semua</a>
            <?php foreach ($daftar_mood as $kunci => $label) {
                $aktif_mood = in_array($kunci, $mood_pilih, true);
            ?>
                <a class="chip<?php echo $aktif_mood ? ' aktif' : ''; ?>" data-grup="mood" data-nilai="<?php echo e($kunci); ?>" data-label="<?php echo e($label); ?>" href="index.php?mood=<?php echo urlencode($kunci); ?>#destinasi"><?php echo e($label); ?><span class="chip-x">&times;</span></a>
            <?php } ?>
        </div>

        <p class="muted">Atau berdasarkan kategori</p>
        <div class="chip-baris" id="kategori-baris">
            <a class="chip<?php echo count($kategori_pilih) === 0 ? ' aktif chip-terkunci' : ''; ?>" data-grup="kategori" data-nilai="" href="index.php#destinasi">Semua</a>
            <?php foreach ($daftar_kat as $k) {
                $aktif_kat = in_array((int) $k["id"], $kategori_pilih, true);
            ?>
                <a class="chip<?php echo $aktif_kat ? ' aktif' : ''; ?>" data-grup="kategori" data-nilai="<?php echo (int) $k["id"]; ?>" data-label="<?php echo e($k["nama"]); ?>" href="index.php?kategori=<?php echo (int) $k["id"]; ?>#destinasi"><?php echo e($k["nama"]); ?><small><?php echo (int) $k["jumlah"]; ?></small><span class="chip-x">&times;</span></a>
            <?php } ?>
        </div>
    </div>

    <?php if (count($unggulan) === 3) { $besar = $unggulan[0]; ?>
    <div class="bagian<?php echo $ada_filter ? ' sembunyi' : ''; ?>" id="bagian-unggulan">
        <p class="eyebrow">Paling disukai</p>
        <h2>Pilihan pengunjung</h2>
        <div class="unggulan">
            <div class="unggulan-besar kartu-klik">
                <?php echo tampil_foto($besar["foto"], $besar["kategori"], $besar["nama"]); ?>
                <div class="tutup"></div>
                <div class="unggulan-teks">
                    <span class="label"><?php echo e($besar["kategori"]); ?></span><?php echo badge_buka($besar["jam_buka"]); ?>
                    <h3><?php echo e($besar["nama"]); ?></h3>
                    <p><?php echo e($besar["lokasi"]); ?></p>
                    <p class="meta-baris"><?php echo tampil_rating($besar["rata"], $besar["jml_ulasan"]); ?><span><?php echo rentang_harga($besar["harga_tiket"], $besar["harga_maks"]); ?></span></p>
                    <a class="tombol" href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $besar["id"]; ?>">Lihat detail</a>
                </div>
            </div>

            <div class="unggulan-samping">
                <?php foreach (array_slice($unggulan, 1) as $s) { ?>
                    <div class="baris-kecil kartu-klik">
                        <?php echo tampil_foto($s["foto"], $s["kategori"], $s["nama"]); ?>
                        <div>
                            <span class="label"><?php echo e($s["kategori"]); ?></span><?php echo badge_buka($s["jam_buka"]); ?>
                            <h3><?php echo e($s["nama"]); ?></h3>
                            <p class="baris-meta"><?php echo e($s["lokasi"]); ?></p>
                            <p class="meta-baris"><?php echo tampil_rating($s["rata"], $s["jml_ulasan"]); ?><span><?php echo rentang_harga($s["harga_tiket"], $s["harga_maks"]); ?></span></p>
                            <a href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $s["id"]; ?>">Lihat detail</a>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php } ?>

    <div class="bagian">
        <p class="eyebrow" id="eyebrow-hasil"><?php echo $ada_filter ? "Hasil pencarian" : "Jelajahi"; ?></p>
        <h2 id="judul-hasil"><?php
            if ($ada_filter) {
                echo $jumlah_cocok . " destinasi ditemukan";
                if (count($label_aktif) > 0) {
                    echo " &middot; " . e(implode(" &middot; ", $label_aktif));
                }
            } else {
                echo "Semua destinasi";
            }
        ?></h2>

        <div id="daftar-hasil">
            <p class="kosong<?php echo ($ada_filter && $jumlah_cocok === 0) ? '' : ' sembunyi'; ?>" id="pesan-kosong">
                Belum ada destinasi yang cocok. <a href="index.php#destinasi" id="reset-filter">Tampilkan semua</a>
            </p>
            <div class="daftar<?php echo $jumlah_terlihat > 0 ? '' : ' sembunyi'; ?>">
                <?php foreach ($semua as $d) { ?>
                    <div class="baris kartu-klik<?php echo $d["_tampil"] ? '' : ' sembunyi'; ?>"
                         data-kategori="<?php echo (int) $d["category_id"]; ?>"
                         data-mood="<?php echo e($d["tag"] ?? ""); ?>"
                         data-teks="<?php echo e($d["_teks"]); ?>"<?php echo $d["_unggulan"] ? ' data-unggulan="1"' : ''; ?>>
                        <?php echo tampil_foto($d["foto"], $d["kategori"], $d["nama"]); ?>
                        <div class="baris-info">
                            <h3><?php echo e($d["nama"]); ?></h3>
                            <p class="baris-meta"><?php echo e($d["kategori"]); ?> &middot; <?php echo e($d["lokasi"]); ?><?php echo badge_buka($d["jam_buka"]); ?></p>
                            <?php echo tampil_rating($d["rata"], $d["jml_ulasan"]); ?>
                        </div>
                        <div class="baris-harga"><?php echo rentang_harga($d["harga_tiket"], $d["harga_maks"]); ?></div>
                        <a class="tombol tombol-garis" href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $d["id"]; ?>">Detail</a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="ajakan">
        <div>
            <h2>Lagi di Jogja sekarang?</h2>
            <p>Izinkan lokasimu, lalu lihat destinasi yang paling dekat.</p>
        </div>
        <a class="tombol" href="<?php echo BASE_URL; ?>destinasi/terdekat.php">Cari yang terdekat</a>
    </div>
</div>

<script type="text/javascript" src="<?php echo BASE_URL; ?>assets/js/filter_destinasi.js" defer="defer"></script>
<?php require_once "includes/footer.php"; ?>