<?php
$judul = "Beranda";
$menu_aktif = "beranda";
require_once "config/koneksi.php";
require_once "includes/header.php";
require_once "includes/mood.php";
require_once "includes/jam_buka.php";
require_once "includes/pesan.php";

/* Baca parameter berisi daftar nilai dipisah koma (mis. ?mood=sunset,malam).
   Mengembalikan array, atau null jika formatnya bukan teks (mis. ?mood[]=a). */
function param_daftar($nama) {
    $v = $_GET[$nama] ?? "";
    if (!is_string($v)) { return null; }
    $item = array();
    foreach (explode(",", trim($v)) as $x) {
        $x = trim($x);
        if ($x !== "" && !in_array($x, $item, true)) { $item[] = $x; }
    }
    return array_slice($item, 0, 30);
}

/* Tambah nilai ke daftar jika belum ada, hapus jika sudah ada (dipakai untuk tautan chip tanpa JavaScript). */
function ganti_nilai($list, $nilai) {
    $i = array_search($nilai, $list, true);
    if ($i === false) { $list[] = $nilai; } else { unset($list[$i]); }
    return array_values($list);
}

function hitung_sql($conn, $sql) {
    $q = mysqli_query($conn, $sql);
    if (!$q) { throw new Exception(mysqli_error($conn)); }
    $r = mysqli_fetch_row($q);
    return (int) $r[0];
}

function url_beranda($cari, $mood_list, $kat_list, $wil_list) {
    $p = array();
    if ($cari !== "") { $p[] = "cari=" . rawurlencode($cari); }
    if (count($kat_list) > 0) { $p[] = "kategori=" . implode(",", $kat_list); }
    if (count($wil_list) > 0) { $p[] = "wilayah=" . implode(",", $wil_list); }
    if (count($mood_list) > 0) { $p[] = "mood=" . implode(",", $mood_list); }
    return "index.php" . (count($p) > 0 ? "?" . implode("&amp;", $p) : "") . "#destinasi";
}

/* Chip filter (bisa dipilih lebih dari satu; klik lagi untuk membatalkan). $nilai "" = chip "Semua". */
function chip_filter($label, $grup, $nilai, $aktif, $href, $jumlah = null) {
    $kelas = "chip" . ($aktif ? " aktif" : "") . (($aktif && $nilai === "") ? " chip-terkunci" : "");
    $judul = ($aktif && $nilai !== "") ? ' title="Klik lagi untuk membatalkan pilihan"' : "";
    $isi = e($label)
         . ($jumlah !== null ? "<small>" . (int) $jumlah . "</small>" : "")
         . ($nilai !== "" ? '<span class="chip-x">&times;</span>' : "");
    return '<a class="' . $kelas . '" href="' . $href . '" data-grup="' . $grup . '" data-nilai="' . e($nilai) . '" data-label="' . e($label) . '"' . $judul . '>' . $isi . '</a>';
}

/* Aturan filter: suasana = salah satu cocok (ATAU), kategori = salah satu cocok (ATAU),
   antar kelompok (suasana, kategori, kata kunci) = semua harus terpenuhi (DAN). */
function cocok_filter($d, $mood_list, $kat_list, $wil_list, $cari) {
    if (count($kat_list) > 0 && !in_array((int) $d["category_id"], $kat_list, true)) { return false; }
    if (count($wil_list) > 0 && !in_array(slug_wilayah($d["wilayah"] ?? ""), $wil_list, true)) { return false; }
    if (count($mood_list) > 0) {
        $tag = (string) ($d["tag"] ?? "");
        $tags = $tag === "" ? array() : explode(",", $tag);
        if (count(array_intersect($mood_list, $tags)) === 0) { return false; }
    }
    if ($cari !== "") {
        $teks = mb_strtolower($d["nama"] . " " . $d["lokasi"], "UTF-8");
        if (mb_strpos($teks, mb_strtolower($cari, "UTF-8"), 0, "UTF-8") === false) { return false; }
    }
    return true;
}

$pesan_filter = array();
$gagal_muat = false;

