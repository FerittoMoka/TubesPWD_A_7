<?php
$judul = "Beranda";
$menu_aktif = "beranda";
require_once "config/koneksi.php";
require_once "includes/header.php";
require_once "includes/mood.php";
require_once "includes/jam_buka.php";

/* Baca parameter GET bertipe teks. Mengembalikan null jika bukan teks (mis. ?kategori[]=1). */
function param_teks($nama) {
    $v = $_GET[$nama] ?? "";
    return is_string($v) ? trim($v) : null;
}

/* Buat URL filter sambil mempertahankan filter lain. $ubah: nama => nilai baru ("" atau 0 = hapus). */
function url_filter($ubah, $cari, $kategori, $mood) {
    $p = array();
    if ($cari !== "") { $p["cari"] = $cari; }
    if ($kategori > 0) { $p["kategori"] = $kategori; }
    if ($mood !== "") { $p["mood"] = $mood; }
    foreach ($ubah as $k => $v) {
        if ($v === "" || $v === 0) { unset($p[$k]); } else { $p[$k] = $v; }
    }
    $q = http_build_query($p, "", "&amp;");
    return "index.php" . ($q !== "" ? "?" . $q : "") . "#destinasi";
}

/* Satu chip filter.
   - tidak aktif            : tautan biasa untuk memilih.
   - aktif + $bisa_batal    : tautan yang membatalkan pilihan (klik lagi = batal), diberi tanda x.
   - aktif tanpa $bisa_batal: <span> terkunci (dipakai untuk chip "Semua", tidak ada yang perlu dibatalkan). */
function chip_filter($label, $aktif, $href, $jumlah = null, $bisa_batal = false) {
    $isi = e($label) . ($jumlah !== null ? "<small>" . (int) $jumlah . "</small>" : "");
    if ($aktif && $bisa_batal) {
        return '<a class="chip aktif" href="' . $href . '" title="Klik lagi untuk membatalkan pilihan">' . $isi . '<span class="chip-x">&times;</span></a>';
    }
    if ($aktif) {
        return '<span class="chip aktif chip-terkunci">' . $isi . '</span>';
    }
    return '<a class="chip" href="' . $href . '">' . $isi . '</a>';
}

$pesan_filter = array();
$gagal_muat = false;

/* ---------- Validasi parameter ---------- */
$cari = param_teks("cari");
if ($cari === null) {
    $pesan_filter[] = "Format kata kunci pencarian tidak sesuai, jadi diabaikan.";
    $cari = "";
}
$cari = mb_substr($cari, 0, 100, "UTF-8");

$daftar_mood = daftar_mood();
$mood = param_teks("mood");
if ($mood === null || ($mood !== "" && !preg_match('/^[a-z-]{1,30}$/', $mood))) {
    $pesan_filter[] = "Parameter suasana tidak sesuai format, jadi diabaikan.";
    $mood = "";
} elseif ($mood !== "" && !isset($daftar_mood[$mood])) {
    $pesan_filter[] = "Suasana yang dipilih tidak ditemukan, jadi ditampilkan semua suasana.";
    $mood = "";
}

// Kategori: format dicek di sini, keberadaannya dicek setelah daftar kategori dimuat
$kat_raw = param_teks("kategori");
$kategori = 0;
if ($kat_raw === null || ($kat_raw !== "" && !ctype_digit($kat_raw))) {
    $pesan_filter[] = "Parameter kategori tidak sesuai format, jadi diabaikan.";
} else {
    $kategori = (int) $kat_raw;
}

/* ---------- Muat data (aman dari error database) ---------- */
$jml_dest = 0;
$jml_kat = 0;
$jml_ulasan = 0;
$daftar_kat = array();
$semua = array();

