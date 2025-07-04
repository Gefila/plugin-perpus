<?php
// Koneksi dan helper generate kode
function generateNoPengembalian($conn) {
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(no_pengembalian, 3) AS UNSIGNED)) AS max_num FROM pengembalian");
    $row = $result->fetch_assoc();
    $next = (int)$row['max_num'] + 1;
    return "PG" . str_pad($next, 4, '0', STR_PAD_LEFT);
}

// Simpan pengembalian
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tgl_pengembalian = $_POST['tgl_pengembalian'];
    $no_peminjaman = $_POST['no_peminjaman'];
    $no_pengembalian = generateNoPengembalian($conn);

    $conn->query("INSERT INTO pengembalian (no_pengembalian, no_peminjaman, tgl_pengembalian) VALUES ('$no_pengembalian', '$no_peminjaman', '$tgl_pengembalian')");

    foreach ($_POST['copy_buku'] as $no_copy) {
        $conn->query("INSERT INTO bisa (no_pengembalian, no_copy_buku) VALUES ('$no_pengembalian', '$no_copy')");
        $conn->query("UPDATE copy_buku SET status_buku = 'tersedia' WHERE no_copy_buku = '$no_copy'");
    }

    // Hitung apakah telat
    $cek = $conn->query("SELECT tgl_harus_kembali FROM peminjaman WHERE no_peminjaman = '$no_peminjaman'");
    $tglHarusKembali = $cek->fetch_assoc()['tgl_harus_kembali'];

    $tarif_denda = (int)$_POST['tarif_denda'];
    $alasan_denda = $_POST['alasan_denda'];
    if ($tgl_pengembalian > $tglHarusKembali && $tarif_denda > 0) {
        $no_denda = "DN" . time();
        $tgl_denda = date('Y-m-d');
        $conn->query("INSERT INTO denda (no_denda, tgl_denda, tarif_denda, alasan_denda, no_pengembalian) VALUES ('$no_denda', '$tgl_denda', '$tarif_denda', '$alasan_denda', '$no_pengembalian')");
    }

    echo "<script>alert('Pengembalian berhasil disimpan!'); window.location.href='admin.php?page=perpus_utama&panggil=pengembalian.php';</script>";
    exit;
}

// Data anggota dan peminjaman
$anggota = $conn->query("SELECT id_anggota, nm_anggota FROM anggota ORDER BY nm_anggota ASC");
$peminjaman = $conn->query("SELECT no_peminjaman, id_anggota, tgl_harus_kembali FROM peminjaman");
$data_peminjaman = [];
while ($p = $peminjaman->fetch_assoc()) {
    $data_peminjaman[$p['id_anggota']][] = $p;
}