/* ---------- Validasi parameter ---------- */
$cari_raw = $_GET["cari"] ?? "";
if (!is_string($cari_raw)) {
    $pesan_filter[] = "Format kata kunci pencarian tidak sesuai, jadi diabaikan.";
    $cari_raw = "";
}
$cari = mb_substr(trim($cari_raw), 0, 100, "UTF-8");

$daftar_mood = daftar_mood();
$mood_list = array();
$mood_raw = param_daftar("mood");
if ($mood_raw === null) {
    $pesan_filter[] = "Parameter suasana tidak sesuai format, jadi diabaikan.";
} else {
    $abaikan = 0;
    foreach ($mood_raw as $m) {
        if (isset($daftar_mood[$m])) { $mood_list[] = $m; } else { $abaikan++; }
    }
    if ($abaikan > 0) { $pesan_filter[] = $abaikan . " suasana pada alamat tidak ditemukan, jadi diabaikan."; }
}

$kat_raw = param_daftar("kategori");
$kat_list = array();
if ($kat_raw === null) {
    $pesan_filter[] = "Parameter kategori tidak sesuai format, jadi diabaikan.";
}

$wilayah_all = daftar_wilayah();
$wil_list = array();
$wil_raw = param_daftar("wilayah");
if ($wil_raw === null) {
    $pesan_filter[] = "Parameter wilayah tidak sesuai format, jadi diabaikan.";
} else {
    $abaikan = 0;
    foreach ($wil_raw as $w) {
        if (isset($wilayah_all[$w])) { $wil_list[] = $w; } else { $abaikan++; }
    }
    if ($abaikan > 0) { $pesan_filter[] = $abaikan . " wilayah pada alamat tidak ditemukan, jadi diabaikan."; }
}

/* ---------- Muat data (aman dari error database) ---------- */
$jml_dest = 0;
$jml_kat = 0;
$jml_ulasan = 0;
$daftar_kat = array();
$semua = array();

try {
    $jml_dest   = hitung_sql($conn, "SELECT COUNT(*) FROM destinations WHERE status = 'aktif'");
    $jml_kat    = hitung_sql($conn, "SELECT COUNT(*) FROM categories");
    $jml_ulasan = hitung_sql($conn, "SELECT COUNT(*) FROM reviews r JOIN destinations d ON d.id = r.destination_id WHERE d.status = 'aktif'");

    $q = mysqli_query($conn, "SELECT c.id, c.nama, COUNT(d.id) AS jumlah FROM categories c LEFT JOIN destinations d ON d.category_id = c.id AND d.status = 'aktif' GROUP BY c.id, c.nama ORDER BY c.nama");
    if (!$q) {
        throw new Exception(mysqli_error($conn));
    }
    while ($k = mysqli_fetch_assoc($q)) {
        $daftar_kat[] = $k;
    }

    if ($kat_raw !== null) {
        $abaikan = 0;
        foreach ($kat_raw as $x) {
            $ada_kat = false;
            if (ctype_digit($x)) {
                foreach ($daftar_kat as $k) {
                    if ((int) $k["id"] === (int) $x) { $ada_kat = true; }
                }
            }
            if ($ada_kat) { $kat_list[] = (int) $x; } else { $abaikan++; }
        }
        $kat_list = array_values(array_unique($kat_list));
        if ($abaikan > 0) { $pesan_filter[] = $abaikan . " kategori pada alamat tidak ditemukan, jadi diabaikan."; }
    }

    // Semua destinasi dimuat sekali; penyaringan dilakukan di PHP (tampilan awal) dan JavaScript (saat chip diklik)
    $sql = "SELECT d.id, d.category_id, d.nama, d.lokasi, d.deskripsi, d.harga_tiket, d.harga_maks, d.jam_buka, d.foto, d.tag, d.wilayah, c.nama AS kategori,
            COALESCE(rev.rata, 0) AS rata,
            COALESCE(rev.jml_ulasan, 0) AS jml_ulasan
            FROM destinations d
            JOIN categories c ON d.category_id = c.id
            LEFT JOIN (
                SELECT destination_id, AVG(rating) AS rata, COUNT(*) AS jml_ulasan
                FROM reviews
                GROUP BY destination_id
            ) rev ON rev.destination_id = d.id
            WHERE d.status = 'aktif'
            ORDER BY d.created_at DESC, d.id DESC";
    $hasil = mysqli_query($conn, $sql);
    if (!$hasil) {
        throw new Exception(mysqli_error($conn));
    }
    while ($row = mysqli_fetch_assoc($hasil)) {
        $semua[] = $row;
    }
} catch (Throwable $ex) {
    $gagal_muat = true;
    $semua = array();
    error_log("Dolan Jogja (index.php): " . $ex->getMessage());
}

