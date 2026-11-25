<?php
require_once __DIR__ . "/../includes/init.php";
header_web("Destinasi Terdekat", "terdekat");
?>
<div class="page-heading">
  <p class="eyebrow">Berdasarkan lokasimu</p>
  <h1>Destinasi terdekat</h1>
  <p>Izinkan akses lokasi pada browser, lalu kami urutkan destinasi dari yang paling dekat dengan posisimu.</p>
  <p><a id="btn-terdekat" class="tombol" href="<?php echo url("api/terdekat.php"); ?>">Gunakan lokasiku</a> <span id="status-terdekat" class="status"></span></p>
</div>
<div id="hasil-terdekat" class="daftar"></div>
<?php footer_web(); ?>
