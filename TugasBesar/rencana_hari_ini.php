<?php
// Mode perjalanan: halaman ringkas satu hari rencana untuk dibuka dari HP saat di jalan.
// Memakai token tautan berbagi yang sama (termasuk gerbang kode akses), hanya-baca,
// dan tidak mencatat apa pun ke database selain statistik kunjungan tautan.
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/jam_buka.php";
require_once __DIR__ . "/includes/rencana_jadwal.php";
require_once __DIR__ . "/includes/rencana_bagi.php";

header("X-Robots-Tag: noindex, nofollow");
header("Referrer-Policy: no-referrer");

pastikan_rencana_hari($conn);
pastikan_rencana_bagi($conn);

function url_hari_ini($token, $hari = 0) {
    $url = url_absolut("rencana_hari_ini.php") . "?token=" . rawurlencode((string) $token);
    return $hari > 0 ? $url . "&hari=" . (int) $hari : $url;
}

$token = trim((string) ($_GET["token"] ?? ""));
$baris = ambil_baris_bagi($conn, $token);

if ($baris === null) {
    http_response_code(404);
    $judul = "Tautan tidak ditemukan";
    require_once __DIR__ . "/includes/header.php";
    ?>
    <div class="wadah">
        <div class="halaman-isi">
            <div class="panel">
                <h1>Tautan tidak ditemukan</h1>
                <p>Tautan ini tidak valid, sudah dimatikan, atau sudah kedaluwarsa.</p>
                <p><a class="tombol" href="<?php echo BASE_URL; ?>index.php">Ke Beranda</a></p>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . "/includes/footer.php";
    exit;
}

/* ---------- Kode akses (sama seperti halaman publik) ---------- */
$pesan_kode = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && bagi_terkunci($baris) && ($_POST["aksi"] ?? "") !== "salin") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $pesan_kode = "Sesi sudah berakhir. Muat ulang halaman lalu coba lagi.";
    } elseif (bagi_terlalu_banyak_coba($token)) {
        http_response_code(429);
        $pesan_kode = "Terlalu banyak percobaan. Tunggu satu menit lalu coba lagi.";
    } elseif (password_verify((string) ($_POST["kode"] ?? ""), (string) $baris["kode_hash"])) {
        buka_bagi($baris);
        reset_coba_kode($token);
        header("Location: " . url_hari_ini($token, (int) ($_GET["hari"] ?? 0)));
        exit;
    } else {
        catat_coba_kode($token);
        $pesan_kode = "Kode akses salah. Periksa lagi angka dari pemilik rencana.";
    }
}

if (!bagi_sudah_terbuka($baris)) {
    $judul = "Mode Perjalanan";
    require_once __DIR__ . "/includes/header.php";
    ?>
    <div class="wadah">
        <div class="halaman-isi">
            <div class="bagi-kepala">
                <p class="eyebrow">Mode perjalanan</p>
                <h1>Kode akses diperlukan</h1>
                <p class="kecil">Pemilik rencana memasang kode akses untuk tautan ini.</p>
            </div>
            <div class="panel">
                <?php if ($pesan_kode !== "") { ?><p class="error"><?php echo e($pesan_kode); ?></p><?php } ?>
                <form method="post" action="<?php echo e(url_hari_ini($token, (int) ($_GET["hari"] ?? 0))); ?>" class="bagi-kode bagi-kode-lebar">
                    <?php echo csrf_field(); ?>
                    <label for="kode-akses">Kode akses</label>
                    <input type="text" id="kode-akses" name="kode" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off" required="required" />
                    <button type="submit" class="tombol">Buka</button>
                </form>
                <p class="kecil">Minta kode 4&ndash;6 angka kepada pemilik rencana. <a href="<?php echo e(url_rencana_publik($token)); ?>">Lihat rencana lengkap</a></p>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . "/includes/footer.php";
    exit;
}

/* ---------- Data rencana ---------- */
if (empty($_SESSION["bagi_dihitung"][$token])) {
    catat_akses_bagi($conn, $token);
    $_SESSION["bagi_dihitung"][$token] = true;
}

$data = muat_rencana_pengguna($conn, (int) $baris["user_id"]);
$tampil_biaya = (int) $baris["tanpa_biaya"] === 0;
$hari_tersedia = array_keys($data["hari"]);

$judul = "Mode Perjalanan";
require_once __DIR__ . "/includes/header.php";

if (count($hari_tersedia) === 0) {
    ?>
    <div class="wadah">
        <div class="halaman-isi">
            <div class="panel">
                <h1>Rencana masih kosong</h1>
                <p>Pemilik belum menambahkan destinasi ke rencana ini.</p>
                <p><a class="tombol" href="<?php echo e(url_rencana_publik($token)); ?>">Buka halaman rencana</a></p>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . "/includes/footer.php";
    exit;
}

