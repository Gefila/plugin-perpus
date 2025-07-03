<?php

function generateNoPengembalian($conn) {
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(no_pengembalian, 3) AS UNSIGNED)) AS max_num FROM pengembalian");
    $row = $result->fetch_assoc();
    $next = (int)$row['max_num'] + 1;
    return "PG" . str_pad($next, 4, '0', STR_PAD_LEFT);
}

function generateNoDenda($conn) {
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(no_denda, 3) AS UNSIGNED)) AS max_num FROM denda");
    $row = $result->fetch_assoc();
    $next = (int)$row['max_num'] + 1;
    return "DN" . $next;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan'])) {
    $tgl_pengembalian = $_POST['tgl_pengembalian'];
    $id_anggota = $_POST['id_anggota'];
    $no_peminjaman = $_POST['no_peminjaman'];
    $no_pengembalian = generateNoPengembalian($conn);

    $conn->query("INSERT INTO pengembalian (no_pengembalian, no_peminjaman, tgl_pengembalian)
                  VALUES ('$no_pengembalian', '$no_peminjaman', '$tgl_pengembalian')");

    foreach ($_POST['no_copy_buku'] as $i => $no_copy) {
        $jumlah_kembali = (int)$_POST['jumlah_kembali'][$i];
        $jumlah_max = (int)$_POST['jumlah_max'][$i];
        if ($jumlah_kembali > $jumlah_max) $jumlah_kembali = $jumlah_max;

        $conn->query("INSERT INTO bisa (no_pengembalian, no_copy_buku, jml_kembali)
                      VALUES ('$no_pengembalian', '$no_copy', $jumlah_kembali)");

        $conn->query("UPDATE copy_buku SET status_buku = 'tersedia' WHERE no_copy_buku = '$no_copy'");
    }

    $cek = $conn->query("SELECT tgl_harus_kembali FROM peminjaman WHERE no_peminjaman = '$no_peminjaman'");
    $tglHarusKembali = $cek->fetch_assoc()['tgl_harus_kembali'];

    if ($tgl_pengembalian > $tglHarusKembali) {
        $tarif_denda = $_POST['tarif_denda'] ?: 0;
        $alasan_denda = $_POST['alasan_denda'] ?: '';
        $tgl_denda = date('Y-m-d');
        $no_denda = generateNoDenda($conn);

        $conn->query("INSERT INTO denda (no_denda, tgl_denda, tarif_denda, alasan_denda, no_pengembalian)
                      VALUES ('$no_denda', '$tgl_denda', '$tarif_denda', '$alasan_denda', '$no_pengembalian')");
    }

    echo "<script>alert('Pengembalian berhasil disimpan!'); window.location.href='admin.php?page=perpus_utama&panggil=pengembalian.php';</script>";
    exit;
}

$anggota_result = $conn->query("SELECT id_anggota, nm_anggota FROM anggota ORDER BY nm_anggota ASC");
$all_peminjaman = $conn->query("SELECT no_peminjaman, id_anggota, tgl_harus_kembali FROM peminjaman");
$peminjamanData = [];
while ($row = $all_peminjaman->fetch_assoc()) {
    $peminjamanData[$row['id_anggota']][] = $row;
}

// Ambil data detail buku yang belum dikembalikan
$detail_buku_query = $conn->query("SELECT d.no_peminjaman, d.no_copy_buku, b.id_buku, b.judul_buku, d.jml_pinjam 
FROM dapat d 
JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku 
JOIN buku b ON cb.id_buku = b.id_buku
WHERE d.no_copy_buku NOT IN (
    SELECT no_copy_buku FROM bisa
    JOIN pengembalian p ON bisa.no_pengembalian = p.no_pengembalian
)");
$detail_buku = [];
while ($row = $detail_buku_query->fetch_assoc()) {
    $detail_buku[$row['no_peminjaman']][] = $row;
}
?>


<h3>Tambah Pengembalian Buku</h3>

<form method="POST">
    <div class="mb-3">
        <label>Nama Anggota</label>
        <select name="id_anggota" id="anggotaSelect" class="form-select" required onchange="filterPeminjaman()">
            <option value="">-- Pilih Anggota --</option>
            <?php while ($a = $anggota_result->fetch_assoc()): ?>
                <option value="<?= $a['id_anggota'] ?>"><?= $a['id_anggota'] ?> - <?= $a['nm_anggota'] ?></option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Nomor Peminjaman</label>
        <select name="no_peminjaman" id="peminjamanSelect" class="form-select" required onchange="tampilkanTabel()">
            <option value="">-- Pilih Nomor Peminjaman --</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Tanggal Pengembalian</label>
        <input type="date" name="tgl_pengembalian" id="tglPengembalian" class="form-control form-control-sm" required onchange="cekDenda()">
    </div>

    <div class="mb-3 bg-light p-3 rounded">
        <table class="table table-bordered">
            <thead>
                <tr class="table-secondary text-center">
                    <th>No</th>
                    <th>ID Buku</th>
                    <th>Judul Buku</th>
                    <th>No Copy Buku</th>
                    <th>Jumlah Kembali</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="tabelPengembalianBody">
                <tr>
                    <td colspan="6" class="text-center text-danger">Silakan pilih anggota dan peminjaman terlebih dahulu.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="mb-3">
        <label>Denda (Rp)</label>
        <input type="number" name="tarif_denda" id="tarif_denda" class="form-control form-control-sm" disabled>
    </div>

    <div class="mb-3">
        <label>Alasan Denda</label>
        <textarea name="alasan_denda" id="alasan_denda" class="form-control form-control-sm" disabled></textarea>
    </div>

    <button type="submit" name="simpan" class="btn btn-primary">Simpan Pengembalian</button>
    <a href="admin.php?page=perpus_utama&panggil=pengembalian.php" class="btn btn-secondary">Batal</a>
</form>

<script>
const peminjamanData = <?= json_encode($peminjamanData) ?>;
const detailBuku = <?= json_encode($detail_buku) ?>;

function filterPeminjaman() {
    const anggotaId = document.getElementById('anggotaSelect').value;
    const peminjamanSelect = document.getElementById('peminjamanSelect');

    peminjamanSelect.innerHTML = '<option value="">-- Pilih Nomor Peminjaman --</option>';

    if (anggotaId && peminjamanData[anggotaId]) {
        peminjamanData[anggotaId].forEach(p => {
            peminjamanSelect.innerHTML += `<option value="${p.no_peminjaman}">${p.no_peminjaman}</option>`;
        });
    }
}

function tampilkanTabel() {
    const noPeminjaman = document.getElementById('peminjamanSelect').value;
    const tbody = document.getElementById('tabelPengembalianBody');
    tbody.innerHTML = '';

    if (!noPeminjaman || !detailBuku[noPeminjaman]) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Tidak ada data buku untuk nomor peminjaman ini.</td></tr>';
        return;
    }

    detailBuku[noPeminjaman].forEach((buku, index) => {
        tbody.innerHTML += `
            <tr>
                <td class="text-center">${index + 1}</td>
                <td>${buku.id_buku}</td>
                <td>${buku.judul_buku}</td>
                <td><input type="hidden" name="no_copy_buku[]" value="${buku.no_copy_buku}">${buku.no_copy_buku}</td>
                <td><input type="number" name="jumlah_kembali[]" class="form-control form-control-sm" min="1" max="${buku.jml_pinjam}" value="${buku.jml_pinjam}" required><input type="hidden" name="jumlah_max[]" value="${buku.jml_pinjam}"></td>
                <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();">-</button></td>
            </tr>`;
    });
}

function cekDenda() {
    const noPeminjaman = document.getElementById('peminjamanSelect').value;
    const tglPengembalian = document.getElementById('tglPengembalian').value;
    const anggotaId = document.getElementById('anggotaSelect').value;

    let tglHarusKembali = '';
    if (anggotaId && peminjamanData[anggotaId]) {
        const pem = peminjamanData[anggotaId].find(p => p.no_peminjaman === noPeminjaman);
        tglHarusKembali = pem?.tgl_harus_kembali || '';
    }

    const dendaField = document.getElementById('tarif_denda');
    const alasanField = document.getElementById('alasan_denda');

    if (tglPengembalian && tglHarusKembali && tglPengembalian > tglHarusKembali) {
        dendaField.disabled = false;
        alasanField.disabled = false;
    } else {
        dendaField.disabled = true;
        alasanField.disabled = true;
        dendaField.value = '';
        alasanField.value = '';
    }
}
</script>
