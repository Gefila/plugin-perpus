<?php
// Function Generate Otomatis
function generateNoPengembalian($conn) {
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(no_pengembalian, 3) AS UNSIGNED)) AS max_num FROM pengembalian");
    $row = $result->fetch_assoc();
    return "PG" . ((int)$row['max_num'] + 1);
}

function generateNoDenda($conn) {
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(no_denda, 3) AS UNSIGNED)) AS max_num FROM denda");
    $row = $result->fetch_assoc();
    return "DN" . ((int)$row['max_num'] + 1);
}

// Proses Simpan Pengembalian
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan'])) {
    $tgl_pengembalian = $_POST['tgl_pengembalian'];
    $id_anggota = $_POST['id_anggota'];
    $no_peminjaman = $_POST['no_peminjaman'];
    $no_pengembalian = generateNoPengembalian($conn);

    $conn->query("INSERT INTO pengembalian (no_pengembalian, no_peminjaman, tgl_pengembalian) VALUES ('$no_pengembalian', '$no_peminjaman', '$tgl_pengembalian')");

    if (!empty($_POST['no_copy_buku'])) {
        foreach ($_POST['no_copy_buku'] as $no_copy) {
            $conn->query("INSERT INTO bisa (no_pengembalian, no_copy_buku) VALUES ('$no_pengembalian', '$no_copy')");
            $conn->query("UPDATE copy_buku SET status_buku = 'tersedia' WHERE no_copy_buku = '$no_copy'");
        }
    }

    $cek = $conn->query("SELECT tgl_harus_kembali FROM peminjaman WHERE no_peminjaman = '$no_peminjaman'");
    $tglHarusKembali = $cek->fetch_assoc()['tgl_harus_kembali'];

    $tarif_denda = isset($_POST['tarif_denda']) ? (int)$_POST['tarif_denda'] : 0;
    $alasan_denda = $_POST['alasan_denda'] ?: '';
    $tgl_denda = $tgl_pengembalian;

    if ($tgl_pengembalian > $tglHarusKembali && $tarif_denda > 0) {
        $no_denda = generateNoDenda($conn);
        $conn->query("INSERT INTO denda (no_denda, tgl_denda, tarif_denda, alasan_denda, no_pengembalian) VALUES ('$no_denda', '$tgl_denda', '$tarif_denda', '$alasan_denda', '$no_pengembalian')");
    }

    echo "<script>alert('Pengembalian berhasil disimpan!'); window.location.href='admin.php?page=perpus_utama&panggil=pengembalian.php';</script>";
    exit;
}

// Ambil Data Anggota
$anggota_result = $conn->query("SELECT id_anggota, nm_anggota FROM anggota ORDER BY nm_anggota ASC");

