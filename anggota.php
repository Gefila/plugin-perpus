<?php

// Handle hapus data pengunjung
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $sqlDel = "DELETE FROM anggota WHERE id_anggota = '$idHapus'";
    if ($conn->query($sqlDel)) {
        echo '<div class="alert alert-success">Data anggota berhasil dihapus.</div>';
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=anggota.php">';
    } else {
        echo '<div class="alert alert-danger">Gagal menghapus data anggota.</div>';
    }
}

// Ambil semua data anggota
$result = $conn->query("SELECT * FROM anggota ORDER BY id_anggota");

?>

<h2 class="text-center">Daftar anggota</h2>

<!-- Tombol tambah -->
<a href="admin.php?page=perpus_utama&panggil=tambah_anggota.php" class="btn btn-success mb-3">
    <i class="fa fa-plus"></i> Tambah anggota
</a>

<table class="table table-striped">
    <thead>
        <tr>
            <th>No</th>
            <th>ID Anggota</th>
            <th>Nama Anggota</th>
            <th>Kelas</th>
            <th>Jenis Kelamin</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $id     = htmlspecialchars($row['id_anggota']);
                $nama   = htmlspecialchars($row['nm_anggota']);
                $kelas  = htmlspecialchars($row['kelas']);
                $jk     = $row['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan';

                echo "<tr>
                    <td>$no</td>
                    <td>$id</td>
                    <td>$nama</td>
                    <td>$kelas</td>
                    <td>$jk</td>
                    <td>
                        <a href='?page=perpus_utama&panggil=tambah_anggota.php&edit=$id' class='btn btn-warning btn-sm'>Edit</a>
                        <a href='?page=perpus_utama&panggil=anggota.php&hapus=$id' class='btn btn-danger btn-sm' onclick=\"return confirm('Yakin hapus data ini?')\">Hapus</a>
                    </td>
                  </tr>";
                $no++;
            }
        } else {
            echo '<tr><td colspan="6" class="text-center">Tidak ada data pengunjung</td></tr>';
        }
        ?>
    </tbody>
</table>