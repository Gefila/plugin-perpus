<?php
// Asumsi koneksi $conn sudah tersedia

// Ambil filter tanggal jika ada
$tgl_dari = $_GET['tgl_dari'] ?? '';
$tgl_sampai = $_GET['tgl_sampai'] ?? '';

$where = "";
if ($tgl_dari && $tgl_sampai) {
    $tgl_dari_sql = $conn->real_escape_string($tgl_dari);
    $tgl_sampai_sql = $conn->real_escape_string($tgl_sampai);
    $where = "WHERE p.tgl_pengembalian BETWEEN '$tgl_dari_sql' AND '$tgl_sampai_sql'";
}

// Query laporan denda
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
$where
ORDER BY pd.id_kembali_denda ASC
";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Hitung hari keterlambatan
        $telat = 0;
        $tgl_pengembalian = strtotime($row['tgl_pengembalian']);
        $tgl_harus_kembali = strtotime($row['tgl_harus_kembali']);
        if ($tgl_pengembalian > $tgl_harus_kembali) {
            $telat = ($tgl_pengembalian - $tgl_harus_kembali) / (60 * 60 * 24);
        }

        $row['hari_telat'] = (int)$telat;

        // Ambil list copy buku
        $no_pengembalian = $conn->real_escape_string($row['no_pengembalian']);
        $copy_q = $conn->query("SELECT no_copy_buku FROM bisa WHERE no_pengembalian = '$no_pengembalian'");
        $copy_list = [];
        while ($c = $copy_q->fetch_assoc()) {
            $copy_list[] = $c['no_copy_buku'];
        }
        $row['copy_buku'] = implode('<br>', $copy_list);

        $data[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Laporan Denda</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

    <!-- Custom Style -->
    <link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet" />
</head>

<body>
<div class="container my-5">   
<!-- Tampilan filter tanggal -->
<form method="GET" action="admin.php" class="mb-4">
    <input type="hidden" name="page" value="perpus_utama" />
    <input type="hidden" name="panggil" value="laporan_denda.php" />
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="tgl_dari" class="form-label fw-semibold text-primary">Dari Tanggal</label>
            <input
                type="date"
                id="tgl_dari"
                name="tgl_dari"
                class="form-control"
                style="border: 2px solid #3498db; border-radius: 8px; background: #f0f6ff;"
                value="<?= htmlspecialchars($tgl_dari) ?>"
            />
        </div>
        <div class="col-md-3">
            <label for="tgl_sampai" class="form-label fw-semibold text-primary">Sampai Tanggal</label>
            <input
                type="date"
                id="tgl_sampai"
                name="tgl_sampai"
                class="form-control"
                style="border: 2px solid #3498db; border-radius: 8px; background: #f0f6ff;"
                value="<?= htmlspecialchars($tgl_sampai) ?>"
            />
        </div>
        <div class="col-md-auto">
            <button type="submit" class="btn btn-primary btn-glow" style="border-radius: 8px;">
                <i class="fa fa-filter me-1"></i> Tampilkan
            </button>
        </div>
    </div>
</form>

<table class="table table-bordered table-hover">
    <thead>
        <tr>
            <th>No</th>
            <th>ID Kembali Denda</th>
            <th>ID Anggota</th>
            <th>Nama Anggota</th>
            <th>Tanggal Pinjam</th>
            <th>Tanggal Kembali</th>
            <th>Copy Buku</th>
            <th>Alasan Denda</th>
            <th>Subtotal (Rp)</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!empty($data)): ?>
        <?php $no = 1; foreach ($data as $item): ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>

                <td class="fw-bold"><?= htmlspecialchars($item['id_kembali_denda']) ?></td>
                <td class="fw-bold"><?= htmlspecialchars($item['id_anggota']) ?></td>
                <td class="fw-bold"><?= htmlspecialchars($item['nm_anggota']) ?></td>
                <td class="fw-bold"><?= date('d M Y', strtotime($item['tgl_peminjaman'])) ?></td>
                <td class="fw-bold"><?= date('d M Y', strtotime($item['tgl_pengembalian'])) ?></td>

                <td>
                    <div class="card bg-primary text-white py-2 px-3" style="border-radius: 8px; font-size: 0.9rem;">
                        <?= $item['copy_buku'] ?>
                    </div>
                </td>
                <td class="fw-bold">
                    <?php 
                    $id_denda = $item['id_denda'] ?? '';
                    $alasan = $item['alasan_denda'] ?? '';
                    $hari_telat = $item['hari_telat'] ?? 0;

                    if ($id_denda === 'D1' && stripos($alasan, 'telat') !== false && $hari_telat > 0): ?>
                        <div class="card bg-danger text-white py-1 px-2 mt-1 d-inline-block" style="font-size: 0.85rem; border-radius: 6px;">
                            Telat <?= $hari_telat ?> hari
                        </div>
                    <?php else: ?>
                        <div class="card bg-warning text-dark py-1 px-2 mt-1 d-inline-block" style="font-size: 0.85rem; border-radius: 6px;">
                            <?= htmlspecialchars($alasan) ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="fw-bold"><?= number_format($item['subtotal'], 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="9" class="text-muted text-center">Tidak ada data denda pada rentang waktu ini.</td>
        </tr>
    <?php endif; ?>
    </tbody>

</table>
