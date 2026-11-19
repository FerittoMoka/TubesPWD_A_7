function muatDestinasi(kategoriPilihan) {
    var inputCari = document.getElementById("input_cari");
    var wadahHasil = document.getElementById("hasil_destinasi");
    var keyword = inputCari ? encodeURIComponent(inputCari.value.trim()) : "";
    var kategori = encodeURIComponent(kategoriPilihan || "Semua");

    var url = "api_destinasi.php?q=" + keyword + "&kategori=" + kategori;

    fetch(url)
        .then(function(response) {
            return response.json();
        })
        .then(function(payload) {
            if (!wadahHasil) return;

            if (payload.total === 0) {
                wadahHasil.innerHTML = "<p>Destinasi tidak ditemukan.</p>";
                return;
            }

            var html = "";
            for (var i = 0; i < payload.items.length; i++) {
                var item = payload.items[i];
                html += "<div class='card-destinasi'>";
                html += "<span class='badge'>" + escapeHtml(item.kategori) + "</span>";
                html += "<h3>" + escapeHtml(item.nama) + "</h3>";
                html += "<p>" + escapeHtml(item.lokasi) + "</p>";
                html += "<p>Rating: " + escapeHtml(item.rating) + " | " + escapeHtml(item.harga_tiket) + "</p>";
                html += "</div>";
            }
            wadahHasil.innerHTML = html;
        })
        .catch(function(err) {
            console.error("Gagal memuat data:", err);
        });
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}