<?php

function generateNoPeminjaman($conn) {
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(no_peminjaman, 3) AS UNSIGNED)) AS max_num FROM peminjaman");
    $row = $result->fetch_assoc();
    $next = (int)$row['max_num'] + 1;
    return "PJ" . $next;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tgl_pinjam = $_POST['tgl_pinjam'];
    $tgl_kembali = $_POST['tgl_kembali'];
    $id_anggota = $_POST['id_anggota'];

    if ($tgl_kembali <= $tgl_pinjam) {
        echo "<script>alert('Tanggal kembali harus lebih besar dari tanggal pinjam!'); window.history.back();</script>";
        exit;
    }

    if (!isset($_POST['copy_buku']) || !is_array($_POST['copy_buku'])) {
        echo "<script>alert('Pilih minimal satu copy buku yang tersedia!'); window.history.back();</script>";
        exit;
    }

    $semua_copy = [];
    foreach ($_POST['copy_buku'] as $copies) {
        foreach ($copies as $no_copy) {
            if (in_array($no_copy, $semua_copy)) {
                echo "<script>alert('Terdeteksi copy buku yang sama dipilih lebih dari satu kali!'); window.history.back();</script>";
                exit;
            }
            $semua_copy[] = $no_copy;
        }
    }

    $no_peminjaman = generateNoPeminjaman($conn);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO peminjaman (no_peminjaman, tgl_peminjaman, tgl_harus_kembali, id_anggota) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $no_peminjaman, $tgl_pinjam, $tgl_kembali, $id_anggota);
        $stmt->execute();

        $cek_stmt = $conn->prepare("SELECT status_buku FROM copy_buku WHERE no_copy_buku = ? AND id_buku = ?");
        $cek_aktif_stmt = $conn->prepare("
            SELECT 1 FROM dapat d
            LEFT JOIN pengembalian p ON d.no_peminjaman = p.no_peminjaman
            LEFT JOIN bisa b ON b.no_copy_buku = d.no_copy_buku AND b.no_pengembalian = p.no_pengembalian
            WHERE d.no_copy_buku = ? AND b.no_copy_buku IS NULL
        ");
        $update_stmt = $conn->prepare("UPDATE copy_buku SET status_buku = 'dipinjam' WHERE no_copy_buku = ?");
        $insert_stmt = $conn->prepare("INSERT INTO dapat (no_peminjaman, no_copy_buku) VALUES (?, ?)");

        foreach ($_POST['copy_buku'] as $id_buku => $copies) {
            foreach ($copies as $no_copy) {
                // Cek apakah status_buku masih tersedia
                $cek_stmt->bind_param("ss", $no_copy, $id_buku);
                $cek_stmt->execute();
                $result = $cek_stmt->get_result();
                $data = $result->fetch_assoc();

                if (!$data || $data['status_buku'] != 'tersedia') {
                    throw new Exception("Copy buku $no_copy untuk buku $id_buku sudah tidak tersedia.");
                }

                // Cek apakah copy buku masih sedang dipinjam (belum dikembalikan di tabel bisa)
                $cek_aktif_stmt->bind_param("s", $no_copy);
                $cek_aktif_stmt->execute();
                $result_aktif = $cek_aktif_stmt->get_result();
                if ($result_aktif->num_rows > 0) {
                    throw new Exception("Copy buku $no_copy masih sedang dipinjam dan belum dikembalikan!");
                }

                // Update status dan simpan
                $update_stmt->bind_param("s", $no_copy);
                $update_stmt->execute();

                $insert_stmt->bind_param("ss", $no_peminjaman, $no_copy);
                $insert_stmt->execute();
            }
        }

        $conn->commit();
        echo "<script>alert('Peminjaman berhasil disimpan!'); window.location.href='admin.php?page=perpus_utama&panggil=peminjaman.php';</script>";
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Gagal menyimpan data: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }
}

// Ambil data anggota
$anggota_result = $conn->query("SELECT id_anggota, nm_anggota FROM anggota ORDER BY nm_anggota ASC");

