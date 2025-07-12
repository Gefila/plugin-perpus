<?php
// Ambil data kunjungan dan nama anggota
$conn = new mysqli("localhost", "root", "", "db_ti6b_uas");
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $sqlDel = "DELETE FROM kunjungan WHERE id_kunjungan= '$idHapus'";
    if ($conn->query($sqlDel)) {
        echo '<div class="alert alert-success">Data Kunjungan berhasil dihapus.</div>';
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=kunjungan.php">';
    } else {
        echo '<div class="alert alert-danger">Gagal menghapus data kunjungan.</div>';
    }
}


$result = $conn->query("
    SELECT k.*, a.nm_anggota 
    FROM kunjungan k 
    JOIN anggota a ON k.id_anggota = a.id_anggota 
    ORDER BY k.tgl_kunjungan DESC
");
?>

<!-- Bootstrap dan Font Awesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<!-- Custom Style -->
<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet">

<!-- Konten -->
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark">
            <i class="fa-solid fa-user-clock text-primary me-2"></i>Data Kunjungan Perpustakaan
        </h3>
        <a href="admin.php?page=perpus_utama&panggil=tambah_kunjungan.php" class="btn btn-primary btn-glow">
            <i class="fa fa-plus-circle me-1"></i> Tambah Kunjungan
        </a>
    </div>

    <div class="card-glass">
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle text-center">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Pengunjung</th>
                        <th>Tanggal Kunjungan</th>
                        <th>Tujuan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id_kunjungan']) ?></td>
                            <td class="text-start"><?= htmlspecialchars($row['nm_anggota']) ?></td>
                            <td><?= date("d M Y", strtotime($row['tgl_kunjungan'])) ?></td>
                            <td class="text-start"><?= htmlspecialchars($row['tujuan']) ?></td>
                            <td>
                                <a href='?page=perpus_utama&panggil=tambah_kunjungan.php&id_kunjungan=<?= htmlspecialchars($row['id_kunjungan']) ?>' class='btn btn-warning btn-glow me-1'>
                                    <i class='fa fa-edit'></i> Edit
                                </a>
                                <a href='?page=perpus_utama&panggil=kunjungan.php&hapus=<?= htmlspecialchars($row['id_kunjungan']) ?>' class='btn btn-danger btn-glow' onclick="return confirm('Yakin hapus data anggota ini?')">
                                    <i class='fa fa-trash'></i> Hapus
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-muted">Tidak ada data kunjungan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
