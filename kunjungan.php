<?php
// Ambil data kunjungan dan nama anggota
$conn = new mysqli("localhost", "root", "", "db_ti6b_uas");
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

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
<style>
    body {
        background: linear-gradient(to right, #eef3ff, #dce7ff);
        font-family: 'Segoe UI', sans-serif;
    }
    .card-glass {
        background: rgba(255, 255, 255, 0.85);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }
    .btn-glow {
        transition: 0.3s ease;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }
    .btn-glow:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
    }
    .table thead th {
        background: linear-gradient(to right, #2c3e50, #3498db);
        color: #fff;
        border: none;
    }
    .table thead th:first-child {
        border-top-left-radius: 12px;
    }
    .table thead th:last-child {
        border-top-right-radius: 12px;
    }
</style>

<!-- Konten -->
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark">
            <i class="fa-solid fa-user-clock text-primary me-2"></i>Data Kunjungan Perpustakaan
        </h3>
        <a href="admin.php?page=perpus_utama&panggil=tambah_kunjungan.php" class="btn btn-success btn-glow">
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
                                <a href="admin.php?page=perpus_utama&panggil=tambah_kunjungan.php&id_kunjungan=<?= $row['id_kunjungan'] ?>" 
                                   class="btn btn-warning btn-sm">
                                    <i class="fa fa-edit"></i>
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
