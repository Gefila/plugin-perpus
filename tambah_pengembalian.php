<?php
// Pastikan koneksi $conn sudah tersedia

function generateNoPengembalian($conn) {
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(no_pengembalian, 3) AS UNSIGNED)) AS max_num FROM pengembalian");
    $row = $result->fetch_assoc();
    $next = (int)$row['max_num'] + 1;
    return "PG" . str_pad($next, 4, '0', STR_PAD_LEFT);
}

// Simpan data pengembalian
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $no_pengembalian = generateNoPengembalian($conn);
    $no_peminjaman = $_POST['no_peminjaman'];
    $tgl_pengembalian = $_POST['tgl_pengembalian'];

    $conn->query("INSERT INTO pengembalian (no_pengembalian, no_peminjaman, tgl_pengembalian) 
                  VALUES ('$no_pengembalian', '$no_peminjaman', '$tgl_pengembalian')");

    if (!empty($_POST['copy_buku'])) {
        foreach ($_POST['copy_buku'] as $no_copy) {
            $conn->query("INSERT INTO bisa (no_pengembalian, no_copy_buku, jml_kembali) 
                          VALUES ('$no_pengembalian', '$no_copy', 1)");
            $conn->query("UPDATE copy_buku SET status_buku = 'tersedia' WHERE no_copy_buku = '$no_copy'");
        }
    }

    echo "<script>alert('Pengembalian berhasil disimpan!'); window.location.href='admin.php?page=perpus_utama&panggil=pengembalian.php';</script>";
    exit;
}

// Ambil data anggota
$anggota_result = $conn->query("SELECT id_anggota, nm_anggota FROM anggota");

// Ambil peminjaman yang masih ada copy belum dikembalikan
$peminjaman_result = $conn->query("
    SELECT p.no_peminjaman, p.id_anggota, p.tgl_harus_kembali
    FROM peminjaman p
    WHERE EXISTS (
        SELECT 1 FROM dapat d
        JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
        WHERE d.no_peminjaman = p.no_peminjaman
        AND d.no_copy_buku NOT IN (
            SELECT no_copy_buku FROM bisa
        )
    )
");

$peminjamanData = [];
while ($row = $peminjaman_result->fetch_assoc()) {
    $peminjamanData[$row['id_anggota']][] = $row;
}

// Ambil copy buku yang belum dikembalikan
$detail_query = $conn->query("
    SELECT d.no_peminjaman, cb.no_copy_buku, b.id_buku, b.judul_buku
    FROM dapat d
    JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
    JOIN buku b ON cb.id_buku = b.id_buku
    WHERE d.no_copy_buku NOT IN (
        SELECT no_copy_buku FROM bisa
    )
");

$detailBuku = [];
while ($row = $detail_query->fetch_assoc()) {
    $detailBuku[$row['no_peminjaman']][] = $row;
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
        <select name="no_peminjaman" id="peminjamanSelect" class="form-select" required onchange="updateTanggalDanStatus(); tampilkanCopyBuku()">
            <option value="">-- Pilih Nomor Peminjaman --</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Tanggal Harus Kembali</label>
        <input type="text" id="tglHarusKembali" class="form-control" readonly>
    </div>

    <div class="mb-3">
        <label>Tanggal Pengembalian</label>
        <input type="date" name="tgl_pengembalian" id="tglPengembalian" class="form-control" required onchange="updateStatus()">
    </div>

    <div class="mb-3">
        <label>Status Pengembalian</label>
        <input type="text" id="statusPengembalian" class="form-control" readonly>
    </div>

    <div class="mb-3">
        <table class="table table-bordered">
            <thead>
                <tr class="table-secondary text-center">
                    <th>No</th>
                    <th>ID Buku</th>
                    <th>Judul Buku</th>
                    <th>No Copy Buku</th>
                    <th>Pilih Kembali</th>
                </tr>
            </thead>
            <tbody id="copyBukuTableBody">
                <tr><td colspan="5" class="text-danger text-center">Silakan pilih anggota dan nomor peminjaman</td></tr>
            </tbody>
        </table>
    </div>

    <button type="submit" class="btn btn-primary">Simpan Pengembalian</button>
    <a href="admin.php?page=perpus_utama&panggil=pengembalian.php" class="btn btn-secondary">Batal</a>
</form>

<script>
const peminjamanData = <?= json_encode($peminjamanData) ?>;
const detailBuku = <?= json_encode($detailBuku) ?>;

function filterPeminjaman() {
    const anggotaId = document.getElementById('anggotaSelect').value;
    const pemSelect = document.getElementById('peminjamanSelect');
    pemSelect.innerHTML = '<option value="">-- Pilih Nomor Peminjaman --</option>';

    if (anggotaId && peminjamanData[anggotaId]) {
        peminjamanData[anggotaId].forEach(p => {
            pemSelect.innerHTML += `<option value="${p.no_peminjaman}">${p.no_peminjaman}</option>`;
        });
    }

    updateTanggalDanStatus();
    tampilkanCopyBuku();
}

function updateTanggalDanStatus() {
    const pemSelect = document.getElementById('peminjamanSelect');
    const noPeminjaman = pemSelect.value;
    const tglHarusKembaliInput = document.getElementById('tglHarusKembali');
    const tglPengembalianInput = document.getElementById('tglPengembalian');
    const statusInput = document.getElementById('statusPengembalian');

    if (!noPeminjaman) {
        tglHarusKembaliInput.value = '';
        statusInput.value = '';
        return;
    }

    // Cari tanggal harus kembali dari peminjamanData
    let tglHarusKembali = '';
    outerLoop:
    for (const anggota in peminjamanData) {
        for (const p of peminjamanData[anggota]) {
            if (p.no_peminjaman === noPeminjaman) {
                tglHarusKembali = p.tgl_harus_kembali;
                break outerLoop;
            }
        }
    }

    tglHarusKembaliInput.value = tglHarusKembali;

    updateStatus();
}

function updateStatus() {
    const tglHarusKembali = document.getElementById('tglHarusKembali').value;
    const tglPengembalian = document.getElementById('tglPengembalian').value;
    const statusInput = document.getElementById('statusPengembalian');

    if (tglPengembalian && tglHarusKembali) {
        statusInput.value = (tglPengembalian > tglHarusKembali) ? 'Telat' : 'Tepat Waktu';
        statusInput.style.color = (tglPengembalian > tglHarusKembali) ? 'red' : 'green';
    } else {
        statusInput.value = '';
        statusInput.style.color = '';
    }
}

function tampilkanCopyBuku() {
    const noPeminjaman = document.getElementById('peminjamanSelect').value;
    const tbody = document.getElementById('copyBukuTableBody');
    tbody.innerHTML = '';

    if (!noPeminjaman || !detailBuku[noPeminjaman]) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">Tidak ada copy buku yang belum dikembalikan.</td></tr>';
        return;
    }

    detailBuku[noPeminjaman].forEach((buku, index) => {
        tbody.innerHTML += `
            <tr>
                <td class="text-center">${index + 1}</td>
                <td>${buku.id_buku}</td>
                <td>${buku.judul_buku}</td>
                <td>${buku.no_copy_buku}</td>
                <td class="text-center">
                    <input type="checkbox" name="copy_buku[]" value="${buku.no_copy_buku}">
                </td>
            </tr>
        `;
    });
}
</script>
