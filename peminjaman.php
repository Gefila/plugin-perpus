<?php
// Koneksi ke database
$conn = new mysqli("localhost", "root", "", "db_ti6b_uas");
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Proses hapus
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);

    $copyQuery = "SELECT no_copy_buku FROM dapat WHERE no_peminjaman = '$idHapus'";
    $copyResult = $conn->query($copyQuery);

    if ($copyResult && $copyResult->num_rows > 0) {
        while ($rowCopy = $copyResult->fetch_assoc()) {
            $noCopy = $rowCopy['no_copy_buku'];
            $conn->query("UPDATE copy_buku SET status_buku = 'tersedia' WHERE no_copy_buku = '$noCopy'");
        }
    }

    $conn->query("DELETE FROM dapat WHERE no_peminjaman = '$idHapus'");
    $conn->query("DELETE FROM peminjaman WHERE no_peminjaman = '$idHapus'");

    echo "<script>
            alert('Data peminjaman berhasil dihapus.');
            window.location.href='admin.php?page=perpus_utama&panggil=peminjaman.php';
          </script>";
    exit;
}

// Ambil data peminjaman dengan format baru
$sql = "
    SELECT 
        p.no_peminjaman, 
        p.tgl_peminjaman, 
        p.tgl_harus_kembali, 
        p.id_anggota, 
        a.nm_anggota,
        b.id_buku, b.judul_buku,
        d.no_copy_buku
    FROM peminjaman p
    LEFT JOIN anggota a ON p.id_anggota = a.id_anggota
    LEFT JOIN dapat d ON p.no_peminjaman = d.no_peminjaman
    LEFT JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
    LEFT JOIN buku b ON cb.id_buku = b.id_buku
    ORDER BY p.no_peminjaman ASC, b.id_buku ASC, d.no_copy_buku ASC
";

$result = $conn->query($sql);

// Susun data peminjaman menjadi array terstruktur
$peminjamanData = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $no = $row['no_peminjaman'];

        if (!isset($peminjamanData[$no])) {
            $peminjamanData[$no] = [
                'no_peminjaman' => $no,
                'tgl_peminjaman' => $row['tgl_peminjaman'],
                'tgl_harus_kembali' => $row['tgl_harus_kembali'],
                'id_anggota' => $row['id_anggota'],
                'nm_anggota' => $row['nm_anggota'],
                'buku' => [],
                'total_copy' => 0
            ];
        }

        $id_buku = $row['id_buku'];
        $judul_buku = $row['judul_buku'];
        $copy = $row['no_copy_buku'];

        if (!isset($peminjamanData[$no]['buku'][$id_buku])) {
            $peminjamanData[$no]['buku'][$id_buku] = [
                'judul' => $judul_buku,
                'copy' => []
            ];
        }

        if ($copy) {
            $peminjamanData[$no]['buku'][$id_buku]['copy'][] = $copy;
            $peminjamanData[$no]['total_copy']++;
        }
    }
}
?>

<!-- Bootstrap & Font Awesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<!-- Custom Style -->
<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet">

<!-- Konten -->
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark">
            <i class="fa-solid fa-book-reader text-primary me-2"></i>Data Peminjaman
        </h3>
        <a href="admin.php?page=perpus_utama&panggil=tambah_peminjaman.php" class="btn btn-primary btn-glow">
            <i class="fa fa-plus-circle me-1"></i> Tambah Peminjaman
        </a>
    </div>

    <div class="card-glass">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead>
                    <tr class="text-center">
                        <th>No</th>
                        <th>No Peminjaman</th>
                        <th style="width:130px">Tgl Pinjam</th>
                        <th style="width:130px">Tgl Kembali</th>
                        <th>ID Anggota</th>
                        <th style="width:130px">Nama</th>
                        <th>Buku yang Dipinjam</th>
                        <th style="width:130px">Copy Buku</th>
                        <th>Jumlah</th>
                        <th style="width:130px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
               $no = 1;
                        if (!empty($peminjamanData)):
                            foreach ($peminjamanData as $data):
                        ?>
                        <tr>
                            <td class="text-center"><?= $no ?></td>
                            <td class="text-center"><?= $data['no_peminjaman'] ?></td>
                            <td class="text-center"><?= date('d M Y', strtotime($data['tgl_peminjaman'])) ?></td>
                            <td class="text-center"><?= date('d M Y', strtotime($data['tgl_harus_kembali'])) ?></td>
                            <td class="text-center"><?= $data['id_anggota'] ?></td>
                            <td class="text-center"><?= $data['nm_anggota'] ?></td>

                            <td>
                                <?php foreach ($data['buku'] as $id_buku => $b): ?>
                                    <strong><?= htmlspecialchars($id_buku) ?></strong> - <?= htmlspecialchars($b['judul']) ?><br>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?php foreach ($data['buku'] as $id_buku => $b): ?>
                                    <strong><?= htmlspecialchars($id_buku) ?></strong>:<br>
                                    <?php foreach ($b['copy'] as $copy): ?>
                                        <?= htmlspecialchars($copy) ?><br>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </td>

                            <td class="text-center"><?= $data['total_copy'] ?></td>
                            <td class="text-center">
                                <a href="admin.php?page=perpus_utama&panggil=tambah_peminjaman.php&edit=<?= urlencode($data['no_peminjaman']) ?>" 
                                    class="btn btn-sm btn-warning">
                                        <i class="fa fa-pencil-alt"></i>
                                    </a>

                                <a href="admin.php?page=perpus_utama&panggil=peminjaman.php&hapus=<?= urlencode($data['no_peminjaman']) ?>" 
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('Yakin ingin menghapus data ini?')">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php 
                            $no++;
                            endforeach;
                        else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">Tidak ada data peminjaman.</td>
                        </tr>
                        <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>