// Ambil Peminjaman Aktif (yang masih ada copy buku belum dikembalikan)
$all_peminjaman = $conn->query("
    SELECT p.no_peminjaman, p.id_anggota, p.tgl_peminjaman, p.tgl_harus_kembali
    FROM peminjaman p
    WHERE EXISTS (
        SELECT 1 FROM dapat d
        WHERE d.no_peminjaman = p.no_peminjaman
        AND NOT EXISTS (
            SELECT 1 FROM bisa b
            JOIN pengembalian pg ON b.no_pengembalian = pg.no_pengembalian
            WHERE b.no_copy_buku = d.no_copy_buku
            AND pg.no_peminjaman = p.no_peminjaman
        )
    )
");

$peminjamanData = [];
while ($row = $all_peminjaman->fetch_assoc()) {
    $peminjamanData[$row['id_anggota']][] = $row;
}

// Ambil Detail Buku yang Belum Dikembalikan di peminjaman terkait
$detail_buku_query = $conn->query("
    SELECT d.no_peminjaman, d.no_copy_buku, b.id_buku, b.judul_buku
    FROM dapat d
    JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
    JOIN buku b ON cb.id_buku = b.id_buku
    WHERE NOT EXISTS (
        SELECT 1 FROM bisa bi
        JOIN pengembalian pe ON bi.no_pengembalian = pe.no_pengembalian
        WHERE bi.no_copy_buku = d.no_copy_buku
        AND pe.no_peminjaman = d.no_peminjaman
    )
");

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
        <select name="no_peminjaman" id="peminjamanSelect" class="form-select" required onchange="updateStatus(); tampilkanTabel()">
            <option value="">-- Pilih Nomor Peminjaman --</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Tanggal Harus Kembali</label>
        <input type="text" id="tgl_harus_kembali_display" class="form-control form-control-sm" readonly>
    </div>

    <div class="mb-3">
        <label>Tanggal Pengembalian</label>
        <input type="date" name="tgl_pengembalian" id="tglPengembalian" class="form-control form-control-sm" required onchange="updateStatus()">
    </div>

    <div class="mb-3">
        <label>Status Pengembalian</label>
        <input type="text" id="status_pengembalian" class="form-control form-control-sm" readonly>
    </div>

    <div class="mb-3 bg-light p-3 rounded">
        <table class="table table-bordered">
            <thead>
                <tr class="table-secondary text-center">
                    <th>No</th>
                    <th>ID Buku</th>
                    <th>Judul Buku</th>
                    <th>No Copy Buku</th>
                </tr>
            </thead>
            <tbody id="tabelPengembalianBody">
                <tr>
                    <td colspan="5" class="text-center text-danger">Silakan pilih anggota dan peminjaman terlebih dahulu.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="mb-3">
        <label>Denda (Rp)</label>
        <input type="text" id="tarif_denda_display" class="form-control form-control-sm">
        <input type="hidden" name="tarif_denda" id="tarif_denda" value="0">
    </div>

    <div class="mb-3">
        <label>Alasan Denda</label>
        <textarea name="alasan_denda" id="alasan_denda" class="form-control form-control-sm"></textarea>
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

        updateStatus();
        tampilkanTabel();
    }

    function tampilkanTabel() {
        const noPeminjaman = document.getElementById('peminjamanSelect').value;
        const tbody = document.getElementById('tabelPengembalianBody');
        tbody.innerHTML = '';

        if (!noPeminjaman || !detailBuku[noPeminjaman]) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Tidak ada data buku untuk nomor peminjaman ini.</td></tr>';
            return;
        }

        detailBuku[noPeminjaman].forEach((buku, index) => {
            tbody.innerHTML += `
                <tr>
                    <td class="text-center">${index + 1}</td>
                    <td>${buku.id_buku}</td>
                    <td>${buku.judul_buku}</td>
                    <td class="text-center">
                        <label>
                            <input type="checkbox" name="no_copy_buku[]" value="${buku.no_copy_buku}"> ${buku.no_copy_buku}
                        </label>
                    </td>
                </tr>`;
        });
    }

    function updateStatus() {
        const noPeminjaman = document.getElementById('peminjamanSelect').value;
        const tglPengembalian = document.getElementById('tglPengembalian').value;
        const anggotaId = document.getElementById('anggotaSelect').value;

        let tglHarusKembali = '';

        if (anggotaId && peminjamanData[anggotaId]) {
            const pem = peminjamanData[anggotaId].find(p => p.no_peminjaman === noPeminjaman);
            if (pem) {
                tglHarusKembali = pem.tgl_harus_kembali || '';
            }
        }

       if (tglHarusKembali) {
            const dateObj = new Date(tglHarusKembali);
            const formattedDate = dateObj.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
            document.getElementById('tgl_harus_kembali_display').value = formattedDate;
        } else {
            document.getElementById('tgl_harus_kembali_display').value = '';
        }


        const statusField = document.getElementById('status_pengembalian');
        if (tglPengembalian && tglHarusKembali) {
            statusField.value = tglPengembalian > tglHarusKembali ? 'Telat' : 'Tepat Waktu';
            statusField.style.color = tglPengembalian > tglHarusKembali ? 'red' : 'green';
        } else {
            statusField.value = '';
            statusField.style.color = '';
        }
    }

    const displayInput = document.getElementById('tarif_denda_display');
    const hiddenInput = document.getElementById('tarif_denda');

    displayInput.addEventListener('input', () => {
        hiddenInput.value = displayInput.value.replace(/[^\d]/g, '') || 0;
    });

    displayInput.addEventListener('blur', () => {
        let angka = displayInput.value.replace(/[^\d]/g, '');
        displayInput.value = angka ? formatRupiah(angka) : '';
    });

    displayInput.addEventListener('focus', () => {
        displayInput.value = hiddenInput.value === '0' ? '' : hiddenInput.value;
    });

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(angka);
    }
</script>
