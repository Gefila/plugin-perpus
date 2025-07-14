<?php
$tgl_mulai = $_POST['tgl_mulai'] ?? '';
$tgl_selesai = $_POST['tgl_selesai'] ?? '';
$hasil_denda = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($tgl_mulai) && !empty($tgl_selesai)) {
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
        WHERE p.tgl_pengembalian BETWEEN '$tgl_mulai' AND '$tgl_selesai'
        ORDER BY pd.id_kembali_denda ASC
    ";
    $hasil_denda = $conn->query($sql);

    // Ambil copy buku untuk setiap pengembalian
    if ($hasil_denda) {
        $data = [];
        while ($row = $hasil_denda->fetch_assoc()) {
            $no_pengembalian = $conn->real_escape_string($row['no_pengembalian']);
            $copy_q = $conn->query("SELECT no_copy_buku FROM bisa WHERE no_pengembalian = '$no_pengembalian'");
            $copy_list = [];
            while ($c = $copy_q->fetch_assoc()) {
                $copy_list[] = $c['no_copy_buku'];
            }
            $row['copy_buku'] = implode(', ', $copy_list);

            // Hitung hari keterlambatan
            $telat = 0;
            $tgl_kembali = strtotime($row['tgl_pengembalian']);
            $tgl_harus_kembali = strtotime($row['tgl_harus_kembali']);
            if ($tgl_kembali > $tgl_harus_kembali) {
                $telat = ($tgl_kembali - $tgl_harus_kembali) / (60 * 60 * 24);
            }
            $row['hari_telat'] = (int)$telat;

            $data[] = $row;
        }
    }
}
?>

<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet" />

<div class="container my-5">
    <h2 class="text-center text-primary fw-bold mb-4">Laporan Denda</h2>

    <form method="post" class="mb-4" id="filterForm">
        <div class="row g-3 align-items-end justify-content-center">
            <div class="col-md-3">
                <label for="tgl_mulai" class="form-label text-primary fw-semibold">Dari Tanggal:</label>
                <input type="date" name="tgl_mulai" id="tgl_mulai" class="form-control"
                    style="border: 2px solid #3498db; border-radius: 8px; background: #f0f6ff;" required
                    value="<?= htmlspecialchars($tgl_mulai) ?>">
            </div>
            <div class="col-md-3">
                <label for="tgl_selesai" class="form-label text-primary fw-semibold">Sampai Tanggal:</label>
                <input type="date" name="tgl_selesai" id="tgl_selesai" class="form-control"
                    style="border: 2px solid #3498db; border-radius: 8px; background: #f0f6ff;" required
                    value="<?= htmlspecialchars($tgl_selesai) ?>">
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-glow" style="border-radius: 8px;">
                    <i class="fa fa-filter me-1"></i> Tampilkan
                </button>
                <button type="button" class="btn btn-success btn-glow" onclick="cetakLaporan()" style="border-radius: 8px;">
                    <i class="fa fa-print me-1"></i> Cetak Laporan
                </button>
            </div>
        </div>
    </form>

    <?php if (!empty($data)): ?>
        <div class="card-glass mb-4">
            <h5 class="text-center text-success">Menampilkan denda dari <strong><?= htmlspecialchars($tgl_mulai) ?></strong> sampai <strong><?= htmlspecialchars($tgl_selesai) ?></strong></h5>
        </div>

        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>No</th>
                    <th>ID Kembali Denda</th>
                    <th>ID Anggota</th>
                    <th>Nama Anggota</th>
                    <th>Tgl Pinjam</th>
                    <th>Tgl Kembali</th>
                    <th>No Copy Buku</th>
                    <th>Alasan Denda</th>
                    <th>Subtotal (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($data as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['id_kembali_denda']) ?></td>
                        <td><?= htmlspecialchars($row['id_anggota']) ?></td>
                        <td><?= htmlspecialchars($row['nm_anggota']) ?></td>
                        <td><?= date('d-m-Y', strtotime($row['tgl_peminjaman'])) ?></td>
                        <td><?= date('d-m-Y', strtotime($row['tgl_pengembalian'])) ?></td>
                        <td>
                            <div class="badge bg-primary text-white p-2"><?= $row['copy_buku'] ?></div>
                        </td>
                        <td>
                            <?php if ($row['id_denda'] === 'D1' && stripos($row['alasan_denda'], 'telat') !== false && $row['hari_telat'] > 0): ?>
                                <div class="badge bg-danger">Telat <?= $row['hari_telat'] ?> hari</div>
                            <?php else: ?>
                                <div class="badge bg-warning text-dark"><?= $row['alasan_denda'] ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold"><?= number_format($row['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
        <div class="alert alert-info text-center">
            Tidak ada data denda pada rentang tanggal ini.
        </div>
    <?php endif; ?>
</div>

<script>
function cetakLaporan() {
    const tglMulai = document.getElementById('tgl_mulai').value;
    const tglSelesai = document.getElementById('tgl_selesai').value;

    if (!tglMulai || !tglSelesai) {
        alert('Silakan pilih tanggal terlebih dahulu.');
        return;
    }

    const url = '<?= plugin_dir_url(__FILE__) ?>cetak_laporan_denda.php?tgl_mulai=' + encodeURIComponent(tglMulai) + '&tgl_selesai=' + encodeURIComponent(tglSelesai);
    window.open(url, '_blank');
}
</script>