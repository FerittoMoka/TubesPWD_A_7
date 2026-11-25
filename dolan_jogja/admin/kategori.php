<?php
require_once __DIR__ . "/../includes/init.php";
wajib_admin();

$edit_id = (int) ($_GET["edit"] ?? 0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cek_csrf();
    $aksi = $_POST["aksi"] ?? "";
    $id   = (int) ($_POST["id"] ?? 0);
    $nama = trim($_POST["nama"] ?? "");

    if ($aksi === "tambah" || $aksi === "ubah") {
        if ($nama === "" || mb_strlen($nama) > 50) {
            flash("error", "Nama kategori wajib diisi (maksimal 50 karakter).");
        } else {
            $s = $conn->prepare("SELECT id FROM categories WHERE nama=? AND id<>?");
            $s->bind_param("si", $nama, $id);
            $s->execute();
            if ($s->get_result()->num_rows) {
                flash("error", "Nama kategori sudah ada.");
            } elseif ($aksi === "tambah") {
                $s = $conn->prepare("INSERT INTO categories (nama) VALUES (?)");
                $s->bind_param("s", $nama);
                $s->execute();
                flash("success", "Kategori ditambahkan.");
            } else {
                $s = $conn->prepare("UPDATE categories SET nama=? WHERE id=?");
                $s->bind_param("si", $nama, $id);
                $s->execute();
                flash("success", "Kategori diperbarui.");
            }
        }
    } elseif ($aksi === "hapus") {
        $s = $conn->prepare("SELECT COUNT(*) n FROM destinations WHERE category_id=?");
        $s->bind_param("i", $id);
        $s->execute();
        if ((int) $s->get_result()->fetch_assoc()["n"] > 0) {
            flash("error", "Kategori masih digunakan destinasi dan tidak dapat dihapus.");
        } else {
            $s = $conn->prepare("DELETE FROM categories WHERE id=?");
            $s->bind_param("i", $id);
            $s->execute();
            flash("success", "Kategori dihapus.");
        }
    }
    redirect("admin/kategori.php");
}

$rows = $conn->query("SELECT c.id, c.nama, COUNT(d.id) AS jumlah
                      FROM categories c LEFT JOIN destinations d ON d.category_id=c.id
                      GROUP BY c.id, c.nama ORDER BY c.nama");
$self = url("admin/kategori.php");

header_web("Kelola Kategori", "admin");
?>
<div class="page-heading">
  <span class="eyebrow">Administrator</span>
  <h1>Kelola kategori</h1>
</div>
<?php subnav_admin("kategori"); ?>

<form class="panel category-form" method="post" action="<?php echo $self; ?>">
  <div>
    <?php echo csrf_field(); ?>
    <input type="hidden" name="aksi" value="tambah" />
    <label for="nama-baru">Nama kategori baru</label>
    <div class="baris-form"><input type="text" id="nama-baru" name="nama" maxlength="50" /><button class="tombol" type="submit">Tambah</button></div>
  </div>
</form>

<div class="panel table-wrap">
  <table>
    <thead><tr><th>Kategori</th><th>Jumlah destinasi</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php while ($c = $rows->fetch_assoc()) { ?>
      <tr>
        <?php if ($edit_id === (int) $c["id"]) { ?>
        <td colspan="2">
          <form class="inline-edit" method="post" action="<?php echo $self; ?>">
            <div>
              <?php echo csrf_field(); ?>
              <input type="hidden" name="aksi" value="ubah" />
              <input type="hidden" name="id" value="<?php echo (int) $c["id"]; ?>" />
              <input type="text" name="nama" maxlength="50" value="<?php echo e($c["nama"]); ?>" />
              <button class="tombol" type="submit">Simpan</button>
              <a class="text-link" href="<?php echo $self; ?>">Batal</a>
            </div>
          </form>
        </td>
        <td></td>
        <?php } else { ?>
        <td><?php echo e($c["nama"]); ?></td>
        <td><?php echo (int) $c["jumlah"]; ?></td>
        <td class="aksi">
          <a href="<?php echo url("admin/kategori.php?edit=" . (int) $c["id"]); ?>">Ubah</a>
          <?php echo form_aksi($self, "hapus", $c["id"], "Hapus", "Hapus kategori ini?"); ?>
        </td>
        <?php } ?>
      </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
<?php footer_web(); ?>
