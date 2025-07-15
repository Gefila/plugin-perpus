<?php
$conn = new mysqli("localhost", "root", "", "db_ti6b_uas");
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Hapus peminjaman
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $copyResult = $conn->query("SELECT no_copy_buku FROM dapat WHERE no_peminjaman = '$idHapus'");
    if ($copyResult && $copyResult->num_rows > 0) {
        while ($row = $copyResult->fetch_assoc()) {
            $conn->query("UPDATE copy_buku SET status_buku = 'tersedia' WHERE no_copy_buku = '" . $conn->real_escape_string($row['no_copy_buku']) . "'");
        }
    }
    $conn->query("DELETE FROM dapat WHERE no_peminjaman = '$idHapus'");
    $conn->query("DELETE FROM peminjaman WHERE no_peminjaman = '$idHapus'");
    echo "<script>alert('Data peminjaman berhasil dihapus.');window.location.href='admin.php?page=perpus_utama&panggil=peminjaman.php';</script>";
    exit;
}

// Ambil semua data peminjaman
$sql = "
SELECT 
    p.no_peminjaman, p.tgl_peminjaman, p.tgl_harus_kembali, 
    a.id_anggota, a.nm_anggota,
    b.id_buku, b.judul_buku,
    cb.no_copy_buku
FROM peminjaman p
JOIN anggota a ON p.id_anggota = a.id_anggota
JOIN dapat d ON p.no_peminjaman = d.no_peminjaman
JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
JOIN buku b ON cb.id_buku = b.id_buku
ORDER BY p.no_peminjaman, cb.no_copy_buku
";

$result = $conn->query($sql);
$peminjamanData = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $noPeminjaman = $row['no_peminjaman'];
        $idBuku = $row['id_buku'];
        $copy = $row['no_copy_buku'];

        if (!isset($peminjamanData[$noPeminjaman])) {
            $peminjamanData[$noPeminjaman] = [
                'no_peminjaman' => $noPeminjaman,
                'tgl_peminjaman' => $row['tgl_peminjaman'],
                'tgl_harus_kembali' => $row['tgl_harus_kembali'],
                'id_anggota' => $row['id_anggota'],
                'nm_anggota' => $row['nm_anggota'],
                'buku' => [],
                'total_copy' => 0
            ];
        }

        if (!isset($peminjamanData[$noPeminjaman]['buku'][$idBuku])) {
            $peminjamanData[$noPeminjaman]['buku'][$idBuku] = [
                'judul' => $row['judul_buku'],
                'copy' => []
            ];
        }

        $peminjamanData[$noPeminjaman]['buku'][$idBuku]['copy'][] = $copy;
        $peminjamanData[$noPeminjaman]['total_copy']++;
    }
}

