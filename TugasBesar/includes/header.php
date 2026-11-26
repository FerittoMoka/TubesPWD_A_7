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
            <a href="<?php echo BASE_URL; ?>index.php"<?php echo aktif("beranda", $menu_aktif); ?>>Beranda</a>
            <a href="<?php echo BASE_URL; ?>index.php#destinasi">Destinasi</a>
            <a href="<?php echo BASE_URL; ?>destinasi/terdekat.php"<?php echo aktif("terdekat", $menu_aktif); ?>>Terdekat</a>
            <a href="<?php echo BASE_URL; ?>peta.php"<?php echo aktif("peta", $menu_aktif); ?>>Peta</a>
            <?php if (sudah_login()) { ?>
                <a href="<?php echo BASE_URL; ?>user/rencana.php"<?php echo aktif("rencana", $menu_aktif); ?>>
                    Rencana <span class="badge-nav<?php echo $nav_jml_rencana === 0 ? ' sembunyi' : ''; ?>" id="badge-rencana"><?php echo $nav_jml_rencana; ?></span>
                </a>
                <?php if ($_SESSION["role"] === "admin") { ?>
                    <a href="<?php echo BASE_URL; ?>admin/index.php">Admin</a>
                <?php } ?>
                <a class="nav-profil<?php echo $menu_aktif === 'profil' ? ' aktif' : ''; ?>" href="<?php echo BASE_URL; ?>user/profil.php">
                    <?php if ($nav_foto !== "") { ?>
                        <img class="nav-avatar" src="<?php echo e($nav_foto); ?>" alt="Profil" />
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