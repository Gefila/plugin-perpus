<?php

// Proses hapus data peminjaman
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);

    // Ambil semua no_copy_buku terkait peminjaman ini
    $copyQuery = "SELECT no_copy_buku FROM dapat WHERE no_peminjaman = '$idHapus'";
    $copyResult = $conn->query($copyQuery);

    if ($copyResult && $copyResult->num_rows > 0) {
        while ($rowCopy = $copyResult->fetch_assoc()) {
            $noCopy = $rowCopy['no_copy_buku'];
            // Update status copy_buku jadi 'tersedia'
            $conn->query("UPDATE copy_buku SET status_buku = 'tersedia' WHERE no_copy_buku = '$noCopy'");
        }
    }

    // Hapus data detail di tabel dapat
    $conn->query("DELETE FROM dapat WHERE no_peminjaman = '$idHapus'");

    // Hapus data peminjaman
    $conn->query("DELETE FROM peminjaman WHERE no_peminjaman = '$idHapus'");

    echo "<script>
            alert('Data peminjaman berhasil dihapus dan status buku dikembalikan.');
            window.location.href='admin.php?page=perpus_utama&panggil=peminjaman.php';
          </script>";
    exit;
}

// Ambil data peminjaman dan anggota
$sql = "SELECT peminjaman.*, anggota.nm_anggota 
        FROM peminjaman 
        LEFT JOIN anggota ON peminjaman.id_anggota = anggota.id_anggota 
        ORDER BY no_peminjaman ASC";
$result = $conn->query($sql);

if (!$result) {
    die("Query Error: " . $conn->error);
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<h2 class="text-center mb-4">Data Peminjaman</h2>
<a href="admin.php?page=perpus_utama&panggil=tambah_peminjaman.php" class="btn btn-primary mb-3">Tambah Peminjaman</a>

<table class="table table-bordered table-striped">
    <thead class="table-dark text-center">
        <tr>
            <th>No</th>
            <th>No Peminjaman</th>
            <th>Tanggal Pinjam</th>
            <th>Tanggal Kembali</th>
            <th>ID Anggota</th>
            <th>Nama Anggota</th>
            <th>Detail Buku Dipinjam</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        while ($row = $result->fetch_assoc()) : 
            $no_peminjaman = $row['no_peminjaman'];

            // Ambil detail buku yang dipinjam pada peminjaman ini
            $detailSql = "
                SELECT b.id_buku, b.judul_buku, COUNT(d.no_copy_buku) AS jumlah
                FROM dapat d
                INNER JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
                INNER JOIN buku b ON cb.id_buku = b.id_buku
                WHERE d.no_peminjaman = '$no_peminjaman'
                GROUP BY b.id_buku, b.judul_buku
            ";
            $detailResult = $conn->query($detailSql);
        ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center"><?= htmlspecialchars($row['no_peminjaman']) ?></td>
                <td><?= date('d-m-Y', strtotime($row['tgl_peminjaman'])) ?></td>
                <td><?= date('d-m-Y', strtotime($row['tgl_harus_kembali'])) ?></td>
                <td class="text-center"><?= htmlspecialchars($row['id_anggota']) ?></td>
                <td><?= htmlspecialchars($row['nm_anggota']) ?></td>
                <td>
                    <?php if ($detailResult && $detailResult->num_rows > 0): ?>
                        <ul class="mb-0">
                            <?php while ($detail = $detailResult->fetch_assoc()): ?>
                                <li>
                                    <strong>ID:</strong> <?= htmlspecialchars($detail['id_buku']) ?>,
                                    <strong>Judul:</strong> <?= htmlspecialchars($detail['judul_buku']) ?>,
                                    <strong>Jumlah:</strong> <?= $detail['jumlah'] ?>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <em>Tidak ada data buku</em>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <a href="admin.php?page=perpus_utama&panggil=peminjaman.php&hapus=<?= urlencode($row['no_peminjaman']) ?>" 
                       class="btn btn-danger btn-sm" 
                       onclick="return confirm('Yakin ingin menghapus data ini?')">Hapus</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>