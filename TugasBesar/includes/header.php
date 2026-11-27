<?php

require_once __DIR__ . "/auth.php";
if (!isset($judul)) { $judul = "Dolan Jogja"; }
if (!isset($menu_aktif)) { $menu_aktif = ""; }

$nav_foto = "";
$nav_inisial = "U";
$nav_jml_rencana = 0;
if (sudah_login() && isset($conn)) {
    $nav_uid = (int) $_SESSION["user_id"];
    $st_nav = mysqli_prepare($conn, "SELECT nama_lengkap, username, foto FROM users WHERE id = ? LIMIT 1");
    if ($st_nav) {
        mysqli_stmt_bind_param($st_nav, "i", $nav_uid);
        mysqli_stmt_execute($st_nav);
        $u_nav = mysqli_fetch_assoc(mysqli_stmt_get_result($st_nav));
        mysqli_stmt_close($st_nav);
        if ($u_nav) {
            $nama_nav = trim($u_nav["nama_lengkap"] ?: $u_nav["username"]);
            $nav_inisial = mb_strtoupper(mb_substr($nama_nav, 0, 1, "UTF-8"), "UTF-8");
            if (!empty($u_nav["foto"])) {
                $nav_foto = BASE_URL . "assets/uploads/profil/" . $u_nav["foto"];
            }
        }
    }
    $st_cnt = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM wishlists WHERE user_id = ?");
    if ($st_cnt) {
        mysqli_stmt_bind_param($st_cnt, "i", $nav_uid);
        mysqli_stmt_execute($st_cnt);
        $r_cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($st_cnt));
        $nav_jml_rencana = (int) ($r_cnt["n"] ?? 0);
        mysqli_stmt_close($st_cnt);
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="id" lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo e($judul); ?> - Dolan Jogja</title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&amp;family=Plus+Jakarta+Sans:wght@400;500;600&amp;display=swap" />
    <link rel="stylesheet" type="text/css" href="<?php echo BASE_URL; ?>assets/css/style.css" />
    <script type="text/javascript" src="<?php echo BASE_URL; ?>assets/js/main.js" defer="defer"></script>
</head>
<body>
<div class="navbar">
    <div class="navbar-isi">
        <a class="logo" href="<?php echo BASE_URL; ?>index.php"><span class="logo-tanda"></span>Dolan Jogja</a>
        <button type="button" class="tombol-menu" onclick="toggleMenu();">Menu</button>
        <div class="menu" id="menu">
            <a href="<?php echo BASE_URL; ?>index.php"<?php echo aktif("beranda", $menu_aktif); ?>>
                <svg class="nav-ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5L12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>
                <span>Beranda</span>
            </a>
            <a href="<?php echo BASE_URL; ?>index.php#destinasi">
                <svg class="nav-ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><polygon points="10 14 14 10 16 16 8 8" fill="currentColor" stroke="none"/></svg>
                <span>Destinasi</span>
            </a>
            <a href="<?php echo BASE_URL; ?>destinasi/terdekat.php"<?php echo aktif("terdekat", $menu_aktif); ?>>
                <svg class="nav-ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-6-5.3-6-10a6 6 0 1 1 12 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2.2"/></svg>
                <span>Terdekat</span>
            </a>
            <a href="<?php echo BASE_URL; ?>peta.php"<?php echo aktif("peta", $menu_aktif); ?>>
                <svg class="nav-ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/></svg>
                <span>Peta</span>
            </a>
            <?php if (sudah_login()) { ?>
                <a href="<?php echo BASE_URL; ?>user/rencana.php"<?php echo aktif("rencana", $menu_aktif); ?>>
                    <svg class="nav-ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span>Rencana</span>
                    <span class="badge-nav<?php echo $nav_jml_rencana === 0 ? ' sembunyi' : ''; ?>" id="badge-rencana"><?php echo $nav_jml_rencana; ?></span>
                </a>
                <?php if ($_SESSION["role"] === "admin") { ?>
                    <a href="<?php echo BASE_URL; ?>admin/index.php">
                        <svg class="nav-ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.6.85 1 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        <span>Admin</span>
                    </a>
                <?php } ?>
                <a class="nav-profil<?php echo $menu_aktif === 'profil' ? ' aktif' : ''; ?>" href="<?php echo BASE_URL; ?>user/profil.php">
                    <?php if ($nav_foto !== "") { ?>
                        <img class="nav-avatar" src="<?php echo e($nav_foto); ?>" alt="Profil" width="32" height="32" />
                    <?php } else { ?>
                        <span class="nav-inisial"><?php echo e($nav_inisial); ?></span>
                    <?php } ?>
                    <span>Profil</span>
                </a>
            <?php } else { ?>
                <a href="<?php echo BASE_URL; ?>auth/login.php">Masuk</a>
                <a class="menu-daftar" href="<?php echo BASE_URL; ?>auth/register.php">Daftar</a>
            <?php } ?>
        </div>
    </div>
</div>