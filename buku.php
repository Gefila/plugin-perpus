<?php
global $conn;

// Proses hapus jika ada parameter ?hapus
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $conn->query("DELETE FROM buku WHERE id_buku = '$idHapus'");
    echo "<script>window.location.href='admin.php?page=perpus_utama&panggil=buku.php';</script>";
    exit;
}

// Proses edit jika ada parameter ?edit
$editData = null;
if (isset($_GET['edit'])) {
    $idEdit = $conn->real_escape_string($_GET['edit']);
    $queryEdit = $conn->query("SELECT * FROM buku WHERE id_buku = '$idEdit'");
    $editData = $queryEdit->fetch_assoc();
}

// Proses update buku
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $idBuku = $conn->real_escape_string($_POST['id_buku']);
    $judul = $conn->real_escape_string($_POST['judul_buku']);
    $pengarang = $conn->real_escape_string($_POST['pengarang']);
    $tahun = $conn->real_escape_string($_POST['thn_terbit']);
    $jumlah = $conn->real_escape_string($_POST['jml_buku']);
    $penerbit = $conn->real_escape_string($_POST['penerbit']);
    $kategori = $conn->real_escape_string($_POST['id_kategori']);

    $conn->query("UPDATE buku SET 
        judul_buku = '$judul', 
        pengarang = '$pengarang', 
        thn_terbit = '$tahun', 
        jml_buku = '$jumlah', 
        penerbit = '$penerbit', 
        id_kategori = '$kategori'
        WHERE id_buku = '$idBuku'");
    
    echo "<script>window.location.href='admin.php?page=perpus_utama&panggil=buku.php';</script>";
    exit;
}

// Ambil data buku
$sql = "SELECT buku.*, kategori.nm_kategori 
        FROM buku 
        LEFT JOIN kategori ON buku.id_kategori = kategori.id_kategori 
        ORDER BY id_buku ASC";

$result = $conn->query($sql);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<div class="container mt-4">
    <h3 class="mb-3">Daftar Buku</h3>
    <a href="admin.php?page=perpus_utama&panggil=tambah_buku.php" class="btn btn-primary mb-3">Tambah Buku</a>

    <!-- Formulir Edit -->
    <?php if ($editData): ?>
        <h4>Edit Buku</h4>
        <form method="post" class="mb-3">
            <input type="hidden" name="id_buku" value="<?= $editData['id_buku'] ?>">
            <div class="mb-2">
                <label>Judul Buku</label>
                <input type="text" name="judul_buku" value="<?= htmlspecialchars($editData['judul_buku']) ?>" class="form-control" required>
            </div>
            <div class="mb-2">
                <label>Pengarang</label>
                <input type="text" name="pengarang" value="<?= htmlspecialchars($editData['pengarang']) ?>" class="form-control" required>
            </div>
            <div class="mb-2">
                <label>Tahun Terbit</label>
                <input type="number" name="thn_terbit" value="<?= $editData['thn_terbit'] ?>" class="form-control" required>
            </div>
            <div class="mb-2">
                <label>Jumlah Buku</label>
                <input type="number" name="jml_buku" value="<?= $editData['jml_buku'] ?>" class="form-control" required>
            </div>
            <div class="mb-2">
                <label>Penerbit</label>
                <input type="text" name="penerbit" value="<?= htmlspecialchars($editData['penerbit']) ?>" class="form-control" required>
            </div>
            <div class="mb-2">
                <label>Kategori</label>
                <input type="text" name="id_kategori" value="<?= htmlspecialchars($editData['id_kategori']) ?>" class="form-control" required>
            </div>
            <button type="submit" name="update" class="btn btn-success">Update Buku</button>
        </form>
    <?php endif; ?>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Judul</th>
                <th>Pengarang</th>
                <th>Tahun</th>
                <th>Jumlah</th>
                <th>Penerbit</th>
                <th>Kategori</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()) : ?>
                <tr>
                    <td><?= $row['id_buku'] ?></td>
                    <td><?= htmlspecialchars($row['judul_buku']) ?></td>
                    <td><?= htmlspecialchars($row['pengarang']) ?></td>
                    <td><?= $row['thn_terbit'] ?></td>
                    <td><?= $row['jml_buku'] ?></td>
                    <td><?= htmlspecialchars($row['penerbit']) ?></td>
                    <td><?= htmlspecialchars($row['nm_kategori']) ?></td>
                    <td>
                        <a href="admin.php?page=perpus_utama&panggil=buku.php&edit=<?= $row['id_buku'] ?>" class="btn btn-warning btn-sm">Edit</a>
                        <a href="admin.php?page=perpus_utama&panggil=buku.php&hapus=<?= $row['id_buku'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus buku ini?')">Hapus</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
