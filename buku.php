<?php

// Proses hapus jika ada parameter ?hapus
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $conn->query("DELETE FROM buku WHERE id_buku = '$idHapus'");
    echo "<script>window.location.href='admin.php?page=perpus_utama&panggil=buku.php';</script>";
}


// Ambil data buku
$sql = "SELECT buku.*, kategori.nm_kategori 
        FROM buku 
        LEFT JOIN kategori ON buku.id_kategori = kategori.id_kategori 
        ORDER BY id_buku ASC";

$result = $conn->query($sql);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<h3 class="mb-3">Daftar Buku</h3>
<a href="admin.php?page=perpus_utama&panggil=tambah_buku.php" class="btn btn-primary mb-3">Tambah Buku</a>


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
                    <a href="admin.php?page=perpus_utama&panggil=tambah_buku.php&edit=<?= $row['id_buku'] ?>" class="btn btn-warning btn-sm">Edit</a>
                    <a href="admin.php?page=perpus_utama&panggil=buku.php&hapus=<?= $row['id_buku'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus buku ini?')">Hapus</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>