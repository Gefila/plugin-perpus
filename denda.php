<?php

// Hapus jika bukan D1
if (isset($_GET['hapus'])) {
    $id = $conn->real_escape_string($_GET['hapus']);
    if ($id !== 'D1') {
        $conn->query("DELETE FROM denda WHERE id_denda = '$id'");
        echo "<script>
            alert('Data denda berhasil dihapus!');
            window.location.href='admin.php?page=perpus_utama&panggil=denda.php';
        </script>";
        exit;
    }
}

// Ambil data D1 dan lainnya
$denda_d1 = $conn->query("SELECT * FROM denda WHERE id_denda = 'D1'")->fetch_assoc();
$denda_lain = $conn->query("SELECT * FROM denda WHERE id_denda != 'D1'");
?>

<!-- Bootstrap & Font Awesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

<!-- Custom Style -->
<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet" />

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="color:#2980b9 "><i class="fas fa-money-bill-wave"></i> Data Denda Perpustakaan</h2>
        <a href="admin.php?page=perpus_utama&panggil=tambah_denda.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah Denda
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-danger text-center">
                <tr>
                    <th scope="col">No</th>
                    <th scope="col">ID Denda</th>
                    <th scope="col">Tarif</th>
                    <th scope="col">Alasan</th>
                    <th scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;

                // Tampilkan D1
                if ($denda_d1):
                ?>
                <tr class="table-light">
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($denda_d1['id_denda']) ?></td>
                    <td class="text-danger fw-semibold">Rp<?= number_format($denda_d1['tarif_denda'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($denda_d1['alasan_denda']) ?></td>
                    <td class="text-center">
                        <a href="admin.php?page=perpus_utama&panggil=tambah_denda.php&edit=<?= $denda_d1['id_denda'] ?>" class="btn btn-warning btn-glow me-1">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </td>
                </tr>
                <?php endif; ?>

                <?php
                // Tampilkan data lainnya
                if ($denda_lain && $denda_lain->num_rows > 0):
                    while ($row = $denda_lain->fetch_assoc()):
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['id_denda']) ?></td>
                    <td class="text-danger fw-semibold">Rp<?= number_format($row['tarif_denda'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['alasan_denda']) ?></td>
                    <td class="text-center">
                        <a href="admin.php?page=perpus_utama&panggil=tambah_denda.php&edit=<?= $row['id_denda'] ?>" class="btn btn-warning btn-glow me-1">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="admin.php?page=perpus_utama&panggil=denda.php&hapus=<?= $row['id_denda'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus data ini?')">
                            <i class="fas fa-trash"></i> Hapus
                        </a>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="fas fa-money-bill-alt fa-2x d-block mb-2"></i>
                        Tidak ada data denda ditemukan.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
