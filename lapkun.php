<?php
// Inisialisasi variabel tanggal
$tgl_dari = isset($_POST['tgl_dari']) ? $_POST['tgl_dari'] : '';
$tgl_sampai = isset($_POST['tgl_sampai']) ? $_POST['tgl_sampai'] : '';

$hasil_kunjungan = null;

// Jika form dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($tgl_dari) && !empty($tgl_sampai)) {
    $sql = "SELECT k.*, a.nm_anggota FROM kunjungan k
            JOIN anggota a ON k.id_anggota = a.id_anggota
            WHERE tgl_kunjungan BETWEEN '$tgl_dari' AND '$tgl_sampai'
            ORDER BY tgl_kunjungan ASC";

    $hasil_kunjungan = $conn->query($sql);
}
?>

<h2 class="text-center">Laporan Kunjungan Berdasarkan Tanggal</h2>

<form method="post" class="form-inline mb-4">
    <div class="form-group mr-2">
        <label for="tgl_dari">Dari Tanggal:</label>
        <input type="date" name="tgl_dari" id="tgl_dari" class="form-control ml-2" required value="<?= htmlspecialchars($tgl_dari) ?>">
    </div>
    <div class="form-group mr-2">
        <label for="tgl_sampai">Sampai Tanggal:</label>
        <input type="date" name="tgl_sampai" id="tgl_sampai" class="form-control ml-2" required value="<?= htmlspecialchars($tgl_sampai) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Tampilkan</button>
</form>

<?php if ($hasil_kunjungan !== null): ?>
    <h5 class="text-center">Menampilkan kunjungan dari <strong><?= htmlspecialchars($tgl_dari) ?></strong> sampai <strong><?= htmlspecialchars($tgl_sampai) ?></strong></h5>

    <table class="table table-bordered table-striped mt-3">
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
                            <td>{$no}</td>
                            <td>{$row['id_kunjungan']}</td>
                            <td>{$row['tgl_kunjungan']}</td>
                            <td>{$row['nm_anggota']}</td>
                            <td>{$row['tujuan']}</td>
                          </tr>";
                    $no++;
                }
            } else {
                echo '<tr><td colspan="5" class="text-center">Tidak ada data kunjungan dalam rentang tanggal tersebut.</td></tr>';
            }
            ?>
        </tbody>
    </table>
<?php endif; ?>
