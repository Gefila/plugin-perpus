<?php

// Fungsi generate ID anggota otomatis (opsional)
function generateIdAnggota($conn) {
    $result = $conn->query("SELECT id_anggota FROM anggota WHERE id_anggota LIKE 'A%' ORDER BY CAST(SUBSTRING(id_anggota, 2) AS UNSIGNED) DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $num = (int) substr($row['id_anggota'], 1);
        $num++;
    } else {
        $num = 1;
    }
    return "A" . $num;
}

// Ambil data jika mode edit
$editData = null;
if (isset($_GET['edit'])) {
    $idEdit = $conn->real_escape_string($_GET['edit']);
    $resultEdit = $conn->query("SELECT * FROM anggota WHERE id_anggota = '$idEdit'");
    if ($resultEdit && $resultEdit->num_rows > 0) {
        $editData = $resultEdit->fetch_assoc();
    }
}

$idAnggota = $editData ? $editData['id_anggota'] : generateIdAnggota($conn);

// Proses simpan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id     = $_POST['id_anggota'];
    $nama   = $_POST['nm_anggota'];
    $kelas  = $_POST['kelas'];
    $jk     = $_POST['jenis_kelamin'];

    // Cek apakah data sudah ada
    $cek = $conn->query("SELECT * FROM anggota WHERE id_anggota = '$id'");
    if ($cek->num_rows > 0) {
        // Update
        $sql = "UPDATE anggota SET nm_anggota='$nama', kelas='$kelas', jenis_kelamin='$jk' WHERE id_anggota='$id'";
    } else {
        // Tambah baru
        $sql = "INSERT INTO anggota (id_anggota, nm_anggota, kelas, jenis_kelamin) VALUES ('$id', '$nama', '$kelas', '$jk')";
    }

    if ($conn->query($sql)) {
        echo '<div class="alert alert-success">Data berhasil disimpan.</div>';
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=anggota.php">';
    } else {
        echo '<div class="alert alert-danger">Gagal menyimpan data: ' . $conn->error . '</div>';
    }
}
?>

<div class="container mt-4">
    <h3 class="mb-4 text-center"><?= $editData ? 'Edit' : 'Tambah' ?> Anggota</h3>
    <form method="POST" class="border p-4 bg-light rounded">
        <div class="mb-3">
            <label>ID Anggota:</label>
            <input type="text" name="id_anggota" class="form-control" value="<?= htmlspecialchars($idAnggota) ?>" readonly>
        </div>
        <div class="mb-3">
            <label>Nama Anggota:</label>
            <input type="text" name="nm_anggota" class="form-control" required value="<?= $editData ? htmlspecialchars($editData['nm_anggota']) : '' ?>">
        </div>
        <div class="mb-3">
            <label>Kelas:</label>
            <input type="number" name="kelas" class="form-control" required value="<?= $editData ? htmlspecialchars($editData['kelas']) : '' ?>">
        </div>
        <div class="mb-3">
            <label>Jenis Kelamin:</label>
            <select name="jenis_kelamin" class="form-select" required>
                <option value="">-- Pilih Jenis Kelamin --</option>
                <option value="L" <?= ($editData && $editData['jenis_kelamin'] == 'L') ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= ($editData && $editData['jenis_kelamin'] == 'P') ? 'selected' : '' ?>>Perempuan</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?= $editData ? 'Update' : 'Simpan' ?></button>
        <a href="?page=perpus_utama&panggil=anggota.php" class="btn btn-secondary">Batal</a>
    </form>
</div>