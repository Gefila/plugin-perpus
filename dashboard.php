<?php
// --- Ambil Statistik Utama ---
$jumlah_buku = $conn->query("SELECT COUNT(*) as total FROM buku")->fetch_assoc()['total'];
$jumlah_copy = $conn->query("SELECT COUNT(*) as total FROM copy_buku")->fetch_assoc()['total'];
$jumlah_anggota = $conn->query("SELECT COUNT(*) as total FROM anggota")->fetch_assoc()['total'];
$jumlah_peminjaman = $conn->query("SELECT COUNT(*) as total FROM peminjaman")->fetch_assoc()['total'];

// --- Ambil Data Kunjungan per Bulan untuk Grafik ---
$bulan_labels = [];
$jumlah_kunjungan = [];
$bulan_nama = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

$query_kunjungan = "
    SELECT MONTH(tgl_kunjungan) AS bulan, COUNT(*) AS total 
    FROM kunjungan 
    GROUP BY MONTH(tgl_kunjungan)
";

$result_kunjungan = $conn->query($query_kunjungan);
while ($row = $result_kunjungan->fetch_assoc()) {
    $bulan_labels[] = $bulan_nama[$row['bulan']];
    $jumlah_kunjungan[] = $row['total'];
}

// --- Ambil Data Buku Belum Dikembalikan / Terlambat ---
$query_terlambat = "
    SELECT a.nm_anggota, b.judul_buku, p.tgl_harus_kembali,
           DATEDIFF(CURDATE(), p.tgl_harus_kembali) AS telat
    FROM peminjaman p
    JOIN anggota a ON a.id_anggota = p.id_anggota
    JOIN dapat d ON d.no_peminjaman = p.no_peminjaman
    JOIN copy_buku cb ON cb.no_copy_buku = d.no_copy_buku
    JOIN buku b ON b.id_buku = cb.id_buku
    WHERE NOT EXISTS (
        SELECT 1 FROM pengembalian k 
        WHERE k.no_peminjaman = p.no_peminjaman
    ) AND p.tgl_harus_kembali < CURDATE()
    ORDER BY p.tgl_harus_kembali ASC
    LIMIT 5
";
$result_terlambat = $conn->query($query_terlambat);

// --- 5 Peminjaman Terakhir ---
$query_peminjaman_terakhir = "
    SELECT p.tgl_peminjaman, a.nm_anggota 
    FROM peminjaman p 
    JOIN anggota a ON a.id_anggota = p.id_anggota 
    ORDER BY p.tgl_peminjaman DESC 
    LIMIT 5
";
$result_peminjaman = $conn->query($query_peminjaman_terakhir);

// --- 5 Pengembalian Terakhir ---
$query_pengembalian_terakhir = "
    SELECT k.tgl_pengembalian, a.nm_anggota 
    FROM pengembalian k 
    JOIN peminjaman p ON k.no_peminjaman = p.no_peminjaman 
    JOIN anggota a ON a.id_anggota = p.id_anggota 
    ORDER BY k.tgl_pengembalian DESC 
    LIMIT 5
";
$result_pengembalian = $conn->query($query_pengembalian_terakhir);
?>

<style>
    .card{
        max-width: 100%;
    }
</style>
<!-- ===== HTML DIMULAI DI SINI ===== -->
<div style="padding-left: 5%; padding-right: 5%;">

    <!-- Kartu Statistik -->
    <div class="row text-center">
        <?php
        $cards = [
            ['label' => 'Total Buku', 'value' => $jumlah_buku, 'bg' => 'primary'],
            ['label' => 'Total Copy Buku', 'value' => $jumlah_copy, 'bg' => 'success'],
            ['label' => 'Total Anggota', 'value' => $jumlah_anggota, 'bg' => 'warning text-dark'],
            ['label' => 'Total Peminjaman', 'value' => $jumlah_peminjaman, 'bg' => 'danger'],
        ];

        foreach ($cards as $card) {
            echo "
            <div class='col-md-3'>
                <div class='card bg-{$card['bg']} text-white mb-3'>
                    <div class='card-body'>
                        <h5>{$card['label']}</h5>
                        <h2>{$card['value']}</h2>
                    </div>
                </div>
            </div>";
        }
        ?>
    </div>

    <!-- Grafik + Peminjaman/Pengembalian -->
    <div class="row mb-4">
        <!-- Grafik -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-secondary text-white">
                    <strong>Grafik Kunjungan Per Bulan</strong>
                </div>
                <div class="card-body">
                    <canvas id="kunjunganChart" height="240"></canvas>
                </div>
            </div>
        </div>

        <!-- Peminjaman & Pengembalian Terakhir -->
        <div class="col-md-6">
            <!-- Peminjaman -->
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <strong>5 Peminjaman Terakhir</strong>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php while ($row = $result_peminjaman->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <strong><?= $row['nm_anggota'] ?></strong> meminjam buku pada <em><?= $row['tgl_peminjaman'] ?></em>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>

            <!-- Pengembalian -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <strong>5 Pengembalian Terakhir</strong>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php while ($row = $result_pengembalian->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <strong><?= $row['nm_anggota'] ?></strong> mengembalikan buku pada <em><?= $row['tgl_pengembalian'] ?></em>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Buku Belum Dikembalikan -->
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            <strong>Buku Belum Dikembalikan / Terlambat</strong>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Anggota</th>
                        <th>Judul Buku</th>
                        <th>Tgl Harus Kembali</th>
                        <th>Keterlambatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_terlambat->num_rows > 0) {
                        $no = 1;
                        while ($row = $result_terlambat->fetch_assoc()) {
                            echo "<tr>
                                <td>{$no}</td>
                                <td>{$row['nm_anggota']}</td>
                                <td>{$row['judul_buku']}</td>
                                <td>{$row['tgl_harus_kembali']}</td>
                                <td>{$row['telat']} hari</td>
                            </tr>";
                            $no++;
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center'>Tidak ada data keterlambatan.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const ctx = document.getElementById('kunjunganChart').getContext('2d');
    const kunjunganChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($bulan_labels); ?>,
            datasets: [{
                label: 'Jumlah Kunjungan',
                data: <?= json_encode($jumlah_kunjungan); ?>,
                fill: true,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: 'white',
                pointBorderColor: 'rgba(54, 162, 235, 1)',
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: { enabled: true }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
</script>