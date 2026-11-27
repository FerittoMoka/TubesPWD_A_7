<?php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";
require_once __DIR__ . "/../includes/rencana_jadwal.php";
cek_login();

$uid = (int) $_SESSION["user_id"];
pastikan_rencana_hari($conn);
$maks_hari = 7;
$orang = max(1, min(50, (int) ($_GET["orang"] ?? $_POST["orang"] ?? 1)));

function rencana_rp($n) { return "Rp " . number_format((int) $n, 0, ",", "."); }
function rencana_harga($min, $maks) {
    if ($maks <= 0) { return "Gratis"; }
    return $min === $maks ? rencana_rp($min) : rencana_rp($min) . " - " . rencana_rp($maks);
}

/* ---------- Proses aksi ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $kembali = BASE_URL . "user/rencana.php?orang=" . $orang;

    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $_SESSION["rencana_pesan"] = array("error", "Token keamanan tidak valid. Silakan coba lagi.");
        header("Location: " . $kembali);
        exit;
    }

    $aksi = $_POST["aksi"] ?? "";

    /* Atur jam mulai satu hari (tidak terikat item tertentu). */
    if ($aksi === "jam_hari") {
        $hari_jam  = (int) ($_POST["hari"] ?? 0);
        $mulai_jam = jam_ke_menit($_POST["jam_mulai"] ?? "");
        $pekan_jam = (int) ($_POST["hari_pekan"] ?? 0);
        if ($pekan_jam < 0 || $pekan_jam > 7) { $pekan_jam = 0; }
        if ($hari_jam < 1 || $hari_jam > $maks_hari) {
            $_SESSION["rencana_pesan"] = array("error", "Hari tidak valid.");
        } elseif ($mulai_jam === null) {
            $_SESSION["rencana_pesan"] = array("error", "Format jam mulai tidak valid (contoh 08:00).");
        } elseif (!simpan_jam_mulai_hari($conn, $uid, $hari_jam, $mulai_jam, $pekan_jam)) {
            $_SESSION["rencana_pesan"] = array("error", "Jam mulai gagal disimpan. Silakan coba lagi.");
        } else {
            $_SESSION["rencana_pesan"] = array("sukses", "Hari ke-" . $hari_jam . " dimulai pukul " . menit_ke_jam($mulai_jam)
                . ($pekan_jam > 0 ? " hari " . label_hari_pekan($pekan_jam) : "") . ".");
        }
        header("Location: " . $kembali);
        exit;
    }

    $wid  = (int) ($_POST["id"] ?? 0);

    $st = mysqli_prepare($conn, "SELECT hari_ke FROM wishlists WHERE id = ? AND user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($st, "ii", $wid, $uid);
    mysqli_stmt_execute($st);
    $w = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);

    if (!$w) {
        $_SESSION["rencana_pesan"] = array("error", "Item tidak ditemukan.");
    } elseif ($aksi === "hapus") {
        $st = mysqli_prepare($conn, "DELETE FROM wishlists WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($st, "ii", $wid, $uid);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        $_SESSION["rencana_pesan"] = array("sukses", "Destinasi dihapus dari rencana.");
    } elseif ($aksi === "pindah_hari") {
        $hari = (int) ($_POST["hari"] ?? 0);
        if ($hari < 1 || $hari > $maks_hari) {
            $_SESSION["rencana_pesan"] = array("error", "Hari tidak valid.");
        } else {
            $st = mysqli_prepare($conn, "SELECT COALESCE(MAX(urutan), 0) + 1 AS n FROM wishlists WHERE user_id = ? AND hari_ke = ?");
            mysqli_stmt_bind_param($st, "ii", $uid, $hari);
            mysqli_stmt_execute($st);
            $n = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($st))["n"];
            mysqli_stmt_close($st);

            $st = mysqli_prepare($conn, "UPDATE wishlists SET hari_ke = ?, urutan = ? WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($st, "iiii", $hari, $n, $wid, $uid);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
            $_SESSION["rencana_pesan"] = array("sukses", "Dipindahkan ke Hari ke-" . $hari . ".");
        }
    } elseif ($aksi === "naik" || $aksi === "turun") {
        $hari = (int) $w["hari_ke"];
        $st = mysqli_prepare($conn, "SELECT w.id FROM wishlists w JOIN destinations d ON d.id = w.destination_id WHERE w.user_id = ? AND w.hari_ke = ? AND d.status = 'aktif' ORDER BY w.urutan, w.id");
        mysqli_stmt_bind_param($st, "ii", $uid, $hari);
        mysqli_stmt_execute($st);
        $ids = array_map("intval", array_column(mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC), "id"));
        mysqli_stmt_close($st);

        $pos = array_search($wid, $ids, true);
        $tuj = ($aksi === "naik") ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($ids[$tuj])) {
            $tmp = $ids[$pos]; $ids[$pos] = $ids[$tuj]; $ids[$tuj] = $tmp;
        }
        // Nomori ulang 1..n agar urutan selalu rapi
        $up = mysqli_prepare($conn, "UPDATE wishlists SET urutan = ? WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($up, "iii", $nomor, $idx, $uid);
        foreach ($ids as $i => $idx) {
            $nomor = $i + 1;
            mysqli_stmt_execute($up);
        }
        mysqli_stmt_close($up);
    }
    header("Location: " . $kembali);
    exit;
}