$ada_filter = ($cari !== "" || count($mood_list) > 0 || count($kat_list) > 0 || count($wil_list) > 0);

// Nama filter aktif untuk judul hasil
$label_filter = array();
foreach ($mood_list as $m) { $label_filter[] = e($daftar_mood[$m]); }
foreach ($daftar_kat as $k) {
    if (in_array((int) $k["id"], $kat_list, true)) { $label_filter[] = e($k["nama"]); }
}



foreach ($wil_list as $w) { $label_filter[] = e($wilayah_all[$w]); }

// Destinasi unggulan (3 rating tertinggi), hanya ditampilkan saat tidak ada filter
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
    $id_unggulan = array_map("intval", array_column($unggulan, "id"));
}
$hero_kartu = count($semua) >= 3 ? array_slice($urut, 0, 6) : array_slice($semua, 0, 6);

$hero_kartu = count($semua) >= 3 ? array_slice($urut, 0, 6) : array_slice($semua, 0, 6);

// Tentukan baris mana yang tampil pada kondisi awal
$jml_cocok = 0;
foreach ($semua as $i => $d) {
    $cocok = cocok_filter($d, $mood_list, $kat_list, $wil_list, $cari);
    $semua[$i]["_cocok"] = $cocok;
    $semua[$i]["_unggulan"] = in_array((int) $d["id"], $id_unggulan, true);
    $semua[$i]["_tampil"] = $cocok && ($ada_filter || !$semua[$i]["_unggulan"]);
    if ($cocok) { $jml_cocok++; }
}
$ada_baris_tampil = false;
foreach ($semua as $d) { if ($d["_tampil"]) { $ada_baris_tampil = true; } }
$kosong_karena_filter = (!$gagal_muat && $ada_filter && $jml_cocok === 0);
?>

