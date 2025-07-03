<?php
$conn = new mysqli("localhost", "root", "", "db_ti6b_uas");
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

// Proses hapus denda jika ada parameter ?hapus=
if (isset($_GET['hapus'])) {
    $id = $conn->real_escape_string($_GET['hapus']);
    $conn->query("DELETE FROM denda WHERE no_denda = '$id'");

    echo "<script>
        alert('Data denda berhasil dihapus!');
        window.location.href='admin.php?page=perpus_utama&panggil=denda.php';
    </script>";
    exit;
}

// Ambil data denda + anggota
$denda = $conn->query("SELECT d.*, a.nm_anggota FROM denda d 
    LEFT JOIN pengembalian p ON d.no_pengembalian = p.no_pengembalian
    LEFT JOIN peminjaman pm ON p.no_peminjaman = pm.no_peminjaman
    LEFT JOIN anggota a ON pm.id_anggota = a.id_anggota");
?>

<div class="container-fluid mt-4">
  <h2 class="text-center">Data Denda</h2>
  <a href="admin.php?page=perpus_utama&panggil=tambah_denda.php" class="btn btn-primary mb-3">+ Tambah Denda</a>

  <table class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr>
        <th>No</th>
        <th>No Denda</th>
        <th>Nama Anggota</th>
        <th>Tarif</th>
        <th>Alasan</th>
        <th>Tanggal Denda</th>
        <th class="text-center">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php $no = 1; while ($row = $denda->fetch_assoc()): ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= $row['no_denda'] ?></td>
          <td><?= $row['nm_anggota'] ?></td>
          <td>Rp<?= number_format($row['tarif_denda'], 0, ',', '.') ?></td>
          <td><?= $row['alasan_denda'] ?></td>
          <td><?= date('d-m-Y', strtotime($row['tgl_denda'])) ?></td>
          <td class="text-center">
            <a href="admin.php?page=perpus_utama&panggil=denda.php&hapus=<?= $row['no_denda'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Yakin ingin menghapus data ini?')">Hapus</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
