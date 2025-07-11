<?php
// Koneksi ke database
$conn = new mysqli("localhost", "root", "", "db_ti6b_uas");
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Hapus data pengembalian
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $conn->query("DELETE FROM bisa WHERE no_pengembalian = '$idHapus'");
    $conn->query("DELETE FROM pengembalian WHERE no_pengembalian = '$idHapus'");

    echo "<script>
            alert('Data pengembalian berhasil dihapus.');
            window.location.href='admin.php?page=perpus_utama&panggil=pengembalian.php';
          </script>";
    exit;
}

// Query data pengembalian
$sql = "
    SELECT 
        p.no_pengembalian, p.no_peminjaman, p.tgl_pengembalian, 
        pm.id_anggota, a.nm_anggota,
        pm.tgl_harus_kembali,
        b.id_buku, b.judul_buku,
        GROUP_CONCAT(bs.no_copy_buku SEPARATOR ', ') AS no_copy,
        COUNT(bs.no_copy_buku) AS jumlah
    FROM pengembalian p
    LEFT JOIN peminjaman pm ON p.no_peminjaman = pm.no_peminjaman
    LEFT JOIN anggota a ON pm.id_anggota = a.id_anggota
    LEFT JOIN bisa bs ON p.no_pengembalian = bs.no_pengembalian
    LEFT JOIN copy_buku cb ON bs.no_copy_buku = cb.no_copy_buku
    LEFT JOIN buku b ON cb.id_buku = b.id_buku
    GROUP BY p.no_pengembalian, b.id_buku
    HAVING jumlah > 0
    ORDER BY p.no_pengembalian ASC
";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[$row['no_pengembalian']][] = $row;
    }
}
?>

<!-- Bootstrap & Font Awesome -->
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
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
    }
    .btn-glow:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(99, 150, 226, 0.35);
    }
    .table thead th {
        background: linear-gradient(to right, #2c3e50, #2980b9);
        color: #fff;
        border: none;
    }
    .table thead th:first-child {
        border-top-left-radius: 12px;
    }
    .table thead th:last-child {
        border-top-right-radius: 12px;
    }
    .badge-copy {
        background-color: #8a92ec;
        padding: 5px 10px;
        border-radius: 12px;
        font-size: 0.85em;
    }
</style>

<!-- Konten -->
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
                        <th>ID - Judul Buku</th>
                        <th>Copy Buku</th>
                        <th>Jumlah</th>
                        <th>Tgl Kembali</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    if (!empty($data)):
                        foreach ($data as $no_pengembalian => $items):
                            $first = true;
                            foreach ($items as $item):
                    ?>
                    <tr>
                        <td><?= $first ? $no : '' ?></td>
                        <td><?= $first ? htmlspecialchars($item['no_pengembalian']) : '' ?></td>
                        <td><?= $first ? htmlspecialchars($item['id_anggota']) : '' ?></td>
                        <td><?= $first ? htmlspecialchars($item['nm_anggota']) : '' ?></td>
                        <td><?= htmlspecialchars($item['id_buku']) ?> - <strong><?= htmlspecialchars($item['judul_buku']) ?></strong></td>
                        <td><span class="badge-copy"><?= htmlspecialchars($item['no_copy']) ?></span></td>
                        <td><span class="badge bg-secondary"><?= $item['jumlah'] ?></span></td>

                        <?php if ($first): ?>
                        <td><?= htmlspecialchars(date('d M Y', strtotime($item['tgl_pengembalian']))) ?></td>
                        <td>
                            <?php
                                $tglKembali = strtotime($item['tgl_pengembalian']);
                                $tglHarus = strtotime($item['tgl_harus_kembali']);
                                if ($tglKembali > $tglHarus) {
                                    echo "<span class='badge bg-danger'>Terlambat</span>";
                                } else {
                                    echo "<span class='badge bg-success'>Tepat Waktu</span>";
                                }
                            ?>
                        </td>
                        <td>
                            <a href="admin.php?page=perpus_utama&panggil=pengembalian.php&hapus=<?= urlencode($item['no_pengembalian']) ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Yakin ingin menghapus data ini?')">
                                <i class="fa fa-trash"></i>
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php 
                            $first = false;
                            endforeach;
                            $no++;
                        endforeach;
                    else: ?>
                    <tr>
                        <td colspan="10" class="text-muted">Tidak ada data pengembalian.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
