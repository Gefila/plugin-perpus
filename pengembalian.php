<?php
// Handle hapus data pengembalian
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $sqlDel = "DELETE FROM pengembalian WHERE id_pengembalian = '$idHapus'";
    if ($conn->query($sqlDel)) {
        echo '<div class="alert alert-success">Data pengembalian berhasil dihapus.</div>';
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=pengembalian.php">';
    } else {
        echo '<div class="alert alert-danger">Gagal menghapus data pengembalian.</div>';
    }
}

// Ambil semua data pengembalian dengan join ke peminjaman dan anggota
$sql = "SELECT p.id_pengembalian, p.tanggal_kembali, pj.id_peminjaman, a.id_anggota, a.nm_anggota
        FROM pengembalian p
        JOIN peminjaman pj ON p.id_peminjaman = pj.id_peminjaman
        JOIN anggota a ON pj.id_anggota = a.id_anggota
        ORDER BY p.id_pengembalian DESC";

$result = $conn->query($sql);
?>

<h2 class="text-center">Daftar Pengembalian</h2>

<!-- Tombol tambah -->
<a href="admin.php?page=perpus_utama&panggil=tambah_pengembalian.php" class="btn btn-success mb-3">
    <i class="fa fa-plus"></i> Tambah Pengembalian
</a>

<table class="table table-striped">
    <thead>
        <tr>
            <th>No</th>
            <th>No. Pengembalian</th>
            <th>Tanggal Kembali</th>
            <th>No. Peminjaman</th>
            <th>ID Anggota</th>
            <th>Nama Anggota</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                    <td>{$no}</td>
                    <td>" . htmlspecialchars($row['id_pengembalian']) . "</td>
                    <td>" . htmlspecialchars($row['tanggal_kembali']) . "</td>
                    <td>" . htmlspecialchars($row['id_peminjaman']) . "</td>
                    <td>" . htmlspecialchars($row['id_anggota']) . "</td>
                    <td>" . htmlspecialchars($row['nm_anggota']) . "</td>
                    <td>
                        <a href='?page=perpus_utama&panggil=pengembalian.php&hapus=" . urlencode($row['id_pengembalian']) . "' 
                           class='btn btn-danger btn-sm' onclick=\"return confirm('Yakin hapus data ini?')\">Hapus</a>
                    </td>
                </tr>";
                $no++;
            }
        } else {
            echo '<tr><td colspan="7" class="text-center">Tidak ada data pengembalian</td></tr>';
        }
        ?>
    </tbody>
</table>