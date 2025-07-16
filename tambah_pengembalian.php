<?php
// Koneksi database (sesuaikan dengan konfigurasi Anda)


function generateNoPengembalian($conn) {
    $r = $conn->query("SELECT MAX(CAST(SUBSTRING(no_pengembalian,3) AS UNSIGNED)) AS max_num FROM pengembalian");
    $row = $r->fetch_assoc();
    $n = ($row && $row['max_num']) ? (int)$row['max_num'] + 1 : 1;
    return "PG" . $n;
}

// Proses form submit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan'])) {
    $no_pengembalian = generateNoPengembalian($conn);
    $tgl_pengembalian = $_POST['tgl_pengembalian'] ?? '';
    $no_peminjaman = $_POST['no_peminjaman'] ?? '';
    $id_denda_tambahan = $_POST['id_denda_tambahan'] ?? null;
    $copy_buku = $_POST['no_copy_buku'] ?? [];

    if (empty($copy_buku)) {
        echo "<script>alert('Pilih minimal satu buku untuk dikembalikan!');history.back();</script>";
        exit;
    }
    if (!$tgl_pengembalian || !$no_peminjaman) {
        echo "<script>alert('Tanggal pengembalian dan nomor peminjaman harus diisi!');history.back();</script>";
        exit;
    }

    $conn->begin_transaction();

    try {
        // Insert pengembalian utama
        $stmt = $conn->prepare("INSERT INTO pengembalian(no_pengembalian, tgl_pengembalian, no_peminjaman) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $no_pengembalian, $tgl_pengembalian, $no_peminjaman);
        if (!$stmt->execute()) throw new Exception("Gagal simpan pengembalian: " . $stmt->error);
        $stmt->close();

        // Prepared statements di luar loop
        $cekSudahKembali = $conn->prepare("
            SELECT 1 FROM bisa bs
            JOIN pengembalian p ON bs.no_pengembalian = p.no_pengembalian
            WHERE bs.no_copy_buku = ? AND p.no_peminjaman = ?
        ");
        $cekDipinjamLain = $conn->prepare("
            SELECT 1 FROM dapat d
            WHERE d.no_copy_buku = ? AND d.no_peminjaman != ?
            AND NOT EXISTS (
                SELECT 1 FROM bisa bs
                JOIN pengembalian p ON bs.no_pengembalian = p.no_pengembalian
                WHERE bs.no_copy_buku = d.no_copy_buku AND p.no_peminjaman = d.no_peminjaman
            )
            LIMIT 1
        ");
        $cekStatus = $conn->prepare("SELECT status_buku FROM copy_buku WHERE no_copy_buku = ?");
        $insert_bisa = $conn->prepare("INSERT INTO bisa(no_pengembalian, no_copy_buku) VALUES (?, ?)");
        $update_copy = $conn->prepare("UPDATE copy_buku SET status_buku='tersedia' WHERE no_copy_buku = ?");

        foreach ($copy_buku as $cb) {
            // Cek apakah sudah dikembalikan
            $cekSudahKembali->bind_param("ss", $cb, $no_peminjaman);
            $cekSudahKembali->execute();
            $res = $cekSudahKembali->get_result();
            if ($res->num_rows > 0) throw new Exception("Copy buku $cb sudah dikembalikan sebelumnya.");

            // Cek apakah sedang dipinjam di peminjaman lain yang belum dikembalikan
            $cekDipinjamLain->bind_param("ss", $cb, $no_peminjaman);
            $cekDipinjamLain->execute();
            $res = $cekDipinjamLain->get_result();
            if ($res->num_rows > 0) throw new Exception("Copy buku $cb masih sedang dipinjam dan belum dikembalikan di transaksi lain!");

            // Cek status buku
            $cekStatus->bind_param("s", $cb);
            $cekStatus->execute();
            $resStatus = $cekStatus->get_result()->fetch_assoc();
            if ($resStatus['status_buku'] != 'dipinjam') throw new Exception("Copy buku $cb tidak dalam status dipinjam.");

            // Insert ke tabel bisa
            $insert_bisa->bind_param("ss", $no_pengembalian, $cb);
            if (!$insert_bisa->execute()) throw new Exception("Gagal simpan detail bisa: " . $insert_bisa->error);

            // Update status buku
            $update_copy->bind_param("s", $cb);
            if (!$update_copy->execute()) throw new Exception("Gagal update status buku: " . $update_copy->error);
        }

        // Tutup prepared statements cek dan insert
        $cekSudahKembali->close();
        $cekDipinjamLain->close();
        $cekStatus->close();
        $insert_bisa->close();
        $update_copy->close();

        // Hitung hari keterlambatan
        $no_peminjaman_esc = $conn->real_escape_string($no_peminjaman);
        $tgl_harus_kembali_row = $conn->query("SELECT tgl_harus_kembali FROM peminjaman WHERE no_peminjaman = '$no_peminjaman_esc'")->fetch_assoc();

        $hari_telat = 0;
        if ($tgl_harus_kembali_row) {
            $tgl_harus_kembali = new DateTime($tgl_harus_kembali_row['tgl_harus_kembali']);
            $tgl_kembali = new DateTime($tgl_pengembalian);

            if ($tgl_kembali > $tgl_harus_kembali) {
                $hari_telat = (int)$tgl_harus_kembali->diff($tgl_kembali)->days;
            }
        }

        // Ambil tarif denda keterlambatan
        $res_tarif = $conn->query("SELECT tarif_denda FROM denda WHERE id_denda = 'D1'");
        if ($res_tarif && $res_tarif->num_rows > 0) {
            $row_tarif = $res_tarif->fetch_assoc();
            $tarif_telat = isset($row_tarif['tarif_denda']) ? (int)$row_tarif['tarif_denda'] : 0;

            if (!empty($copy_buku) && $hari_telat > 0 && $tarif_telat > 0) {
                $jumlah_copy_telat = count($copy_buku);
                $subtotal_telat = $tarif_telat * $hari_telat * $jumlah_copy_telat;

                $stmt_telat = $conn->prepare("INSERT INTO pengembalian_denda(no_pengembalian, id_denda, jumlah_copy, subtotal) VALUES (?, 'D1', ?, ?)");
                $stmt_telat->bind_param("sii", $no_pengembalian, $jumlah_copy_telat, $subtotal_telat);
                if (!$stmt_telat->execute()) {
                    throw new Exception("Gagal simpan denda keterlambatan: " . $stmt_telat->error);
                }
                $stmt_telat->close();
            }
        } else {
            throw new Exception("Tarif denda D1 tidak ditemukan.");
        }

        // Simpan denda tambahan jika ada
        if (!empty($id_denda_tambahan)) {
            $stmt_tarif = $conn->prepare("SELECT tarif_denda FROM denda WHERE id_denda = ?");
            $stmt_tarif->bind_param("s", $id_denda_tambahan);
            $stmt_tarif->execute();
            $result_tarif_tambahan = $stmt_tarif->get_result();
            if ($result_tarif_tambahan && $result_tarif_tambahan->num_rows > 0) {
                $tarif_tambahan = (int)$result_tarif_tambahan->fetch_assoc()['tarif_denda'];
                $jumlah_copy = count($copy_buku);
                $subtotal_tambahan = $tarif_tambahan * $jumlah_copy;

                $stmt_denda = $conn->prepare("INSERT INTO pengembalian_denda(no_pengembalian, id_denda, jumlah_copy, subtotal) VALUES (?, ?, ?, ?)");
                $stmt_denda->bind_param("ssii", $no_pengembalian, $id_denda_tambahan, $jumlah_copy, $subtotal_tambahan);
                if (!$stmt_denda->execute()) throw new Exception("Gagal simpan denda tambahan: " . $stmt_denda->error);
                $stmt_denda->close();
            }
            $stmt_tarif->close();
        }

        $conn->commit();
        echo "<script>alert('Pengembalian berhasil disimpan!');location='admin.php?page=perpus_utama&panggil=pengembalian.php';</script>";
        exit;

    } catch (Exception $ex) {
        $conn->rollback();
        echo "<script>alert('Gagal menyimpan data: " . addslashes($ex->getMessage()) . "');history.back();</script>";
        exit;
    }
}

// === Bagian tampilan data ===
$anggota = $conn->query("SELECT * FROM anggota ORDER BY nm_anggota");
$anggotaData = [];
while ($row = $anggota->fetch_assoc()) {
    $anggotaData[] = $row;
}

$denda_opsional = $conn->query("SELECT * FROM denda WHERE id_denda != 'D1'");
$tarif_telat = 0;
$result_tarif_telat = $conn->query("SELECT tarif_denda FROM denda WHERE id_denda='D1'");
if ($result_tarif_telat && $result_tarif_telat->num_rows > 0) {
    $tarif_telat = (int)$result_tarif_telat->fetch_assoc()['tarif_denda'];
}

$peminjaman = $conn->query("
    SELECT p.no_peminjaman, p.id_anggota, p.tgl_peminjaman, p.tgl_harus_kembali
    FROM peminjaman p
    WHERE EXISTS (
        SELECT 1 FROM dapat d
        WHERE d.no_peminjaman = p.no_peminjaman
        AND NOT EXISTS (
            SELECT 1 FROM bisa bs
            JOIN pengembalian pg ON bs.no_pengembalian = pg.no_pengembalian
            WHERE bs.no_copy_buku = d.no_copy_buku AND pg.no_peminjaman = d.no_peminjaman
        )
    )
");

$detail_buku = $conn->query("
    SELECT d.no_peminjaman, d.no_copy_buku, b.id_buku, b.judul_buku
    FROM dapat d
    JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
    JOIN buku b ON cb.id_buku = b.id_buku
    WHERE cb.status_buku = 'dipinjam'
    AND NOT EXISTS (
        SELECT 1 FROM bisa bs
        JOIN pengembalian p ON bs.no_pengembalian = p.no_pengembalian
        WHERE bs.no_copy_buku = d.no_copy_buku AND p.no_peminjaman = d.no_peminjaman
    )
");

$peminjamanData = [];
while ($row = $peminjaman->fetch_assoc()) {
    $peminjamanData[$row['id_anggota']][] = $row;
}

$detailBuku = [];
while ($row = $detail_buku->fetch_assoc()) {
    $detailBuku[$row['no_peminjaman']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<title>Tambah Pengembalian Buku</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<!-- FontAwesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

<style>
/* Main Form Container */
.perpus-form-container {
    max-width: 900px;
    margin: 2rem auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

/* Form Header */
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

/* Form Body */
.perpus-form-body {
    padding: 0 2rem 2rem;
}

/* Input Groups */
.perpus-input-group {
    margin-bottom: 1.5rem;
    position: relative;
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

.perpus-input-field:focus {
    box-shadow: none;
}

/* Select Styles */
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

/* Button Styles */
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

.perpus-btn-secondary {
    background: #f8f9fc;
    color: #4e73df;
    border: 1px solid #d1d3e2;
}

.perpus-btn-secondary:hover {
    background: #e2e6ea;
    color: #4e73df;
}

/* Alert Messages */
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

/* List Group for Books */
.perpus-list-group {
    border-radius: 8px;
    overflow: hidden;
}

.perpus-list-group-item {
    padding: 0.75rem 1.25rem;
    border: 1px solid #e3e6f0;
    background-color: white;
    display: flex;
    align-items: center;
}

.perpus-list-group-item label {
    cursor: pointer;
    margin-bottom: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.perpus-list-group-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #4e73df;
}

/* Readonly Inputs */
.readonly-blue {
    background-color: #f8f9fc;
    color: #4a5568;
    border: 1px solid #d1d3e2;
}

/* Status Display */
.status-display {
    font-size: 1.1rem;
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 8px;
}

.status-tepat {
    background-color: #d1f3e6;
    color: #1cc88a;
}

.status-telat {
    background-color: #fadbd8;
    color: #e74a3b;
}

/* Responsive Adjustments */
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
    
    .perpus-list-group-item {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
</head>

<body class="p-3">

<div class="perpus-form-container">
    <div class="perpus-form-header">
        <h2>
            <i class="fas fa-book"></i>
            Tambah Pengembalian Buku
        </h2>
    </div>
    
    <div class="perpus-form-body">
        <form method="POST" id="formPengembalian">
            <!-- Anggota -->
            <div class="perpus-input-group">
                <label for="anggotaSelect">Anggota</label>
                <div class="perpus-select-wrapper">
                    <select name="id_anggota" id="anggotaSelect" class="form-select" onchange="filterPeminjaman()" required>
                        <option value="">-- Pilih Anggota --</option>
                        <?php foreach ($anggotaData as $a): ?>
                            <option value="<?= htmlspecialchars($a['id_anggota']) ?>">
                                <?= htmlspecialchars($a['id_anggota'] . " - " . $a['nm_anggota']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Nomor Peminjaman -->
            <div class="perpus-input-group">
                <label for="peminjamanSelect">Nomor Peminjaman</label>
                <div class="perpus-select-wrapper">
                    <select name="no_peminjaman" id="peminjamanSelect" class="form-select" onchange="updateInfo()" required>
                        <option value="">-- Pilih Nomor Peminjaman --</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <!-- Tanggal Harus Kembali -->
                <div class="col-md-6">
                    <div class="perpus-input-group">
                        <label for="tgl_harus_kembali_display">Tanggal Harus Kembali</label>
                        <div class="perpus-input-wrapper">
                            <span class="perpus-input-icon">
                                <i class="fas fa-calendar-day"></i>
                            </span>
                            <input type="text" id="tgl_harus_kembali_display" class="perpus-input-field readonly-blue" readonly />
                        </div>
                    </div>
                </div>
                
                <!-- Tanggal Pengembalian -->
                <div class="col-md-6">
                    <div class="perpus-input-group">
                        <label for="tglPengembalian">Tanggal Pengembalian</label>
                        <div class="perpus-input-wrapper">
                            <span class="perpus-input-icon">
                                <i class="fas fa-calendar-check"></i>
                            </span>
                            <input type="date" name="tgl_pengembalian" id="tglPengembalian" class="perpus-input-field" onchange="updateDenda()" required />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Pengembalian -->
            <div class="perpus-input-group">
                <label>Status Pengembalian</label>
                <div id="status_pengembalian" class="status-display"></div>
            </div>

            <!-- Denda Telat -->
            <div class="perpus-input-group">
                <label for="denda_telat_display">Denda Telat (per hari × per buku)</label>
                <div class="perpus-input-wrapper">
                    <span class="perpus-input-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                    <input type="text" id="denda_telat_display" class="perpus-input-field readonly-blue" readonly />
                </div>
            </div>

            <!-- Denda Tambahan -->
            <div class="perpus-input-group">
                <label for="id_denda_tambahan">Denda Tambahan (per buku)</label>
                <div class="perpus-select-wrapper">
                    <select name="id_denda_tambahan" id="id_denda_tambahan" class="form-select" onchange="updateDenda()">
                        <option value="">-- None --</option>
                        <?php
                            $denda_opsional->data_seek(0);
                            while ($d = $denda_opsional->fetch_assoc()):
                        ?>
                            <option value="<?= htmlspecialchars($d['id_denda']) ?>">
                                <?= htmlspecialchars($d['alasan_denda']) ?> - Rp<?= number_format($d['tarif_denda'], 0, ',', '.') ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- Total Denda -->
            <div class="perpus-input-group">
                <label for="total_denda_display">Total Denda</label>
                <div class="perpus-input-wrapper">
                    <span class="perpus-input-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </span>
                    <input type="text" id="total_denda_display" class="perpus-input-field readonly-blue fw-bold" readonly />
                    <input type="hidden" name="tarif_denda" id="tarif_denda" />
                </div>
            </div>

            <!-- Buku Dikembalikan -->
            <div class="perpus-input-group">
                <label>Buku Dikembalikan</label>
                <div id="tabelBuku" class="perpus-list-group"></div>
            </div>

            <!-- Tombol Simpan -->
            <div class="perpus-btn-group">
                <a href="admin.php?page=perpus_utama&panggil=pengembalian.php" class="perpus-btn perpus-btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
                <button name="simpan" class="perpus-btn perpus-btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const dataPeminjaman = <?= json_encode($peminjamanData) ?>;
const dataBuku = <?= json_encode($detailBuku) ?>;
const tarifPerHari = <?= $tarif_telat ?>;

function filterPeminjaman() {
  const anggota = document.getElementById('anggotaSelect').value;
  const select = document.getElementById('peminjamanSelect');
  select.innerHTML = '<option value="">-- Pilih Nomor Peminjaman --</option>';
  if (anggota in dataPeminjaman) {
    dataPeminjaman[anggota].forEach(p => {
      select.innerHTML += `<option value="${p.no_peminjaman}">${p.no_peminjaman}</option>`;
    });
  }
  document.getElementById('tgl_harus_kembali_display').value = '';
  document.getElementById('tabelBuku').innerHTML = '';
  document.getElementById('status_pengembalian').innerHTML = '';
  document.getElementById('denda_telat_display').value = '';
  document.getElementById('total_denda_display').value = '';
  document.getElementById('tarif_denda').value = '';
  document.getElementById('id_denda_tambahan').value = '';
  document.getElementById('tglPengembalian').value = '';
}

function updateInfo() {
  const no = document.getElementById('peminjamanSelect').value;
  const buku = dataBuku[no] || [];

  let html = '<ul class="perpus-list-group">';
  buku.forEach(b => {
    html += `<li class="perpus-list-group-item">
      <label><input type="checkbox" name="no_copy_buku[]" value="${b.no_copy_buku}" onchange="updateDenda()"> 
      ${b.id_buku} - ${b.judul_buku} (Copy: ${b.no_copy_buku})</label>
    </li>`;
  });
  html += '</ul>';
  document.getElementById('tabelBuku').innerHTML = html;

  // Tampilkan tanggal harus kembali
  let tglKembali = '';
  for (const anggota in dataPeminjaman) {
    const data = dataPeminjaman[anggota].find(p => p.no_peminjaman === no);
    if (data) {
      tglKembali = data.tgl_harus_kembali;
      break;
    }
  }

  if (tglKembali) {
    const date = new Date(tglKembali);
    const formatted = date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    document.getElementById('tgl_harus_kembali_display').value = formatted;
  } else {
    document.getElementById('tgl_harus_kembali_display').value = '';
  }

  updateDenda();
}

function updateDenda() {
  const tglValue = document.getElementById('tglPengembalian').value;
  if (!tglValue) {
    resetDenda();
    return;
  }
  const tgl = new Date(tglValue);
  const no = document.getElementById('peminjamanSelect').value;

  if (!no) {
    resetDenda();
    return;
  }

  let kembali = null;
  for (const anggota in dataPeminjaman) {
    const data = dataPeminjaman[anggota].find(p => p.no_peminjaman === no);
    if (data) {
      kembali = new Date(data.tgl_harus_kembali);
      break;
    }
  }
  if (!kembali) {
    resetDenda();
    return;
  }

  let hari = Math.ceil((tgl - kembali) / (1000 * 60 * 60 * 24));
  if (hari < 0) hari = 0;

  // Hitung jumlah buku yang diceklist
  const bukuChecked = document.querySelectorAll('input[name="no_copy_buku[]"]:checked');
  const jumlahBuku = bukuChecked.length;

  // Hitung denda keterlambatan
  const dendaTelat = hari * tarifPerHari * jumlahBuku;

  // Status pengembalian
  const statusDiv = document.getElementById('status_pengembalian');
  if (tgl && kembali) {
    statusDiv.className = tgl <= kembali 
      ? "status-display status-tepat" 
      : "status-display status-telat";
    statusDiv.innerHTML = tgl <= kembali
      ? "<i class='fas fa-check-circle me-1'></i> Tepat Waktu"
      : `<i class='fas fa-exclamation-circle me-1'></i> Terlambat ${hari} hari`;
  }

  // Denda tambahan
  const tambahan = document.getElementById('id_denda_tambahan');
  let tarifTambahan = 0;
  if (tambahan.value) {
    const option = tambahan.options[tambahan.selectedIndex];
    tarifTambahan = parseInt(option.text.split('Rp')[1].replace(/\D/g, '')) || 0;
  }
  const totalTambahan = tarifTambahan * jumlahBuku;

  // Total denda
  const totalDenda = dendaTelat + totalTambahan;

  document.getElementById('denda_telat_display').value = dendaTelat.toLocaleString('id-ID', {style: 'currency', currency: 'IDR'});
  document.getElementById('total_denda_display').value = totalDenda.toLocaleString('id-ID', {style: 'currency', currency: 'IDR'});
  document.getElementById('tarif_denda').value = totalDenda;
}

function resetDenda() {
  document.getElementById('status_pengembalian').innerHTML = '';
  document.getElementById('denda_telat_display').value = '';
  document.getElementById('total_denda_display').value = '';
  document.getElementById('tarif_denda').value = '';
}

function toggleSubmit() {
  const jumlah = document.querySelectorAll('input[name="no_copy_buku[]"]:checked').length;
  document.querySelector('[name="simpan"]').disabled = jumlah === 0;
}
document.addEventListener('change', function(e) {
  if (e.target.name === 'no_copy_buku[]') toggleSubmit();
});

// Set tanggal pengembalian default ke hari ini
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('tglPengembalian').value = today;
    document.getElementById('tglPengembalian').min = today;
});
</script>

</body>
</html>