<?php
// Proses hapus data peminjaman
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

// Query gabungan data peminjaman dan detail buku
$sql = "
    SELECT 
        p.no_peminjaman, p.tgl_peminjaman, p.tgl_harus_kembali, p.id_anggota, a.nm_anggota,
        b.id_buku, b.judul_buku,
        GROUP_CONCAT(d.no_copy_buku SEPARATOR ', ') AS no_copy,
        COUNT(d.no_copy_buku) AS jumlah
    FROM peminjaman p
    LEFT JOIN anggota a ON p.id_anggota = a.id_anggota
    LEFT JOIN dapat d ON p.no_peminjaman = d.no_peminjaman
    LEFT JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
    LEFT JOIN buku b ON cb.id_buku = b.id_buku
    GROUP BY p.no_peminjaman, b.id_buku
    ORDER BY p.no_peminjaman ASC
";

$result = $conn->query($sql);
if (!$result) {
    die("Query Error: " . $conn->error);
}

// Menyiapkan data grup berdasarkan no_peminjaman
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[$row['no_peminjaman']][] = $row;
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
            <th>Tgl Pinjam</th>
            <th>Tgl Harus Kembali</th>
            <th>ID Anggota</th>
            <th>Nama Anggota</th>
            <th>ID - Judul Buku</th>
            <th>No Copy Buku</th>
            <th>Jumlah Pinjam</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        foreach ($data as $no_peminjaman => $items):
            $first = true;
            foreach ($items as $item):
        ?>
            <tr>
                <td class="text-center"><?= $first ? $no : '' ?></td>
                <td class="text-center"><?= $first ? htmlspecialchars($item['no_peminjaman']) : '' ?></td>
                <td><?= $first ? date('d-m-Y', strtotime($item['tgl_peminjaman'])) : '' ?></td>
                <td><?= $first ? date('d-m-Y', strtotime($item['tgl_harus_kembali'])) : '' ?></td>
                <td class="text-center"><?= $first ? htmlspecialchars($item['id_anggota']) : '' ?></td>
                <td><?= $first ? htmlspecialchars($item['nm_anggota']) : '' ?></td>
                <td>
                    <?= htmlspecialchars($item['id_buku']) ?> - <?= htmlspecialchars($item['judul_buku']) ?>
                </td>
                <td><?= htmlspecialchars($item['no_copy']) ?></td>
                <td class="text-center"><?= $item['jumlah'] ?></td>
                <td class="text-center">
                    <?php if ($first): ?>
                        <a href="admin.php?page=perpus_utama&panggil=peminjaman.php&hapus=<?= urlencode($item['no_peminjaman']) ?>" 
                            class="btn btn-danger btn-sm" 
                            onclick="return confirm('Yakin ingin menghapus data ini?')">Hapus</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php 
            $first = false;
            endforeach;
            $no++;
        endforeach;
        ?>
    </tbody>
</table>
