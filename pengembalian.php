<?php
// Asumsi $conn sudah konek ke database MySQL

// Hapus data pengembalian jika ada parameter hapus
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $conn->query("DELETE FROM pengembalian_denda WHERE no_pengembalian = '$idHapus'");
    $conn->query("DELETE FROM bisa WHERE no_pengembalian = '$idHapus'");
    $conn->query("DELETE FROM pengembalian WHERE no_pengembalian = '$idHapus'");

    echo "<script>
            alert('Data pengembalian berhasil dihapus.');
            window.location.href='admin.php?page=perpus_utama&panggil=pengembalian.php';
          </script>";
    exit;
}

// Query data pengembalian lengkap dengan total denda per pengembalian
$sql = "
SELECT
    p.no_pengembalian, p.no_peminjaman, p.tgl_pengembalian,
    pm.id_anggota, a.nm_anggota,
    pm.tgl_harus_kembali,
    GROUP_CONCAT(DISTINCT CONCAT(b.id_buku, ' - ', b.judul_buku) SEPARATOR '<br>') AS buku_list,
    GROUP_CONCAT(DISTINCT bs.no_copy_buku SEPARATOR ', ') AS no_copy,
    COUNT(DISTINCT bs.no_copy_buku) AS jumlah,
    IFNULL(pd.total_denda, 0) AS total_denda
FROM pengembalian p
LEFT JOIN peminjaman pm ON p.no_peminjaman = pm.no_peminjaman
LEFT JOIN anggota a ON pm.id_anggota = a.id_anggota
LEFT JOIN bisa bs ON p.no_pengembalian = bs.no_pengembalian
LEFT JOIN copy_buku cb ON bs.no_copy_buku = cb.no_copy_buku
LEFT JOIN buku b ON cb.id_buku = b.id_buku
LEFT JOIN (
    SELECT no_pengembalian, SUM(subtotal) AS total_denda
    FROM pengembalian_denda
    GROUP BY no_pengembalian
) pd ON p.no_pengembalian = pd.no_pengembalian
GROUP BY p.no_pengembalian
ORDER BY p.no_pengembalian ASC
";

$result = $conn->query($sql);

$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

?>

<!-- Bootstrap & Font Awesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<!-- Custom Style (ubah sesuai lokasi file style kamu) -->
<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet">

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark">
            <i class="fa-solid fa-rotate-left text-primary me-2"></i>Data Pengembalian
        </h3>
        <a href="admin.php?page=perpus_utama&panggil=tambah_pengembalian.php" class="btn btn-primary btn-glow">
            <i class="fa fa-plus me-1"></i> Tambah Pengembalian
        </a>
    </div>

    <div class="card-glass">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No Pengembalian</th>
                        <th>ID Anggota</th>
                        <th>Nama</th>
                        <th>Judul Buku</th>
                        <th>Copy Buku</th>
                        <th>Jumlah Kembali</th>
                        <th>Tgl Kembali</th>
                        <th>Status</th>
                        <th>Total Denda</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data)): ?>
                        <?php $no=1; foreach ($data as $item):
                            $tglKembali = strtotime($item['tgl_pengembalian']);
                            $tglHarus = strtotime($item['tgl_harus_kembali']);
                            $hariTelat = 0;
                            if ($tglKembali > $tglHarus) {
                                $hariTelat = floor(($tglKembali - $tglHarus) / (60 * 60 * 24));
                            }
                        ?>
                        <tr>
                            <td><?= $no ?></td>
                            <td><?= htmlspecialchars($item['no_pengembalian']) ?></td>
                            <td><?= htmlspecialchars($item['id_anggota']) ?></td>
                            <td><?= htmlspecialchars($item['nm_anggota']) ?></td>
                            <td style="text-align:left;"><?= $item['buku_list'] ?></td>
                            <td><?= htmlspecialchars($item['no_copy']) ?></td>
                            <td><span class="badge bg-secondary"><?= $item['jumlah'] ?></span></td>
                            <td><?= date('d M Y', strtotime($item['tgl_pengembalian'])) ?></td>
                            <td>
                                <?php 
                                $tglKembali = strtotime($item['tgl_pengembalian']);
                                $tglHarus = strtotime($item['tgl_harus_kembali']);
                                if ($tglKembali > $tglHarus) {
                                    echo "<span class='badge bg-danger'>Terlambat {$hariTelat} hari </span>";
                                } else {
                                    echo "<span class='badge bg-success'>Tepat Waktu</span>";
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($item['total_denda'] > 0): ?>
                                    <span class="badge bg-warning text-dark">
                                        Rp <?= number_format($item['total_denda'], 0, ',', '.') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="admin.php?page=perpus_utama&panggil=pengembalian.php&hapus=<?= urlencode($item['no_pengembalian']) ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Yakin ingin menghapus data ini?')">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php $no++; endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="11" class="text-muted">Tidak ada data pengembalian.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