/* ---------- Ambil data ---------- */
$st = mysqli_prepare($conn, "SELECT w.id, w.hari_ke, d.id AS did, d.nama, d.lokasi, d.jam_buka, d.hari_tutup, d.harga_tiket, d.harga_maks, d.foto, d.latitude, d.longitude, c.nama AS kategori
    FROM wishlists w
    JOIN destinations d ON d.id = w.destination_id
    JOIN categories c ON c.id = d.category_id
    WHERE w.user_id = ? AND d.status = 'aktif'
    ORDER BY w.hari_ke, w.urutan, w.id");
mysqli_stmt_bind_param($st, "i", $uid);
mysqli_stmt_execute($st);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
mysqli_stmt_close($st);

$hari_list = array();
$total_min = 0;
$total_maks = 0;
$jml_titik_peta = 0;
foreach ($rows as $r) {
    $min = (int) $r["harga_tiket"];
    $mx  = ($r["harga_maks"] !== null) ? max($min, (int) $r["harga_maks"]) : $min;
    $h   = (int) $r["hari_ke"];
    if (!isset($hari_list[$h])) { $hari_list[$h] = array("item" => array(), "min" => 0, "maks" => 0); }
    $r["_min"] = $min;
    $r["_maks"] = $mx;
    $hari_list[$h]["item"][] = $r;
    $hari_list[$h]["min"]  += $min;
    $hari_list[$h]["maks"] += $mx;
    $total_min  += $min;
    $total_maks += $mx;
    $jml_titik_peta++;
}
ksort($hari_list);

/* Jadwal jam per hari: waktu tiba/keluar tiap destinasi, jarak tempuh, dan peringatan jam buka. */
$pengaturan_hari = ambil_pengaturan_hari($conn, $uid);
$jadwal_hari = array();
$jarak_hari = array();
$jarak_total = 0.0;
foreach ($hari_list as $h_j => $blok_j) {
    $atur_j = $pengaturan_hari[$h_j] ?? array("jam" => "08:00", "pekan" => 0);
    $mulai_j = jam_ke_menit($atur_j["jam"]);
    if ($mulai_j === null) { $mulai_j = 8 * 60; }
    $jadwal_hari[$h_j] = susun_jadwal_hari($blok_j["item"], $mulai_j, (int) $atur_j["pekan"]);
    $jarak_hari[$h_j] = $jadwal_hari[$h_j]["km"];
    $jarak_total += $jadwal_hari[$h_j]["km"];
}
$jarak_total = round($jarak_total, 1);

/* Pilihan moda transportasi untuk perkiraan biaya per kilometer. */
$moda_transport = array(
    "motor" => array("label" => "Motor pribadi", "tarif" => 1500),
    "mobil" => array("label" => "Mobil pribadi", "tarif" => 2500),
    "sewa"  => array("label" => "Sewa mobil + driver", "tarif" => 6000),
);

// Teks ringkasan untuk tombol "Salin"
$teks = array("Rencana Dolan Jogja (" . $orang . " orang)", "");
foreach ($hari_list as $h => $blok) {
    $jad_h_teks = $jadwal_hari[$h];
    $teks[] = "Hari ke-" . $h . " (" . ($jad_h_teks["pekan"] > 0 ? label_hari_pekan($jad_h_teks["pekan"]) . ", " : "")
            . menit_ke_jam($jad_h_teks["mulai"]) . "-" . menit_ke_jam($jad_h_teks["selesai"])
            . ", " . number_format($jad_h_teks["km"], 1, ",", ".") . " km)";
    foreach ($blok["item"] as $i => $r) {
        $teks[] = ($i + 1) . ". " . menit_ke_jam($jad_h_teks["item"][$i]["tiba"]) . " " . $r["nama"] . " - " . $r["lokasi"]
                . " (" . rencana_harga($r["_min"], $r["_maks"]) . "/orang)";
    }
    $teks[] = "Subtotal: " . rencana_harga($blok["min"] * $orang, $blok["maks"] * $orang);
    $teks[] = "";
}
$teks[] = "Total estimasi tiket: " . rencana_harga($total_min * $orang, $total_maks * $orang);
$teks[] = "Estimasi jarak rute: " . number_format($jarak_total, 1, ",", ".") . " km (belum termasuk perjalanan pulang)";
$ringkasan = implode("\n", $teks);

$pesan = $_SESSION["rencana_pesan"] ?? null;
unset($_SESSION["rencana_pesan"]);
$csrf_token = generate_csrf_token();

function rencana_hidden($token, $orang, $id) {
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '" />'
         . '<input type="hidden" name="orang" value="' . (int) $orang . '" />'
         . '<input type="hidden" name="id" value="' . (int) $id . '" />';
}

$judul = "Rencana Dolan";
$menu_aktif = "rencana";
require_once __DIR__ . "/../includes/header.php";
?>
<div class="wadah">
    <div class="halaman-isi">
        <?php if (count($hari_list) > 0) { ?>
        <div class="cetak-header">
            <div class="cetak-header-isi">
                <div>
                    <p class="cetak-eyebrow">Dolan Jogja</p>
                    <h1>Itinerary Resmi &mdash; Dolan Jogja</h1>
                    <p class="cetak-meta">Dicetak pada <?php echo e(date("d/m/Y H:i")); ?> WIB &middot; <?php echo count($hari_list); ?> hari &middot; <?php echo $orang; ?> orang</p>
                </div>
                <div class="cetak-qr">
                    <div id="qr-rencana" class="qr-kotak"></div>
                    <span>Pindai untuk membuka rencana</span>
                </div>
            </div>
            <div class="cetak-anggaran">
                <span>Total estimasi tiket (<?php echo $orang; ?> orang): <strong><?php echo e(rencana_harga($total_min * $orang, $total_maks * $orang)); ?></strong></span>
                <span>Patungan tiket per orang: <strong><?php echo e(rencana_harga($total_min, $total_maks)); ?></strong></span>
                <span>Estimasi jarak rute: <strong><?php echo e(number_format($jarak_total, 1, ",", ".")); ?> km</strong></span>
                <span class="cetak-biaya" id="cetak-biaya-transport">Estimasi transport: <strong>-</strong></span>
            </div>
        </div>
        <?php } ?>

        <h1>Rencana Dolan</h1>
        <p class="tidak-dicetak">Susun destinasi favoritmu per hari, lihat estimasi biaya tiket, lalu cetak atau salin ringkasannya.</p>

        <?php if ($pesan) { ?><p class="<?php echo e($pesan[0]); ?>"><?php echo e($pesan[1]); ?></p><?php } ?>

        <?php if (count($hari_list) === 0) { ?>
            <p class="kosong">Belum ada destinasi di rencana Anda. Buka halaman detail destinasi lalu klik &quot;Simpan ke favorit&quot;. <a href="<?php echo BASE_URL; ?>index.php#destinasi">Jelajahi destinasi</a></p>
        <?php } else { ?>

            <div class="panel total-rencana">
                <form method="get" action="rencana.php" class="form-isi form-orang tidak-dicetak">
                    <div>
                        <label for="orang">Jumlah orang</label>
                        <input type="number" id="orang" name="orang" min="1" max="50" value="<?php echo $orang; ?>" />
                        <input type="submit" value="Hitung" />
                    </div>
                </form>
                <div class="patungan-grid tidak-dicetak">
                    <div>
                        <label for="moda-transport">Moda transportasi (perkiraan biaya)</label>
                        <select id="moda-transport">
                            <option value="0">Isi biaya transport manual</option>
                            <?php foreach ($moda_transport as $kunci_moda => $m) { ?>
                                <option value="<?php echo (int) $m["tarif"]; ?>" data-moda="<?php echo e($kunci_moda); ?>"><?php echo e($m["label"]); ?> (&plusmn; <?php echo e(rupiah($m["tarif"])); ?>/km)</option>
                            <?php } ?>
                        </select>
                        <p class="kecil moda-keterangan">Estimasi jarak rute: <strong><?php echo e(number_format($jarak_total, 1, ",", ".")); ?> km</strong>. Biaya transport terisi otomatis sesuai moda.</p>
                    </div>
                    <div>
                        <label for="biaya-transport">Estimasi transportasi / bensin (Rp)</label>
                        <input type="number" id="biaya-transport" min="0" step="5000" value="0" />
                    </div>
                    <div>
                        <label for="biaya-konsumsi">Estimasi makan &amp; jajan (Rp)</label>
                        <input type="number" id="biaya-konsumsi" min="0" step="5000" value="0" />
                    </div>
                </div>

                <p class="total-label">Total estimasi tiket untuk <?php echo $orang; ?> orang</p>
                <p class="total-angka"><?php echo e(rencana_harga($total_min * $orang, $total_maks * $orang)); ?></p>

                <div class="patungan-hasil" id="patungan-hasil">
                    <span>Total keseluruhan: <strong id="total-gabungan"><?php echo e(rencana_harga($total_min * $orang, $total_maks * $orang)); ?></strong></span>
                    <span>Patungan per orang: <strong id="patungan-orang"><?php echo e(rencana_harga($total_min, $total_maks)); ?></strong></span>
                </div>
                <p class="kecil">Perkiraan dari data harga tiket; harga bisa berbeda saat hari libur. Belum termasuk transportasi dan makan. Jadwal memakai estimasi durasi kunjungan per kategori dan kecepatan rata-rata 30 km/jam.</p>
                <div class="rencana-aksi tidak-dicetak">
                    <button type="button" class="tombol" onclick="cetakRencana();">Cetak</button>
                    <button type="button" class="tombol tombol-garis" onclick="salinRingkasan();">Salin ringkasan</button>
                    <a class="tombol tombol-garis" href="<?php echo BASE_URL; ?>peta.php?rute=1">Lihat Rute di Peta</a>
                    <span id="status-salin" class="kecil"></span>
                </div>
                <textarea id="ringkasan-teks" class="sembunyi" rows="6" cols="40" readonly="readonly"><?php echo e($ringkasan); ?></textarea>
            </div>

            <?php foreach ($hari_list as $h => $blok) { $jad_h = $jadwal_hari[$h]; ?>
                <div class="panel">
                    <h3>Hari ke-<?php echo (int) $h; ?> <span class="kecil">&middot; <?php if ($jad_h["pekan"] > 0) { ?><?php echo e(label_hari_pekan($jad_h["pekan"])); ?> &middot; <?php } ?><?php echo e(menit_ke_jam($jad_h["mulai"])); ?>&ndash;<?php echo e(menit_ke_jam($jad_h["selesai"])); ?><?php if ($jad_h["km"] > 0) { ?> &middot; &plusmn; <?php echo e(number_format($jad_h["km"], 1, ",", ".")); ?> km<?php } ?> &middot; subtotal <?php echo e(rencana_harga($blok["min"] * $orang, $blok["maks"] * $orang)); ?></span></h3>

                    <form method="post" action="rencana.php" class="jadwal-hari tidak-dicetak">
                        <div class="inline">
                            <?php echo rencana_hidden($csrf_token, $orang, 0); ?>
                            <input type="hidden" name="hari" value="<?php echo (int) $h; ?>" />
                            <label for="jam-hari-<?php echo (int) $h; ?>">Mulai pukul</label>
                            <input type="time" id="jam-hari-<?php echo (int) $h; ?>" name="jam_mulai" value="<?php echo e(jam_input($jad_h["mulai"])); ?>" step="300" />
                            <label for="pekan-hari-<?php echo (int) $h; ?>">pada hari</label>
                            <select id="pekan-hari-<?php echo (int) $h; ?>" name="hari_pekan" title="Hari kunjungan, dipakai untuk mengecek hari tutup">
                                <option value="0">-</option>
                                <?php foreach (daftar_hari_pekan() as $n_hari => $nama_hari) { ?>
                                    <option value="<?php echo (int) $n_hari; ?>"<?php echo ((int) $jad_h["pekan"] === (int) $n_hari) ? ' selected="selected"' : ""; ?>><?php echo e($nama_hari); ?></option>
                                <?php } ?>
                            </select>
                            <button type="submit" name="aksi" value="jam_hari" class="btn-kecil">Atur jam</button>
                            <span class="kecil"><?php echo count($blok["item"]); ?> destinasi &middot; &plusmn; <?php echo e(jam_ringkas($jad_h["menit_kunjungan"])); ?> jam di lokasi<?php if ($jad_h["menit_jalan"] > 0) { ?> &middot; <?php echo (int) $jad_h["menit_jalan"]; ?> menit perjalanan<?php } ?></span>
                        </div>
                    </form>
                    <?php
                    $tanpa_jam = 0;
                    foreach ($blok["item"] as $r_jam) {
                        if (count(rentang_jam_buka($r_jam["jam_buka"])) === 0) { $tanpa_jam++; }
                    }
                    ?>
                    <?php if ($tanpa_jam > 0) { ?>
                        <p class="jadwal-catatan info"><?php echo $tanpa_jam; ?> destinasi belum punya data jam buka, jadi jam jadwalnya belum bisa dicek.</p>
                    <?php } ?>

                    <div class="daftar">
                        <?php foreach ($blok["item"] as $i => $r) { ?>
                            <div class="baris baris-rencana">
                                <?php echo tampil_foto($r["foto"], $r["kategori"], $r["nama"]); ?>
                                <?php $jad_i = $jad_h["item"][$i]; ?>
                                <div class="baris-info">
                                    <p class="baris-jadwal">
                                        <strong><?php echo e(menit_ke_jam($jad_i["tiba"])); ?></strong> tiba
                                        &middot; selesai &plusmn; <?php echo e(menit_ke_jam($jad_i["pulang"])); ?>
                                        <?php if ($jad_i["menit_jalan"] > 0) { ?>&middot; <?php echo e(number_format($jad_i["km"], 1, ",", ".")); ?> km (<?php echo (int) $jad_i["menit_jalan"]; ?> mnt)<?php } ?>
                                    </p>
                                    <h3><a href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $r["did"]; ?>"><?php echo ($i + 1) . ". " . e($r["nama"]); ?></a></h3>
                                    <p class="baris-meta"><?php echo e($r["kategori"]); ?> &middot; <?php echo e($r["lokasi"]); ?><span class="cetak-saja"> &middot; Jam buka: <?php echo e($r["jam_buka"] ?: "belum tersedia"); ?></span></p>
                                    <?php foreach ($jad_i["catatan"] as $c_jad) { ?>
                                        <p class="jadwal-catatan <?php echo e($c_jad[0]); ?>"><?php echo e($c_jad[1]); ?></p>
                                    <?php } ?>
                                </div>
                                <div class="baris-harga"><?php echo e(rencana_harga($r["_min"], $r["_maks"])); ?><span class="kecil"> /orang</span></div>

                                <div class="rencana-aksi tidak-dicetak">
                                    <form method="post" action="rencana.php" class="inline">
                                        <div class="inline">
                                            <?php echo rencana_hidden($csrf_token, $orang, $r["id"]); ?>
                                            <select name="hari" title="Pindah ke hari">
                                                <?php for ($n = 1; $n <= $maks_hari; $n++) { ?>
                                                    <option value="<?php echo $n; ?>"<?php if ($n === (int) $h) { echo ' selected="selected"'; } ?>>Hari <?php echo $n; ?></option>
                                                <?php } ?>
                                            </select>
                                            <button type="submit" name="aksi" value="pindah_hari" class="btn-kecil">Pindah</button>
                                        </div>
                                    </form>
                                    <form method="post" action="rencana.php" class="inline">
                                        <div class="inline">
                                            <?php echo rencana_hidden($csrf_token, $orang, $r["id"]); ?>
                                            <button type="submit" name="aksi" value="naik" class="btn-kecil" title="Naikkan urutan"<?php if ($i === 0) { echo ' disabled="disabled"'; } ?>>&uarr;</button>
                                            <button type="submit" name="aksi" value="turun" class="btn-kecil" title="Turunkan urutan"<?php if ($i === count($blok["item"]) - 1) { echo ' disabled="disabled"'; } ?>>&darr;</button>
                                        </div>
                                    </form>
                                    <form method="post" action="rencana.php" class="inline" onsubmit="return confirm('Hapus destinasi ini dari rencana?');">
                                        <div class="inline">
                                            <?php echo rencana_hidden($csrf_token, $orang, $r["id"]); ?>
                                            <button type="submit" name="aksi" value="hapus" class="btn-kecil btn-bahaya">Hapus</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <div class="cetak-catatan">
                <p class="kecil">Rencana ini dibuat dengan Dolan Jogja. Jam buka dan harga tiket dapat berubah; konfirmasi ulang sebelum berangkat.</p>
            </div>

        <?php } ?>
    </div>
</div>

<?php if (count($hari_list) > 0) { ?>
<script type="text/javascript" src="<?php echo BASE_URL; ?>assets/js/qrcode.js"></script>
<?php } ?>
<script type="text/javascript">
//<![CDATA[
function cetakRencana() { window.print(); }

function salinRingkasan() {
    var teks = document.getElementById("ringkasan-teks").value;
    var status = document.getElementById("status-salin");
    function ok() { status.textContent = "Ringkasan disalin."; }
    function gagal() { status.textContent = "Gagal menyalin. Gunakan tombol Cetak."; }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(teks).then(ok, gagal);
        return;
    }
    var t = document.createElement("textarea");
    t.value = teks;
    document.body.appendChild(t);
    t.select();
    try { document.execCommand("copy") ? ok() : gagal(); } catch (e) { gagal(); }
    document.body.removeChild(t);
}

(function () {
    var inTrans = document.getElementById("biaya-transport");
    var inKons  = document.getElementById("biaya-konsumsi");
    var moda    = document.getElementById("moda-transport");
    var elTotal = document.getElementById("total-gabungan");
    var elOrang = document.getElementById("patungan-orang");
    var cetakBiaya = document.getElementById("cetak-biaya-transport");
    if (!inTrans || !inKons || !elTotal || !elOrang) { return; }

    var tiketMin = <?php echo (int) ($total_min * $orang); ?>;
    var tiketMaks = <?php echo (int) ($total_maks * $orang); ?>;
    var jmlOrang = <?php echo (int) $orang; ?>;
    var jarakKm = <?php echo json_encode((float) $jarak_total); ?>;

    function rp(n) { return "Rp " + Math.round(n).toLocaleString("id-ID"); }
    function fmt(a, b) { return a <= 0 && b <= 0 ? "Gratis" : (a === b ? rp(a) : rp(a) + " - " + rp(b)); }

    function update() {
        var trans = Math.max(0, parseInt(inTrans.value, 10) || 0);
        var kons = Math.max(0, parseInt(inKons.value, 10) || 0);
        var totMin = tiketMin + trans + kons;
        var totMaks = tiketMaks + trans + kons;
        elTotal.textContent = fmt(totMin, totMaks);
        elOrang.textContent = fmt(totMin / jmlOrang, totMaks / jmlOrang);
        if (cetakBiaya) {
            cetakBiaya.innerHTML = "Estimasi transport: <strong>" + (trans > 0 ? rp(trans) : "-") + "</strong>";
        }
    }

    /* Moda transportasi: biaya = jarak rute x tarif per km. */
    if (moda) {
        moda.addEventListener("change", function () {
            var tarif = parseInt(moda.value, 10) || 0;
            if (tarif > 0) {
                inTrans.value = Math.round(jarakKm * tarif);
            }
            update();
        });
    }

    inTrans.addEventListener("input", update);
    inKons.addEventListener("input", update);
    update();
})();

/* Kode QR lokal untuk header cetak: dibuat di browser, tanpa layanan pihak ketiga */
(function () {
    var wadah = document.getElementById("qr-rencana");
    if (!wadah || typeof qrcode === "undefined") { return; }
    try {
        var qr = qrcode(0, "M");
        qr.addData(window.location.href);
        qr.make();
        wadah.innerHTML = qr.createSvgTag({ cellSize: 3, margin: 0 });
    } catch (e) {
        wadah.className += " qr-gagal";
        wadah.innerHTML = '<span class="kecil">QR tidak tersedia</span>';
    }
})();
//]]>
</script>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
