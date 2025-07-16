<?php
$tgl_mulai = $_POST['tgl_mulai'] ?? '';
$tgl_selesai = $_POST['tgl_selesai'] ?? '';
$data = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($tgl_mulai) && !empty($tgl_selesai)) {
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

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $no_pengembalian = $row['no_pengembalian'];

            if (!isset($data[$no_pengembalian])) {
                $data[$no_pengembalian] = [
                    'no_pengembalian' => $no_pengembalian,
                    'no_peminjaman' => $row['no_peminjaman'],
                    'tgl_pengembalian' => $row['tgl_pengembalian'],
                    'id_anggota' => $row['id_anggota'],
                    'nm_anggota' => $row['nm_anggota'],
                    'tgl_harus_kembali' => $row['tgl_harus_kembali'],
                    'total_denda' => $row['total_denda'],
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
}
?>

<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet" />

<div class="container my-5">
    <h2 class="text-center text-primary fw-bold mb-4">Laporan Pengembalian Buku</h2>

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
                <!-- Tombol Tampilkan -->
                <button type="submit" class="btn btn-primary btn-glow" style="border-radius: 8px;">
                    <i class="fa fa-filter me-1"></i> Tampilkan
                </button>
                <!-- Tombol Cetak -->
                <button type="button" class="btn btn-success btn-glow" onclick="cetakLaporan()" style="border-radius: 8px;">
                    <i class="fa fa-print me-1"></i> Cetak Laporan
                </button>
            </div>
        </div>
    </form>

    <?php if (!empty($data)): ?>
        <div class="card-glass mb-4">
            <h5 class="text-center text-success">
                Menampilkan pengembalian dari <strong><?= htmlspecialchars($tgl_mulai) ?></strong> sampai <strong><?= htmlspecialchars($tgl_selesai) ?></strong>
            </h5>
        </div>

        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>No</th>
                    <th>No Pengembalian</th>
                    <th>Tanggal Kembali</th>
                    <th>ID Anggota</th>
                    <th>Nama Anggota</th>
                    <th>Judul Buku</th>
                    <th>No Copy Buku</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1;
                foreach ($data as $item): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= $item['no_pengembalian'] ?></td>
                        <td><?= date('d-m-Y', strtotime($item['tgl_pengembalian'])) ?></td>
                        <td><?= $item['id_anggota'] ?></td>
                        <td><?= $item['nm_anggota'] ?></td>
                        <td>
                            <?php foreach ($item['buku'] as $id_buku => $b): ?>
                                <strong><?= htmlspecialchars($id_buku) ?></strong> - <?= htmlspecialchars($b['judul']) ?><br>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php foreach ($item['buku'] as $id_buku => $b): ?>
                                <strong><?= htmlspecialchars($id_buku) ?>:</strong><br>
                                <?php foreach ($b['copy'] as $copy): ?>
                                    <?= htmlspecialchars($copy) ?><br>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </td>
                        <td><?= count($item['buku']) > 0 ? array_sum(array_map('count', array_column($item['buku'], 'copy'))) : 0 ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

<script>
    function cetakLaporan() {
        const tglMulai = document.getElementById('tgl_mulai').value;
        const tglSelesai = document.getElementById('tgl_selesai').value;

        if (!tglMulai || !tglSelesai) {
            alert('Silakan pilih rentang tanggal terlebih dahulu!');
            return;
        }

        const url = '<?= plugin_dir_url(__FILE__) ?>cetak_laporan_pengembalian.php?tgl_mulai=' + tglMulai + '&tgl_selesai=' + tglSelesai;
        window.open(url, '_blank');
    }
</script>