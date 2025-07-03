<?php
// Proses hapus data pengembalian
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);

    // Hapus data detail di tabel bisa
    $conn->query("DELETE FROM bisa WHERE no_pengembalian = '$idHapus'");

    // Hapus data utama pengembalian
    $conn->query("DELETE FROM pengembalian WHERE no_pengembalian = '$idHapus'");

    echo "<script>
            alert('Data pengembalian berhasil dihapus.');
            window.location.href='admin.php?page=perpus_utama&panggil=pengembalian.php';
          </script>";
    exit;
}

// Query gabungan data pengembalian dan detail buku
$sql = "
    SELECT 
        p.no_pengembalian, p.no_peminjaman, p.tgl_pengembalian, pm.id_anggota, a.nm_anggota,
        b.id_buku, b.judul_buku,
        GROUP_CONCAT(bs.no_copy_buku SEPARATOR ', ') AS no_copy,
        COUNT(bs.no_copy_buku) AS jumlah
    FROM pengembalian p
    LEFT JOIN peminjaman pm ON p.no_peminjaman = pm.no_peminjaman
    LEFT JOIN anggota a ON pm.id_anggota = a.id_anggota
    LEFT JOIN bisa bs ON p.no_pengembalian = bs.no_pengembalian
    LEFT JOIN copy_buku cb ON bs.no_copy_buku = cb.no_copy_buku
    LEFT JOIN buku b ON cb.id_buku = b.id_buku
    GROUP BY p.no_pengembalian, p.no_peminjaman, p.tgl_pengembalian, pm.id_anggota, a.nm_anggota, b.id_buku, b.judul_buku
    ORDER BY p.no_pengembalian ASC
";

$result = $conn->query($sql);
if (!$result) {
    die("Query Error: " . $conn->error);
}

// Menyiapkan data grup berdasarkan no_pengembalian
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[$row['no_pengembalian']][] = $row;
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<h2 class="text-center mb-4">Data Pengembalian</h2>
<a href="admin.php?page=perpus_utama&panggil=tambah_pengembalian.php" class="btn btn-primary mb-3">Tambah Pengembalian</a>

<table class="table table-bordered table-striped">
    <thead class="table-dark text-center">
        <tr>
            <th>No</th>
            <th>No Pengembalian</th>
            <th>ID Anggota</th>
            <th>Nama Anggota</th>
            <th>ID - Judul Buku</th>
            <th>No Copy Buku</th>
            <th>Jumlah Kembali</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        foreach ($data as $no_pengembalian => $items):
            $first = true;
            foreach ($items as $item):
        ?>
                <tr>
                    <td class="text-center"><?= $first ? $no : '' ?></td>
                    <td class="text-center"><?= $first ? htmlspecialchars($item['no_pengembalian']) : '' ?></td>
                    <td class="text-center"><?= $first ? htmlspecialchars($item['id_anggota']) : '' ?></td>
                    <td><?= $first ? htmlspecialchars($item['nm_anggota']) : '' ?></td>
                    <td>
                        <?= htmlspecialchars($item['id_buku']) ?> - <?= htmlspecialchars($item['judul_buku']) ?>
                    </td>
                    <td><?= htmlspecialchars($item['no_copy']) ?></td>
                    <td class="text-center"><?= $item['jumlah'] ?></td>
                    <td class="text-center">
                        <?php if ($first): ?>
                            <a href="admin.php?page=perpus_utama&panggil=pengembalian.php&hapus=<?= urlencode($item['no_pengembalian']) ?>"
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