try {
    $jml_dest   = hitung_baris($conn, "destinations");
    $jml_kat    = hitung_baris($conn, "categories");
    $jml_ulasan = hitung_baris($conn, "reviews");

    $q = mysqli_query($conn, "SELECT c.id, c.nama, COUNT(d.id) AS jumlah FROM categories c LEFT JOIN destinations d ON d.category_id = c.id GROUP BY c.id, c.nama ORDER BY c.nama");
    if (!$q) {
        throw new Exception(mysqli_error($conn));
    }
    while ($k = mysqli_fetch_assoc($q)) {
        $daftar_kat[] = $k;
    }

    if ($kategori > 0) {
        $ada_kat = false;
        foreach ($daftar_kat as $k) {
            if ((int) $k["id"] === $kategori) { $ada_kat = true; }
        }
        if (!$ada_kat) {
            $pesan_filter[] = "Kategori yang dipilih tidak ditemukan, jadi ditampilkan semua kategori.";
            $kategori = 0;
        }
    }

    $sql = "SELECT d.id, d.nama, d.lokasi, d.harga_tiket, d.harga_maks, d.jam_buka, d.foto, c.nama AS kategori,
            (SELECT AVG(r.rating) FROM reviews r WHERE r.destination_id = d.id) AS rata,
            (SELECT COUNT(*) FROM reviews r WHERE r.destination_id = d.id) AS jml_ulasan
            FROM destinations d JOIN categories c ON d.category_id = c.id
            WHERE (d.nama LIKE ? OR d.lokasi LIKE ?)";
    $like = "%" . $cari . "%";
    $tipe = "ss";
    $param = array($like, $like);

    if ($kategori > 0) {
        $sql .= " AND d.category_id = ?";
        $tipe .= "i";
        $param[] = $kategori;
    }
    if ($mood !== "") {
        $sql .= " AND FIND_IN_SET(?, d.tag) > 0";
        $tipe .= "s";
        $param[] = $mood;
    }
    $sql .= " ORDER BY d.created_at DESC, d.id DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, $tipe, ...$param);
    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($hasil)) {
        $semua[] = $row;
    }
    mysqli_stmt_close($stmt);
} catch (Throwable $ex) {
    $gagal_muat = true;
    $semua = array();
    error_log("Dolan Jogja (index.php): " . $ex->getMessage());
}

$ada_filter = ($cari !== "" || $kategori > 0 || $mood !== "");

// Nama filter aktif untuk judul hasil
$label_filter = array();
if ($mood !== "") { $label_filter[] = e($daftar_mood[$mood]); }
if ($kategori > 0) {
    foreach ($daftar_kat as $k) {
        if ((int) $k["id"] === $kategori) { $label_filter[] = e($k["nama"]); }
    }
}

$unggulan = array();
$lainnya = $semua;
if (!$ada_filter && count($semua) >= 3) {
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
    $lainnya = array_values(array_filter($semua, function ($x) use ($id_unggulan) {
        return !in_array($x["id"], $id_unggulan);
    }));
}
?>