// Copy buku yang belum dikembalikan
$copy = $conn->query("
    SELECT d.no_peminjaman, cb.no_copy_buku, b.id_buku, b.judul_buku
    FROM dapat d
    JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
    JOIN buku b ON cb.id_buku = b.id_buku
    WHERE d.no_copy_buku NOT IN (SELECT no_copy_buku FROM bisa)
");
$copy_data = [];
while ($c = $copy->fetch_assoc()) {
    $copy_data[$c['no_peminjaman']][] = $c;
}
?>

<h3>Tambah Pengembalian Buku</h3>
<form method="POST">
    <div class="mb-3">
        <label>Nama Anggota</label>
        <select name="id_anggota" id="anggotaSelect" class="form-select" onchange="filterPeminjaman()" required>
            <option value="">-- Pilih Anggota --</option>
            <?php while ($a = $anggota->fetch_assoc()): ?>
                <option value="<?= $a['id_anggota'] ?>"><?= $a['id_anggota'] ?> - <?= $a['nm_anggota'] ?></option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Nomor Peminjaman</label>
        <select name="no_peminjaman" id="peminjamanSelect" class="form-select" onchange="tampilkanTabel(); updateStatus();" required>
            <option value="">-- Pilih Nomor Peminjaman --</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Tanggal Harus Kembali</label>
        <input type="text" id="tglHarusKembali" class="form-control" readonly>
    </div>

    <div class="mb-3">
        <label>Tanggal Pengembalian</label>
        <input type="date" name="tgl_pengembalian" id="tglPengembalian" class="form-control" onchange="updateStatus()" required>
    </div>

    <div class="mb-3">
        <label>Status Pengembalian</label>
        <input type="text" id="statusPengembalian" class="form-control" readonly>
    </div>

    <div class="mb-3 bg-light p-3 rounded">
        <table class="table table-bordered">
            <thead class="table-secondary text-center">
                <tr><th>No</th><th>ID Buku</th><th>Judul Buku</th><th>No Copy Buku</th></tr>
            </thead>
            <tbody id="tabelBuku">
                <tr><td colspan="4" class="text-center text-danger">Silakan pilih anggota dan peminjaman.</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mb-3">
        <label>Denda (Rp)</label>
        <input type="text" id="tarif_denda_display" class="form-control" value="0">
        <input type="hidden" name="tarif_denda" id="tarif_denda" value="0">
    </div>

    <div class="mb-3">
        <label>Alasan Denda</label>
        <textarea name="alasan_denda" class="form-control"></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Simpan Pengembalian</button>
</form>

<script>
const dataPeminjaman = <?= json_encode($data_peminjaman) ?>;
const dataCopy = <?= json_encode($copy_data) ?>;

function filterPeminjaman() {
    const anggota = document.getElementById('anggotaSelect').value;
    const select = document.getElementById('peminjamanSelect');
    select.innerHTML = '<option value="">-- Pilih Nomor Peminjaman --</option>';

    if (anggota && dataPeminjaman[anggota]) {
        dataPeminjaman[anggota].forEach(p => {
            if (dataCopy[p.no_peminjaman]) {
                select.innerHTML += `<option value="${p.no_peminjaman}" data-tgl="${p.tgl_harus_kembali}">${p.no_peminjaman}</option>`;
            }
        });
    }
    tampilkanTabel();
    updateStatus();
}

function tampilkanTabel() {
    const noPeminjaman = document.getElementById('peminjamanSelect').value;
    const tbody = document.getElementById('tabelBuku');
    tbody.innerHTML = '';

    if (!noPeminjaman || !dataCopy[noPeminjaman]) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Tidak ada copy buku untuk peminjaman ini.</td></tr>';
        return;
    }

    dataCopy[noPeminjaman].forEach((b, i) => {
        tbody.innerHTML += `<tr>
            <td class="text-center">${i + 1}</td>
            <td>${b.id_buku}</td>
            <td>${b.judul_buku}</td>
            <td><label><input type="checkbox" name="copy_buku[]" value="${b.no_copy_buku}"> ${b.no_copy_buku}</label></td>
        </tr>`;
    });
}

function updateStatus() {
    const tglHarus = document.getElementById('peminjamanSelect').selectedOptions[0]?.dataset?.tgl || '';
    const tglKembali = document.getElementById('tglPengembalian').value;
    document.getElementById('tglHarusKembali').value = tglHarus;

    const status = document.getElementById('statusPengembalian');
    if (tglKembali && tglHarus) {
        status.value = tglKembali > tglHarus ? 'Telat' : 'Tepat Waktu';
        status.style.color = tglKembali > tglHarus ? 'red' : 'green';
    } else {
        status.value = '';
        status.style.color = '';
    }
}

// Format denda
const displayInput = document.getElementById('tarif_denda_display');
const hiddenInput = document.getElementById('tarif_denda');

displayInput.addEventListener('input', () => {
    hiddenInput.value = displayInput.value.replace(/[^\d]/g, '') || 0;
});
displayInput.addEventListener('blur', () => {
    let angka = displayInput.value.replace(/[^\d]/g, '');
    displayInput.value = angka ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(angka) : '';
});
displayInput.addEventListener('focus', () => {
    displayInput.value = hiddenInput.value === '0' ? '' : hiddenInput.value;
});
</script>
