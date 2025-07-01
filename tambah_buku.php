<?php
ob_start(); // Mulai output buffering

// Ambil data kategori
$kategori = $conn->query("SELECT * FROM kategori");

// Fungsi generate ID Buku otomatis
function generateIdBuku($conn) {
    $result = $conn->query("SELECT id_buku FROM buku WHERE id_buku LIKE 'B%' ORDER BY CAST(SUBSTRING(id_buku, 2) AS UNSIGNED) DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $num = (int) substr($row['id_buku'], 1);
        $num++;
    } else {
        $num = 1;
    }
    return "B" . $num;
}

// Ambil data jika edit
$editData = null;
if (isset($_GET['edit'])) {
    $idEdit = $conn->real_escape_string($_GET['edit']);
    $resultEdit = $conn->query("SELECT * FROM buku WHERE id_buku = '$idEdit'");
    if ($resultEdit && $resultEdit->num_rows > 0) {
        $editData = $resultEdit->fetch_assoc();
    }
}

// Gunakan ID otomatis atau ID dari data edit
$id_buku_otomatis = $editData ? $editData['id_buku'] : generateIdBuku($conn);

// Simpan data jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $id_buku     = $_POST['id_buku'];
    $judul       = $_POST['judul_buku'];
    $pengarang   = $_POST['pengarang'];
    $tahun       = $_POST['thn_terbit'];
    $jumlah      = (int) $_POST['jml_buku'];
    $penerbit    = $_POST['penerbit'];
    $id_kategori = $_POST['id_kategori'];

    // Cek apakah data sudah ada (edit)
    $cek = $conn->query("SELECT * FROM buku WHERE id_buku = '$id_buku'");
    if ($cek && $cek->num_rows > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE buku SET judul_buku=?, pengarang=?, thn_terbit=?, jml_buku=?, penerbit=?, id_kategori=? WHERE id_buku=?");
        $stmt->bind_param("sssssss", $judul, $pengarang, $tahun, $jumlah, $penerbit, $id_kategori, $id_buku);
    } else {
        // Insert baru
        $stmt = $conn->prepare("INSERT INTO buku VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $id_buku, $judul, $pengarang, $tahun, $jumlah, $penerbit, $id_kategori);
    }

    if ($stmt->execute()) {
        if ($cek->num_rows == 0) {
            // Tambah copy_buku baru (jika insert buku baru)
            for ($i = 1; $i <= $jumlah; $i++) {
                $no_copy = $id_buku . '-CB' . $i;
                $conn->query("INSERT INTO copy_buku (no_copy_buku, id_buku, status_buku) VALUES ('$no_copy', '$id_buku', 'tersedia')");
            }
        } else {
            // Update: Sesuaikan copy_buku
            $resultJumlah = $conn->query("SELECT jml_buku FROM buku WHERE id_buku = '$id_buku'");
            $jumlah_lama = $editData['jml_buku'] ?? 0;
            $jumlah_lama = (int)$jumlah_lama;

            if ($jumlah > $jumlah_lama) {
                for ($i = $jumlah_lama + 1; $i <= $jumlah; $i++) {
                    $no_copy = $id_buku . '-CB' . $i;
                    $conn->query("INSERT INTO copy_buku (no_copy_buku, id_buku, status_buku) VALUES ('$no_copy', '$id_buku', 'tersedia')");
                }
            } elseif ($jumlah < $jumlah_lama) {
                for ($i = $jumlah_lama; $i > $jumlah; $i--) {
                    $no_copy = $id_buku . '-CB' . $i;
                    $conn->query("DELETE FROM copy_buku WHERE no_copy_buku = '$no_copy'");
                }
            }
        }

        echo "<div class='alert alert-success'>Data buku berhasil disimpan.</div>";
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=buku.php">';
    } else {
        echo "<div class='alert alert-danger'>Gagal menyimpan data: " . htmlspecialchars($stmt->error) . "</div>";
    }
}
?>

<div class="container mt-4">
    <h3 class="mb-4 text-center"><?= $editData ? 'Edit' : 'Tambah' ?> Buku</h3>
    <form method="post" class="border p-4 rounded bg-light">
        <div class="mb-3">
            <label>ID Buku:</label>
            <input type="text" name="id_buku" class="form-control" value="<?= htmlspecialchars($id_buku_otomatis) ?>" readonly>
        </div>
        <div class="mb-3">
            <label>Judul Buku:</label>
            <input type="text" name="judul_buku" class="form-control" required value="<?= isset($editData['judul_buku']) ? htmlspecialchars($editData['judul_buku']) : '' ?>">
        </div>
        <div class="mb-3">
            <label>Pengarang:</label>
            <input type="text" name="pengarang" class="form-control" value="<?= isset($editData['pengarang']) ? htmlspecialchars($editData['pengarang']) : '' ?>">
        </div>
        <div class="mb-3">
            <label>Tahun Terbit:</label>
            <input type="number" name="thn_terbit" class="form-control" value="<?= isset($editData['thn_terbit']) ? htmlspecialchars($editData['thn_terbit']) : '' ?>">
        </div>
        <div class="mb-3">
            <label>Jumlah Buku:</label>
            <input type="number" name="jml_buku" class="form-control" required value="<?= isset($editData['jml_buku']) ? htmlspecialchars($editData['jml_buku']) : '' ?>">
        </div>
        <div class="mb-3">
            <label>Penerbit:</label>
            <input type="text" name="penerbit" class="form-control" value="<?= isset($editData['penerbit']) ? htmlspecialchars($editData['penerbit']) : '' ?>">
        </div>
        <div class="mb-3">
            <label>Kategori:</label>
            <select name="id_kategori" class="form-select" required>
                <option value="">-- Pilih Kategori --</option>
                <?php while ($row = $kategori->fetch_assoc()) : ?>
                    <option value="<?= $row['id_kategori'] ?>" <?= (isset($editData['id_kategori']) && $editData['id_kategori'] == $row['id_kategori']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['nm_kategori']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?= $editData ? 'Update' : 'Simpan' ?></button>
        <a href="?page=perpus_utama&panggil=buku.php" class="btn btn-secondary">Batal</a>
    </form>
</div>

<?php ob_end_flush(); ?>