<div class="hero">
    <div class="hero-isi">
        <p class="atas">Informasi Pariwisata Yogyakarta</p>
        <h1>Jogja lebih seru kalau tahu mau ke mana.</h1>
        <p class="pengantar">Cari destinasi, cek harga tiket dan jam buka, lalu baca ulasan pengunjung sebelum berangkat.</p>

        <form method="get" action="index.php#destinasi" class="form-cari">
            <input type="text" name="cari" value="<?php echo e($cari); ?>" placeholder="Cari nama atau lokasi" maxlength="100" />
            <select name="kategori">
                <option value="0">Semua kategori</option>
                <?php foreach ($daftar_kat as $k) { ?>
                    <option value="<?php echo (int) $k["id"]; ?>"<?php if ($kategori === (int) $k["id"]) { echo ' selected="selected"'; } ?>><?php echo e($k["nama"]); ?></option>
                <?php } ?>
            </select>
            <?php if ($mood !== "") { ?><input type="hidden" name="mood" value="<?php echo e($mood); ?>" /><?php } ?>
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

        <?php foreach ($pesan_filter as $pf) { ?>
            <p class="error"><?php echo e($pf); ?></p>
        <?php } ?>
        <?php if ($gagal_muat) { ?>
            <p class="error">Data belum dapat dimuat saat ini. Silakan muat ulang halaman beberapa saat lagi.</p>
        <?php } ?>

        <p class="muted">Berdasarkan suasana atau waktu kunjungan</p>
        <?php if (count($daftar_mood) === 0) { ?>
            <p class="kecil">Belum ada pilihan suasana yang tersedia.</p>
        <?php } else { ?>
            <div class="chip-baris" id="mood-baris">
                <?php echo chip_filter("Semua suasana", $mood === "", url_filter(array("mood" => ""), $cari, $kategori, $mood)); ?>
                <?php foreach ($daftar_mood as $kunci => $label) { ?>
                    <?php $dipilih = ($mood === $kunci); ?>
                    <?php echo chip_filter($label, $dipilih, url_filter(array("mood" => $dipilih ? "" : $kunci), $cari, $kategori, $mood), null, true); ?>
                <?php } ?>
            </div>
        <?php } ?>

        <p class="muted">Atau berdasarkan kategori</p>
        <?php if (count($daftar_kat) === 0) { ?>
            <?php if (!$gagal_muat) { ?><p class="kecil">Belum ada kategori yang tersedia.</p><?php } ?>
        <?php } else { ?>
            <div class="chip-baris" id="kategori-baris">
                <?php echo chip_filter("Semua", $kategori === 0, url_filter(array("kategori" => ""), $cari, $kategori, $mood)); ?>
                <?php foreach ($daftar_kat as $k) { ?>
                    <?php $dipilih = ($kategori === (int) $k["id"]); ?>
                    <?php echo chip_filter($k["nama"], $dipilih, url_filter(array("kategori" => $dipilih ? 0 : (int) $k["id"]), $cari, $kategori, $mood), $k["jumlah"], true); ?>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <div id="area-hasil">
    <div class="skeleton-hasil">
        <div class="skeleton-baris"></div>
        <div class="skeleton-baris"></div>
        <div class="skeleton-baris"></div>
    </div>

    <?php if (count($unggulan) === 3) { $besar = $unggulan[0]; ?>
    <div class="bagian">
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
                    <a class="tombol" href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo $besar["id"]; ?>">Lihat detail</a>
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
                            <a href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo $s["id"]; ?>">Lihat detail</a>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php } ?>

    <div class="bagian">
        <?php if ($ada_filter) { ?>
            <p class="eyebrow">Hasil pencarian</p>
            <h2><?php echo count($semua); ?> destinasi ditemukan<?php if (count($label_filter) > 0) { echo " &middot; " . implode(" &middot; ", $label_filter); } ?></h2>
        <?php } else { ?>
            <p class="eyebrow">Jelajahi</p>
            <h2>Semua destinasi</h2>
        <?php } ?>

        <div id="daftar-hasil">
        <?php if ($gagal_muat) { ?>
            <p class="kosong">Destinasi belum dapat ditampilkan saat ini. <a href="index.php#destinasi">Coba muat ulang</a></p>
        <?php } elseif (count($semua) === 0) { ?>
            <?php if ($ada_filter) { ?>
                <p class="kosong">Tidak ada destinasi yang sesuai dengan filter yang dipilih. <a href="index.php#destinasi">Hapus semua filter</a></p>
            <?php } else { ?>
                <p class="kosong">Belum ada destinasi yang tersedia.</p>
            <?php } ?>
        <?php } elseif (count($lainnya) > 0) { ?>
            <div class="daftar">
                <?php foreach ($lainnya as $d) { ?>
                    <div class="baris kartu-klik">
                        <?php echo tampil_foto($d["foto"], $d["kategori"], $d["nama"]); ?>
                        <div class="baris-info">
                            <h3><?php echo e($d["nama"]); ?></h3>
                            <p class="baris-meta"><?php echo e($d["kategori"]); ?> &middot; <?php echo e($d["lokasi"]); ?><?php echo badge_buka($d["jam_buka"]); ?></p>
                            <?php echo tampil_rating($d["rata"], $d["jml_ulasan"]); ?>
                        </div>
                        <div class="baris-harga"><?php echo rentang_harga($d["harga_tiket"], $d["harga_maks"]); ?></div>
                        <a class="tombol tombol-garis" href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo $d["id"]; ?>">Detail</a>
                    </div>
                <?php } ?>
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

<script type="text/javascript">
//<![CDATA[
// Indikator memuat singkat saat berpindah filter (halaman dimuat ulang oleh server).
(function () {
    var area = document.getElementById("area-hasil");
    if (!area) { return; }
    function mulai() { area.classList.add("memuat"); }
    document.addEventListener("click", function (e) {
        var a = e.target.closest ? e.target.closest("a.chip") : null;
        if (a && !e.ctrlKey && !e.metaKey && !e.shiftKey) { mulai(); }
    });
    var form = document.querySelector(".form-cari");
    if (form) { form.addEventListener("submit", mulai); }
    // tombol "kembali" browser: pastikan indikator tidak tertinggal
    window.addEventListener("pageshow", function () { area.classList.remove("memuat"); });
})();
//]]>
</script>

<?php require_once "includes/footer.php"; ?>