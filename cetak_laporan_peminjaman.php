<?php
require_once('../../../wp-load.php');
global $wpdb;
$conn = $wpdb->dbh;

$tgl_mulai = $_GET['tgl_mulai'] ?? '';
$tgl_selesai = $_GET['tgl_selesai'] ?? '';

if (!$tgl_mulai || !$tgl_selesai) {
    echo "<div style='text-align:center;color:red;'>Silakan pilih tanggal terlebih dahulu.</div>";
    exit;
}

$query = "
    SELECT 
        pj.no_peminjaman,
        pj.tgl_peminjaman,
        a.id_anggota,
        a.nm_anggota,
        b.id_buku,
        b.judul_buku,
        GROUP_CONCAT(d.no_copy_buku SEPARATOR ', ') AS no_copy,
        COUNT(d.no_copy_buku) AS jumlah
    FROM peminjaman pj
    LEFT JOIN anggota a ON pj.id_anggota = a.id_anggota
    LEFT JOIN dapat d ON pj.no_peminjaman = d.no_peminjaman
    LEFT JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
    LEFT JOIN buku b ON cb.id_buku = b.id_buku
    WHERE pj.tgl_peminjaman BETWEEN '$tgl_mulai' AND '$tgl_selesai'
    GROUP BY pj.no_peminjaman, b.id_buku
    ORDER BY pj.tgl_peminjaman ASC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Laporan Peminjaman Buku</title>
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
        }

        .container {
            padding: 20px 40px;
        }

        h2 {
            text-align: center;
            margin-bottom: 0;
        }

        p {
            text-align: center;
            margin-top: 5px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #555;
            padding: 8px;
            text-align: center;
        }

        th {
            background-color: #eee;
        }

        @media print {
            html, body {
                width: 210mm;
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
    <h2>LAPORAN PEMINJAMAN BUKU</h2>
    <p>Periode: <?= date('d-m-Y', strtotime($tgl_mulai)) ?> s/d <?= date('d-m-Y', strtotime($tgl_selesai)) ?></p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>No Peminjaman</th>
                <th>Tanggal Pinjam</th>
                <th>ID Anggota</th>
                <th>Nama Anggota</th>
                <th>ID Buku</th>
                <th>Judul Buku</th>
                <th>No Copy Buku</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= $row['no_peminjaman'] ?></td>
                    <td><?= date('d-m-Y', strtotime($row['tgl_peminjaman'])) ?></td>
                    <td><?= $row['id_anggota'] ?></td>
                    <td><?= $row['nm_anggota'] ?></td>
                    <td><?= $row['id_buku'] ?></td>
                    <td><?= $row['judul_buku'] ?></td>
                    <td><?= $row['no_copy'] ?></td>
                    <td><?= $row['jumlah'] ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="9">Tidak ada data peminjaman dalam periode ini.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php if ($result && $result->num_rows > 0): ?>
        <div style="text-align:center; margin-top:20px;">
            <button id="btn-cetak" onclick="window.print()" class="btn btn-danger">Cetak</button>
        </div>
    <?php endif; ?>
</div>

<script>
    // window.onload = function () {
    //     window.print();
    // };
    window.addEventListener('afterprint', function () {
        window.close();
    });
</script>
</body>
</html>
