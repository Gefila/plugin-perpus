<?php
// Handle hapus data denda
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $sqlDel = "DELETE FROM denda WHERE id_denda = '$idHapus'";
    if ($conn->query($sqlDel)) {
        echo '<div class="alert alert-success">Data denda berhasil dihapus.</div>';
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=denda.php">';
    } else {
        echo '<div class="alert alert-danger">Gagal menghapus data denda.</div>';
    }
}

// Ambil data denda lengkap
$sql = "SELECT d.id_denda, d.tanggal_denda, d.harga_denda, d.alasan, 
               p.id_pengembalian, pj.tanggal_pinjam, 
               a.id_anggota, a.nm_anggota
        FROM denda d
        JOIN pengembalian p ON d.id_pengembalian = p.id_pengembalian
        JOIN peminjaman pj ON p.id_peminjaman = pj.id_peminjaman
        JOIN anggota a ON pj.id_anggota = a.id_anggota
        ORDER BY d.id_denda DESC";

$result = $conn->query($sql);
?>

<h2 class="text-center">Daftar Denda</h2>

<!-- Tombol tambah -->
<a href="admin.php?page=perpus_utama&panggil=tambah_denda.php" class="btn btn-success mb-3">
    <i class="fa fa-plus"></i> Tambah Denda
</a>

<table class="table table-striped table-bordered">
    <thead class="table-dark text-center">
        <tr>
            <th>No. Denda</th>
            <th>Tanggal Denda</th>
            <th>No. Pengembalian</th>
            <th>Tanggal Peminjaman</th>
            <th>Harga Denda</th>
            <th>Alasan Denda</th>
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
                    <td class='text-center'>{$no}</td>
                    <td>" . htmlspecialchars($row['id_denda']) . "</td>
                    <td>" . htmlspecialchars($row['tanggal_denda']) . "</td>
                    <td>" . htmlspecialchars($row['id_pengembalian']) . "</td>
                    <td>" . htmlspecialchars($row['tanggal_pinjam']) . "</td>
                    <td>Rp " . number_format($row['harga_denda'], 0, ',', '.') . "</td>
                    <td>" . htmlspecialchars($row['alasan']) . "</td>
                    <td>" . htmlspecialchars($row['id_anggota']) . "</td>
                    <td>" . htmlspecialchars($row['nm_anggota']) . "</td>
                    <td class='text-center'>
                        <a href='?page=perpus_utama&panggil=denda.php&hapus=" . urlencode($row['id_denda']) . "' 
                           class='btn btn-danger btn-sm' onclick=\"return confirm('Yakin hapus data ini?')\">Hapus</a>
                    </td>
                </tr>";
                $no++;
            }
        } else {
            echo '<tr><td colspan="10" class="text-center">Tidak ada data denda</td></tr>';
        }
        ?>
    </tbody>
</table>