<?php
/* init.php - bootstrap aplikasi: sesi, koneksi, helper, dan template halaman */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        "httponly" => true,
        "samesite" => "Lax",
        "secure"   => !empty($_SERVER["HTTPS"]),
    ]);
    session_start();
}
require_once __DIR__ . "/../config/koneksi.php";

/* BASE = path URL folder proyek, mis. "/dolan_jogja/" */
$_root = str_replace("\\", "/", (string) realpath(__DIR__ . "/.."));
$_doc  = str_replace("\\", "/", (string) realpath($_SERVER["DOCUMENT_ROOT"] ?? ""));
$_base = "/";
if ($_doc !== "" && strpos($_root, $_doc) === 0) {
    $_base = rtrim(substr($_root, strlen($_doc)), "/") . "/";
}
define("BASE", $_base);

/* ---------- Pengguna yang sedang login (dimuat ulang dari DB tiap request) ---------- */
$USER = null;
if (!empty($_SESSION["user_id"])) {
    $s = $conn->prepare("SELECT id,username,email,nama_lengkap,no_hp,foto,role,created_at FROM users WHERE id=?");
    $uid_sesi = (int) $_SESSION["user_id"];
    $s->bind_param("i", $uid_sesi);
    $s->execute();
    $USER = $s->get_result()->fetch_assoc();
    if (!$USER) { $_SESSION = array(); }
}

/* ---------- Helper umum ---------- */
function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8"); }
function url($p = "") { return e(BASE . $p); }
function redirect($p) { header("Location: " . BASE . $p); exit; }
function login() { global $USER; return $USER !== null; }
function admin() { global $USER; return $USER !== null && $USER["role"] === "admin"; }

function rupiah($n) { return (int) $n === 0 ? "Gratis" : "Rp " . number_format((int) $n, 0, ",", "."); }
function harga($min, $max) {
    return ($max !== null && (int) $max > (int) $min) ? rupiah($min) . " - " . rupiah($max) : rupiah($min);
}
function tanggal($ts) {
    $bulan = array("", "Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des");
    $t = strtotime($ts);
    return date("j", $t) . " " . $bulan[(int) date("n", $t)] . " " . date("Y", $t);
}

function flash($tipe, $pesan) { $_SESSION["flash"] = array($tipe, $pesan); }
function tampil_flash() {
    if (!empty($_SESSION["flash"])) {
        $f = $_SESSION["flash"];
        unset($_SESSION["flash"]);
        echo '<div class="alert alert-' . e($f[0]) . '">' . e($f[1]) . '</div>';
    }
}

function wajib_login() {
    if (!login()) { flash("error", "Silakan masuk terlebih dahulu."); redirect("auth/login.php"); }
}
function wajib_admin() {
    wajib_login();
    if (!admin()) { flash("error", "Halaman ini khusus administrator."); redirect("index.php"); }
}

/* ---------- CSRF ---------- */
function csrf() {
    if (empty($_SESSION["csrf"])) { $_SESSION["csrf"] = bin2hex(random_bytes(32)); }
    return $_SESSION["csrf"];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '" />'; }
function cek_csrf() {
    if (!hash_equals($_SESSION["csrf"] ?? "", (string) ($_POST["csrf"] ?? ""))) {
        http_response_code(403);
        exit("Permintaan tidak valid (token CSRF).");
    }
}

/* Form kecil untuk aksi POST (hapus, dll) - valid XHTML Strict (form berisi div).
   $action harus sudah berupa hasil url() (sudah di-escape). */
function form_aksi($action, $aksi, $id, $label, $pesan = "", $kelas = "link-danger") {
    $on = $pesan !== "" ? ' onsubmit="return konfirmasi(\'' . e(addslashes($pesan)) . '\');"' : "";
    return '<form class="inline" method="post" action="' . $action . '"' . $on . '><div>'
        . csrf_field()
        . '<input type="hidden" name="aksi" value="' . e($aksi) . '" />'
        . '<input type="hidden" name="id" value="' . (int) $id . '" />'
        . '<button type="submit" class="' . e($kelas) . '">' . e($label) . '</button>'
        . '</div></form>';
}

/* ---------- Upload gambar (profil / destinasi) ---------- */
/* Return: array(nama_file|null, pesan_error|null). Tidak ada file dipilih => array(null, null) */
function simpan_gambar($f, $folder) {
    if (!isset($f["error"]) || $f["error"] === UPLOAD_ERR_NO_FILE) { return array(null, null); }
    if ($f["error"] !== UPLOAD_ERR_OK) { return array(null, "Upload gagal (kode " . (int) $f["error"] . ")."); }
    if ($f["size"] > 2 * 1024 * 1024) { return array(null, "Ukuran foto maksimal 2 MB."); }
    $fi   = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($f["tmp_name"]);
    $peta = array("image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp");
    if (!isset($peta[$mime]) || @getimagesize($f["tmp_name"]) === false) {
        return array(null, "Format foto harus JPG, PNG, atau WEBP.");
    }
    $dir = __DIR__ . "/../uploads/" . $folder;
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    $nama = bin2hex(random_bytes(10)) . "." . $peta[$mime];
    if (!move_uploaded_file($f["tmp_name"], $dir . "/" . $nama)) {
        return array(null, "Foto gagal disimpan di server.");
    }
    return array($nama, null);
}
function hapus_gambar($folder, $nama) {
    if ($nama && preg_match('/^[a-f0-9]{20}\.(jpg|png|webp)$/', $nama)) {
        @unlink(__DIR__ . "/../uploads/" . $folder . "/" . $nama);
    }
}
function foto_src($folder, $nama) { return $nama ? url("uploads/" . $folder . "/" . $nama) : ""; }