/* Hari yang ditampilkan: pilihan ?hari=N, atau hari ini menurut tanggal mulai. */
$hari_hari_ini = 0;
$catatan_tanggal = "";
if ($data["tanggal_mulai"] !== "") {
    $zona = new DateTimeZone("Asia/Jakarta");
    $tgl_mulai = DateTimeImmutable::createFromFormat("!Y-m-d", $data["tanggal_mulai"], $zona);
    if ($tgl_mulai) {
        $tgl_kini = new DateTimeImmutable("today", $zona);
        $selisih = (int) round(($tgl_kini->getTimestamp() - $tgl_mulai->getTimestamp()) / 86400);
        $hari_hari_ini = $selisih + 1;
        $hari_terakhir = max($hari_tersedia);
        if ($hari_hari_ini < 1) {
            $catatan_tanggal = "Rencana dimulai " . tanggal_ringkas($data["tanggal_mulai"]) . ". Hari ke-1 ditampilkan sebagai gambaran.";
        } elseif ($hari_hari_ini > $hari_terakhir) {
            $catatan_tanggal = "Tanggal rencana sudah lewat. Hari ke-" . $hari_terakhir . " ditampilkan sebagai arsip.";
        }
    }
}

/* ?hari=N menang bila valid; kalau tidak, pakai hari ini menurut tanggal mulai. */
$hari_diminta = (int) ($_GET["hari"] ?? 0);
if (in_array($hari_diminta, $hari_tersedia, true)) {
    $hari_pilih = $hari_diminta;
} elseif ($hari_hari_ini >= 1 && $hari_hari_ini <= max($hari_tersedia)) {
    $hari_pilih = $hari_hari_ini;
} elseif ($hari_hari_ini > max($hari_tersedia)) {
    $hari_pilih = max($hari_tersedia);
} else {
    $hari_pilih = min($hari_tersedia);
}

$blok = $data["hari"][$hari_pilih];
$jad = $data["jadwal"][$hari_pilih];
$jumlah_pos = count($blok["item"]);
$label_tanggal = $jad["tanggal"] !== null
    ? label_hari_pekan($jad["pekan"]) . ", " . tanggal_ringkas($jad["tanggal"])
    : "Tanggal belum diisi";
