<?php

// ambil data kunjungan beserta nama anggota
$result = $conn->query("SELECT k.*, a.nm_anggota FROM kunjungan k JOIN anggota a ON k.id_anggota = a.id_anggota ORDER BY k.tgl_kunjungan DESC");
?>

<h2 class="text-center mb-4">Data Kunjungan</h2>

<a href="admin.php?page=perpus_utama&panggil=tambah_kunjungan.php" class="btn btn-success mb-3">Tambah Kunjungan</a>

<div class="table-responsive">
<table class="table table-bordered">
    <thead>
        <tr>
            <th>ID Kunjungan</th>
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
                <td><?= htmlspecialchars($row['tgl_kunjungan']) ?></td>
                <td><?= htmlspecialchars($row['tujuan']) ?></td>
                <td>
                    <a href="admin.php?page=perpus_utama&panggil=tambah_kunjungan.php&id_kunjungan=<?= htmlspecialchars($row['id_kunjungan']) ?>" class="btn btn-warning btn-sm">Edit</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>
