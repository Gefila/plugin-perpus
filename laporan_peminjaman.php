<?php
$tgl_mulai = $_POST['tgl_mulai'] ?? '';
$tgl_selesai = $_POST['tgl_selesai'] ?? '';
$data = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($tgl_mulai) && !empty($tgl_selesai)) {
    $sql = "
        SELECT 
            pj.no_peminjaman,
            pj.tgl_peminjaman,
            a.id_anggota,
            a.nm_anggota,
            b.id_buku,
            b.judul_buku,
            d.no_copy_buku
        FROM peminjaman pj
        LEFT JOIN anggota a ON pj.id_anggota = a.id_anggota
        LEFT JOIN dapat d ON pj.no_peminjaman = d.no_peminjaman
        LEFT JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
        LEFT JOIN buku b ON cb.id_buku = b.id_buku
        WHERE pj.tgl_peminjaman BETWEEN '$tgl_mulai' AND '$tgl_selesai'
        ORDER BY pj.tgl_peminjaman ASC
    ";
    
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $no_peminjaman = $row['no_peminjaman'];

            if (!isset($data[$no_peminjaman])) {
                $data[$no_peminjaman] = [
                    'no_peminjaman' => $no_peminjaman,
                    'tgl_peminjaman' => $row['tgl_peminjaman'],
                    'id_anggota' => $row['id_anggota'],
                    'nm_anggota' => $row['nm_anggota'],
                    'buku' => []
                ];
            }

            $id_buku = $row['id_buku'];
            if ($id_buku) {
                if (!isset($data[$no_peminjaman]['buku'][$id_buku])) {
                    $data[$no_peminjaman]['buku'][$id_buku] = [
                        'judul' => $row['judul_buku'],
                        'copy' => []
                    ];
                }

                if ($row['no_copy_buku']) {
                    $data[$no_peminjaman]['buku'][$id_buku]['copy'][] = $row['no_copy_buku'];
                }
            }
        }
    }
}
?>

<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet" />

<div class="container my-5">
    <h2 class="text-center text-primary fw-bold mb-4">Laporan Peminjaman Buku</h2>

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
            <h5 class="text-center text-success">Menampilkan peminjaman dari <strong><?= htmlspecialchars($tgl_mulai) ?></strong> sampai <strong><?= htmlspecialchars($tgl_selesai) ?></strong></h5>
        </div>

        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>No</th>
                    <th>No Peminjaman</th>
                    <th>Tanggal Pinjam</th>
                    <th>ID Anggota</th>
                    <th>Nama Anggota</th>
                    <th>Judul Buku</th>
                    <th>Copy Buku</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
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
                        <td><?= htmlspecialchars($item['no_peminjaman']) ?></td>
                        <td><?= date('d-m-Y', strtotime($item['tgl_peminjaman'])) ?></td>
                        <td><?= htmlspecialchars($item['id_anggota']) ?></td>
                        <td><?= htmlspecialchars($item['nm_anggota']) ?></td>
                        <td class="text-start"><?= $judulList ?></td>
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

    const url = '<?= plugin_dir_url(__FILE__) ?>cetak_laporan_peminjaman.php?tgl_mulai=' + tglMulai + '&tgl_selesai=' + tglSelesai;
    window.open(url, '_blank');
}
</script>