<div class="hero">
    <div class="hero-isi">
        <?php tampil_pesan(); ?>
        <div class="hero-grid">
            <div class="hero-kiri">
                <?php
                $jam_sekarang = (int) date("G");
                if ($jam_sekarang >= 5 && $jam_sekarang < 11) {
                    $sapa_jawa = "Sugeng enjang, siap njajah Jogja hari ini?";
                } elseif ($jam_sekarang >= 11 && $jam_sekarang < 15) {
                    $sapa_jawa = "Sugeng siang, mau ngadem di mana siang ini?";
                } elseif ($jam_sekarang >= 15 && $jam_sekarang < 18) {
                    $sapa_jawa = "Sugeng sonten, saatnya berburu senja di Jogja.";
                } else {
                    $sapa_jawa = "Sugeng dalu, yuk cek kuliner & suasana malam Jogja.";
                }
                ?>
                <p class="atas"><?php echo sudah_login() ? e($_SESSION["username"] . " · " . $sapa_jawa) : e($sapa_jawa); ?></p>
                <h1>Jogja lebih seru kalau tahu mau ke mana.</h1>
                <p class="pengantar">Cari destinasi, cek harga tiket dan jam buka, lalu baca ulasan pengunjung sebelum berangkat.</p>

                <form method="get" action="index.php#destinasi" class="form-cari" id="form-cari">
                    <div class="cari-bungkus">
                        <input type="text" id="cari-input" name="cari" value="<?php echo e($cari); ?>" placeholder="Cari nama atau lokasi" maxlength="100" autocomplete="off" />
                        <div class="cari-saran sembunyi" id="cari-saran"></div>
                    </div>
                    <input type="hidden" id="in-kategori" name="kategori" value="<?php echo e(implode(",", $kat_list)); ?>" />
                    <input type="hidden" id="in-mood" name="mood" value="<?php echo e(implode(",", $mood_list)); ?>" />
                    <input type="hidden" id="in-wilayah" name="wilayah" value="<?php echo e(implode(",", $wil_list)); ?>" />
                    <button type="submit" class="tombol">Cari</button>
                </form>

                <div class="hero-angka">
                    <div><strong><?php echo $jml_dest; ?></strong><span>destinasi</span></div>
                    <div><strong><?php echo $jml_kat; ?></strong><span>kategori</span></div>
                    <div><strong><?php echo $jml_ulasan; ?></strong><span>ulasan</span></div>
                </div>
            </div>

            <?php if (count($hero_kartu) > 0) { ?>
            <div class="hero-showcase" id="heroShowcase">
                <div class="hero-dots" id="heroDots">
                    <?php foreach ($hero_kartu as $idx_d => $hd) { ?>
                        <button type="button" class="hero-dot<?php echo $idx_d === 0 ? ' aktif' : ''; ?>" data-slide="<?php echo $idx_d; ?>" aria-label="Slide <?php echo $idx_d + 1; ?>"></button>
                    <?php } ?>
                </div>

                <button type="button" class="hero-nav-melayang hero-nav-kiri" id="heroPrev" title="Sebelumnya" aria-label="Slide sebelumnya">&larr;</button>
                <button type="button" class="hero-nav-melayang hero-nav-kanan" id="heroNext" title="Berikutnya" aria-label="Slide berikutnya">&rarr;</button>

                <div class="hero-track" id="heroTrack">
                    <?php foreach ($hero_kartu as $hk) {
                        $skor_h = ((int) $hk["jml_ulasan"] > 0) ? number_format((float) $hk["rata"], 1) : "Baru";
                        $label_kurasi = "";
                        if ((int) $hk["harga_tiket"] === 0) {
                            $label_kurasi = "Wisata Gratis";
                        } elseif ((float) $hk["rata"] >= 4.7 && (int) $hk["jml_ulasan"] >= 3) {
                            $label_kurasi = "Favorit Wisatawan";
                        }
                    ?>
                        <div class="hero-slide">
                            <?php echo tampil_foto($hk["foto"], $hk["kategori"], $hk["nama"]); ?>
                            <div class="hero-slide-gradasi"></div>
                            <div class="hero-slide-atas">
                                <div>
                                    <span class="label"><?php echo e($hk["kategori"]); ?></span>
                                    <?php if ($label_kurasi !== "") { ?>
                                        <span class="label label-kurasi"><?php echo e($label_kurasi); ?></span>
                                    <?php } ?>
                                </div>
                                <?php echo badge_buka($hk["jam_buka"]); ?>
                            </div>
                            <div class="hero-slide-bawah">
                                <h3><?php echo e($hk["nama"]); ?></h3>
                                <p class="hero-slide-deskripsi"><?php echo e($hk["deskripsi"] ?: $hk["lokasi"]); ?></p>
                                <div class="hero-slide-bawah-baris">
                                    <div class="hero-kartu-meta">
                                        <span>&#9733; <?php echo e($skor_h); ?> (<?php echo (int) $hk["jml_ulasan"]; ?>) &middot; <?php echo e(rentang_harga($hk["harga_tiket"], $hk["harga_maks"])); ?></span>
                                    </div>
                                    <a class="tombol" href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $hk["id"]; ?>">Jelajahi Detail</a>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
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

        <p class="muted">Suasana atau waktu kunjungan <span class="kecil">(boleh pilih lebih dari satu)</span></p>
        <?php if (count($daftar_mood) === 0) { ?>
            <p class="kecil">Belum ada pilihan suasana yang tersedia.</p>
        <?php } else { ?>
            <div class="chip-baris" id="mood-baris">
                <?php echo chip_filter("Semua suasana", "mood", "", count($mood_list) === 0, url_beranda($cari, array(), $kat_list, $wil_list)); ?>
                <?php foreach ($daftar_mood as $kunci => $label) { ?>
                    <?php echo chip_filter($label, "mood", $kunci, in_array($kunci, $mood_list, true), url_beranda($cari, ganti_nilai($mood_list, $kunci), $kat_list, $wil_list)); ?>
                <?php } ?>
            </div>
        <?php } ?>

        <p class="muted">Kategori <span class="kecil">(boleh pilih lebih dari satu)</span></p>
        <?php if (count($daftar_kat) === 0) { ?>
            <?php if (!$gagal_muat) { ?><p class="kecil">Belum ada kategori yang tersedia.</p><?php } ?>
        <?php } else { ?>
            <div class="chip-baris" id="kategori-baris">
                <?php echo chip_filter("Semua", "kategori", "", count($kat_list) === 0, url_beranda($cari, $mood_list, array(), $wil_list)); ?>
                <?php foreach ($daftar_kat as $k) { $kid = (int) $k["id"]; ?>
                    <?php echo chip_filter($k["nama"], "kategori", (string) $kid, in_array($kid, $kat_list, true), url_beranda($cari, $mood_list, ganti_nilai($kat_list, $kid), $wil_list), $k["jumlah"]); ?>
                <?php } ?>
            </div>
        <?php } ?>

        <p class="muted">Wilayah <span class="kecil">(boleh pilih lebih dari satu)</span></p>
        <div class="chip-baris" id="wilayah-baris">
            <?php echo chip_filter("Semua wilayah", "wilayah", "", count($wil_list) === 0, url_beranda($cari, $mood_list, $kat_list, array())); ?>
            <?php foreach ($wilayah_all as $slug => $nama_wil) { ?>
                <?php echo chip_filter($nama_wil, "wilayah", $slug, in_array($slug, $wil_list, true), url_beranda($cari, $mood_list, $kat_list, ganti_nilai($wil_list, $slug))); ?>
            <?php } ?>
        </div>

        <div class="acak-kotak">
            <div class="acak-kontrol">
                <div>
                    <strong>Bingung mau ke mana?</strong>
                    <span class="kecil">Biarkan sistem memilihkan 1 destinasi kejutan untuk Anda.</span>
                </div>
                <div class="acak-tombol-grup">
                    <select id="acak-anggaran" title="Filter anggaran">
                        <option value="semua">Semua harga</option>
                        <option value="hemat">Hemat (&lt;= Rp 15.000)</option>
                    </select>
                    <button type="button" class="tombol" id="btn-acak-destinasi">Acak Destinasi!</button>
                </div>
            </div>
            <div class="acak-hasil sembunyi" id="acak-hasil"></div>
        </div>
    </div>

    

    <?php if (count($unggulan) === 3) { $besar = $unggulan[0]; ?>
    <div id="bagian-unggulan"<?php if ($ada_filter) { echo ' class="sembunyi"'; } ?>>
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
    </div>
    <?php } ?>

    <div class="bagian">
        <p class="eyebrow" id="eyebrow-hasil"><?php echo $ada_filter ? "Hasil pencarian" : "Jelajahi"; ?></p>
        <h2 id="judul-hasil"><?php
            if ($ada_filter) {
                echo $jml_cocok . " destinasi ditemukan" . (count($label_filter) > 0 ? " &middot; " . implode(" &middot; ", $label_filter) : "");
            } else {
                echo "Semua destinasi";
            }
        ?></h2>

        <div id="daftar-hasil">
        <?php if ($gagal_muat) { ?>
            <p class="kosong">Destinasi belum dapat ditampilkan saat ini. <a href="index.php#destinasi">Coba muat ulang</a></p>
        <?php } elseif (count($semua) === 0) { ?>
            <p class="kosong">Belum ada destinasi yang tersedia.</p>
        <?php } else { ?>
            <p class="kosong<?php echo $kosong_karena_filter ? "" : " sembunyi"; ?>" id="pesan-kosong"><strong>Destinasi tidak ditemukan.</strong> Tidak ada destinasi yang sesuai dengan filter yang dipilih. <a href="index.php#destinasi" id="reset-filter">Hapus semua filter</a></p>
            <div class="daftar<?php echo $ada_baris_tampil ? "" : " sembunyi"; ?>">
                <?php foreach ($semua as $d) { ?>
                    <div class="baris kartu-klik<?php echo $d["_tampil"] ? "" : " sembunyi"; ?>" data-kategori="<?php echo (int) $d["category_id"]; ?>" data-mood="<?php echo e((string) ($d["tag"] ?? "")); ?>" data-wilayah="<?php echo e(slug_wilayah($d["wilayah"] ?? "")); ?>" data-teks="<?php echo e(mb_strtolower($d["nama"] . " " . $d["lokasi"], "UTF-8")); ?>"<?php if ($d["_unggulan"]) { echo ' data-unggulan="1"'; } ?>>
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

    <div class="bagian sembunyi" id="terakhir-dilihat">
        <p class="eyebrow">Riwayat Jelajah</p>
        <h2>Terakhir kamu lihat</h2>
        <div class="recent-grid" id="recent-grid"></div>
    </div>

    <div class="ajakan">
        <div>
            <h2>Lagi di Jogja sekarang?</h2>
            <p>Izinkan lokasimu, lalu lihat destinasi yang paling dekat.</p>
        </div>
        <a class="tombol" href="<?php echo BASE_URL; ?>destinasi/terdekat.php">Cari yang terdekat</a>
    </div>
</div>

<script type="text/javascript" src="<?php echo BASE_URL; ?>assets/js/filter-beranda.js"></script>
<script type="text/javascript">
//<![CDATA[
(function () {
    var btn = document.getElementById("btn-acak-destinasi");
    var hasil = document.getElementById("acak-hasil");
    var anggaran = document.getElementById("acak-anggaran");
    if (!btn || !hasil) { return; }

    var sumber = <?php
        $pool = array();
        foreach ($semua as $item) {
            $pool[] = array(
                "id" => (int) $item["id"],
                "nama" => $item["nama"],
                "kategori" => $item["kategori"],
                "lokasi" => $item["lokasi"],
                "harga_angka" => (int) $item["harga_tiket"],
                "harga" => rentang_harga($item["harga_tiket"], $item["harga_maks"]),
                "url" => BASE_URL . "destinasi/detail.php?id=" . (int) $item["id"]
            );
        }
        echo json_encode($pool, JSON_UNESCAPED_UNICODE);
    ?>;

    function esc(s) {
        return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    btn.addEventListener("click", function () {
        var mode = anggaran ? anggaran.value : "semua";
        var kandidat = sumber.filter(function (d) {
            return mode !== "hemat" || d.harga_angka <= 15000;
        });
        if (kandidat.length === 0) { kandidat = sumber; }
        if (kandidat.length === 0) { return; }

        btn.disabled = true;
        btn.textContent = "Mengacak...";
        hasil.classList.remove("sembunyi");

        var putar = setInterval(function () {
            var tmp = kandidat[Math.floor(Math.random() * kandidat.length)];
            hasil.innerHTML = '<div class="acak-animasi">Memilih: <strong>' + esc(tmp.nama) + '</strong>...</div>';
        }, 90);

        setTimeout(function () {
            clearInterval(putar);
            var pilih = kandidat[Math.floor(Math.random() * kandidat.length)];
            hasil.innerHTML =
                '<div class="acak-kartu">' +
                    '<div>' +
                        '<span class="label">' + esc(pilih.kategori) + '</span> ' +
                        '<strong class="acak-nama">' + esc(pilih.nama) + '</strong>' +
                        '<p class="kecil">' + esc(pilih.lokasi) + ' &middot; <strong>' + esc(pilih.harga) + '</strong></p>' +
                    '</div>' +
                    '<a class="tombol" href="' + esc(pilih.url) + '">Lihat Detail</a>' +
                '</div>';
            btn.disabled = false;
            btn.textContent = "Acak Lagi!";
        }, 950);
    });
})();
//]]>
</script>
<script type="text/javascript">
//<![CDATA[
(function () {
    var box = document.getElementById("terakhir-dilihat");
    var grid = document.getElementById("recent-grid");
    if (!box || !grid || !window.localStorage) { return; }
    try {
        var daftar = JSON.parse(localStorage.getItem("dolan_recent") || "[]");
        if (!Array.isArray(daftar) || daftar.length === 0) { return; }
        var esc = function (s) {
            return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
        };
        grid.innerHTML = daftar.map(function (it) {
            return '<a class="recent-kartu" href="' + esc(it.url) + '">' +
                '<span class="label">' + esc(it.kategori) + '</span>' +
                '<strong>' + esc(it.nama) + '</strong>' +
                '<span class="kecil">' + esc(it.harga) + '</span>' +
            '</a>';
        }).join("");
        box.classList.remove("sembunyi");
    } catch (e) {}
})();
//]]>
</script>
<?php require_once "includes/footer.php"; ?>