// Ambil data buku dan stok tersedia
$buku_result = $conn->query("SELECT buku.id_buku, judul_buku,
    (SELECT COUNT(*) FROM copy_buku WHERE id_buku = buku.id_buku AND status_buku = 'tersedia') AS stok
FROM buku ORDER BY judul_buku ASC");

$bookData = [];
while ($b = $buku_result->fetch_assoc()) {
    $bookData[$b['id_buku']] = [
        'judul' => $b['judul_buku'],
        'stok' => (int)$b['stok']
    ];
}

// Ambil data copy buku tersedia per buku
$copyBukuAll = [];
foreach ($bookData as $id_buku => $data) {
    $copyResult = $conn->query("SELECT no_copy_buku FROM copy_buku WHERE id_buku = '$id_buku' AND status_buku = 'tersedia' ORDER BY no_copy_buku ASC");
    $copies = [];
    while ($c = $copyResult->fetch_assoc()) {
        $copies[] = $c['no_copy_buku'];
    }
    $copyBukuAll[$id_buku] = $copies;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Tambah Peminjaman Buku</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
<style>
    /* Agar checkbox dan label rapih */
    .copy-buku-container label {
        cursor: pointer;
    }
</style>
</head>
<body class="p-3">

<h3 style="text-align: center;">Tambah Peminjaman Buku</h3>

<form method="POST" class="container">

<div class="mb-3" style="max-width: 200px;">
    <label class="form-label">Tanggal Pinjam</label>
    <input type="date" name="tgl_pinjam" id="tgl_pinjam" class="form-control form-control-sm" required />
</div>

<div class="mb-3" style="max-width: 200px;">
    <label class="form-label">Tanggal Kembali</label>
    <input type="date" name="tgl_kembali" id="tgl_kembali" class="form-control form-control-sm" required />
</div>


    <div class="mb-3 w-auto">
        <label class="form-label">Nama Anggota</label>
        <select name="id_anggota" class="form-select" required>
            <option value="">-- Pilih Anggota --</option>
            <?php while ($a = $anggota_result->fetch_assoc()) : ?>
                <option value="<?= htmlspecialchars($a['id_anggota']) ?>"><?= htmlspecialchars($a['nm_anggota']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3 bg-light p-3 rounded">
        <table class="table table-bordered" id="tabel_buku">
            <thead>
                <tr class="table-secondary text-center">
                    <th>No</th>
                    <th>ID Buku</th>
                    <th>Judul Buku</th>
                    <th>Copy Buku (tersedia)</th>
                    <th>Jumlah</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                <td class="text-center">1</td>
                <td>
                    <select class="form-select form-select-sm id-buku" required>
                        <option value="">PILIH</option>
                        <?php foreach ($bookData as $id => $data): ?>
                            <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($id) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="id_buku[]" class="id-buku-hidden" />
                </td>
                <td>
                    <select class="form-select form-select-sm judul-buku" required>
                        <option value="">PILIH</option>
                        <?php foreach ($bookData as $id => $data): ?>
                            <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($data['judul']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <div class="copy-buku-container" style="font-size: 0.85rem;"></div>
                </td>
                <td>
                    <input type="number" name="jumlah[]" class="form-control form-control-sm jumlah-buku" readonly value="0" min="0" required />
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm btn-hapus">-</button>
                </td>
            </tr>

            </tbody>
        </table>
        <button type="button" id="btn-tambah" class="btn btn-success btn-sm">Tambah Baris</button>
    </div>

    <button type="submit" class="btn btn-primary">Simpan Peminjaman</button>
    <a href="admin.php?page=perpus_utama&panggil=peminjaman.php" class="btn btn-secondary">Batal</a>
</form>

<script>
const bookData = <?= json_encode($bookData) ?>;
const copyBukuData = <?= json_encode($copyBukuAll) ?>;
const tableBody = document.querySelector("#tabel_buku tbody");
const btnTambah = document.getElementById("btn-tambah");

const tglPinjam = document.getElementById('tgl_pinjam');
const tglKembali = document.getElementById('tgl_kembali');

tglPinjam.addEventListener('change', () => {
    tglKembali.min = tglPinjam.value;
});

function renderCopyCheckboxes(id_buku, container) {
    container.innerHTML = '';
    if (!id_buku || !copyBukuData[id_buku] || copyBukuData[id_buku].length === 0) {
        container.innerHTML = '<small><i>Tidak ada copy buku tersedia</i></small>';
        return;
    }

    const copies = copyBukuData[id_buku];
    const wrapper = document.createElement('div');
    wrapper.style.display = 'flex';
    wrapper.style.gap = '12px';

    const colCount = 3;
    const rowCount = Math.ceil(copies.length / colCount);
    let grid = Array(rowCount).fill(null).map(() => Array(colCount).fill(null));

    copies.forEach((copy, i) => {
        const row = i % rowCount;
        const col = Math.floor(i / rowCount);
        grid[row][col] = copy;
    });

    for (let col = 0; col < colCount; col++) {
        const colDiv = document.createElement('div');
        colDiv.style.display = 'flex';
        colDiv.style.flexDirection = 'column';
        colDiv.style.gap = '4px';

        for (let row = 0; row < rowCount; row++) {
            const copy = grid[row][col];
            if (!copy) continue;

            const label = document.createElement('label');
            label.style.userSelect = 'none';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = `copy_buku[${id_buku}][]`;
            checkbox.value = copy;
            checkbox.addEventListener('change', () => {
                updateJumlahByCheckbox(container.closest('tr'));
            });

            label.appendChild(checkbox);
            label.appendChild(document.createTextNode(' ' + copy));
            colDiv.appendChild(label);
        }

        wrapper.appendChild(colDiv);
    }

    container.appendChild(wrapper);
}

function updateJumlahByCheckbox(tr) {
    const container = tr.querySelector('.copy-buku-container');
    const jumlahInput = tr.querySelector('.jumlah-buku');
    const checkedCount = container.querySelectorAll('input[type=checkbox]:checked').length;
    jumlahInput.value = checkedCount;
}

function buatBarisBaru(nomor) {
    const row = document.createElement("tr");
    row.innerHTML = `
        <td class="text-center">${nomor}</td>
        <td>
            <select class="form-select form-select-sm id-buku" required>
                <option value="">PILIH</option>
                ${Object.entries(bookData).map(([id]) => `<option value="${id}">${id}</option>`).join('')}
            </select>
            <input type="hidden" name="id_buku[]" class="id-buku-hidden" />
        </td>
        <td>
            <select class="form-select form-select-sm judul-buku" required>
                <option value="">PILIH</option>
                ${Object.entries(bookData).map(([id, data]) => `<option value="${id}">${data.judul}</option>`).join('')}
            </select>
        </td>
        <td>
            <div class="copy-buku-container" style="font-size: 0.85rem;"></div>
        </td>
        <td>
            <input type="number" name="jumlah[]" class="form-control form-control-sm jumlah-buku" readonly value="0" min="0" required />
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm btn-hapus">-</button>
        </td>
    `;
    return row;
}

function updateNomor() {
    [...tableBody.rows].forEach((row, i) => {
        row.cells[0].textContent = i + 1;
    });
}

function updateDropdownOptions() {
    const allRows = [...document.querySelectorAll("#tabel_buku tbody tr")];
    const selectedIds = allRows.map(row => row.querySelector(".id-buku-hidden")?.value).filter(Boolean);

    allRows.forEach(row => {
        const idSelect = row.querySelector(".id-buku");
        const judulSelect = row.querySelector(".judul-buku");
        const currentId = row.querySelector(".id-buku-hidden")?.value;

        const options = Object.entries(bookData).filter(([id]) => !selectedIds.includes(id) || id === currentId);

        idSelect.innerHTML = `<option value="">PILIH</option>` + options.map(([id]) =>
            `<option value="${id}" ${id === currentId ? "selected" : ""}>${id}</option>`
        ).join('');

        judulSelect.innerHTML = `<option value="">PILIH</option>` + options.map(([id, data]) =>
            `<option value="${id}" ${id === currentId ? "selected" : ""}>${data.judul}</option>`
        ).join('');
    });
}

btnTambah.addEventListener("click", () => {
    const rowCount = tableBody.rows.length + 1;
    const row = buatBarisBaru(rowCount);
    tableBody.appendChild(row);
    updateDropdownOptions();
});

tableBody.addEventListener("change", (e) => {
    const row = e.target.closest("tr");
    if (!row) return;

    const idSelect = row.querySelector(".id-buku");
    const judulSelect = row.querySelector(".judul-buku");
    const idHidden = row.querySelector(".id-buku-hidden");
    const copyContainer = row.querySelector(".copy-buku-container");
    const jumlahInput = row.querySelector(".jumlah-buku");

    if (e.target.classList.contains("judul-buku")) {
        const selectedId = judulSelect.value;
        idSelect.value = selectedId;
        idHidden.value = selectedId;
        renderCopyCheckboxes(selectedId, copyContainer);
        jumlahInput.value = 0;
        updateDropdownOptions();
    } else if (e.target.classList.contains("id-buku")) {
        const selectedId = idSelect.value;
        judulSelect.value = selectedId;
        idHidden.value = selectedId;
        renderCopyCheckboxes(selectedId, copyContainer);
        jumlahInput.value = 0;
        updateDropdownOptions();
    }
});

tableBody.addEventListener("click", (e) => {
    if (e.target.classList.contains("btn-hapus")) {
        e.target.closest("tr").remove();
        updateNomor();
        updateDropdownOptions();
    }
});
</script>
