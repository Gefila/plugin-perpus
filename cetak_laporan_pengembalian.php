<?php
global $conn;

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$tgl_mulai = $_GET['tgl_mulai'] ?? '';
$tgl_selesai = $_GET['tgl_selesai'] ?? '';

if (!$tgl_mulai || !$tgl_selesai) {
    echo "<div class='alert alert-danger text-center'>Silakan pilih tanggal terlebih dahulu.</div>";
    exit;
}

$query = "
    SELECT 
        pg.no_pengembalian,
        pg.tgl_pengembalian,
        a.id_anggota,
        a.nm_anggota,
        b.id_buku,
        b.judul_buku,
        GROUP_CONCAT(d.no_copy_buku SEPARATOR ', ') AS no_copy,
        COUNT(d.no_copy_buku) AS jumlah
    FROM pengembalian pg
    LEFT JOIN peminjaman pj ON pg.no_peminjaman = pj.no_peminjaman
    LEFT JOIN anggota a ON pj.id_anggota = a.id_anggota
    LEFT JOIN dapat d ON pj.no_peminjaman = d.no_peminjaman
    LEFT JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
    LEFT JOIN buku b ON cb.id_buku = b.id_buku
    WHERE pg.tgl_pengembalian BETWEEN '$tgl_mulai' AND '$tgl_selesai'
    GROUP BY pg.no_pengembalian, b.id_buku
    ORDER BY pg.tgl_pengembalian ASC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pengembalian Buku</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <style>
<style>
    body {
        font-family: Arial;
        font-size: 14px;
        margin: 40px;
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
        /* 🔒 Sembunyikan bagian WordPress admin */
        #adminmenumain,
        #adminmenuwrap,
        #adminmenuback,
        #wpadminbar,
        #wpfooter,
        .update-nag,
        .notice,
        .error,
        .updated,
        .wrap > h1,
        .wrap > .nav-tab-wrapper,
        .wrap > .notice,
        .wrap > .error,
        .wrap > .updated,
        .wrap > .wp-header-end,
        .wrap > form,
        .wrap > div:not(.cetak-laporan-container),
        .wrap > *:not(.cetak-laporan-container),
        .wrap > br,

        /* ✅ Sembunyikan navbar plugin */
        nav.navbar {
            display: none !important;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
        }

        #btn-cetak {
            display: none;
        }
    }
</style>


</head>
<body onload="window.print()">

    <h2>LAPORAN PENGEMBALIAN BUKU</h2>
    <p>Periode: <?= date('d-m-Y', strtotime($tgl_mulai)) ?> s/d <?= date('d-m-Y', strtotime($tgl_selesai)) ?></p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>No Pengembalian</th>
                <th>Tanggal Pengembalian</th>
                <th>ID Anggota</th>
                <th>Nama Anggota</th>
                <th>ID Buku</th>
                <th>Judul Buku</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= $row['no_pengembalian'] ?></td>
                        <td><?= date('d-m-Y', strtotime($row['tgl_pengembalian'])) ?></td>
                        <td><?= $row['id_anggota'] ?></td>
                        <td><?= $row['nm_anggota'] ?></td>
                        <td><?= $row['id_buku'] ?></td>
                        <td><?= $row['judul_buku'] ?></td>
                        <td><?= $row['jumlah'] ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">Tidak ada data pengembalian dalam periode ini.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($result && $result->num_rows > 0): ?>
        <div style="text-align:right; margin-top:20px;">
            <button id="btn-cetak" onclick="window.print()" class="btn btn-primary">Cetak</button>
        </div>
    <?php endif; ?>
</div>
<script>
    // window.print() hanya dijalankan saat tombol diklik
    window.addEventListener('afterprint', function () {
        window.close();
    });
</script>
</body>
</html>