$judul_halaman = "Hari ke-" . $hari_pilih;
?>
<div class="wadah">
    <div class="halaman-isi">
        <div class="bagi-kepala">
            <p class="eyebrow">Mode perjalanan</p>
            <h1><?php echo e($judul_halaman); ?></h1>
            <p class="kecil">
                <?php echo e($label_tanggal); ?> &middot;
                <?php echo e(menit_ke_jam($jad["mulai"])); ?>&ndash;<?php echo e(menit_ke_jam($jad["selesai"])); ?> &middot;
                &plusmn; <?php echo e(number_format($jad["km"], 1, ",", ".")); ?> km &middot;
                <?php echo $jumlah_pos; ?> destinasi
            </p>
            <?php if ($catatan_tanggal !== "") { ?><p class="kecil jadwal-catatan peringatan"><?php echo e($catatan_tanggal); ?></p><?php } ?>
        </div>

        <?php if (count($hari_tersedia) > 1) { ?>
            <div class="chip-baris hari-ini-hari">
                <?php foreach ($hari_tersedia as $h_pil) {
                    $label_pil = "Hari " . $h_pil;
                    $tgl_pil = $data["jadwal"][$h_pil]["tanggal"] ?? null;
                    if ($tgl_pil !== null) { $label_pil .= " \u00b7 " . tanggal_ringkas($tgl_pil); }
                ?>
                    <a class="chip<?php echo $h_pil === $hari_pilih ? " aktif" : ""; ?>" href="<?php echo e(url_hari_ini($token, $h_pil)); ?>"<?php echo $h_pil === $hari_pilih ? ' aria-current="page"' : ""; ?>><?php echo e($label_pil); ?></a>
                <?php } ?>
            </div>
        <?php } ?>

        <div class="panel hari-ini">
            <div class="hari-ini-atas">
                <p class="kecil" id="hari-ini-status">0 dari <?php echo $jumlah_pos; ?> destinasi sudah dikunjungi.</p>
                <div class="hari-ini-bar"><span id="hari-ini-bar" style="width:0%"></span></div>
            </div>

            <div class="hari-ini-daftar" id="hari-ini-daftar" data-token="<?php echo e(substr($token, 0, 8) . "-" . $hari_pilih); ?>">
                <?php foreach ($blok["item"] as $i => $r) {
                    $jp = $jad["item"][$i];
                    $koordinat = ($r["latitude"] !== null && $r["longitude"] !== null)
                        ? (float) $r["latitude"] . "," . (float) $r["longitude"] : "";
                    $tautan_maps = $koordinat !== ""
                        ? "https://www.google.com/maps/dir/?api=1&destination=" . rawurlencode($koordinat)
                        : "https://www.google.com/maps/search/?api=1&query=" . rawurlencode($r["nama"] . " " . $r["lokasi"]);
                ?>
                <div class="hari-ini-pos" data-pos="<?php echo $i + 1; ?>">
                    <div class="hari-ini-jam">
                        <strong><?php echo e(menit_ke_jam($jp["tiba"])); ?></strong>
                        <span class="kecil">tiba</span>
                    </div>
                    <div class="hari-ini-isi">
                        <h3><span class="hari-ini-nomor"><?php echo $i + 1; ?></span> <?php echo e($r["nama"]); ?></h3>
                        <p class="kecil"><?php echo e($r["lokasi"]); ?> &middot; <?php echo e(menit_ke_jam($jp["tiba"])); ?>&ndash;<?php echo e(menit_ke_jam($jp["pulang"])); ?><?php if ($jp["km"] > 0) { ?> &middot; <?php echo e(number_format($jp["km"], 1, ",", ".")); ?> km dari pos sebelumnya<?php } ?></p>
                        <?php foreach ($jp["catatan"] as $c) { ?>
                            <p class="jadwal-catatan <?php echo e($c[0]); ?>"><?php echo e($c[1]); ?></p>
                        <?php } ?>
                        <?php if ($tampil_biaya) { ?>
                            <p class="kecil">Tiket: <strong><?php echo e(rencana_harga($r["_min"], $r["_maks"])); ?></strong> /orang</p>
                        <?php } ?>
                        <div class="hari-ini-aksi">
                            <label class="hari-ini-cek">
                                <input type="checkbox" data-pos="<?php echo $i + 1; ?>" />
                                <span>Sudah sampai</span>
                            </label>
                            <a href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $r["did"]; ?>">Detail</a>
                            <a href="<?php echo e($tautan_maps); ?>" target="_blank" rel="noopener">Rute</a>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>

            <div class="rencana-aksi hari-ini-aksi-bawah">
                <button type="button" class="tombol tombol-garis" id="hari-ini-ulang">Ulangi tanda kunjungan</button>
                <a class="tombol tombol-garis" href="<?php echo e(url_rencana_publik($token)); ?>">Rencana lengkap</a>
            </div>
            <p class="kecil">Tanda &quot;sudah sampai&quot; hanya tersimpan di perangkat ini (bukan di server), jadi bisa dipakai tanpa akun.</p>
        </div>
    </div>
</div>

<script type="text/javascript">
//<![CDATA[
(function () {
    var daftar = document.getElementById("hari-ini-daftar");
    if (!daftar) { return; }
    var kunci = "dolan-hari-ini-" + daftar.getAttribute("data-token");
    var kotak = daftar.querySelectorAll("input[type=checkbox][data-pos]");
    var posEl = daftar.querySelectorAll(".hari-ini-pos");
    var status = document.getElementById("hari-ini-status");
    var bar = document.getElementById("hari-ini-bar");

    function baca() {
        try {
            var isi = JSON.parse(localStorage.getItem(kunci) || "[]");
            return Array.isArray(isi) ? isi.map(String) : [];
        } catch (e) { return []; }
    }
    function simpan(isi) {
        try { localStorage.setItem(kunci, JSON.stringify(isi)); } catch (e) { /* penyimpanan penuh */ }
    }
    function gambar() {
        var selesai = baca();
        var jumlah = 0;
        Array.prototype.forEach.call(posEl, function (el) {
            var pos = el.getAttribute("data-pos");
            var sudah = selesai.indexOf(pos) !== -1;
            el.classList.toggle("selesai", sudah);
            el.classList.remove("berikutnya");
            if (sudah) { jumlah++; }
        });
        Array.prototype.forEach.call(kotak, function (cb) {
            cb.checked = selesai.indexOf(cb.getAttribute("data-pos")) !== -1;
        });
        /* Pos pertama yang belum ditandai disorot sebagai tujuan berikutnya. */
        for (var i = 0; i < posEl.length; i++) {
            if (selesai.indexOf(posEl[i].getAttribute("data-pos")) === -1) {
                posEl[i].classList.add("berikutnya");
                break;
            }
        }
        if (status) {
            status.textContent = jumlah + " dari " + posEl.length + " destinasi sudah dikunjungi.";
        }
        if (bar) { bar.style.width = (posEl.length > 0 ? Math.round((jumlah / posEl.length) * 100) : 0) + "%"; }
    }

    Array.prototype.forEach.call(kotak, function (cb) {
        cb.addEventListener("change", function () {
            var pos = cb.getAttribute("data-pos");
            var isi = baca();
            var i = isi.indexOf(pos);
            if (cb.checked && i === -1) { isi.push(pos); }
            if (!cb.checked && i !== -1) { isi.splice(i, 1); }
            simpan(isi);
            gambar();
        });
    });

    var ulang = document.getElementById("hari-ini-ulang");
    if (ulang) {
        ulang.addEventListener("click", function () {
            simpan([]);
            gambar();
        });
    }

    gambar();
})();
//]]>
</script>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
