<?php

// Fungsi generate ID kategori otomatis
function generateIdKategori($conn) {
    $result = $conn->query("SELECT id_kategori FROM kategori WHERE id_kategori LIKE 'K%' ORDER BY CAST(SUBSTRING(id_kategori, 2) AS UNSIGNED) DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastId = $row['id_kategori']; // contoh: K12
        $num = (int) substr($lastId, 1);
        $num++;
    } else {
        $num = 1;
    }
    return "K" . $num;
}

// Ambil data edit jika ada
$editData = null;
if (isset($_GET['edit'])) {
    $idEdit = $conn->real_escape_string($_GET['edit']);
    $resultEdit = $conn->query("SELECT * FROM kategori WHERE id_kategori = '$idEdit'");
    if ($resultEdit && $resultEdit->num_rows > 0) {
        $editData = $resultEdit->fetch_assoc();
    }
}

// Jika bukan edit, generate ID baru
$idKategoriOtomatis = $editData ? $editData['id_kategori'] : generateIdKategori($conn);

// Handle simpan data (insert atau update)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Saat submit, jika edit maka ambil dari form, jika tambah pakai ID otomatis
    $idKategori = $editData ? $conn->real_escape_string($_POST['idKategori']) : $idKategoriOtomatis;
    $nmKategori = $conn->real_escape_string($_POST['nmKategori']);

    if (empty($idKategori)) {
        echo '<div class="alert alert-danger">ID Kategori harus diisi.</div>';
    } else {
        $cek = $conn->query("SELECT * FROM kategori WHERE id_kategori = '$idKategori'");
        if ($cek->num_rows > 0) {
            // Update
            $sql = "UPDATE kategori SET nm_kategori = '$nmKategori' WHERE id_kategori = '$idKategori'";
        } else {
            // Insert baru
            $sql = "INSERT INTO kategori (id_kategori, nm_kategori) VALUES ('$idKategori', '$nmKategori')";
        }

        if ($conn->query($sql)) {
            echo '<div class="alert alert-success">Data berhasil disimpan.</div>';
            echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=kategori.php">';
            exit;
        } else {
            echo '<div class="alert alert-danger">Gagal menyimpan data: ' . $conn->error . '</div>';
        }
    }
}
?>

<h2 class="text-center"><?= isset($editData) ? "Edit" : "Tambah" ?> Kategori</h2>

<form method="POST" class="mb-4">
    <div class="mb-3">
        <label for="idKategori" class="form-label">ID Kategori (max 4 karakter)</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-tag"></i></span>
            <input type="text" maxlength="4" class="form-control" id="idKategori" name="idKategori" required
                value="<?= htmlspecialchars($idKategoriOtomatis) ?>" readonly>
        </div>
    </div>
    <div class="mb-3">
        <label for="nmKategori" class="form-label">Nama Kategori (max 20 karakter)</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-book"></i></span>
            <input type="text" maxlength="20" class="form-control" id="nmKategori" name="nmKategori" required
                value="<?= isset($editData['nm_kategori']) ? htmlspecialchars($editData['nm_kategori']) : '' ?>">
        </div>
    </div>
    <button type="submit" class="btn btn-primary">
        <?= isset($editData) ? 'Update' : 'Simpan' ?>
    </button>
    <a href="?page=perpus_utama&panggil=kategori.php" class="btn btn-secondary">Batal</a>
</form>