// Cek pengembalian
$peminjamanTidakBisaEdit = [];
foreach ($peminjamanData as $noPeminjaman => &$data) {
    $allCopy = [];
    foreach ($data['buku'] as $buku) {
        foreach ($buku['copy'] as $copy) {
            $allCopy[] = $copy;
        }
    }

    $escapedCopies = array_map([$conn, 'real_escape_string'], $allCopy);
    $copyList = "'" . implode("','", $escapedCopies) . "'";

    $cek = $conn->query("
        SELECT b.no_copy_buku FROM bisa b
        JOIN pengembalian p ON b.no_pengembalian = p.no_pengembalian
        WHERE b.no_copy_buku IN ($copyList)
          AND p.no_peminjaman = '" . $conn->real_escape_string($noPeminjaman) . "'
    ");

    $returned = [];
    while ($cek && $r = $cek->fetch_assoc()) {
        $returned[] = $r['no_copy_buku'];
    }

    $copyBelum = array_diff($allCopy, $returned);
    $data['copy_belum_kembali'] = $copyBelum;
    $jumlahBelumKembali = count($copyBelum);
    $totalCopy = count($allCopy);

    if ($jumlahBelumKembali === 0) {
        $data['status_peminjaman'] = 'Peminjaman selesai';
        $peminjamanTidakBisaEdit[] = $noPeminjaman;
    } else {
        $data['status_peminjaman'] = "Belum kembali $jumlahBelumKembali dari $totalCopy buku";
        if (count($returned) > 0) {
            $peminjamanTidakBisaEdit[] = $noPeminjaman;
        }
    }
    }
?>

<!-- TAMPILAN -->
<!-- Bootstrap & Font Awesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet">

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark">
            <i class="fa-solid fa-book-reader text-primary me-2"></i>Data Peminjaman
        </h3>
    <a href="admin.php?page=perpus_utama&panggil=tambah_peminjaman.php" class="btn btn-primary mb-3">
        <i class="fa fa-plus-circle me-1"></i> Tambah Peminjaman
    </a>
    </div>

<div class="card-glass">
    <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle text-center">
        <thead class="table-dark">
            <tr>
                <th>No</th>
                <th>No Peminjaman</th>
                <th>Tanggal Pinjam</th>
                <th>Tanggal Kembali</th>
                <th>Anggota</th>
                <th>Buku</th>
                <th>Copy Buku</th>
                <th>Total</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($peminjamanData)): $no = 1; foreach ($peminjamanData as $pinjam): ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center"><?= htmlspecialchars($pinjam['no_peminjaman']) ?></td>
                <td class="text-center"><?= date('d-m-Y', strtotime($pinjam['tgl_peminjaman'])) ?></td>
                <td class="text-center"><?= date('d-m-Y', strtotime($pinjam['tgl_harus_kembali'])) ?></td>
                <td><?= htmlspecialchars($pinjam['id_anggota']) ?> - <?= htmlspecialchars($pinjam['nm_anggota']) ?></td>
                <td style="text-align:left;">
                    <?php foreach ($pinjam['buku'] as $id_buku => $b): ?>
                        <strong><?= htmlspecialchars($id_buku) ?></strong>: <?= htmlspecialchars($b['judul']) ?><br>
                    <?php endforeach; ?>
                </td>
                <td style="text-align:left;">
                    <?php foreach ($pinjam['buku'] as $id_buku => $b): ?>
                        <strong><?= htmlspecialchars($id_buku) ?></strong>:<br>
                        <?php foreach ($b['copy'] as $copy): ?>
                            <?php
                                $isBelumKembali = in_array($copy, $pinjam['copy_belum_kembali']);
                                $warna = $isBelumKembali ? 'black' : 'blue';
                                $teks = htmlspecialchars($copy);
                                echo $isBelumKembali
                                    ? "<span style='color:$warna; display:block;'>$teks</span>"
                                    : "<span style='color:$warna; display:block;'><strong>$teks</strong></span>";
                            ?>
                        <?php endforeach; ?>
                        <br>
                    <?php endforeach; ?>
                </td>
                <td class="text-center"><?= $pinjam['total_copy'] ?></td>
                <td><?= $pinjam['status_peminjaman'] ?></td>
            <td class="text-center align-middle">
                <div class="d-flex justify-content-center align-items-center" style="gap: 5px;">
                    <?php if (in_array($pinjam['no_peminjaman'], $peminjamanTidakBisaEdit)): ?>
                        <button class="btn btn-sm btn-secondary" disabled title="Tidak bisa diedit karena ada buku yang sudah dikembalikan">
                            <i class="fa fa-pencil-alt"></i>
                        </button>
                    <?php else: ?>
                        <a href="admin.php?page=perpus_utama&panggil=tambah_peminjaman.php&edit=<?= urlencode($pinjam['no_peminjaman']) ?>" 
                        class="btn btn-sm btn-warning" title="Edit">
                            <i class="fa fa-pencil-alt"></i>
                        </a>
                    <?php endif; ?>

                    <a href="admin.php?page=perpus_utama&panggil=peminjaman.php&hapus=<?= $pinjam['no_peminjaman'] ?>" 
                    onclick="return confirm('Yakin hapus?')" 
                    class="btn btn-sm btn-danger" title="Hapus">
                        <i class="fa fa-trash"></i>
                    </a>
                </div>
            </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="10" class="text-center text-muted">Tidak ada data.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
            </div>
            </div>
