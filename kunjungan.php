<?php
// ambil data kunjungan beserta nama anggota
$result = $conn->query("SELECT k.*, a.nm_anggota FROM kunjungan k JOIN anggota a ON k.id_anggota = a.id_anggota ORDER BY k.tgl_kunjungan DESC");
?>

<!-- CDN Bootstrap dan Font Awesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary">📚 Data Kunjungan Perpustakaan</h2>
        <a href="admin.php?page=perpus_utama&panggil=tambah_kunjungan.php" class="btn btn-success shadow-sm">
            <i class="fa fa-plus-circle me-1"></i> Tambah Kunjungan
        </a>
    </div>

    <div class="table-responsive shadow-sm rounded bg-white p-3">
        <table class="table table-hover table-bordered align-middle">
            <thead class="table-primary text-center">
                <tr>
                    <th>ID</th>
                    <th>Nama Pengunjung</th>
                    <th>Tanggal Kunjungan</th>
                    <th>Tujuan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id_kunjungan']) ?></td>
                        <td><?= htmlspecialchars($row['nm_anggota']) ?></td>
                        <td><?= date("d M Y", strtotime($row['tgl_kunjungan'])) ?></td>
                        <td><?= htmlspecialchars($row['tujuan']) ?></td>
                        <td class="text-center">
                            <a href="admin.php?page=perpus_utama&panggil=tambah_kunjungan.php&id_kunjungan=<?= htmlspecialchars($row['id_kunjungan']) ?>" 
                               class="btn btn-warning btn-sm">
                                <i class="fa fa-edit"></i> Edit
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
