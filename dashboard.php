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
$sql = "
SELECT 
    p.no_peminjaman,
    p.tgl_harus_kembali,
    a.id_anggota,
    a.nm_anggota,
    b.id_buku,
    b.judul_buku,
    cb.no_copy_buku,
    DATEDIFF(CURDATE(), p.tgl_harus_kembali) AS telat
FROM peminjaman p
JOIN anggota a ON a.id_anggota = p.id_anggota
JOIN dapat d ON d.no_peminjaman = p.no_peminjaman
JOIN copy_buku cb ON cb.no_copy_buku = d.no_copy_buku
JOIN buku b ON b.id_buku = cb.id_buku
WHERE NOT EXISTS (
    SELECT 1 FROM pengembalian k
    WHERE k.no_peminjaman = p.no_peminjaman
)
AND p.tgl_harus_kembali < CURDATE()
ORDER BY p.tgl_harus_kembali ASC
";

$result = $conn->query($sql);
$data_terlambat = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $no_peminjaman = $row['no_peminjaman'];

        if (!isset($data_terlambat[$no_peminjaman])) {
            $data_terlambat[$no_peminjaman] = [
                'no_peminjaman' => $no_peminjaman,
                'tgl_harus_kembali' => $row['tgl_harus_kembali'],
                'id_anggota' => $row['id_anggota'],
                'nm_anggota' => $row['nm_anggota'],
                'telat' => $row['telat'],
                'buku' => []
            ];
        }

        $id_buku = $row['id_buku'];
        if ($id_buku) {
            if (!isset($data_terlambat[$no_peminjaman]['buku'][$id_buku])) {
                $data_terlambat[$no_peminjaman]['buku'][$id_buku] = [
                    'judul' => $row['judul_buku'],
                    'copy' => []
                ];
            }

            if ($row['no_copy_buku']) {
                $data_terlambat[$no_peminjaman]['buku'][$id_buku]['copy'][] = $row['no_copy_buku'];
            }
        }
    }
}

// --- 5 Peminjaman Terakhir ---
$query_peminjaman_terakhir = "
    SELECT p.*, a.nm_anggota 
    FROM peminjaman p 
    JOIN anggota a ON a.id_anggota = p.id_anggota 
    ORDER BY p.tgl_peminjaman DESC 
    LIMIT 5
";
$result_peminjaman = $conn->query($query_peminjaman_terakhir);

// --- 5 Pengembalian Terakhir ---
$query_pengembalian_terakhir = "
    SELECT k.*, a.nm_anggota 
    FROM pengembalian k 
    JOIN peminjaman p ON k.no_peminjaman = p.no_peminjaman 
    JOIN anggota a ON a.id_anggota = p.id_anggota 
    ORDER BY k.tgl_pengembalian DESC 
    LIMIT 5
";
$result_pengembalian = $conn->query($query_pengembalian_terakhir);

?>

<style>
    .card {
        max-width: 100%;
    }
</style>
<!-- ===== HTML DIMULAI DI SINI ===== -->
<div style="padding: 0 5%;">
    <!-- Kartu Statistik -->
    <div class="row text-center mb-2">
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
                <div class='card bg-{$card['bg']} text-white'>
                    <div class='card-body'>
                        <h5>{$card['label']}</h5>
                        <h2>{$card['value']}</h2>
                    </div>
                </div>
            </div>";
        }
        ?>
    </div>

    <!-- Grafik dan Panel Kanan -->
    <div class="row mb-4">
        <!-- Grafik Kunjungan -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-secondary text-white">
                    <strong>Grafik Kunjungan Per Bulan</strong>
                </div>
                <div class="card-body">
                    <canvas id="kunjunganChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Panel Kanan -->
        <div class="col-md-6">
            <!-- Peminjaman & Pengembalian Terakhir -->
            <div class="row h-100">
                <!-- Peminjaman -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <strong>5 Peminjaman Terakhir</strong>
                        </div>
                        <div class="card-body p-2">
                            <ul class="list-group list-group-flush">
                                <?php while ($row = $result_peminjaman->fetch_assoc()): ?>
                                    <li class="list-group-item small">
                                        <strong><?= $row['nm_anggota'] ?></strong><br>
                                        <em><?= $row['tgl_peminjaman'] ?></em>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Pengembalian -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <strong>5 Pengembalian Terakhir</strong>
                        </div>
                        <div class="card-body p-2">
                            <ul class="list-group list-group-flush">
                                <?php while ($row = $result_pengembalian->fetch_assoc()): ?>
                                    <li class="list-group-item small">
                                        <strong><?= $row['nm_anggota'] ?></strong><br>
                                        <em><?= $row['tgl_pengembalian'] ?></em>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Buku Belum Dikembalikan -->
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <strong>Buku yang Telat Dikembalikan</strong>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Anggota</th>
                                    <th>Judul Buku</th>
                                    <th>Copy Buku</th>
                                    <th>Deadline</th>
                                    <th>Telat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($data_terlambat)) {
                                    $no = 1;
                                    foreach ($data_terlambat as $row) {
                                        foreach ($row['buku'] as $buku) {
                                            echo "<tr>
                        <td>{$no}</td>
                        <td>{$row['nm_anggota']}</td>
                        <td>{$buku['judul']}</td>
                        <td>" . implode(', ', $buku['copy']) . "</td>
                        <td>{$row['tgl_harus_kembali']}</td>
                        <td>{$row['telat']} hari</td>
                    </tr>";
                                            $no++;
                                        }
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center'>Tidak ada keterlambatan.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('kunjunganChart').getContext('2d');
    new Chart(ctx, {
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
                pointRadius: 4,
                pointBackgroundColor: '#fff',
                pointBorderColor: 'rgba(54, 162, 235, 1)'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    enabled: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
</script>



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
                legend: {
                    position: 'top'
                },
                tooltip: {
                    enabled: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
</script>