<?php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";
require_once __DIR__ . "/../includes/pesan.php";
require_once __DIR__ . "/../includes/jam_buka.php";
require_once __DIR__ . "/../includes/ulasan_bantuan.php";
require_once __DIR__ . "/../includes/galeri.php";

$id = (int) ($_GET["id"] ?? 0);
$st = mysqli_prepare($conn, "SELECT d.*, c.nama AS kategori,
    (SELECT AVG(rating) FROM reviews WHERE destination_id = d.id) AS rata,
    (SELECT COUNT(*) FROM reviews WHERE destination_id = d.id) AS jml_ulasan
    FROM destinations d JOIN categories c ON c.id = d.category_id WHERE d.id = ?");
mysqli_stmt_bind_param($st, "i", $id);
mysqli_stmt_execute($st);
$d = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
if (!$d) {
    http_response_code(404);
    $judul = "Tidak ditemukan";
    require_once __DIR__ . "/../includes/header.php";
    echo '<div class="wadah"><div class="halaman-isi"><p class="kosong">Destinasi tidak ditemukan. <a href="' . BASE_URL . 'index.php">Kembali ke beranda</a></p></div></div>';
    require_once __DIR__ . "/../includes/footer.php";
    exit;
}

/* Fitur "ulasan terbantu": tabel dibuat sekali per request bila belum ada. */
$ada_bantuan = pastikan_bantuan_ulasan($conn);

/* Galeri multi-slide: pastikan tabel destination_photos sudah ada. */
pastikan_galeri_destinasi($conn);

$sql_ulasan = "SELECT r.id, r.user_id, r.rating, r.komentar, r.created_at, u.username";
if ($ada_bantuan) {
    $sql_ulasan .= ",
        (SELECT COUNT(*) FROM review_helps h WHERE h.review_id = r.id) AS membantu,
        (SELECT COUNT(*) FROM review_helps h2 JOIN reviews r2 ON r2.id = h2.review_id WHERE r2.user_id = r.user_id) AS total_membantu";
} else {
    $sql_ulasan .= ", 0 AS membantu, 0 AS total_membantu";
}
$sql_ulasan .= " FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.destination_id = ? ORDER BY r.created_at DESC";

$st = mysqli_prepare($conn, $sql_ulasan);
mysqli_stmt_bind_param($st, "i", $id);
mysqli_stmt_execute($st);
$ulasan_semua = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
mysqli_stmt_close($st);

/* Ulasan mana saja yang sudah ditandai membantu oleh pengguna yang sedang masuk. */
$sudah_bantu = array();
if ($ada_bantuan && sudah_login()) {
    $st = mysqli_prepare($conn, "SELECT review_id FROM review_helps WHERE user_id = ?");
    mysqli_stmt_bind_param($st, "i", $_SESSION["user_id"]);
    mysqli_stmt_execute($st);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC) as $baris_bantu) {
        $sudah_bantu[(int) $baris_bantu["review_id"]] = true;
    }
    mysqli_stmt_close($st);
}

$distribusi = array(5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0);
$milik = null;
foreach ($ulasan_semua as $r) {
    $rt = (int) $r["rating"];
    if (isset($distribusi[$rt])) { $distribusi[$rt]++; }
    if (sudah_login() && (int) $r["user_id"] === (int) $_SESSION["user_id"]) { $milik = $r; }
}

$opsi_urut = array(
    "terbaru"   => "Terbaru",
    "membantu"  => "Paling Membantu",
    "tertinggi" => "Rating Tertinggi",
    "terendah"  => "Rating Terendah",
);
$urut_ulasan = $_GET["urut"] ?? "terbaru";
if (!isset($opsi_urut[$urut_ulasan])) {
    $urut_ulasan = "terbaru";
}
$filter_bintang = (int) ($_GET["bintang"] ?? 0);
if ($filter_bintang < 1 || $filter_bintang > 5) { $filter_bintang = 0; }

$ulasan = array_values(array_filter($ulasan_semua, function ($r) use ($filter_bintang) {
    return $filter_bintang === 0 || (int) $r["rating"] === $filter_bintang;
}));
if ($urut_ulasan === "tertinggi") {
    usort($ulasan, function ($a, $b) { return (int) $b["rating"] <=> (int) $a["rating"]; });
} elseif ($urut_ulasan === "terendah") {
    usort($ulasan, function ($a, $b) { return (int) $a["rating"] <=> (int) $b["rating"]; });
} elseif ($urut_ulasan === "membantu") {
    usort($ulasan, function ($a, $b) {
        $ba = (int) $a["membantu"];
        $bb = (int) $b["membantu"];
        if ($ba === $bb) {
            return strtotime($b["created_at"]) <=> strtotime($a["created_at"]);
        }
        return $bb <=> $ba;
    });
}

