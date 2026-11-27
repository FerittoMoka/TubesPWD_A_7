<?php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";
require_once __DIR__ . "/../includes/pesan.php";
require_once __DIR__ . "/../includes/jam_buka.php";
require_once __DIR__ . "/../includes/ulasan_bantuan.php";

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
        <div style="height:280px;border-radius:12px;overflow:hidden;margin-bottom:16px;"><?php echo tampil_foto($d["foto"], $d["kategori"], $d["nama"]); ?></div>
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
//]]>
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
