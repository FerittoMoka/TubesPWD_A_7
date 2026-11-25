<?php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";

if (sudah_login()) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$csrfToken = generate_csrf_token();
$judul = "Daftar";
require_once __DIR__ . "/../includes/header.php";
?>
<div class="wadah">
    <div class="kotak auth-kotak">
        <p class="eyebrow">Bergabung dengan Dolan Jogja</p>
        <h1>Daftar Akun</h1>
        <p class="muted auth-sub">Buat akun untuk menulis ulasan dan menyimpan rencana perjalanan Jogjamu.</p>

        <form id="formRegister" action="proses_register.php" method="post" class="form-isi">
            <div>
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>" />

                <div class="field-grup">
                    <label for="nama_lengkap">Nama Lengkap</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" maxlength="100" required="required" placeholder="Contoh: Budi Santoso" />
                </div>

                <div class="field-grup">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" maxlength="30" required="required" placeholder="budisantoso" />
                    <small id="statusUsername" class="status-cek"></small>
                </div>

                <div class="field-grup">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" maxlength="100" required="required" placeholder="budi@email.com" />
                    <small id="statusEmail" class="status-cek"></small>
                </div>

                <div class="field-grup">
                    <label for="password">Password</label>
                    <div class="input-password-wrap">
                        <input type="password" id="password" name="password" required="required" />
                        <button type="button" class="btn-toggle-pass" id="togglePass" title="Tampilkan atau sembunyikan password">Lihat</button>
                    </div>
                    <div class="strength-bar-wrap">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <small id="strengthLabel" class="status-cek">Kekuatan password: -</small>
                </div>

                <div class="field-grup">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required="required" />
                    <small id="statusKonfirmasi" class="status-cek"></small>
                </div>

                <div class="auth-submit-baris">
                    <input type="submit" id="btnDaftar" name="register" value="Daftar Akun" />
                </div>
            </div>
        </form>

        <p class="auth-footer-link">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
    </div>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>assets/js/register.js"></script>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>