$tersimpan = false;
if (sudah_login()) {
    $st = mysqli_prepare($conn, "SELECT id FROM wishlists WHERE user_id = ? AND destination_id = ? LIMIT 1");
    mysqli_stmt_bind_param($st, "ii", $_SESSION["user_id"], $id);
    mysqli_stmt_execute($st);
    $tersimpan = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
}

$st = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM favorites WHERE destination_id = ?");
mysqli_stmt_bind_param($st, "i", $id);
mysqli_stmt_execute($st);
$jml_favorit = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($st))["n"];
mysqli_stmt_close($st);

$difavoritkan = false;
if (sudah_login()) {
    $st = mysqli_prepare($conn, "SELECT id FROM favorites WHERE user_id = ? AND destination_id = ? LIMIT 1");
    mysqli_stmt_bind_param($st, "ii", $_SESSION["user_id"], $id);
    mysqli_stmt_execute($st);
    $difavoritkan = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
}

/* Harga untuk kartu tiket & estimasi rombongan */
$harga_min  = (int) $d["harga_tiket"];
$harga_maks = ($d["harga_maks"] !== null && (int) $d["harga_maks"] > $harga_min) ? (int) $d["harga_maks"] : $harga_min;

// Terkait: kategori yang sama dulu, lalu sisanya (maks. 3)
$st = mysqli_prepare($conn, "SELECT d.id, d.nama, d.foto, d.harga_tiket, d.harga_maks, c.nama AS kategori,
    (SELECT AVG(rating) FROM reviews WHERE destination_id = d.id) AS rata,
    (SELECT COUNT(*) FROM reviews WHERE destination_id = d.id) AS jml_ulasan
    FROM destinations d JOIN categories c ON c.id = d.category_id
    WHERE d.id <> ? ORDER BY (d.category_id = ?) DESC, d.id LIMIT 3");
mysqli_stmt_bind_param($st, "ii", $id, $d["category_id"]);
mysqli_stmt_execute($st);
$terkait = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
mysqli_stmt_close($st);

$wl = $_GET["wl"] ?? "";

$judul = $d["nama"];
require_once __DIR__ . "/../includes/header.php";
?>
<div class="wadah"><div class="halaman-isi">
    <p class="breadcrumb">
        <a href="<?php echo BASE_URL; ?>index.php">Beranda</a> &gt;
        <a href="<?php echo BASE_URL; ?>index.php?kategori=<?php echo (int) $d["category_id"]; ?>#destinasi"><?php echo e($d["kategori"]); ?></a> &gt;
        <span class="sekarang"><?php echo e($d["nama"]); ?></span>
    </p>
    <?php if ($wl === "tersimpan") { ?><p class="sukses">Disimpan ke daftar favorit.</p><?php } ?>
    <?php if ($wl === "dihapus") { ?><p class="sukses">Dihapus dari daftar favorit.</p><?php } ?>
    <?php if ($wl === "gagal") { ?><p class="error">Gagal memproses favorit. Silakan coba lagi.</p><?php } ?>
    <div class="panel">
        <?php
        /* Galeri: foto admin (destination_photos) + folder galeri/<id>/, lalu foto utama. */
        $daftar_foto = galeri_destinasi($d["id"]);
        if (count($daftar_foto) === 0 && foto_ada($d["foto"])) {
            $berkas_utama = basename((string) $d["foto"]);
            $daftar_foto[] = array(
                "id"         => null,
                "berkas"     => $berkas_utama,
                "url"        => BASE_URL . "assets/uploads/destinasi/" . rawurlencode($berkas_utama),
                "keterangan" => "Foto utama " . $d["nama"],
            );
        }
        $jml_slide = count($daftar_foto);
        ?>
        <?php if ($jml_slide > 0) { ?>
        <div class="galeri-detail" id="galeriDestinasi">
            <div class="galeri-viewport">
                <div class="galeri-track" id="galeriTrack">
                    <?php foreach ($daftar_foto as $i_slide => $f_slide) { ?>
                        <div class="galeri-slide" data-index="<?php echo $i_slide; ?>">
                            <img src="<?php echo e($f_slide["url"]); ?>" alt="<?php echo e($d["nama"] . " - Foto " . ($i_slide + 1)); ?>" width="1280" height="853" loading="<?php echo $i_slide === 0 ? "eager" : "lazy"; ?>" />
                            <?php if (!empty($f_slide["keterangan"])) { ?>
                                <span class="galeri-caption"><?php echo e($f_slide["keterangan"]); ?></span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <?php if ($jml_slide > 1) { ?>
                    <button type="button" class="galeri-nav galeri-prev" id="galeriPrev" title="Foto sebelumnya" aria-label="Foto sebelumnya">&#8249;</button>
                    <button type="button" class="galeri-nav galeri-next" id="galeriNext" title="Foto berikutnya" aria-label="Foto berikutnya">&#8250;</button>
                <?php } ?>
                <button type="button" class="galeri-zoom" id="galeriZoom" title="Perbesar foto" aria-label="Perbesar foto">Perbesar</button>
                <span class="galeri-counter" id="galeriCounter">1 / <?php echo $jml_slide; ?></span>
            </div>
            <?php if ($jml_slide > 1) { ?>
                <div class="galeri-thumbs" id="galeriThumbs">
                    <?php foreach ($daftar_foto as $i_slide => $f_slide) { ?>
                        <button type="button" class="galeri-thumb<?php echo $i_slide === 0 ? " aktif" : ""; ?>" data-target="<?php echo $i_slide; ?>" title="<?php echo e($f_slide["keterangan"] ?: "Foto " . ($i_slide + 1)); ?>" aria-label="Foto <?php echo $i_slide + 1; ?>">
                            <img src="<?php echo e($f_slide["url"]); ?>" alt="Thumbnail <?php echo $i_slide + 1; ?>" width="92" height="62" loading="lazy" />
                        </button>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
        <div class="galeri-lightbox" id="galeriLightbox" aria-hidden="true">
            <div class="galeri-lightbox-isi">
                <button type="button" class="galeri-lightbox-tutup" id="galeriTutup" title="Tutup" aria-label="Tutup">&times;</button>
                <?php if ($jml_slide > 1) { ?>
                    <button type="button" class="galeri-lightbox-nav kiri" id="galeriLbPrev" title="Foto sebelumnya" aria-label="Foto sebelumnya">&#8249;</button>
                    <button type="button" class="galeri-lightbox-nav kanan" id="galeriLbNext" title="Foto berikutnya" aria-label="Foto berikutnya">&#8250;</button>
                <?php } ?>
                <img id="galeriLbImg" src="<?php echo e($daftar_foto[0]["url"]); ?>" alt="<?php echo e($d["nama"]); ?>" />
                <p class="galeri-lightbox-ket">
                    <span id="galeriLbCaption"><?php echo e($daftar_foto[0]["keterangan"]); ?></span>
                    <span class="galeri-lightbox-hitung" id="galeriLbHitung">1 / <?php echo $jml_slide; ?></span>
                </p>
            </div>
        </div>
        <?php } else { ?>
            <div class="galeri-destinasi galeri-kosong"><?php echo tampil_monogram($d["kategori"], $d["nama"]); ?></div>
        <?php } ?>
        <div class="info-praktis">
            <div class="info-praktis-item">
                <span class="info-ikon" aria-hidden="true">&#127903;</span>
                <span class="info-label">Tiket masuk</span>
                <strong><?php echo e(rentang_harga($harga_min, $harga_maks)); ?></strong>
            </div>
            <div class="info-praktis-item">
                <span class="info-ikon" aria-hidden="true">&#128336;</span>
                <span class="info-label">Jam operasional</span>
                <strong><?php echo e($d["jam_buka"] ?: "Tidak tersedia"); ?></strong>
            </div>
            <div class="info-praktis-item">
                <span class="info-ikon" aria-hidden="true">&#9203;</span>
                <span class="info-label">Estimasi durasi</span>
                <strong><?php echo e(durasi_kunjungan($d["kategori"])); ?></strong>
            </div>
            <div class="info-praktis-item">
                <span class="info-ikon" aria-hidden="true">&#128663;</span>
                <span class="info-label">Akses &amp; parkir</span>
                <strong><?php echo e(akses_parkir($d["kategori"])); ?></strong>
            </div>
        </div>
        <span class="label"><?php echo e($d["kategori"]); ?></span><?php echo badge_buka($d["jam_buka"]); ?>
        <h2><?php echo e($d["nama"]); ?></h2>
        <p><?php echo e($d["lokasi"]); ?></p>
        <p class="meta-baris"><?php echo tampil_rating($d["rata"], $d["jml_ulasan"]); ?></p>
        <p><strong>Harga tiket:</strong> <?php echo e(rentang_harga($harga_min, $harga_maks)); ?><br />
        <strong>Jam buka:</strong> <?php echo e($d["jam_buka"] ?: "Tidak tersedia"); ?></p>
        <p><?php echo nl2br(e($d["deskripsi"])); ?></p>

        <div class="tiket-estimasi" id="estimasiTiket"
             data-harga-min="<?php echo $harga_min; ?>"
             data-harga-maks="<?php echo $harga_maks; ?>">
            <h3>Kartu tiket &amp; estimasi rombongan</h3>
            <div class="tiket-baris">
                <div class="tiket-tarif">
                    <span class="kecil">Tarif per orang</span>
                    <strong><?php echo e(rentang_harga($harga_min, $harga_maks)); ?></strong>
                </div>
                <div class="stepper">
                    <button type="button" class="stepper-btn" id="btnOrangKurang" title="Kurangi jumlah pengunjung" aria-label="Kurangi jumlah pengunjung">&minus;</button>
                    <input type="number" id="inputJumlahOrang" value="2" min="1" max="99" step="1" aria-label="Jumlah pengunjung" />
                    <button type="button" class="stepper-btn" id="btnOrangTambah" title="Tambah jumlah pengunjung" aria-label="Tambah jumlah pengunjung">+</button>
                    <span class="kecil">pengunjung</span>
                </div>
            </div>
            <p class="tiket-total">Total estimasi tiket: <strong id="teksTotalTiket"><?php echo e(rentang_harga($harga_min * 2, $harga_maks * 2)); ?></strong></p>
            <p class="kecil">Hitungan ini hanya tiket masuk per orang; belum termasuk parkir, transportasi, atau biaya wahana tambahan.</p>
        </div>

        <?php if ($d["latitude"] !== null && $d["longitude"] !== null) { ?>
            <p class="tautan-lokasi">
                <a class="tombol tombol-garis" href="<?php echo BASE_URL; ?>peta.php?fokus=<?php echo (int) $d["id"]; ?>">Lihat di Peta</a>
                <a class="tombol tombol-garis" href="https://www.google.com/maps?q=<?php echo e($d["latitude"]); ?>,<?php echo e($d["longitude"]); ?>" target="_blank" rel="noopener noreferrer">Buka di Google Maps</a>
            </p>
        <?php } ?>

        <div class="aksi-destinasi-grup">
            <?php if (sudah_login()) {
                $token_aksi = generate_csrf_token();
            ?>
                <button type="button"
                        class="tombol tombol-garis btn-favorit<?php echo $difavoritkan ? ' aktif' : ''; ?>"
                        data-endpoint="<?php echo BASE_URL; ?>user/toggle_favorit.php"
                        data-id="<?php echo (int) $d["id"]; ?>"
                        data-csrf="<?php echo e($token_aksi); ?>">
                    <span class="ikon-hati"><?php echo $difavoritkan ? "&#9829;" : "&#9825;"; ?></span>
                    <span class="teks-fav"><?php echo $difavoritkan ? "Difavoritkan" : "Favorit"; ?></span>
                    (<span class="hitung-fav"><?php echo $jml_favorit; ?></span>)
                </button>

                <button type="button"
                        class="tombol btn-rencana-toggle<?php echo $tersimpan ? ' tombol-garis' : ''; ?>"
                        data-endpoint="<?php echo BASE_URL; ?>user/toggle_rencana.php"
                        data-id="<?php echo (int) $d["id"]; ?>"
                        data-csrf="<?php echo e($token_aksi); ?>">
                    <?php echo $tersimpan ? "&#10003; Tersimpan di Rencana" : "+ Rencana Dolan"; ?>
                </button>
            <?php } else { ?>
                <span class="kecil">&#9829; <?php echo $jml_favorit; ?> menyukai</span>
                <a class="tombol" href="<?php echo BASE_URL; ?>auth/login.php">Masuk untuk Favorit &amp; Rencana Dolan</a>
            <?php } ?>
        </div>
    </div>

    <div class="panel" id="ulasan">
        <h3>Ulasan (<?php echo count($ulasan_semua); ?>)</h3>
        <?php tampil_pesan(); ?>

        <?php if (count($ulasan_semua) > 0) { ?>
            <div class="rating-ringkas">
                <div class="rating-skor-besar">
                    <strong><?php echo e(number_format((float) $d["rata"], 1)); ?></strong>
                    <span>dari 5 &middot; <?php echo count($ulasan_semua); ?> ulasan</span>
                </div>
                <div class="rating-bar-daftar">
                    <?php for ($b = 5; $b >= 1; $b--) {
                        $jml_b = $distribusi[$b];
                        $persen = (int) round(($jml_b / count($ulasan_semua)) * 100);
                    ?>
                        <div class="rating-bar-baris">
                            <span class="rating-bar-label"><?php echo $b; ?> bintang</span>
                            <div class="rating-bar-jalur"><div class="rating-bar-isi" style="width:<?php echo $persen; ?>%"></div></div>
                            <span class="rating-bar-jumlah"><?php echo $jml_b; ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <?php if (sudah_login()) {
            $skor_awal = $milik ? (int) $milik["rating"] : 5;
            $ket_bintang = array(
                5 => "5/5 \u{00B7} Luar biasa",
                4 => "4/5 \u{00B7} Sangat bagus",
                3 => "3/5 \u{00B7} Cukup bagus",
                2 => "2/5 \u{00B7} Kurang",
                1 => "1/5 \u{00B7} Mengecewakan",
            );
        ?>
            <form method="post" action="<?php echo BASE_URL; ?>review/simpan.php" class="form-isi">
                <div>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="destination_id" value="<?php echo (int) $d["id"]; ?>" />
                    <label><?php echo $milik ? "Ubah ulasan Anda" : "Tulis ulasan"; ?></label>
                    <div class="rating-input-grup">
                        <div class="bintang-pilih">
                            <?php for ($i = 5; $i >= 1; $i--) { ?>
                                <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>"<?php if ($skor_awal === $i) { echo ' checked="checked"'; } ?> />
                                <label for="star<?php echo $i; ?>" title="<?php echo e($ket_bintang[$i]); ?>">&#9733;</label>
                            <?php } ?>
                        </div>
                        <span class="kecil" id="labelSkorBintang"><?php echo e($ket_bintang[$skor_awal]); ?></span>
                    </div>
                    <p><textarea name="komentar" id="komentarUlasan" rows="3" maxlength="600" placeholder="Ceritakan pengalamanmu (maks. 600 karakter)"><?php echo e($milik ? $milik["komentar"] : ""); ?></textarea></p>
                    <p class="kecil hitung-karakter"><span id="hitungKomentar">0</span>/600 karakter &middot; baris kosong berlebih dirapikan otomatis.</p>
                    <p><input type="submit" value="<?php echo $milik ? "Perbarui ulasan" : "Kirim ulasan"; ?>" /></p>
                </div>
            </form>
        <?php } else { ?>
            <p class="kecil"><a href="<?php echo BASE_URL; ?>auth/login.php">Masuk</a> untuk menulis ulasan.</p>
        <?php } ?>

        <div id="wadah-ulasan">
            <?php if (count($ulasan_semua) > 0) { ?>
                <div class="ulasan-filter">
                    <?php
                    $base_ulasan = BASE_URL . "destinasi/detail.php?id=" . (int) $d["id"];
                    foreach ($opsi_urut as $k_urut => $lbl_urut) {
                        $href = $base_ulasan . "&urut=" . $k_urut . ($filter_bintang > 0 ? "&bintang=" . $filter_bintang : "") . "#ulasan";
                    ?>
                        <a class="chip<?php echo $urut_ulasan === $k_urut ? ' aktif' : ''; ?>" href="<?php echo e($href); ?>"><?php echo e($lbl_urut); ?></a>
                    <?php } ?>
                    <?php if ($filter_bintang > 0) { ?>
                        <a class="chip" href="<?php echo e($base_ulasan . "&urut=" . $urut_ulasan . "#ulasan"); ?>">Semua bintang (Reset)</a>
                    <?php } else { ?>
                        <?php for ($b = 5; $b >= 1; $b--) { if ($distribusi[$b] > 0) { ?>
                            <a class="chip" href="<?php echo e($base_ulasan . "&urut=" . $urut_ulasan . "&bintang=" . $b . "#ulasan"); ?>"><?php echo $b; ?>&#9733; (<?php echo $distribusi[$b]; ?>)</a>
                        <?php } } ?>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if (count($ulasan) === 0) { ?>
                <p class="kosong"><?php echo $filter_bintang > 0 ? "Tidak ada ulasan dengan rating " . $filter_bintang . " bintang." : "Belum ada ulasan."; ?></p>
            <?php } ?>
            <?php foreach ($ulasan as $r) {
                $nilai_rt = (int) $r["rating"];
                $jml_bantu = (int) $r["membantu"];
                $sudah_vote = isset($sudah_bantu[(int) $r["id"]]);
                $kontributor = $ada_bantuan && (int) $r["total_membantu"] >= 3;
            ?>
                <div class="ulasan-item">
                    <strong><?php echo e($r["username"]); ?></strong>
                    <?php if ($kontributor) { ?>
                        <span class="badge-kontributor" title="Kontributor terverifikasi: <?php echo (int) $r["total_membantu"]; ?> ulasan Anda dinilai membantu">Kontributor Terverifikasi</span>
                    <?php } ?>
                    <span class="bintang-tampil" title="<?php echo $nilai_rt; ?>/5">
                        <?php for ($s = 1; $s <= 5; $s++) { ?>
                            <span class="<?php echo $s <= $nilai_rt ? 'isi' : 'kosong'; ?>">&#9733;</span>
                        <?php } ?>
                    </span>
                    <span class="kecil"> &middot; <?php echo e(date("d M Y", strtotime($r["created_at"]))); ?></span>
                    <p><?php echo nl2br(e($r["komentar"])); ?></p>

                    <div class="ulasan-aksi-baris">
                        <?php if ($ada_bantuan && sudah_login() && (int) $r["user_id"] !== (int) $_SESSION["user_id"]) { ?>
                            <form method="post" action="<?php echo BASE_URL; ?>review/vote.php" class="inline">
                                <div class="inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $r["id"]; ?>" />
                                    <input type="hidden" name="destination_id" value="<?php echo (int) $d["id"]; ?>" />
                                    <input type="hidden" name="urut" value="<?php echo e($urut_ulasan); ?>" />
                                    <input type="hidden" name="bintang" value="<?php echo (int) $filter_bintang; ?>" />
                                    <button type="submit" class="btn-bantuan<?php echo $sudah_vote ? " aktif" : ""; ?>" title="<?php echo $sudah_vote ? "Batalkan tanda membantu" : "Tandai ulasan ini membantu"; ?>">
                                        &#128077; Membantu (<span><?php echo $jml_bantu; ?></span>)
                                    </button>
                                </div>
                            </form>
                        <?php } elseif ($jml_bantu > 0) { ?>
                            <span class="btn-bantuan statis" title="Jumlah pengunjung yang menilai ulasan ini membantu">&#128077; Membantu (<?php echo $jml_bantu; ?>)</span>
                        <?php } ?>

                        <?php if (sudah_login() && ((int) $r["user_id"] === (int) $_SESSION["user_id"] || $_SESSION["role"] === "admin")) { ?>
                            <form method="post" action="<?php echo BASE_URL; ?>review/hapus.php" class="inline" onsubmit="return konfirmasiHapus('ulasan ini');">
                                <div class="inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $r["id"]; ?>" />
                                    <input type="hidden" name="kembali" value="detail" />
                                    <button type="submit" class="btn-kecil btn-bahaya">Hapus</button>
                                </div>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <?php if (count($terkait) > 0) { ?>
    <div class="bagian">
        <p class="eyebrow">Masih penasaran?</p>
        <h2>Pilihan terkait lainnya</h2>
        <div class="terkait-grid">
            <?php foreach ($terkait as $t) { ?>
                <div class="baris-kecil kartu-klik">
                    <?php echo tampil_foto($t["foto"], $t["kategori"], $t["nama"]); ?>
                    <div>
                        <span class="label"><?php echo e($t["kategori"]); ?></span>
                        <h3><?php echo e($t["nama"]); ?></h3>
                        <p class="meta-baris"><?php echo tampil_rating($t["rata"], $t["jml_ulasan"]); ?><span><?php echo e(rentang_harga($t["harga_tiket"], $t["harga_maks"])); ?></span></p>
                        <a href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $t["id"]; ?>">Lihat detail</a>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div></div>

<script type="text/javascript">
//<![CDATA[
(function () {
    if (!window.localStorage) { return; }
    try {
        var item = {
            id: <?php echo (int) $d["id"]; ?>,
            nama: <?php echo json_encode($d["nama"], JSON_UNESCAPED_UNICODE); ?>,
            kategori: <?php echo json_encode($d["kategori"], JSON_UNESCAPED_UNICODE); ?>,
            harga: <?php echo json_encode(rentang_harga($harga_min, $harga_maks), JSON_UNESCAPED_UNICODE); ?>,
            url: <?php echo json_encode(BASE_URL . "destinasi/detail.php?id=" . (int) $d["id"], JSON_UNESCAPED_UNICODE); ?>
        };
        var daftar = JSON.parse(localStorage.getItem("dolan_recent") || "[]");
        if (!Array.isArray(daftar)) { daftar = []; }
        daftar = daftar.filter(function (x) { return x && x.id !== item.id; });
        daftar.unshift(item);
        localStorage.setItem("dolan_recent", JSON.stringify(daftar.slice(0, 4)));
    } catch (e) {}
})();

/* Kartu tiket: stepper jumlah pengunjung + total estimasi live */
(function () {
    var kotak = document.getElementById("estimasiTiket");
    if (!kotak) { return; }
    var hargaMin = parseInt(kotak.getAttribute("data-harga-min"), 10) || 0;
    var hargaMaks = parseInt(kotak.getAttribute("data-harga-maks"), 10) || 0;
    if (hargaMaks < hargaMin) { hargaMaks = hargaMin; }

    var input = document.getElementById("inputJumlahOrang");
    var kurang = document.getElementById("btnOrangKurang");
    var tambah = document.getElementById("btnOrangTambah");
    var teks = document.getElementById("teksTotalTiket");
    if (!input || !teks) { return; }

    function rp(n) { return "Rp " + Number(n).toLocaleString("id-ID"); }
    function jumlah() {
        var n = parseInt(input.value, 10);
        if (isNaN(n) || n < 1) { n = 1; }
        if (n > 99) { n = 99; }
        return n;
    }
    function perbarui() {
        var n = jumlah();
        if (hargaMin === 0 && hargaMaks === 0) {
            teks.textContent = "Gratis";
            return;
        }
        var a = hargaMin * n;
        var b = hargaMaks * n;
        teks.textContent = (a === b) ? rp(a) : rp(a) + " - " + rp(b);
    }
    function ubah(selisih) {
        input.value = Math.max(1, Math.min(99, jumlah() + selisih));
        perbarui();
    }
    if (kurang) { kurang.addEventListener("click", function () { ubah(-1); }); }
    if (tambah) { tambah.addEventListener("click", function () { ubah(1); }); }
    input.addEventListener("input", perbarui);
    input.addEventListener("change", function () { input.value = jumlah(); perbarui(); });
    perbarui();
})();

/* Penghitung karakter ulasan (0/600) */
(function () {
    var area = document.getElementById("komentarUlasan");
    var hitung = document.getElementById("hitungKomentar");
    if (!area || !hitung) { return; }
    var batas = parseInt(area.getAttribute("maxlength"), 10) || 600;

    function perbarui() {
        var n = area.value.length;
        hitung.textContent = n;
        hitung.parentNode.className = (n >= batas * 0.9) ? "kecil hitung-karakter hampir-penuh" : "kecil hitung-karakter";
    }
    area.addEventListener("input", perbarui);
    perbarui();
})();
/* Galeri foto destinasi: swipe/drag, thumbnail, panah, hash, lightbox, preload */
(function () {
    var kotak = document.getElementById("galeriDestinasi");
    var track = document.getElementById("galeriTrack");
    if (!kotak || !track) { return; }
    var slide = track.getElementsByClassName("galeri-slide");
    var total = slide.length;
    if (total < 1) { return; }

    var navPrev = document.getElementById("galeriPrev");
    var navNext = document.getElementById("galeriNext");
    var zoom = document.getElementById("galeriZoom");
    var hitung = document.getElementById("galeriCounter");
    var wadahThumb = document.getElementById("galeriThumbs");
    var thumb = wadahThumb ? wadahThumb.getElementsByClassName("galeri-thumb") : [];
    var lb = document.getElementById("galeriLightbox");
    var lbImg = document.getElementById("galeriLbImg");
    var lbCap = document.getElementById("galeriLbCaption");
    var lbHitung = document.getElementById("galeriLbHitung");
    var lbPrev = document.getElementById("galeriLbPrev");
    var lbNext = document.getElementById("galeriLbNext");
    var lbTutup = document.getElementById("galeriTutup");

    var idx = 0;
    var programatik = false;
    var mulaiX = 0;
    var mulaiScroll = 0;
    var geserJauh = 0;
    var sedangGeser = false;
    var blokirKlik = false;
    var terlihat = true;

    function keterangan(i) {
        var cap = slide[i].getElementsByClassName("galeri-caption")[0];
        return cap ? cap.textContent : "";
    }

    function perbarui(tulisHash) {
        if (hitung) { hitung.textContent = (idx + 1) + " / " + total; }
        for (var i = 0; i < thumb.length; i++) {
            thumb[i].className = (i === idx) ? "galeri-thumb aktif" : "galeri-thumb";
        }
        for (var j = 0; j < total; j++) {
            var gambar = slide[j].getElementsByTagName("img")[0];
            if (!gambar) { continue; }
            gambar.setAttribute("loading", (j >= idx - 1 && j <= idx + 1) ? "eager" : "lazy");
        }
        if (tulisHash !== false && window.history && window.history.replaceState) {
            try { window.history.replaceState(null, "", "#foto-" + (idx + 1)); } catch (e) {}
        }
        if (lb && lb.className.indexOf("buka") !== -1) { isiLightbox(); }
    }

    function ke(i) {
        idx = ((i % total) + total) % total;
        programatik = true;
        if (track.scrollTo) {
            track.scrollTo({ left: idx * track.clientWidth, behavior: "smooth" });
        } else {
            track.scrollLeft = idx * track.clientWidth;
        }
        perbarui();
        window.setTimeout(function () { programatik = false; }, 650);
    }

    function isiLightbox() {
        if (!lb || !lbImg) { return; }
        var gambar = slide[idx].getElementsByTagName("img")[0];
        if (gambar) {
            lbImg.src = gambar.getAttribute("src");
            lbImg.alt = gambar.getAttribute("alt");
        }
        if (lbCap) { lbCap.textContent = keterangan(idx); }
        if (lbHitung) { lbHitung.textContent = (idx + 1) + " / " + total; }
    }

    function bukaLightbox(i) {
        if (!lb) { return; }
        if (i !== idx) { ke(i); } else { perbarui(); }
        isiLightbox();
        lb.className = "galeri-lightbox buka";
        lb.setAttribute("aria-hidden", "false");
        if (document.body.className.indexOf("tanpa-scroll") === -1) {
            document.body.className = (document.body.className + " tanpa-scroll").replace(/^\s+/, "");
        }
        if (lbTutup) { lbTutup.focus(); }
    }

    function tutupLightbox() {
        if (!lb) { return; }
        lb.className = "galeri-lightbox";
        lb.setAttribute("aria-hidden", "true");
        document.body.className = document.body.className.replace(/\s*tanpa-scroll/, "");
        if (zoom) { zoom.focus(); }
    }

    track.addEventListener("scroll", function () {
        if (programatik || sedangGeser || track.clientWidth <= 0) { return; }
        var baru = Math.round(track.scrollLeft / track.clientWidth);
        if (baru !== idx && baru >= 0 && baru < total) { idx = baru; perbarui(); }
    });

    /* Geser dengan mouse (sentuhan memakai scroll native) */
    track.addEventListener("mousedown", function (e) {
        if (e.button !== 0) { return; }
        sedangGeser = true;
        geserJauh = 0;
        mulaiX = e.pageX;
        mulaiScroll = track.scrollLeft;
        track.className = "galeri-track sedang-geser";
        e.preventDefault();
    });
    document.addEventListener("mousemove", function (e) {
        if (!sedangGeser) { return; }
        geserJauh = e.pageX - mulaiX;
        track.scrollLeft = mulaiScroll - geserJauh;
    });
    document.addEventListener("mouseup", function () {
        if (!sedangGeser) { return; }
        sedangGeser = false;
        track.className = "galeri-track";
        if (Math.abs(geserJauh) > 8) {
            blokirKlik = true;
            ke(idx + (geserJauh < 0 ? 1 : -1));
            window.setTimeout(function () { blokirKlik = false; }, 350);
        }
    });

    for (var s = 0; s < total; s++) {
        (function (elemen, i) {
            elemen.addEventListener("click", function () {
                if (blokirKlik || sedangGeser) { return; }
                bukaLightbox(i);
            });
        })(slide[s], s);
    }

    if (zoom) { zoom.addEventListener("click", function () { bukaLightbox(idx); }); }
    if (navPrev) { navPrev.addEventListener("click", function () { ke(idx - 1); }); }
    if (navNext) { navNext.addEventListener("click", function () { ke(idx + 1); }); }
    for (var d = 0; d < thumb.length; d++) {
        (function (tombol, i) {
            tombol.addEventListener("click", function () { ke(i); });
        })(thumb[d], d);
    }

    if (lb) {
        if (lbTutup) { lbTutup.addEventListener("click", tutupLightbox); }
        if (lbPrev) { lbPrev.addEventListener("click", function () { ke(idx - 1); }); }
        if (lbNext) { lbNext.addEventListener("click", function () { ke(idx + 1); }); }
        lb.addEventListener("click", function (e) {
            if (e.target === lb) { tutupLightbox(); }
        });
    }

    if (window.IntersectionObserver) {
        new window.IntersectionObserver(function (entri) {
            terlihat = entri[0] ? entri[0].isIntersecting : true;
        }, { threshold: 0.35 }).observe(kotak);
    }

    document.addEventListener("keydown", function (e) {
        var kode = e.keyCode || 0;
        var kiri = e.key === "ArrowLeft" || kode === 37;
        var kanan = e.key === "ArrowRight" || kode === 39;
        var lbBuka = lb && lb.className.indexOf("buka") !== -1;
        if (e.key === "Escape" || kode === 27) {
            if (lbBuka) { tutupLightbox(); }
            return;
        }
        if (!kiri && !kanan) { return; }
        if (lbBuka) {
            e.preventDefault();
            ke(idx + (kanan ? 1 : -1));
            return;
        }
        if (!terlihat) { return; }
        var tag = (e.target && e.target.tagName ? e.target.tagName : "").toLowerCase();
        if (tag === "input" || tag === "textarea" || tag === "select") { return; }
        e.preventDefault();
        ke(idx + (kanan ? 1 : -1));
    });

    var cocok = /^#foto-(\d+)$/.exec(window.location.hash || "");
    if (cocok) {
        var awal = parseInt(cocok[1], 10) - 1;
        if (!isNaN(awal) && awal >= 0 && awal < total) { idx = awal; }
    }
    if (track.scrollTo) {
        track.scrollTo({ left: idx * track.clientWidth, behavior: "auto" });
    } else {
        track.scrollLeft = idx * track.clientWidth;
    }
    perbarui();

    window.addEventListener("resize", function () {
        if (track.scrollTo) {
            track.scrollTo({ left: idx * track.clientWidth, behavior: "auto" });
        } else {
            track.scrollLeft = idx * track.clientWidth;
        }
    });
})();
//]]>
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
