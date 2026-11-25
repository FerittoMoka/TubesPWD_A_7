<?php

require_once __DIR__ . "/auth.php";
if (!isset($judul)) { $judul = "Dolan Jogja"; }
if (!isset($menu_aktif)) { $menu_aktif = ""; }
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
            <?php if (sudah_login()) { ?>
                <a href="<?php echo BASE_URL; ?>user/rencana.php"<?php echo aktif("rencana", $menu_aktif); ?>>Rencana</a>
                <?php if ($_SESSION["role"] === "admin") { ?>
                    <a href="<?php echo BASE_URL; ?>admin/index.php">Admin</a>
                <?php } ?>
                <a href="<?php echo BASE_URL; ?>user/profil.php"<?php echo aktif("profil", $menu_aktif); ?>>Profil</a>
                <a class="menu-daftar" href="<?php echo BASE_URL; ?>auth/logout.php">Keluar</a>
            <?php } else { ?>
                <a href="<?php echo BASE_URL; ?>auth/login.php">Masuk</a>
                <a class="menu-daftar" href="<?php echo BASE_URL; ?>auth/register.php">Daftar</a>
            <?php } ?>
        </div>
    </div>
</div>