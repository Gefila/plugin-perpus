<?php
// Inisialisasi variabel tanggal
$tgl_dari = $_POST['tgl_dari'] ?? '';
$tgl_sampai = $_POST['tgl_sampai'] ?? '';

$hasil_kunjungan = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($tgl_dari) && !empty($tgl_sampai)) {
    $sql = "SELECT k.*, a.nm_anggota FROM kunjungan k
            JOIN anggota a ON k.id_anggota = a.id_anggota
            WHERE tgl_kunjungan BETWEEN '$tgl_dari' AND '$tgl_sampai'
            ORDER BY tgl_kunjungan ASC";
    $hasil_kunjungan = $conn->query($sql);
}
?>

<!-- Tambahkan link ke Bootstrap dan CSS kamu jika belum -->
<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet" />

<div class="container my-5">
    <h2 class="text-center text-primary fw-bold mb-4">Laporan Kunjungan Berdasarkan Tanggal</h2>

    <form method="post" class="mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="tgl_dari" class="form-label text-primary fw-semibold">Dari Tanggal:</label>
                <input type="date" name="tgl_dari" id="tgl_dari" class="form-control"                 style="border: 2px solid #3498db; border-radius: 8px; background: #f0f6ff;" required value="<?= htmlspecialchars($tgl_dari) ?>">
            </div>
            <div class="col-md-3">
                <label for="tgl_sampai" class="form-label text-primary fw-semibold">Sampai Tanggal:</label>
                <input type="date" name="tgl_sampai" id="tgl_sampai" class="form-control"                 style="border: 2px solid #3498db; border-radius: 8px; background: #f0f6ff;" required value="<?= htmlspecialchars($tgl_sampai) ?>">
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary btn-glow" style="border-radius: 8px;">
                    <i class="fa fa-filter me-1"></i> Tampilkan
                </button>
            </div>
        </div>
    </form>

    <?php if ($hasil_kunjungan !== null): ?>
        <div class="card-glass mb-4">
            <h5 class="text-center text-success">Menampilkan kunjungan dari <strong><?= htmlspecialchars($tgl_dari) ?></strong> sampai <strong><?= htmlspecialchars($tgl_sampai) ?></strong></h5>
        </div>

        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>No</th>
                    <th>ID Kunjungan</th>
                    <th>Tanggal</th>
                    <th>Nama Pengunjung</th>
                    <th>Tujuan</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                if ($hasil_kunjungan->num_rows > 0) {
                    while ($row = $hasil_kunjungan->fetch_assoc()) {
                        echo "<tr>
                                <td class='text-center'>{$no}</td>
                                <td class='fw-bold'>{$row['id_kunjungan']}</td>
                                <td class='fw-bold'>" . date('d M Y', strtotime($row['tgl_kunjungan'])) . "</td>
                                <td class='fw-bold'>{$row['nm_anggota']}</td>
                                <td class='fw-bold'>{$row['tujuan']}</td>
                              </tr>";
                        $no++;
                    }
                } else {
                    echo '<tr><td colspan="5" class="text-muted text-center">Tidak ada data kunjungan dalam rentang tanggal tersebut.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