/* Avatar: foto jika ada, jika tidak inisial nama */
function avatar($u, $kelas = "avatar") {
    if (!empty($u["foto"])) {
        return '<img class="' . e($kelas) . '" src="' . foto_src("profil", $u["foto"]) . '" alt="Foto ' . e($u["nama_lengkap"]) . '" />';
    }
    return '<div class="' . e($kelas) . '">' . e(mb_strtoupper(mb_substr($u["nama_lengkap"], 0, 1))) . '</div>';
}

/* Kartu destinasi untuk daftar di beranda */
function kartu_destinasi($d) {
    $gambar = !empty($d["foto"])
        ? '<img class="foto" src="' . foto_src("destinasi", $d["foto"]) . '" alt="' . e($d["nama"]) . '" />'
        : '<div class="placeholder ph-' . ((int) $d["id"] % 5) . '"><span>' . e(mb_substr($d["nama"], 0, 1)) . '</span></div>';
    $rating = $d["rating"] ? "&#9733; " . e($d["rating"]) . " (" . (int) $d["total_review"] . ")" : "Belum ada ulasan";
    return '<div class="place-card"><div class="place-image">' . $gambar
        . '<span class="tag">' . e($d["kategori"]) . '</span></div>'
        . '<div class="place-body"><h3>' . e($d["nama"]) . '</h3>'
        . '<p class="muted">' . e($d["lokasi"]) . '</p>'
        . '<div class="place-meta"><strong>' . harga($d["harga_tiket"], $d["harga_maks"]) . '</strong><span>' . $rating . '</span></div>'
        . '<a class="text-link" href="' . url("destinasi/detail.php?id=" . (int) $d["id"]) . '">Lihat detail &#8594;</a></div></div>';
}

/* ---------- Template halaman (XHTML 1.0 Strict) ---------- */
function header_web($title = "Dolan Jogja") {
    global $USER;
    header("Content-Type: text/html; charset=UTF-8");
    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">' . "\n";
    ?>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="id" lang="id">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo e($title); ?> | Dolan Jogja</title>
<link rel="stylesheet" type="text/css" href="<?php echo url("assets/css/style.css"); ?>" />
<script type="text/javascript" src="<?php echo url("assets/js/app.js"); ?>" defer="defer"></script>
</head>
<body>
<div class="topbar">
<a class="brand" href="<?php echo url("index.php"); ?>"><span class="brand-mark">D</span>Dolan<span>Jogja</span></a>
<button id="nav-button" class="nav-toggle" type="button">Menu</button>
<div id="nav" class="nav">
<a href="<?php echo url("index.php"); ?>">Beranda</a>
<a href="<?php echo url("index.php#destinasi"); ?>">Destinasi</a>
<?php if (login()) { ?>
<a href="<?php echo url("user/wishlist.php"); ?>">Favorit</a>
<a href="<?php echo url("user/profil.php"); ?>">Profil</a>
<?php if (admin()) { ?><a href="<?php echo url("admin/index.php"); ?>">Admin</a><?php } ?>
<a class="nav-cta" href="<?php echo url("auth/logout.php"); ?>">Keluar</a>
<?php } else { ?>
<a href="<?php echo url("auth/login.php"); ?>">Masuk</a>
<a class="nav-cta" href="<?php echo url("auth/register.php"); ?>">Daftar</a>
<?php } ?>
</div>
</div>
<div class="container">
<?php tampil_flash(); ?>
<?php
}

function subnav_admin($aktif) {
    $menu = array("index" => "Dashboard", "kategori" => "Kategori", "pengguna" => "Pengguna", "destinasi" => "+ Destinasi");
    echo '<div class="subnav">';
    foreach ($menu as $k => $label) {
        echo '<a class="' . ($k === $aktif ? "aktif" : "") . '" href="' . url("admin/" . $k . ".php") . '">' . e($label) . '</a>';
    }
    echo '</div>';
}

function footer_web() { ?>
</div>
<div class="footer">
<div><strong>Dolan Jogja</strong><p>Temukan inspirasi perjalanan dan destinasi menarik di Yogyakarta.</p></div>
<small>Proyek Tugas Besar Pemrograman Web Dasar - 2026/2027</small>
</div>
</body>
</html>
<?php }
