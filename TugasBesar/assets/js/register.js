// Validasi registrasi real-time: cek username/email ke server, kekuatan password, konfirmasi.
// Dipindah dari <script> inline di register.php agar halaman valid sebagai XHTML.
(function () {
    "use strict";

    // register.php dan cek_ketersediaan.php berada di folder yang sama (auth/)
    var endpointCek = "cek_ketersediaan.php";

    var form = document.getElementById("formRegister");
    if (!form) { return; }

    var namaInput = document.getElementById("nama_lengkap");
    var usernameInput = document.getElementById("username");
    var emailInput = document.getElementById("email");
    var passInput = document.getElementById("password");
    var toggleBtn = document.getElementById("togglePass");

    var statusUser = document.getElementById("statusUsername");
    var statusEmail = document.getElementById("statusEmail");
    var strengthBar = document.getElementById("strengthBar");
    var strengthLabel = document.getElementById("strengthLabel");

    // Atribut ARIA diset lewat JS (tidak ada di DTD XHTML 1.0, tapi tetap membantu pembaca layar)
    [statusUser, statusEmail].forEach(function (el) {
        if (el) { el.setAttribute("aria-live", "polite"); }
    });
    if (toggleBtn) { toggleBtn.setAttribute("aria-label", "Tampilkan atau sembunyikan password"); }

    var state = { usernameOk: true, emailOk: true };
    var timerUser = null;
    var timerEmail = null;
    var permintaanTerakhir = { username: 0, email: 0 };

    function setStatus(el, teks, mode) {
        if (!el) { return; }
        el.textContent = teks;
        el.className = "status-cek" + (mode ? " cek-" + mode : "");
    }

    function cekKeServer(tipe, nilai, targetEl, stateKey) {
        // nomor permintaan: abaikan respons lama jika pengguna sudah mengetik lagi
        var nomor = ++permintaanTerakhir[tipe];

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
                if (nomor !== permintaanTerakhir[tipe]) { return; }
                state[stateKey] = Boolean(data.tersedia);
                setStatus(targetEl, data.pesan || "", data.tersedia ? "ok" : "error");
            })
            .catch(function () {
                if (nomor !== permintaanTerakhir[tipe]) { return; }
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
            clearTimeout(timerUser);
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
            clearTimeout(timerEmail);
            cekKeServer("email", this.value.trim(), statusEmail, "emailOk");
        });
    }

    function hitungKekuatan(pw) {
        var skor = 0;
        if (pw.length >= 6) { skor++; }
        if (pw.length >= 10) { skor++; }
        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) { skor++; }
        if (/[0-9]/.test(pw) || /[^A-Za-z0-9]/.test(pw)) { skor++; }
        return skor;
    }

    function updateStrength() {
        if (!passInput || !strengthBar || !strengthLabel) { return; }
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

    if (passInput) {
        passInput.addEventListener("input", updateStrength);
    }

    if (toggleBtn && passInput) {
        toggleBtn.addEventListener("click", function () {
            var tersembunyi = passInput.type === "password";
            passInput.type = tersembunyi ? "text" : "password";
            this.textContent = tersembunyi ? "Sembunyikan" : "Lihat";
        });
    }

    // Validasi sebelum kirim (tanpa konfirmasi password)
    form.addEventListener("submit", function (ev) {
        var gagal = function (pesan, el) {
            ev.preventDefault();
            alert(pesan);
            if (el) { el.focus(); }
        };

        if (namaInput.value.replace(/\s/g, "") === "") {
            return gagal("Nama lengkap tidak boleh kosong!", namaInput);
        }
        if (!/^[A-Za-z0-9_.-]+$/.test(usernameInput.value.trim())) {
            return gagal("Username hanya boleh huruf, angka, titik, garis bawah, atau strip!", usernameInput);
        }
        if (emailInput.value.indexOf("@") === -1) {
            return gagal("Format email tidak valid!", emailInput);
        }
        if (!state.usernameOk) {
            return gagal("Username sudah digunakan, silakan pilih username lain.", usernameInput);
        }
        if (!state.emailOk) {
            return gagal("Email sudah digunakan, silakan gunakan email lain.", emailInput);
        }
        if (passInput.value.length < 6) {
            return gagal("Password minimal harus 6 karakter!", passInput);
        }
    });
})();