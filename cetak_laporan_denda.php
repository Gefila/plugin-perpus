<?php
require_once('../../../wp-load.php');
global $wpdb;
$conn = $wpdb->dbh;

$tgl_dari = $_GET['tgl_mulai'] ?? '';
$tgl_sampai = $_GET['tgl_selesai'] ?? '';

if (!$tgl_dari || !$tgl_sampai) {
    echo "<div style='text-align:center;color:red;'>Silakan pilih tanggal terlebih dahulu.</div>";
    exit;
}

$where = "WHERE p.tgl_pengembalian BETWEEN '$tgl_dari' AND '$tgl_sampai'";

// Query laporan denda
$sql = "
SELECT
    pd.id_kembali_denda,
    pd.id_denda,
    a.id_anggota,
    a.nm_anggota,
    pm.tgl_peminjaman,
    p.tgl_pengembalian,
    d.alasan_denda,
    pd.subtotal,
    pd.jumlah_copy,
    p.no_pengembalian,
    pm.tgl_harus_kembali
FROM pengembalian_denda pd
JOIN pengembalian p ON pd.no_pengembalian = p.no_pengembalian
JOIN peminjaman pm ON p.no_peminjaman = pm.no_peminjaman
JOIN anggota a ON pm.id_anggota = a.id_anggota
JOIN denda d ON pd.id_denda = d.id_denda
$where
ORDER BY pd.id_kembali_denda ASC
";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $telat = 0;
        $tgl_pengembalian = strtotime($row['tgl_pengembalian']);
        $tgl_harus_kembali = strtotime($row['tgl_harus_kembali']);
        if ($tgl_pengembalian > $tgl_harus_kembali) {
            $telat = ($tgl_pengembalian - $tgl_harus_kembali) / (60 * 60 * 24);
        }

        $row['hari_telat'] = (int)$telat;

        $no_pengembalian = $conn->real_escape_string($row['no_pengembalian']);
        $copy_q = $conn->query("SELECT no_copy_buku FROM bisa WHERE no_pengembalian = '$no_pengembalian'");
        $copy_list = [];
        while ($c = $copy_q->fetch_assoc()) {
            $copy_list[] = $c['no_copy_buku'];
        }
        $row['copy_buku'] = implode(', ', $copy_list);

        $data[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 20mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            margin: 0;
            padding: 0;
            background-color: #f0f6ff;
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
            background-color: white;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #555;
            padding: 8px;
            text-align: center;
        }

        th {
            background-color: #e3f2fd;
        }

        .badge {
            font-weight: bold;
            color: #000;
        }

        #btn-cetak {
            margin-top: 20px;
            text-align: center;
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
    <h2>LAPORAN DENDA PERPUSTAKAAN</h2>
    <p>Periode: <?= date('d-m-Y', strtotime($tgl_dari)) ?> s/d <?= date('d-m-Y', strtotime($tgl_sampai)) ?></p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>ID Kembali Denda</th>
                <th>ID Anggota</th>
                <th>Nama</th>
                <th>Tgl Pinjam</th>
                <th>Tgl Kembali</th>
                <th>No Copy Buku</th>
                <th>Alasan Denda</th>
                <th>Subtotal (Rp)</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($data)): ?>
            <?php $no = 1;
            foreach ($data as $item): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($item['id_kembali_denda']) ?></td>
                    <td><?= htmlspecialchars($item['id_anggota']) ?></td>
                    <td><?= htmlspecialchars($item['nm_anggota']) ?></td>
                    <td><?= date('d M Y', strtotime($item['tgl_peminjaman'])) ?></td>
                    <td><?= date('d M Y', strtotime($item['tgl_pengembalian'])) ?></td>
                    <td><?= htmlspecialchars($item['copy_buku']) ?></td>
                    <td>
                        <?php
                        $alasan = $item['alasan_denda'];
                        $hari_telat = $item['hari_telat'] ?? 0;
                        if ($item['id_denda'] === 'D1' && stripos($alasan, 'telat') !== false && $hari_telat > 0) {
                            echo "<span class='badge'>Telat {$hari_telat} hari</span>";
                        } else {
                            echo "<span class='badge'>" . htmlspecialchars($alasan) . "</span>";
                        }
                        ?>
                    </td>
                    <td><?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="9" class="text-muted">Tidak ada data denda dalam periode ini.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($data)): ?>
        <div id="btn-cetak" class="text-center">
            <button class="btn btn-success mt-4" onclick="window.print()">Cetak</button>
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
