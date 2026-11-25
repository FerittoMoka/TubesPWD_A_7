const dataPilihan = {
    "pilihan-1": {
        kategori: "Unggulan",
        judul: "Judul Pilihan Pertama",
        meta: "Lokasi / Info Singkat • Rating 4.8",
        gambar: "assets/img/gambar1.jpg",
        deskripsi: "Deskripsi lengkap mengenai item pertama. Jelaskan keunggulan, latar belakang, atau detail utama yang dicari pengguna.",
        poin: [
            "Fasilitas atau fitur utama pertama",
            "Jam operasional atau spesifikasi teknis",
            "Estimasi biaya atau aksesibilitas"
        ]
    },
    "pilihan-2": {
        kategori: "Rekomendasi",
        judul: "Judul Pilihan Kedua",
        meta: "Lokasi / Info Singkat • Rating 4.9",
        gambar: "assets/img/gambar2.jpg",
        deskripsi: "Deskripsi lengkap mengenai item kedua agar pengguna mendapatkan informasi utuh sebelum mengambil keputusan.",
        poin: [
            "Keunggulan utama pilihan kedua",
            "Informasi pendukung lainnya",
            "Catatan tambahan bagi pengguna"
        ]
    }
};

const params = new URLSearchParams(window.location.search);
const itemKey = params.get("item") || "pilihan-1";
const item = dataPilihan[itemKey];

if (item) {
    document.getElementById("detail-category").textContent = item.kategori;
    document.getElementById("detail-title").textContent = item.judul;
    document.getElementById("detail-meta").textContent = item.meta;
    document.getElementById("detail-img").src = item.gambar;
    document.getElementById("detail-desc").textContent = item.deskripsi;

    const listEl = document.getElementById("detail-list");
    listEl.innerHTML = "";
    item.poin.forEach(teks => {
        const li = document.createElement("li");
        li.textContent = teks;
        listEl.appendChild(li);
    });
} else {
    document.getElementById("detail-title").textContent = "Data tidak ditemukan";
    document.getElementById("detail-desc").textContent = "Silakan kembali ke beranda dan pilih item yang tersedia.";
}

document.getElementById("btn-action").addEventListener("click", () => {
    alert(`"${item ? item.judul : 'Item'}" berhasil disimpan ke daftar favorit!`);
});