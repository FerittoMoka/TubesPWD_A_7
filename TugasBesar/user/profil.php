<?php
require_once "../config/koneksi.php";
require_once "../includes/auth.php";
require_once "../includes/csrf.php";
cek_login();

$pesan = "";
$jenis_pesan = "";
$tab_aktif = $_GET["tab"] ?? "biodata";
$user_id = (int) $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "update_biodata";

    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $pesan = "Token keamanan tidak valid.";
        $jenis_pesan = "error";
    } elseif ($aksi === "update_biodata") {
        $tab_aktif = "biodata";
        $nama_lengkap = trim($_POST["nama_lengkap"] ?? "");
        $username = trim($_POST["username"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $no_hp = trim($_POST["no_hp"] ?? "");

        if ($nama_lengkap === "" || $username === "" || $email === "") {
            $pesan = "Nama lengkap, username, dan email wajib diisi.";
            $jenis_pesan = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pesan = "Format email tidak valid.";
            $jenis_pesan = "error";
        } elseif (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
            $pesan = "Username hanya boleh huruf, angka, titik, garis bawah, atau strip.";
            $jenis_pesan = "error";
        } else {
            $cek = mysqli_prepare($conn, "SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1");
            mysqli_stmt_bind_param($cek, "ssi", $username, $email, $user_id);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);

            if (mysqli_stmt_num_rows($cek) > 0) {
                $pesan = "Username atau email sudah digunakan pengguna lain.";
                $jenis_pesan = "error";
                mysqli_stmt_close($cek);
            } else {
                mysqli_stmt_close($cek);

                $foto_baru = null;
                if (isset($_FILES["foto"]) && $_FILES["foto"]["error"] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
                        $pesan = "Foto gagal diunggah.";
                        $jenis_pesan = "error";
                    } else {
                        $size = (int) $_FILES["foto"]["size"];
                        $tipe = mime_content_type($_FILES["foto"]["tmp_name"]);
                        $tipe_valid = array("image/jpeg", "image/png", "image/webp");
                        if ($size > 2097152 || !in_array($tipe, $tipe_valid, true)) {
                            $pesan = "Foto harus JPG, PNG, atau WEBP maksimal 2 MB.";
                            $jenis_pesan = "error";
                        } else {
                            // Ekstensi ditentukan dari tipe file yang terverifikasi, bukan dari nama kiriman pengguna
                            $peta_ext = array("image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp");
                            $ext = $peta_ext[$tipe];
                            $foto_baru = "user_" . $user_id . "_" . time() . "." . $ext;
                            $folder = "../assets/uploads/profil";
                            if (!is_dir($folder)) {
                                mkdir($folder, 0777, true);
                            }
                            if (!move_uploaded_file($_FILES["foto"]["tmp_name"], $folder . "/" . $foto_baru)) {
                                $foto_baru = null;
                                $pesan = "Foto gagal disimpan.";
                                $jenis_pesan = "error";
                            }
                        }
                    }
                }

                if ($jenis_pesan !== "error") {
                    $foto_lama = null;
                    if ($foto_baru !== null) {
                        $q_lama = mysqli_prepare($conn, "SELECT foto FROM users WHERE id = ? LIMIT 1");
                        mysqli_stmt_bind_param($q_lama, "i", $user_id);
                        mysqli_stmt_execute($q_lama);
                        $r_lama = mysqli_fetch_assoc(mysqli_stmt_get_result($q_lama));
                        mysqli_stmt_close($q_lama);
                        $foto_lama = $r_lama["foto"] ?? null;
                    }
                    if ($foto_baru !== null) {
                        $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap=?, username=?, email=?, no_hp=?, foto=? WHERE id=?");
                        mysqli_stmt_bind_param($stmt, "sssssi", $nama_lengkap, $username, $email, $no_hp, $foto_baru, $user_id);
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap=?, username=?, email=?, no_hp=? WHERE id=?");
                        mysqli_stmt_bind_param($stmt, "ssssi", $nama_lengkap, $username, $email, $no_hp, $user_id);
                    }

                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION["username"] = $username;
                        if ($foto_baru !== null && !empty($foto_lama) && $foto_lama !== $foto_baru) {
                            $path_lama = "../assets/uploads/profil/" . basename($foto_lama);
                            if (is_file($path_lama)) { @unlink($path_lama); }
                        }
                        $pesan = "Biodata dan foto profil berhasil diperbarui.";
                        $jenis_pesan = "sukses";
                    } else {
                        $pesan = "Profil gagal diperbarui.";
                        $jenis_pesan = "error";
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    } elseif ($aksi === "update_password") {
        $tab_aktif = "keamanan";
        $password_lama = $_POST["password_lama"] ?? "";
        $password_baru = $_POST["password_baru"] ?? "";
        $konfirmasi_password = $_POST["konfirmasi_password"] ?? "";

        if ($password_lama === "" || $password_baru === "" || $konfirmasi_password === "") {
            $pesan = "Semua kolom password wajib diisi.";
            $jenis_pesan = "error";
        } elseif (strlen($password_baru) < 6) {
            $pesan = "Password baru minimal 6 karakter.";
            $jenis_pesan = "error";
        } elseif ($password_baru !== $konfirmasi_password) {
            $pesan = "Konfirmasi password baru tidak cocok.";
            $jenis_pesan = "error";
        } else {
            $q_pass = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($q_pass, "i", $user_id);
            mysqli_stmt_execute($q_pass);
            $data_pass = mysqli_fetch_assoc(mysqli_stmt_get_result($q_pass));
            mysqli_stmt_close($q_pass);

            if (!$data_pass || !password_verify($password_lama, $data_pass["password"])) {
                $pesan = "Password lama yang Anda masukkan salah.";
                $jenis_pesan = "error";
            } else {
                $hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $hash, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $pesan = "Password berhasil diperbarui.";
                    $jenis_pesan = "sukses";
                } else {
                    $pesan = "Gagal mengganti password.";
                    $jenis_pesan = "error";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$stmt = mysqli_prepare($conn, "SELECT username, email, nama_lengkap, no_hp, foto, role, created_at FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$riwayat_ulasan = array();
$q_rev = mysqli_prepare(
    $conn,
    "SELECT r.*, d.nama AS nama_destinasi, d.lokasi AS lokasi_destinasi
     FROM reviews r
     JOIN destinations d ON r.destination_id = d.id
     WHERE r.user_id = ?
     ORDER BY r.id DESC"
);
if ($q_rev) {
    mysqli_stmt_bind_param($q_rev, "i", $user_id);
    mysqli_stmt_execute($q_rev);
    $res_rev = mysqli_stmt_get_result($q_rev);
    while ($row = mysqli_fetch_assoc($res_rev)) {
        $riwayat_ulasan[] = $row;
    }
    mysqli_stmt_close($q_rev);
}

$daftar_favorit = array();

$wilayah_diy = array("Kota Yogyakarta", "Sleman", "Bantul", "Gunungkidul", "Kulon Progo");
$wilayah_terjelajah = array();
foreach ($riwayat_ulasan as $ru) {
    foreach ($wilayah_diy as $wd) {
        if (stripos((string) ($ru["lokasi_destinasi"] ?? ""), $wd) !== false) {
            $wilayah_terjelajah[$wd] = true;
        }
    }
}
foreach ($daftar_favorit as $df) {
    foreach ($wilayah_diy as $wd) {
        if (stripos((string) ($df["lokasi"] ?? ""), $wd) !== false) {
            $wilayah_terjelajah[$wd] = true;
        }
    }
}

$q_fav = mysqli_prepare(
    $conn,
    "SELECT d.id, d.nama, d.lokasi, d.foto, d.harga_tiket, d.harga_maks, c.nama AS kategori
     FROM favorites f
     JOIN destinations d ON f.destination_id = d.id
     JOIN categories c ON d.category_id = c.id
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC"
);
if ($q_fav) {
    mysqli_stmt_bind_param($q_fav, "i", $user_id);
    mysqli_stmt_execute($q_fav);
    $daftar_favorit = mysqli_fetch_all(mysqli_stmt_get_result($q_fav), MYSQLI_ASSOC);
    mysqli_stmt_close($q_fav);
}
$jml_favorit = count($daftar_favorit);

$jml_rencana = 0;
$q_rcn = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM wishlists WHERE user_id = ?");
if ($q_rcn) {
    mysqli_stmt_bind_param($q_rcn, "i", $user_id);
    mysqli_stmt_execute($q_rcn);
    $jml_rencana = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($q_rcn))["n"];
    mysqli_stmt_close($q_rcn);
}

$jml_ulasan = count($riwayat_ulasan);

$daftar_favorit = array();
$q_fav = mysqli_prepare(
    $conn,
    "SELECT d.id, d.nama, d.lokasi, d.foto, d.harga_tiket, d.harga_maks, c.nama AS kategori
     FROM favorites f
     JOIN destinations d ON f.destination_id = d.id
     JOIN categories c ON d.category_id = c.id
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC"
);
if ($q_fav) {
    mysqli_stmt_bind_param($q_fav, "i", $user_id);
    mysqli_stmt_execute($q_fav);
    $daftar_favorit = mysqli_fetch_all(mysqli_stmt_get_result($q_fav), MYSQLI_ASSOC);
    mysqli_stmt_close($q_fav);
}
$jml_favorit = count($daftar_favorit);

$jml_rencana = 0;
$q_rcn = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM wishlists WHERE user_id = ?");
if ($q_rcn) {
    mysqli_stmt_bind_param($q_rcn, "i", $user_id);
    mysqli_stmt_execute($q_rcn);
    $jml_rencana = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($q_rcn))["n"];
    mysqli_stmt_close($q_rcn);
}

$wilayah_diy = array("Kota Yogyakarta", "Sleman", "Bantul", "Gunungkidul", "Kulon Progo");
$wilayah_terjelajah = array();
foreach ($riwayat_ulasan as $ru) {
    foreach ($wilayah_diy as $wd) {
        if (stripos((string) ($ru["lokasi_destinasi"] ?? ""), $wd) !== false) {
            $wilayah_terjelajah[$wd] = true;
        }
    }
}
foreach ($daftar_favorit as $df) {
    foreach ($wilayah_diy as $wd) {
        if (stripos((string) ($df["lokasi"] ?? ""), $wd) !== false) {
            $wilayah_terjelajah[$wd] = true;
        }
    }
}
if ($jml_ulasan === 0) {
    $badge_traveler = "Wisatawan Baru";
    $badge_kelas = "badge-baru";
} elseif ($jml_ulasan <= 3) {
    $badge_traveler = "Penjelajah Jogja";
    $badge_kelas = "badge-penjelajah";
} else {
    $badge_traveler = "Duta Wisata Istimewa";
    $badge_kelas = "badge-duta";
}

$inisial = mb_strtoupper(mb_substr(trim($user["nama_lengkap"] ?: $user["username"]), 0, 1, "UTF-8"), "UTF-8");
$tgl_gabung = !empty($user["created_at"]) ? date("d M Y", strtotime($user["created_at"])) : "-";
$url_foto = !empty($user["foto"]) ? BASE_URL . "assets/uploads/profil/" . e($user["foto"]) : "";

$csrf_token = generate_csrf_token();
$judul = "Profil";
$menu_aktif = "profil";
require_once "../includes/header.php";
?>
<div class="wadah">
    <div class="halaman-isi">
        <div class="profil-kepala">
            <div>
                <p class="eyebrow">Akun Pengguna</p>
                <h1>Profil Pengguna</h1>
            </div>
            <div class="profil-kepala-aksi">
                <?php if (($user["role"] ?? "") === "admin") { ?>
                    <a class="tombol" href="<?php echo BASE_URL; ?>admin/index.php">Panel Admin</a>
                <?php } ?>
                <a class="tombol" href="<?php echo BASE_URL; ?>user/rencana.php">Rencana Dolan</a>
                <a class="tombol tombol-garis" href="<?php echo BASE_URL; ?>index.php">Kembali ke Beranda</a>
            </div>
        </div>

        <?php if ($pesan !== "") { ?>
            <div class="alert <?php echo $jenis_pesan === "sukses" ? "alert-success" : "alert-error"; ?>">
                <?php echo e($pesan); ?>
            </div>
        <?php } ?>

        <div class="profil-grid">
            <aside class="panel profil-kartu">
                <div class="profil-cover"></div>

                <div class="profil-avatar-bingkai" id="avatarPreviewBox">
                    <?php if ($url_foto !== "") { ?>
                        <img src="<?php echo $url_foto; ?>" alt="Foto <?php echo e($user["nama_lengkap"]); ?>" id="avatarPreviewImg" width="116" height="116" />
                    <?php } else { ?>
                        <span class="profil-inisial" id="avatarInitial"><?php echo e($inisial); ?></span>
                        <img src="" alt="Pratinjau foto" id="avatarPreviewImg" class="sembunyi" />
                    <?php } ?>
                </div>

                <div class="profil-badge-baris">
                    <span class="label"><?php echo e(strtoupper($user["role"] ?: "pengguna")); ?></span>
                    <span class="badge-traveler <?php echo $badge_kelas; ?>"><?php echo e($badge_traveler); ?></span>
                </div>

                <h2 class="profil-nama"><?php echo e($user["nama_lengkap"]); ?></h2>
                <p class="profil-handle">@<?php echo e($user["username"]); ?></p>

                <div class="profil-statistik profil-statistik-3">
                    <div>
                        <strong><?php echo $jml_ulasan; ?></strong>
                        <span>Ulasan</span>
                    </div>
                    <div>
                        <strong><?php echo $jml_favorit; ?></strong>
                        <span>Favorit</span>
                    </div>
                    <div>
                        <strong><?php echo $jml_rencana; ?></strong>
                        <span>Rencana</span>
                    </div>
                </div>

                <div class="profil-meta-list">
                    <div class="profil-meta-item">
                        <span>Email</span>
                        <strong><?php echo e($user["email"]); ?></strong>
                    </div>
                    <div class="profil-meta-item">
                        <span>Nomor HP</span>
                        <strong><?php echo e(!empty($user["no_hp"]) ? $user["no_hp"] : "Belum diisi"); ?></strong>
                    </div>
                    <div class="profil-meta-item">
                        <span>Bergabung Sejak</span>
                        <strong><?php echo e($tgl_gabung); ?></strong>
                    </div>
                </div>

                <div class="paspor-wilayah">
                    <span class="kecil">Jelajah Wilayah DIY (<?php echo count($wilayah_terjelajah); ?>/5)</span>
                    <div class="paspor-wilayah-list">
                        <?php foreach ($wilayah_diy as $wd) { ?>
                            <span class="paspor-pill<?php echo isset($wilayah_terjelajah[$wd]) ? ' aktif' : ''; ?>"><?php echo e($wd); ?></span>
                        <?php } ?>
                    </div>
                </div>

                <div class="profil-logout-area">
                    <a class="tombol tombol-garis btn-keluar-profil" href="<?php echo BASE_URL; ?>auth/logout.php" onclick="return confirm('Keluar dari akun sekarang?');">Keluar dari Akun</a>
                </div>
            </aside>

            <section class="panel profil-form-panel">
                <div class="profil-tabs" role="tablist">
                    <button type="button" class="profil-tab<?php echo $tab_aktif === "biodata" ? " aktif" : ""; ?>" data-tab="biodata">Edit Biodata &amp; Foto</button>
                    <button type="button" class="profil-tab<?php echo $tab_aktif === "keamanan" ? " aktif" : ""; ?>" data-tab="keamanan">Keamanan Akun</button>
                    <button type="button" class="profil-tab<?php echo $tab_aktif === "ulasan" ? " aktif" : ""; ?>" data-tab="ulasan">Riwayat Ulasan (<?php echo $jml_ulasan; ?>)</button>
                    <button type="button" class="profil-tab<?php echo $tab_aktif === "favorit" ? " aktif" : ""; ?>" data-tab="favorit">Destinasi Favorit (<?php echo $jml_favorit; ?>)</button>
                </div>

                <!-- TAB 1: BIODATA & FOTO -->
                <div class="profil-tab-konten<?php echo $tab_aktif !== "biodata" ? " sembunyi" : ""; ?>" id="tab-biodata">
                    <h2>Perbarui Informasi Profil</h2>
                    <p class="muted profil-sub">Kelola identitas akun dan foto profil Anda.</p>

                    <form method="post" action="profil.php" enctype="multipart/form-data" class="form-isi profil-form">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>" />
                        <input type="hidden" name="aksi" value="update_biodata" />

                        <div class="profil-form-grid">
                            <div>
                                <label for="nama_lengkap">Nama lengkap</label>
                                <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo e($user["nama_lengkap"]); ?>" maxlength="100" required="required" />
                            </div>
                            <div>
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" value="<?php echo e($user["username"]); ?>" maxlength="30" required="required" />
                            </div>
                            <div>
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo e($user["email"]); ?>" maxlength="100" required="required" />
                            </div>
                            <div>
                                <label for="no_hp">Nomor HP</label>
                                <input type="text" id="no_hp" name="no_hp" value="<?php echo e($user["no_hp"] ?? ""); ?>" maxlength="20" placeholder="08xxxxxxxxxx" />
                            </div>
                        </div>

                        <div class="profil-bagian-pisah">
                            <h3>Foto Profil</h3>
                            <p class="kecil">Format JPG, PNG, atau WEBP · Maksimal 2 MB. Pratinjau muncul langsung di kartu kiri.</p>
                            <div class="profil-upload-baris">
                                <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" />
                                <span class="kecil" id="namaFileFoto">
                                    <?php echo !empty($user["foto"]) ? "File aktif: " . e($user["foto"]) : "Belum ada foto terpasang"; ?>
                                </span>
                            </div>
                        </div>

                        <div class="profil-aksi">
                            <input type="submit" value="Simpan Perubahan" />
                        </div>
                    </form>
                </div>

                <!-- TAB 2: KEAMANAN AKUN -->
                <div class="profil-tab-konten<?php echo $tab_aktif !== "keamanan" ? " sembunyi" : ""; ?>" id="tab-keamanan">
                    <h2>Ganti Password</h2>
                    <p class="muted profil-sub">Demi keamanan akun, masukkan password lama sebelum membuat password baru.</p>

                    <form method="post" action="profil.php?tab=keamanan" class="form-isi profil-form">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>" />
                        <input type="hidden" name="aksi" value="update_password" />

                        <p>
                            <label for="password_lama">Password lama</label>
                            <input type="password" id="password_lama" name="password_lama" required="required" autocomplete="current-password" />
                        </p>
                        <div class="profil-form-grid">
                            <div>
                                <label for="password_baru">Password baru <span class="kecil">(min. 6 karakter)</span></label>
                                <input type="password" id="password_baru" name="password_baru" minlength="6" required="required" autocomplete="new-password" />
                            </div>
                            <div>
                                <label for="konfirmasi_password">Ulangi password baru</label>
                                <input type="password" id="konfirmasi_password" name="konfirmasi_password" minlength="6" required="required" autocomplete="new-password" />
                            </div>
                        </div>

                        <div class="profil-aksi">
                            <input type="submit" value="Perbarui Password" />
                        </div>
                    </form>

                    <div class="profil-bagian-pisah">
                        <h3>Sesi Login</h3>
                        <p class="kecil">Akhiri sesi akun Anda pada perangkat ini setelah selesai menggunakan Dolan Jogja.</p>
                        <a class="btn-kecil btn-bahaya" href="<?php echo BASE_URL; ?>auth/logout.php" onclick="return confirm('Keluar dari akun sekarang?');">Keluar dari Akun</a>
                    </div>
                </div>

                <!-- TAB 3: RIWAYAT ULASAN -->
                <div class="profil-tab-konten<?php echo $tab_aktif !== "ulasan" ? " sembunyi" : ""; ?>" id="tab-ulasan">
                    <h2>Riwayat Ulasan Saya</h2>
                    <p class="muted profil-sub">Daftar destinasi wisata Yogyakarta yang pernah Anda ulas.</p>

                    <?php if ($jml_ulasan === 0) { ?>
                        <div class="profil-kosong">
                            <p>Anda belum menulis ulasan destinasi mana pun.</p>
                            <a class="tombol" href="<?php echo BASE_URL; ?>index.php#destinasi">Jelajahi Destinasi</a>
                        </div>
                    <?php } else { ?>
                        <div class="profil-ulasan-daftar">
                            <?php foreach ($riwayat_ulasan as $u) {
                                $teks_ulasan = $u["komentar"] ?? ($u["ulasan"] ?? ($u["isi"] ?? ""));
                                $tgl_ulasan = !empty($u["created_at"]) ? date("d M Y", strtotime($u["created_at"])) : "";
                            ?>
                                <div class="profil-ulasan-item">
                                    <div class="profil-ulasan-atas">
                                        <div>
                                            <h3><a href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $u["destination_id"]; ?>"><?php echo e($u["nama_destinasi"]); ?></a></h3>
                                            <p class="kecil"><?php echo e($u["lokasi_destinasi"]); ?><?php echo $tgl_ulasan !== "" ? " · " . e($tgl_ulasan) : ""; ?></p>
                                        </div>
                                        <span class="rating"><span class="bintang"></span> <?php echo (int) $u["rating"]; ?>/5</span>
                                    </div>
                                    <?php if ($teks_ulasan !== "") { ?>
                                        <p class="profil-ulasan-teks"><?php echo e($teks_ulasan); ?></p>
                                    <?php } ?>
                                    <div class="profil-ulasan-aksi">
                                        <a class="btn-kecil" href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $u["destination_id"]; ?>#ulasan">Lihat / Kelola di Halaman Destinasi</a>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

                <!-- TAB 4: DESTINASI FAVORIT -->
                <div class="profil-tab-konten<?php echo $tab_aktif !== "favorit" ? " sembunyi" : ""; ?>" id="tab-favorit">
                    <h2>Destinasi Favorit Saya</h2>
                    <p class="muted profil-sub">Koleksi destinasi yang pernah Anda tandai suka.</p>

                    <?php if ($jml_favorit === 0) { ?>
                        <div class="profil-kosong">
                            <p>Belum ada destinasi yang ditandai favorit.</p>
                            <a class="tombol" href="<?php echo BASE_URL; ?>index.php#destinasi">Cari Destinasi</a>
                        </div>
                    <?php } else { ?>
                        <div class="profil-ulasan-daftar">
                            <?php foreach ($daftar_favorit as $fav) { ?>
                                <div class="profil-ulasan-item">
                                    <div class="profil-ulasan-atas">
                                        <div>
                                            <h3><a href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $fav["id"]; ?>"><?php echo e($fav["nama"]); ?></a></h3>
                                            <p class="kecil"><?php echo e($fav["kategori"]); ?> &middot; <?php echo e($fav["lokasi"]); ?></p>
                                        </div>
                                        <span class="label"><?php echo e(rentang_harga($fav["harga_tiket"], $fav["harga_maks"])); ?></span>
                                    </div>
                                    <div class="profil-ulasan-aksi">
                                        <a class="btn-kecil" href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $fav["id"]; ?>">Lihat Detail</a>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
(function () {
    // 1. Live preview foto profil
    var inputFoto = document.getElementById("foto");
    var imgPreview = document.getElementById("avatarPreviewImg");
    var initialEl = document.getElementById("avatarInitial");
    var labelFile = document.getElementById("namaFileFoto");

    if (inputFoto && imgPreview) {
        inputFoto.addEventListener("change", function () {
            var file = this.files && this.files[0];
            if (!file) return;

            if (labelFile) {
                var kb = (file.size / 1024).toFixed(1);
                labelFile.textContent = "Dipilih: " + file.name + " (" + kb + " KB)";
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                imgPreview.src = e.target.result;
                imgPreview.classList.remove("sembunyi");
                if (initialEl) {
                    initialEl.classList.add("sembunyi");
                }
            };
            reader.readAsDataURL(file);
        });
    }

    // 2. Navigasi Tab Profil
    var tabs = document.querySelectorAll(".profil-tab");
    var panels = document.querySelectorAll(".profil-tab-konten");

    tabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
            var target = this.getAttribute("data-tab");
            tabs.forEach(function (t) { t.classList.remove("aktif"); });
            panels.forEach(function (p) { p.classList.add("sembunyi"); });

            this.classList.add("aktif");
            var panel = document.getElementById("tab-" + target);
            if (panel) {
                panel.classList.remove("sembunyi");
            }
        });
    });
})();
</script>
<?php require_once "../includes/footer.php"; ?>