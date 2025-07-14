<?php
$tgl_mulai = $_POST['tgl_mulai'] ?? '';
$tgl_selesai = $_POST['tgl_selesai'] ?? '';
$hasil_pengembalian = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($tgl_mulai) && !empty($tgl_selesai)) {
    $sql = "
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
    $hasil_pengembalian = $conn->query($sql);
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

    <?php if ($hasil_pengembalian !== null): ?>
        <div class="card-glass mb-4">
            <h5 class="text-center text-success">Menampilkan pengembalian dari <strong><?= htmlspecialchars($tgl_mulai) ?></strong> sampai <strong><?= htmlspecialchars($tgl_selesai) ?></strong></h5>
        </div>

        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>No</th>
                    <th>No Pengembalian</th>
                    <th>Tanggal Kembali</th>
                    <th>ID Anggota</th>
                    <th>Nama Anggota</th>
                    <th>ID Buku</th>
                    <th>Judul Buku</th>
                    <th>No Copy Buku</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                if ($hasil_pengembalian->num_rows > 0) {
                    while ($row = $hasil_pengembalian->fetch_assoc()) {
                        echo "<tr>
                                <td>{$no}</td>
                                <td>{$row['no_pengembalian']}</td>
                                <td>" . date('d-m-Y', strtotime($row['tgl_pengembalian'])) . "</td>
                                <td>{$row['id_anggota']}</td>
                                <td>{$row['nm_anggota']}</td>
                                <td>{$row['id_buku']}</td>
                                <td>{$row['judul_buku']}</td>
                                <td>{$row['no_copy']}</td>
                                <td>{$row['jumlah']}</td>
                              </tr>";
                        $no++;
                    }
                } else {
                    echo '<tr><td colspan="9" class="text-muted text-center">Tidak ada data pengembalian dalam rentang tanggal tersebut.</td></tr>';
                }
                ?>
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
