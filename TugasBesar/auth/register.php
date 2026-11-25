<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";

if (sudah_login()) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Daftar - Dolan Jogja</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" type="text/css" href="<?php echo BASE_URL; ?>assets/css/style.css" />
</head>
<body>
    <div class="kotak auth-kotak">
        <p class="eyebrow">Bergabung dengan Dolan Jogja</p>
        <h1>Daftar Akun</h1>
        <p class="muted auth-sub">Buat akun untuk menulis ulasan dan menyimpan rencana perjalanan Jogjamu.</p>

        <form id="formRegister" action="proses_register.php" method="post" onsubmit="return validasiForm();" class="form-isi">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>" />

            <div class="field-grup">
                <label for="nama_lengkap">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" maxlength="100" required="required" placeholder="Contoh: Budi Santoso" />
            </div>

            <div class="field-grup">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" maxlength="30" required="required" placeholder="budisantoso" autocomplete="username" />
                <small id="statusUsername" class="status-cek" aria-live="polite"></small>
            </div>

            <div class="field-grup">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="100" required="required" placeholder="budi@email.com" autocomplete="email" />
                <small id="statusEmail" class="status-cek" aria-live="polite"></small>
            </div>

            <div class="field-grup">
                <label for="password">Password</label>
                <div class="input-password-wrap">
                    <input type="password" id="password" name="password" minlength="6" required="required" autocomplete="new-password" />
                    <button type="button" class="btn-toggle-pass" id="togglePass" aria-label="Tampilkan atau sembunyikan password">Lihat</button>
                </div>
                <div class="strength-bar-wrap" aria-hidden="true">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <small id="strengthLabel" class="status-cek">Kekuatan password: -</small>
            </div>

            <div class="field-grup">
                <label for="confirm_password">Konfirmasi Password</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="6" required="required" autocomplete="new-password" />
                <small id="statusKonfirmasi" class="status-cek" aria-live="polite"></small>
            </div>

            <div class="auth-submit-baris">
                <input type="submit" id="btnDaftar" name="register" value="Daftar Akun" />
            </div>
        </form>

        <p class="auth-footer-link">Sudah punya akun? <a href="login.php">Masuk di sini</a> · <a href="<?php echo BASE_URL; ?>index.php">Beranda</a></p>
    </div>

    <script>
    (function () {
        var endpointCek = "<?php echo BASE_URL; ?>auth/cek_ketersediaan.php";
        var usernameInput = document.getElementById("username");
        var emailInput = document.getElementById("email");
        var passInput = document.getElementById("password");
        var konfInput = document.getElementById("confirm_password");
        var toggleBtn = document.getElementById("togglePass");

        var statusUser = document.getElementById("statusUsername");
        var statusEmail = document.getElementById("statusEmail");
        var statusKonf = document.getElementById("statusKonfirmasi");
        var strengthBar = document.getElementById("strengthBar");
        var strengthLabel = document.getElementById("strengthLabel");

        var state = { usernameOk: true, emailOk: true };
        var timerUser = null;
        var timerEmail = null;

        function setStatus(el, teks, mode) {
            if (!el) return;
            el.textContent = teks;
            el.className = "status-cek" + (mode ? " cek-" + mode : "");
        }

        function cekKeServer(tipe, nilai, targetEl, stateKey) {
            if (nilai === "") {
                setStatus(targetEl, "", "");
                state[stateKey] = true;
                return;
            }
            setStatus(targetEl, "Memeriksa ketersediaan...", "netral");

            var url = endpointCek + "?tipe=" + encodeURIComponent(tipe) + "&nilai=" + encodeURIComponent(nilai);
            fetch(url)
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    state[stateKey] = Boolean(data.tersedia);
                    setStatus(targetEl, data.pesan || "", data.tersedia ? "ok" : "error");
                })
                .catch(function () {
                    state[stateKey] = true;
                    setStatus(targetEl, "", "");
                });
        }

        if (usernameInput) {
            usernameInput.addEventListener("input", function () {
                clearTimeout(timerUser);
                var val = this.value.trim();
                timerUser = setTimeout(function () {
                    cekKeServer("username", val, statusUser, "usernameOk");
                }, 300);
            });
            usernameInput.addEventListener("blur", function () {
                cekKeServer("username", this.value.trim(), statusUser, "usernameOk");
            });
        }

        if (emailInput) {
            emailInput.addEventListener("input", function () {
                clearTimeout(timerEmail);
                var val = this.value.trim();
                timerEmail = setTimeout(function () {
                    cekKeServer("email", val, statusEmail, "emailOk");
                }, 300);
            });
            emailInput.addEventListener("blur", function () {
                cekKeServer("email", this.value.trim(), statusEmail, "emailOk");
            });
        }

        function hitungKekuatan(pw) {
            var skor = 0;
            if (pw.length >= 6) skor++;
            if (pw.length >= 10) skor++;
            if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) skor++;
            if (/[0-9]/.test(pw) || /[^A-Za-z0-9]/.test(pw)) skor++;
            return skor;
        }

        function updateStrength() {
            if (!passInput || !strengthBar || !strengthLabel) return;
            var pw = passInput.value;
            if (pw === "") {
                strengthBar.style.width = "0%";
                strengthBar.className = "strength-bar";
                strengthLabel.textContent = "Kekuatan password: -";
                strengthLabel.className = "status-cek";
                return;
            }
            var skor = hitungKekuatan(pw);
            if (skor <= 1) {
                strengthBar.style.width = "33%";
                strengthBar.className = "strength-bar lemah";
                strengthLabel.textContent = "Kekuatan password: Lemah (gunakan min. 6 karakter, huruf & angka)";
                strengthLabel.className = "status-cek cek-error";
            } else if (skor <= 3) {
                strengthBar.style.width = "68%";
                strengthBar.className = "strength-bar sedang";
                strengthLabel.textContent = "Kekuatan password: Sedang";
                strengthLabel.className = "status-cek cek-netral";
            } else {
                strengthBar.style.width = "100%";
                strengthBar.className = "strength-bar kuat";
                strengthLabel.textContent = "Kekuatan password: Kuat";
                strengthLabel.className = "status-cek cek-ok";
            }
        }

        function updateKonfirmasi() {
            if (!passInput || !konfInput || !statusKonf) return;
            if (konfInput.value === "") {
                setStatus(statusKonf, "", "");
                return;
            }
            if (passInput.value === konfInput.value) {
                setStatus(statusKonf, "Password cocok.", "ok");
            } else {
                setStatus(statusKonf, "Konfirmasi password belum cocok.", "error");
            }
        }

        if (passInput) {
            passInput.addEventListener("input", function () {
                updateStrength();
                updateKonfirmasi();
            });
        }
        if (konfInput) {
            konfInput.addEventListener("input", updateKonfirmasi);
        }

        if (toggleBtn && passInput) {
            toggleBtn.addEventListener("click", function () {
                var tersembunyi = passInput.type === "password";
                passInput.type = tersembunyi ? "text" : "password";
                if (konfInput) konfInput.type = tersembunyi ? "text" : "password";
                this.textContent = tersembunyi ? "Sembunyikan" : "Lihat";
            });
        }

        window.validasiForm = function () {
            var nama = document.getElementById("nama_lengkap").value;
            var username = usernameInput.value.trim();
            var email = emailInput.value.trim();
            var pass = passInput.value;
            var konfirmasi = konfInput.value;

            if (nama.replace(/\s/g, "") === "") {
                alert("Nama lengkap tidak boleh kosong!");
                return false;
            }
            if (!/^[A-Za-z0-9_.-]+$/.test(username)) {
                alert("Username hanya boleh huruf, angka, titik, garis bawah, atau strip!");
                return false;
            }
            if (email.indexOf("@") === -1) {
                alert("Format email tidak valid!");
                return false;
            }
            if (!state.usernameOk) {
                alert("Username sudah digunakan, silakan pilih username lain.");
                usernameInput.focus();
                return false;
            }
            if (!state.emailOk) {
                alert("Email sudah digunakan, silakan gunakan email lain.");
                emailInput.focus();
                return false;
            }
            if (pass.length < 6) {
                alert("Password minimal harus 6 karakter!");
                return false;
            }
            if (pass !== konfirmasi) {
                alert("Konfirmasi password tidak cocok!");
                return false;
            }
            return true;
        };
    })();
    </script>
</body>
</html>