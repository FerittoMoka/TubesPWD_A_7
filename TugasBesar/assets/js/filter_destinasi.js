// Filter beranda dinamis: pilih lebih dari satu suasana dan kategori, hasil berubah seketika tanpa memuat ulang halaman.
// Aturan: suasana = salah satu cocok, kategori = salah satu cocok, antar kelompok + kata kunci = semua terpenuhi.
(function () {
    "use strict";

    var chips = document.querySelectorAll("a.chip[data-grup]");
    var daftar = document.getElementById("daftar-hasil");
    if (!daftar || chips.length === 0) { return; }

    var rows = daftar.querySelectorAll(".baris[data-kategori]");
    var wadahDaftar = daftar.querySelector(".daftar");
    var pesanKosong = document.getElementById("pesan-kosong");
    var unggulan = document.getElementById("bagian-unggulan");
    var judul = document.getElementById("judul-hasil");
    var eyebrow = document.getElementById("eyebrow-hasil");
    var inputCari = document.getElementById("cari-input");
    var form = document.getElementById("form-cari");
    var inMood = document.getElementById("in-mood");
    var inKategori = document.getElementById("in-kategori");
    var reset = document.getElementById("reset-filter");

    // Keadaan awal dibaca dari chip yang sudah ditandai aktif oleh server
    var state = { mood: [], kategori: [], cari: inputCari ? inputCari.value.trim() : "" };
    Array.prototype.forEach.call(chips, function (c) {
        var nilai = c.getAttribute("data-nilai");
        if (nilai !== "" && c.classList.contains("aktif")) {
            state[c.getAttribute("data-grup")].push(nilai);
        }
    });

    function tampilkan(el, tampil) {
        if (tampil) { el.classList.remove("sembunyi"); } else { el.classList.add("sembunyi"); }
    }

    function bangunUrl() {
        var p = [];
        if (state.cari) { p.push("cari=" + encodeURIComponent(state.cari)); }
        if (state.kategori.length) { p.push("kategori=" + state.kategori.join(",")); }
        if (state.mood.length) { p.push("mood=" + state.mood.join(",")); }
        return location.pathname + (p.length ? "?" + p.join("&") : "") + "#destinasi";
    }

    function terapkan() {
        var adaFilter = state.mood.length > 0 || state.kategori.length > 0 || state.cari !== "";
        var cariKecil = state.cari.toLowerCase();
        var jumlah = 0;
        var terlihat = 0;

        Array.prototype.forEach.call(rows, function (row) {
            var cocok = true;
            if (state.kategori.length && state.kategori.indexOf(row.getAttribute("data-kategori")) === -1) {
                cocok = false;
            }
            if (cocok && state.mood.length) {
                var tagAttr = row.getAttribute("data-mood") || "";
                var tags = tagAttr === "" ? [] : tagAttr.split(",");
                cocok = state.mood.some(function (m) { return tags.indexOf(m) !== -1; });
            }
            if (cocok && cariKecil) {
                cocok = (row.getAttribute("data-teks") || "").indexOf(cariKecil) !== -1;
            }
            var tampil = cocok && (adaFilter || !row.hasAttribute("data-unggulan"));
            tampilkan(row, tampil);
            if (cocok) { jumlah++; }
            if (tampil) { terlihat++; }
        });

        // Chip: tandai yang aktif
        var label = [];
        Array.prototype.forEach.call(chips, function (c) {
            var grup = c.getAttribute("data-grup");
            var nilai = c.getAttribute("data-nilai");
            var aktif = nilai === "" ? state[grup].length === 0 : state[grup].indexOf(nilai) !== -1;
            tampilkan(c, true);
            if (aktif) { c.classList.add("aktif"); } else { c.classList.remove("aktif"); }
            if (nilai === "") {
                if (aktif) { c.classList.add("chip-terkunci"); } else { c.classList.remove("chip-terkunci"); }
            } else if (aktif) {
                c.setAttribute("title", "Klik lagi untuk membatalkan pilihan");
                label.push(c.getAttribute("data-label"));
            } else {
                c.removeAttribute("title");
            }
        });

        // Judul, bagian unggulan, daftar, dan pesan kosong
        if (adaFilter) {
            if (eyebrow) { eyebrow.textContent = "Hasil pencarian"; }
            if (judul) { judul.textContent = jumlah + " destinasi ditemukan" + (label.length ? " \u00B7 " + label.join(" \u00B7 ") : ""); }
        } else {
            if (eyebrow) { eyebrow.textContent = "Jelajahi"; }
            if (judul) { judul.textContent = "Semua destinasi"; }
        }
        if (unggulan) { tampilkan(unggulan, !adaFilter); }
        if (wadahDaftar) { tampilkan(wadahDaftar, terlihat > 0); }
        if (pesanKosong) { tampilkan(pesanKosong, adaFilter && jumlah === 0); }

        // Samakan formulir pencarian dan alamat halaman (agar bisa dibagikan / dimuat ulang)
        if (inMood) { inMood.value = state.mood.join(","); }
        if (inKategori) { inKategori.value = state.kategori.join(","); }
        try { history.replaceState(null, "", bangunUrl()); } catch (e) { /* abaikan */ }
    }

    document.addEventListener("click", function (e) {
        var chip = e.target.closest ? e.target.closest("a.chip[data-grup]") : null;
        if (!chip) { return; }
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) { return; }
        e.preventDefault();
        var grup = chip.getAttribute("data-grup");
        var nilai = chip.getAttribute("data-nilai");
        if (nilai === "") {
            state[grup] = [];
        } else {
            var i = state[grup].indexOf(nilai);
            if (i === -1) { state[grup].push(nilai); } else { state[grup].splice(i, 1); }
        }
        terapkan();
    });

    if (inputCari) {
        inputCari.addEventListener("input", function () {
            state.cari = inputCari.value.trim().slice(0, 100);
            terapkan();
        });
    }
    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            state.cari = inputCari ? inputCari.value.trim().slice(0, 100) : "";
            terapkan();
            var tujuan = document.getElementById("destinasi");
            if (tujuan && tujuan.scrollIntoView) { tujuan.scrollIntoView({ behavior: "smooth" }); }
        });
    }
    if (reset) {
        reset.addEventListener("click", function (e) {
            e.preventDefault();
            state.mood = [];
            state.kategori = [];
            state.cari = "";
            if (inputCari) { inputCari.value = ""; }
            terapkan();
        });
    }
})();
