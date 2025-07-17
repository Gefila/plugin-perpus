<?php
require_once('../../../wp-load.php');
global $wpdb;
$conn = $wpdb->dbh;

$tgl_dari = $_GET['tgl_dari'] ?? '';
$tgl_sampai = $_GET['tgl_sampai'] ?? '';

if (!$tgl_dari || !$tgl_sampai) {
    echo "<div style='text-align:center;color:red;'>Silakan pilih tanggal terlebih dahulu.</div>";
    exit;
}

$query = "
    SELECT 
        k.id_kunjungan, 
        k.tgl_kunjungan, 
        k.id_anggota,
        COALESCE(a.nm_anggota, k.nama_pengunjung) AS nama_pengunjung,
        k.tujuan
    FROM kunjungan k
    LEFT JOIN anggota a ON k.id_anggota = a.id_anggota
    WHERE k.tgl_kunjungan BETWEEN '$tgl_dari' AND '$tgl_sampai'
    ORDER BY k.tgl_kunjungan ASC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
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

        .badge {
            font-size: 13px;
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
    <h2>LAPORAN KUNJUNGAN</h2>
    <p>Periode: <?= date('d-m-Y', strtotime($tgl_dari)) ?> s/d <?= date('d-m-Y', strtotime($tgl_sampai)) ?></p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>ID Kunjungan</th>
                <th>Tanggal</th>
                <th>Nama Pengunjung</th>
                <th>Status</th>
                <th>Tujuan</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['id_kunjungan']) ?></td>
                    <td><?= date('d M Y', strtotime($row['tgl_kunjungan'])) ?></td>
                    <td><?= htmlspecialchars($row['nama_pengunjung']) ?></td>
                    <td>
                        <?= $row['id_anggota'] ? '<span class="badge bg-primary">Anggota</span>' : '<span class="badge bg-secondary">Non-Anggota</span>' ?>
                    </td>
                    <td><?= htmlspecialchars($row['tujuan']) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">Tidak ada data kunjungan dalam periode ini.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if ($result && $result->num_rows > 0): ?>
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
