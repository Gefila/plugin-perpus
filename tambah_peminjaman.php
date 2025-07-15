<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Fungsi generate nomor peminjaman
function generateNoPeminjaman($conn) {
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(no_peminjaman, 3) AS UNSIGNED)) AS max_num FROM peminjaman");
    $row = $result->fetch_assoc();
    $next = (int)$row['max_num'] + 1;
    return "PJ" . $next;
}

// Inisialisasi variabel
$isEdit = false;
$no_peminjaman = '';
$tgl_pinjam = date('Y-m-d');
$tgl_kembali = '';
$id_anggota = '';
$detail_peminjaman = [];
$error = '';
$success = '';

// Cek mode edit
if (isset($_GET['edit'])) {
    $isEdit = true;
    $no_peminjaman = $conn->real_escape_string($_GET['edit']);
    
    // Ambil data peminjaman utama
    $peminjaman_result = $conn->query("SELECT * FROM peminjaman WHERE no_peminjaman = '$no_peminjaman'");
    if ($peminjaman_result && $peminjaman_result->num_rows > 0) {
        $peminjaman = $peminjaman_result->fetch_assoc();
        $tgl_pinjam = $peminjaman['tgl_peminjaman'];
        $tgl_kembali = $peminjaman['tgl_harus_kembali'];
        $id_anggota = $peminjaman['id_anggota'];
        
        // Ambil detail buku yang dipinjam
        $detail_result = $conn->query("
            SELECT cb.no_copy_buku, cb.id_buku, b.judul_buku
            FROM copy_buku cb
            JOIN buku b ON cb.id_buku = b.id_buku
            WHERE cb.status_buku = 'tersedia'
        ");
        
        while ($detail = $detail_result->fetch_assoc()) {
            $detail_peminjaman[$detail['id_buku']][] = $detail['no_copy_buku'];
        }
    }
}

// Proses simpan data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tgl_pinjam = $_POST['tgl_pinjam'];
    $tgl_kembali = $_POST['tgl_kembali'];
    $id_anggota = $_POST['id_anggota'];
    if (isset($_POST['no_peminjaman']) && !empty($_POST['no_peminjaman'])) {
    $isEdit = true;
    $no_peminjaman = $_POST['no_peminjaman'];
} else {
    $isEdit = false;
    $no_peminjaman = generateNoPeminjaman($conn); // <-- PENTING!
}


    // Validasi tanggal
    if ($tgl_kembali <= $tgl_pinjam) {
        $error = "Tanggal kembali harus lebih besar dari tanggal pinjam!";
    }

    // Validasi buku yang dipilih
   if (!$isEdit && (!isset($_POST['copy_buku']) || !is_array($_POST['copy_buku']))) {
    $error = "Pilih minimal satu copy buku yang tersedia!";
}

    // Validasi duplikat copy buku
   $semua_copy = [];
if (isset($_POST['copy_buku']) && is_array($_POST['copy_buku'])) {
    foreach ($_POST['copy_buku'] as $copies) {
        foreach ($copies as $no_copy) {
            if (in_array($no_copy, $semua_copy)) {
                $error = "Terdeteksi copy buku yang sama dipilih lebih dari satu kali!";
                break 2;
            }
            $semua_copy[] = $no_copy;
        }
    }
}

    if (empty($error)) {
        $conn->begin_transaction();
        try {
            if ($isEdit) {
                $no_peminjaman = $_POST['no_peminjaman'];
                
                // Update data peminjaman utama
                $stmt = $conn->prepare("UPDATE peminjaman SET 
                    tgl_peminjaman = ?, 
                    tgl_harus_kembali = ?, 
                    id_anggota = ? 
                    WHERE no_peminjaman = ?");
                $stmt->bind_param("ssss", $tgl_pinjam, $tgl_kembali, $id_anggota, $no_peminjaman);
            } else {
                $no_peminjaman = generateNoPeminjaman($conn);
                
                // Insert data peminjaman baru
                $stmt = $conn->prepare("INSERT INTO peminjaman 
                    (no_peminjaman, tgl_peminjaman, tgl_harus_kembali, id_anggota) 
                    VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $no_peminjaman, $tgl_pinjam, $tgl_kembali, $id_anggota);
            }
            
            if (!$stmt->execute()) {
                    throw new Exception("Gagal simpan peminjaman: " . $stmt->error);
                }

            
            // Untuk mode edit, hapus dulu semua detail yang ada
            if ($isEdit) {
                // Kembalikan status copy buku yang sebelumnya dipinjam
                $update_old = $conn->prepare("UPDATE copy_buku cb
                    JOIN dapat d ON cb.no_copy_buku = d.no_copy_buku
                    SET cb.status_buku = 'tersedia'
                    WHERE d.no_peminjaman = ?");
                $update_old->bind_param("s", $no_peminjaman);
                if (!$update_old->execute()) throw new Exception("Gagal update status buku lama: " . $update_old->error);
                $update_old->close();
                
                // Hapus detail peminjaman lama
                $delete_old = $conn->prepare("DELETE FROM dapat WHERE no_peminjaman = ?");
                $delete_old->bind_param("s", $no_peminjaman);
                if (!$delete_old->execute()) throw new Exception("Gagal hapus detail lama: " . $delete_old->error);
                $delete_old->close();
            }
            
            // Proses penyimpanan detail buku
            $cek_stmt = $conn->prepare("SELECT status_buku FROM copy_buku WHERE no_copy_buku = ? AND id_buku = ?");
            $cek_aktif_stmt = $conn->prepare("
                SELECT 1 FROM dapat d
                WHERE d.no_copy_buku = ? AND NOT EXISTS (
                    SELECT 1 FROM bisa bs
                    JOIN pengembalian p ON bs.no_pengembalian = p.no_pengembalian
                    WHERE bs.no_copy_buku = d.no_copy_buku AND p.no_peminjaman = d.no_peminjaman
                )
                LIMIT 1
            ");
            $update_stmt = $conn->prepare("UPDATE copy_buku SET status_buku = 'dipinjam' WHERE no_copy_buku = ?");
            $insert_stmt = $conn->prepare("INSERT INTO dapat (no_peminjaman, no_copy_buku) VALUES (?, ?)");

            if (isset($_POST['copy_buku']) && is_array($_POST['copy_buku'])) {
                foreach ($_POST['copy_buku'] as $id_buku => $copies) {
                     foreach ($copies as $no_copy) {
                    // cek status buku
                    $cek_stmt->bind_param("ss", $no_copy, $id_buku);
                    $cek_stmt->execute();
                    $result = $cek_stmt->get_result();
                    $data = $result->fetch_assoc();

                    if (!$data || $data['status_buku'] != 'tersedia') {
                        throw new Exception("Copy buku $no_copy untuk buku $id_buku sudah tidak tersedia.");
                    }

                    // cek peminjaman aktif
                    $cek_aktif_stmt->bind_param("s", $no_copy);
                    $cek_aktif_stmt->execute();
                    $result_aktif = $cek_aktif_stmt->get_result();
                    if ($result_aktif->num_rows > 0) {
                        throw new Exception("Copy buku $no_copy masih sedang dipinjam dan belum dikembalikan!");
                    }

                    // update status & insert dapat
                    $update_stmt->bind_param("s", $no_copy);
                    if (!$update_stmt->execute()) throw new Exception("Gagal update status buku: " . $update_stmt->error);
                    if (empty($no_peminjaman)) {
                    throw new Exception("Nomor peminjaman kosong sebelum insert ke 'dapat'");
                    
                 }

                    $insert_stmt->bind_param("ss", $no_peminjaman, $no_copy);
                    if (!$insert_stmt->execute()) throw new Exception("Gagal simpan detail dapat: " . $insert_stmt->error);
                }
            }
            }

            $cek_stmt->close();
            $cek_aktif_stmt->close();
            $update_stmt->close();
            $insert_stmt->close();

            $conn->commit();
            $success = "Peminjaman berhasil " . ($isEdit ? 'diupdate' : 'disimpan') . "!";
            echo "<script>window.location.href='admin.php?page=perpus_utama&panggil=peminjaman.php';</script>";
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}

if ($isEdit && (!isset($_POST['copy_buku']) || !is_array($_POST['copy_buku']))) {
    // Ambil ulang detail peminjaman dari database agar ditampilkan ulang di form
    $detail_peminjaman = [];

    $detail_result = $conn->query("
        SELECT d.no_copy_buku, cb.id_buku 
        FROM dapat d
        JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
        WHERE d.no_peminjaman = '$no_peminjaman'
    ");

    while ($detail = $detail_result->fetch_assoc()) {
        $detail_peminjaman[$detail['id_buku']][] = $detail['no_copy_buku'];
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
$copyResult = $conn->query("
    SELECT cb.no_copy_buku 
    FROM copy_buku cb
    WHERE cb.id_buku = '$id_buku'
    AND NOT EXISTS (
        SELECT 1 FROM dapat d
        WHERE d.no_copy_buku = cb.no_copy_buku
        AND NOT EXISTS (
            SELECT 1 FROM bisa bs
            JOIN pengembalian p ON p.no_pengembalian = bs.no_pengembalian
            WHERE bs.no_copy_buku = d.no_copy_buku
            AND p.no_peminjaman = d.no_peminjaman
        )
    )
    ORDER BY cb.no_copy_buku ASC
");

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
    <title><?= $isEdit ? 'Edit' : 'Tambah' ?> Peminjaman</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <style>
        .perpus-form-container {
            max-width: 900px;
            margin: 2rem auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .perpus-form-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .perpus-form-header h2 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .perpus-form-body {
            padding: 0 2rem 2rem;
        }
        
        .perpus-input-group {
            margin-bottom: 1.5rem;
        }
        
        .perpus-input-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #4e73df;
        }
        
        .perpus-input-wrapper {
            display: flex;
            border: 1px solid #d1d3e2;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .perpus-input-wrapper:focus-within {
            border-color: #4e73df;
            box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.25);
        }
        
        .perpus-input-icon {
            padding: 0.75rem 1rem;
            background-color: #f8f9fc;
            color: #4e73df;
            display: flex;
            align-items: center;
            border-right: 1px solid #d1d3e2;
        }
        
        .perpus-input-field {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            outline: none;
            background-color: white;
        }
        
        .perpus-select-wrapper {
            position: relative;
        }
        
        .perpus-select-wrapper select {
            appearance: none;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 1px solid #d1d3e2;
            border-radius: 8px;
            width: 100%;
            background-color: white;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
            transition: all 0.3s ease;
        }
        
        .perpus-select-wrapper select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.25);
            outline: none;
        }
        
        .perpus-btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .perpus-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .perpus-btn-primary {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
        }
        
        .perpus-btn-primary:hover {
            background: linear-gradient(135deg, #3e63cf 0%, #123aae 100%);
            transform: translateY(-2px);
        }
        
        .perpus-btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        
        .perpus-btn-warning:hover {
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
            transform: translateY(-2px);
        }
        
        .perpus-btn-secondary {
            background: #f8f9fc;
            color: #4e73df;
            border: 1px solid #d1d3e2;
        }
        
        .perpus-btn-secondary:hover {
            background: #e2e6ea;
            color: #4e73df;
        }
        
        .perpus-alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .perpus-alert-success {
            background-color: #d1f3e6;
            color: #1cc88a;
            border-left: 4px solid #1cc88a;
        }
        
        .perpus-alert-danger {
            background-color: #fadbd8;
            color: #e74a3b;
            border-left: 4px solid #e74a3b;
        }
        
        .copy-buku-container label {
            cursor: pointer;
            user-select: none;
            margin-right: 15px;
        }
        
        .readonly-blue {
            background-color: #e9f0f8;
            color: #2c3e50;
        }
        
        @media (max-width: 768px) {
            .perpus-form-container {
                margin: 1rem;
            }
            
            .perpus-form-body {
                padding: 0 1.5rem 1.5rem;
            }
            
            .perpus-btn-group {
                flex-direction: column;
            }
            
            .perpus-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body class="p-3">
    <div class="perpus-form-container">
        <div class="perpus-form-header">
            <h2>
                <i class="fas fa-book-open"></i>
                <?= $isEdit ? 'Edit Peminjaman Buku' : 'Tambah Peminjaman Buku' ?>
            </h2>
        </div>
        
        <div class="perpus-form-body">
            <?php if ($error): ?>
                <div class="perpus-alert perpus-alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="perpus-alert perpus-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $success ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="formPeminjaman">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="no_peminjaman" value="<?= htmlspecialchars($no_peminjaman) ?>">
                <?php endif; ?>
                
                <div class="perpus-input-group">
                    <label for="id_anggota">Nama Anggota</label>
                    <div class="perpus-select-wrapper">
                        <select name="id_anggota" required>
                            <option value="">-- Pilih Anggota --</option>
                            <?php 
                            $anggota_result->data_seek(0); // Reset pointer result
                            while ($a = $anggota_result->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($a['id_anggota']) ?>" 
                                    <?= ($id_anggota == $a['id_anggota']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['nm_anggota']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="perpus-input-group">
                            <label for="tgl_pinjam">Tanggal Pinjam</label>
                            <div class="perpus-input-wrapper">
                                <span class="perpus-input-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </span>
                                <input type="date" name="tgl_pinjam" id="tgl_pinjam" class="perpus-input-field" 
                                       value="<?= htmlspecialchars($tgl_pinjam) ?>" required />
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="perpus-input-group">
                            <label for="tgl_kembali">Tanggal Kembali</label>
                            <div class="perpus-input-wrapper">
                                <span class="perpus-input-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </span>
                                <input type="date" name="tgl_kembali" id="tgl_kembali" class="perpus-input-field" 
                                       value="<?= htmlspecialchars($tgl_kembali) ?>" required />
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="perpus-input-group bg-light p-3 rounded">
                    <h5 class="mb-3">Daftar Buku yang Dipinjam</h5>
                    <table class="table table-bordered" id="tabel_buku">
                        <thead>
                            <tr class="table-secondary text-center">
                                <th width="5%">No</th>
                                <th width="15%">ID Buku</th>
                                <th width="30%">Judul Buku</th>
                                <th width="30%">Copy Buku (tersedia)</th>
                                <th width="10%">Jumlah</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$isEdit || empty($detail_peminjaman)): ?>
                                <tr>
                                    <td class="text-center">1</td>
                                    <td>
                                        <div class="perpus-select-wrapper">
                                            <select class="id-buku" required>
                                                <option value="">PILIH</option>
                                                <?php foreach ($bookData as $id => $data): ?>
                                                    <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($id) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <input type="hidden" name="id_buku[]" class="id-buku-hidden" />
                                    </td>
                                    <td>
                                        <div class="perpus-select-wrapper">
                                            <select class="judul-buku" required>
                                                <option value="">PILIH</option>
                                                <?php foreach ($bookData as $id => $data): ?>
                                                    <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($data['judul']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="copy-buku-container" style="font-size: 0.85rem;"></div>
                                    </td>
                                    <td>
                                        <input type="number" name="jumlah[]" class="form-control form-control-sm jumlah-buku readonly-blue" 
                                               readonly value="0" min="0" required />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm btn-hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <button type="button" id="btn-tambah" class="btn btn-success btn-sm">
                        <i class="fa-solid fa-plus me-1"></i> Tambah Baris
                    </button>
                </div>
                
                <div class="perpus-btn-group">
                    <a href="admin.php?page=perpus_utama&panggil=peminjaman.php" class="perpus-btn perpus-btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <button type="submit" class="perpus-btn <?= $isEdit ? 'perpus-btn-warning' : 'perpus-btn-primary' ?>">
                        <i class="fas fa-save"></i> <?= $isEdit ? 'Update' : 'Simpan' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const bookData = <?= json_encode($bookData) ?>;
    const copyBukuData = <?= json_encode($copyBukuAll) ?>;
    const tableBody = document.querySelector("#tabel_buku tbody");
    const btnTambah = document.getElementById("btn-tambah");
    const tglPinjam = document.getElementById('tgl_pinjam');
    const tglKembali = document.getElementById('tgl_kembali');
    const isEditMode = <?= $isEdit ? 'true' : 'false' ?>;
    const detailPeminjaman = <?= json_encode($detail_peminjaman) ?>;

    // Set tanggal minimal untuk tanggal kembali
    tglPinjam.addEventListener('change', () => {
        tglKembali.min = tglPinjam.value;
    });

    // Fungsi untuk render checkbox copy buku
   function renderCopyCheckboxes(id_buku, container, selectedCopies = []) {
    container.innerHTML = '';
    const copies = copyBukuData[id_buku] || [];

    if (copies.length === 0 && selectedCopies.length === 0) {
        container.innerHTML = '<small><i>Tidak ada copy buku tersedia</i></small>';
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.style.display = 'flex';
    wrapper.style.flexWrap = 'wrap';
    wrapper.style.gap = '10px';

    const allCopies = new Set([...copies, ...selectedCopies]);

    allCopies.forEach(copy => {
        const label = document.createElement('label');
        label.style.display = 'flex';
        label.style.alignItems = 'center';
        label.style.gap = '5px';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = `copy_buku[${id_buku}][]`;
        checkbox.value = copy;
        checkbox.checked = selectedCopies.includes(copy); // centang jika sebelumnya dipilih

        checkbox.addEventListener('change', () => {
            updateJumlahByCheckbox(container.closest('tr'));
        });

        label.appendChild(checkbox);
        label.appendChild(document.createTextNode(copy));
        wrapper.appendChild(label);
    });

    container.appendChild(wrapper);
}



    // Update jumlah berdasarkan checkbox yang dicentang
    function updateJumlahByCheckbox(tr) {
        const container = tr.querySelector('.copy-buku-container');
        const jumlahInput = tr.querySelector('.jumlah-buku');
        const checkedCount = container.querySelectorAll('input[type=checkbox]:checked').length;
        jumlahInput.value = checkedCount;
    }

    // Fungsi untuk membuat baris baru
    function buatBarisBaru(nomor) {
        const row = document.createElement("tr");
        row.innerHTML = `
            <td class="text-center">${nomor}</td>
            <td>
                <div class="perpus-select-wrapper">
                    <select class="id-buku" required>
                        <option value="">PILIH</option>
                        ${Object.entries(bookData).map(([id]) => `<option value="${id}">${id}</option>`).join('')}
                    </select>
                </div>
                <input type="hidden" name="id_buku[]" class="id-buku-hidden" />
            </td>
            <td>
                <div class="perpus-select-wrapper">
                    <select class="judul-buku" required>
                        <option value="">PILIH</option>
                        ${Object.entries(bookData).map(([id, data]) => `<option value="${id}">${data.judul}</option>`).join('')}
                    </select>
                </div>
            </td>
            <td>
                <div class="copy-buku-container" style="font-size: 0.85rem;"></div>
            </td>
            <td>
                <input type="number" name="jumlah[]" class="form-control form-control-sm jumlah-buku readonly-blue" 
                       readonly value="0" min="0" required />
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm btn-hapus">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;
        return row;
    }

    // Update nomor urut baris
    function updateNomor() {
        [...tableBody.rows].forEach((row, i) => {
            row.cells[0].textContent = i + 1;
        });
    }

    // Update opsi dropdown berdasarkan buku yang sudah dipilih
    function updateDropdownOptions() {
        const allRows = [...document.querySelectorAll("#tabel_buku tbody tr")];
        const selectedIds = allRows.map(row => row.querySelector(".id-buku-hidden")?.value).filter(Boolean);

        allRows.forEach(row => {
            const idSelect = row.querySelector(".id-buku");
            const judulSelect = row.querySelector(".judul-buku");
            const currentId = row.querySelector(".id-buku-hidden")?.value;

            const options = Object.entries(bookData).filter(([id]) => 
                !selectedIds.includes(id) || id === currentId
            );

            idSelect.innerHTML = `<option value="">PILIH</option>` + options.map(([id]) =>
                `<option value="${id}" ${id === currentId ? "selected" : ""}>${id}</option>`
            ).join('');

            judulSelect.innerHTML = `<option value="">PILIH</option>` + options.map(([id, data]) =>
                `<option value="${id}" ${id === currentId ? "selected" : ""}>${data.judul}</option>`
            ).join('');
        });
    }

    // Event listener untuk tombol tambah baris
    btnTambah.addEventListener("click", () => {
        const rowCount = tableBody.rows.length + 1;
        const row = buatBarisBaru(rowCount);
        tableBody.appendChild(row);
        updateDropdownOptions();
    });

    // Event listener untuk perubahan pada tabel
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

    // Event listener untuk tombol hapus
    tableBody.addEventListener("click", (e) => {
        if (e.target.classList.contains("btn-hapus")) {
            if (tableBody.rows.length > 1) {
                e.target.closest("tr").remove();
                updateNomor();
                updateDropdownOptions();
            } else {
                alert("Minimal harus ada satu buku yang dipinjam!");
            }
        }
    });

   // Inisialisasi untuk mode edit
if (isEditMode && Object.keys(detailPeminjaman).length > 0) {
    document.addEventListener('DOMContentLoaded', function() {
        // Hapus baris default jika ada
        const defaultRow = document.querySelector("#tabel_buku tbody tr");
        if (defaultRow) defaultRow.remove();
        
        // ✅ Tambahkan baris untuk setiap buku yang dipinjam
        Object.entries(detailPeminjaman).forEach(([id_buku, copies], index) => {
            const row = buatBarisBaru(index + 1);
            tableBody.appendChild(row);

            // Set nilai dropdown
            row.querySelector('.id-buku').value = id_buku;
            row.querySelector('.judul-buku').value = id_buku;
            row.querySelector('.id-buku-hidden').value = id_buku;

            // Render copy buku dan centang yang sudah dipilih
            const container = row.querySelector('.copy-buku-container');
            renderCopyCheckboxes(id_buku, container, copies);

            // Update jumlah
            updateJumlahByCheckbox(row);
        });

        // Update nomor dan dropdown
        updateNomor();
        updateDropdownOptions();
    });
}

    </script>
</body>
</html>