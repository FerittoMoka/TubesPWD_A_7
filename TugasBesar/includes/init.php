<?php
/*
 * Lapisan kompatibilitas untuk halaman admin/ (admin/index.php, destinasi.php, kategori.php, pengguna.php, ulasan.php).
 * Dibangun di atas auth.php, fungsi.php, dan csrf.php milik proyek ini, jadi tidak ada kerangka kedua.
 * Variabel global yang tersedia setelah file ini dimuat: $conn, dan $USER (setelah wajib_admin()).
 */
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/csrf.php";
require_once __DIR__ . "/pesan.php";
require_once __DIR__ . "/keamanan.php";

if (!function_exists('url')) {
    function url($path = "") { return BASE_URL . ltrim($path, "/"); }
}
if (!function_exists('redirect')) {
    function redirect($path) { header("Location: " . url($path)); exit; }
}
if (!function_exists('flash')) {
    function flash($jenis, $teks) { set_pesan($jenis === "error" ? "error" : "sukses", $teks); }
}
if (!function_exists('wajib_admin')) {
    function wajib_admin() {
        global $USER;
        cek_admin();
        $USER = array("id" => (int) $_SESSION["user_id"], "username" => $_SESSION["username"] ?? "", "role" => $_SESSION["role"] ?? "");
    }
}
if (!function_exists('cek_csrf')) {
    function cek_csrf() {
        if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
            flash("error", "Token keamanan tidak valid. Silakan coba lagi.");
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }
    }
}
if (!function_exists('tanggal')) {
    function tanggal($waktu) {
        $bln = array(1 => "Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des");
        $t = strtotime((string) $waktu);
        return $t ? date("j", $t) . " " . $bln[(int) date("n", $t)] . " " . date("Y", $t) : "-";
    }
}
if (!function_exists('harga')) {
    function harga($min, $maks = null) {
        $min = (int) $min;
        $maks = ($maks === null || $maks === "") ? null : (int) $maks;
        if ($min === 0 && !$maks) { return "Gratis"; }
        $rp = function ($n) { return "Rp " . number_format($n, 0, ",", "."); };
        return $maks && $maks > $min ? $rp($min) . " - " . $rp($maks) : $rp($min);
    }
}
if (!function_exists('form_aksi')) {
    // Form kecil berisi satu tombol aksi (mis. Hapus) dengan token CSRF dan konfirmasi opsional.
    function form_aksi($action, $aksi, $id, $label, $konfirmasi = "") {
        $js = $konfirmasi !== "" ? ' onsubmit="return confirm(' . e(json_encode($konfirmasi)) . ');"' : "";
        return '<form class="inline" method="post" action="' . e($action) . '"' . $js . '><div class="inline">'
            . csrf_field()
            . '<input type="hidden" name="aksi" value="' . e($aksi) . '" />'
            . '<input type="hidden" name="id" value="' . (int) $id . '" />'
            . '<button type="submit" class="link-btn' . ($aksi === "hapus" ? " bahaya" : "") . '">' . e($label) . '</button>'
            . '</div></form>';
    }
}
if (!function_exists('subnav_admin')) {
    function subnav_admin($aktif) {
        $menu = array(
            "index"     => array("admin/index.php", "Ringkasan"),
            "destinasi" => array("admin/destinasi.php", "Tambah destinasi"),
            "kategori"  => array("admin/kategori.php", "Kategori"),
            "ulasan"    => array("admin/ulasan.php", "Ulasan"),
            "pengguna"  => array("admin/pengguna.php", "Pengguna"),
        );
        echo '<div class="subnav">';
        foreach ($menu as $kunci => $m) {
            echo '<a' . ($kunci === $aktif ? ' class="aktif"' : '') . ' href="' . e(url($m[0])) . '">' . e($m[1]) . '</a>';
        }
        echo '</div>';
    }
}
if (!function_exists('header_web')) {
    function header_web($judul = "Admin") {
        $menu_aktif = "admin";
        require_once __DIR__ . "/header.php";
        echo '<div class="wadah admin-wrap">';
        tampil_pesan();
    }
}
if (!function_exists('footer_web')) {
    function footer_web() {
        echo '</div>';
        require_once __DIR__ . "/footer.php";
    }
}

/* ---------- Unggah gambar: disimpan di assets/uploads/<folder>/ ---------- */
function _folder_gambar($folder) {
    return in_array($folder, array("destinasi", "profil"), true) ? $folder : null;
}
if (!function_exists('simpan_gambar')) {
    // Mengembalikan array(nama_file | null, pesan_error | null)
    function simpan_gambar($file, $folder) {
        $folder = _folder_gambar($folder);
        if ($folder === null) { return array(null, "Folder gambar tidak valid."); }
        if (empty($file) || ($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return array(null, null); }
        if ($file["error"] !== UPLOAD_ERR_OK) { return array(null, "Foto gagal diunggah."); }
        if ((int) $file["size"] > 2097152) { return array(null, "Foto maksimal 2 MB."); }

        $boleh = array("image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp");
        $tipe = function_exists("mime_content_type") ? mime_content_type($file["tmp_name"]) : "";
        if (!isset($boleh[$tipe])) { return array(null, "Foto harus berformat JPG, PNG, atau WEBP."); }

        $dir = __DIR__ . "/../assets/uploads/" . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) { return array(null, "Folder penyimpanan tidak dapat dibuat."); }
        $nama = $folder . "_" . bin2hex(random_bytes(8)) . "." . $boleh[$tipe];
        if (!move_uploaded_file($file["tmp_name"], $dir . "/" . $nama)) { return array(null, "Foto gagal disimpan."); }
        return array($nama, null);
    }
}
if (!function_exists('hapus_gambar')) {
    function hapus_gambar($folder, $nama) {
        $folder = _folder_gambar($folder);
        if ($folder === null || $nama === null || $nama === "") { return; }
        $path = __DIR__ . "/../assets/uploads/" . $folder . "/" . basename($nama);
        if (is_file($path)) { @unlink($path); }
    }
}
if (!function_exists('foto_src')) {
    function foto_src($folder, $nama) {
        return e(BASE_URL . "assets/uploads/" . $folder . "/" . rawurlencode((string) $nama));
    }
}