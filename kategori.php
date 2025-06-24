<?php

// Handle hapus data
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $sqlDel = "DELETE FROM kategori WHERE id_kategori = '$idHapus'";
    if ($conn->query($sqlDel)) {
        echo '<div class="alert alert-success">Data berhasil dihapus.</div>';
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=kategori.php">';
    } else {
        echo '<div class="alert alert-danger">Gagal menghapus data.</div>';
    }
}

// Ambil semua data untuk ditampilkan
$result = $conn->query("SELECT * FROM kategori ORDER BY id_kategori");

?>

<h2 class="text-center">Daftar Kategori</h2>

<!-- Tombol tambah -->
<a href="admin.php?page=perpus_utama&panggil=tambah_kategori.php" class="btn btn-success mb-3">
    <i class="fa fa-plus"></i> Tambah Kategori
</a>

<table class="table table-striped">
    <thead>
        <tr>
            <th>No</th>
            <th>ID Kategori</th>
            <th>Nama Kategori</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $id = htmlspecialchars($row['id_kategori']);
                $nama = htmlspecialchars($row['nm_kategori']);
                echo "<tr>
                    <td>$no</td>
                    <td>$id</td>
                    <td>$nama</td>
                    <td>
                        <a href='?page=perpus_utama&panggil=tambah_kategori.php&edit=$id' class='btn btn-warning btn-sm'>Edit</a>
                        <a href='?page=perpus_utama&panggil=kategori.php&hapus=$id' class='btn btn-danger btn-sm' onclick=\"return confirm('Yakin hapus data ini?')\">Hapus</a>
                    </td>
                  </tr>";
                $no++;
            }
        } else {
            echo '<tr><td colspan="4" class="text-center">Tidak ada data kategori</td></tr>';
        }
        ?>
    </tbody>
</table>