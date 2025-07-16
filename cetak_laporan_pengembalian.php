<?php
require_once('../../../wp-load.php');
global $wpdb;
$conn = $wpdb->dbh;

$tgl_mulai = $_GET['tgl_mulai'] ?? '';
$tgl_selesai = $_GET['tgl_selesai'] ?? '';

if (!$tgl_mulai || !$tgl_selesai) {
    die("Silakan pilih tanggal terlebih dahulu.");
}

$sql = "
    SELECT
        p.no_pengembalian, p.no_peminjaman, p.tgl_pengembalian,
        pm.id_anggota, a.nm_anggota,
        pm.tgl_harus_kembali,
        b.id_buku, b.judul_buku,
        bs.no_copy_buku,
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
    WHERE p.tgl_pengembalian BETWEEN '$tgl_mulai' AND '$tgl_selesai'
    ORDER BY p.tgl_pengembalian ASC
";

$result = $conn->query($sql);
$data = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $no_pengembalian = $row['no_pengembalian'];

        if (!isset($data[$no_pengembalian])) {
            $data[$no_pengembalian] = [
                'no_pengembalian' => $no_pengembalian,
                'tgl_pengembalian' => $row['tgl_pengembalian'],
                'id_anggota' => $row['id_anggota'],
                'nm_anggota' => $row['nm_anggota'],
                'buku' => []
            ];
        }

        $id_buku = $row['id_buku'];
        if ($id_buku) {
            if (!isset($data[$no_pengembalian]['buku'][$id_buku])) {
                $data[$no_pengembalian]['buku'][$id_buku] = [
                    'judul' => $row['judul_buku'],
                    'copy' => []
                ];
            }

            if ($row['no_copy_buku']) {
                $data[$no_pengembalian]['buku'][$id_buku]['copy'][] = $row['no_copy_buku'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pengembalian Buku</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <style>
        @page {
            size: A4 portrait;
            margin: 20mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            background-color: #f0f6ff;
            margin: 0;
            padding: 0;
        }
        .container {
            padding: 20px 40px;
        }
        h2 {
            text-align: center;
            margin-bottom: 0;
            color: #0d6efd;
        }
        p {
            text-align: center;
            margin-top: 5px;
            margin-bottom: 20px;
            color: #198754;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background-color: white;
        }
        th, td {
            border: 1px solid #555;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #e3f2fd;
            font-weight: bold;
        }

        @media print {
            html, body {
                width: 230mm;
                height: 297mm;
            }
            #btn-cetak {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>LAPORAN PENGEMBALIAN BUKU</h2>
    <p>Periode: <?= date('d-m-Y', strtotime($tgl_mulai)) ?> s/d <?= date('d-m-Y', strtotime($tgl_selesai)) ?></p>

    <table>
        <thead>
        <tr>
            <th>No</th>
            <th>No Pengembalian</th>
            <th>Tanggal Kembali</th>
            <th>ID Anggota</th>
            <th>Nama Anggota</th>
            <th>Judul Buku</th>
            <th>Copy Buku</th>
            <th>Jumlah</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($data)): ?>
            <?php $no = 1; ?>
            <?php foreach ($data as $item): ?>
                <?php
                $judulList = '';
                $copyList = '';
                $jumlahCopy = 0;
                foreach ($item['buku'] as $id_buku => $b) {
                    $judulList .= "<strong>" . htmlspecialchars($id_buku) . "</strong> - " . htmlspecialchars($b['judul']) . "<br>";
                    foreach ($b['copy'] as $copy) {
                        $copyList .= htmlspecialchars($copy) . "<br>";
                    }
                    $jumlahCopy += count($b['copy']);
                }
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($item['no_pengembalian']) ?></td>
                    <td><?= date('d-m-Y', strtotime($item['tgl_pengembalian'])) ?></td>
                    <td><?= htmlspecialchars($item['id_anggota']) ?></td>
                    <td><?= htmlspecialchars($item['nm_anggota']) ?></td>
                    <td class="text-left"><?= $judulList ?></td>
                                            <td>
                            <?php foreach ($item['buku'] as $id_buku => $b): ?>
                                <strong><?= htmlspecialchars($id_buku) ?>:</strong><br>
                                <?php foreach ($b['copy'] as $copy): ?>
                                    <?= htmlspecialchars($copy) ?><br>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </td>
                    <td><?= $jumlahCopy ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" class="text-muted">Tidak ada data pengembalian dalam periode ini.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($data)): ?>
        <div style="text-align:center; margin-top:20px;">
            <button id="btn-cetak" onclick="window.print()" class="btn btn-success">Cetak</button>
        </div>
    <?php endif; ?>
</div>

<script>
    window.addEventListener('afterprint', function () {
        window.close();
    });
</script>
</body>
</html>
