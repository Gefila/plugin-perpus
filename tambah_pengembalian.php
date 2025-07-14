<?php
// koneksi $conn diasumsikan sudah tersedia

function generateNoPengembalian($conn) {
    $r = $conn->query("SELECT MAX(CAST(SUBSTRING(no_pengembalian,3) AS UNSIGNED)) AS max_num FROM pengembalian");
    $row = $r->fetch_assoc();
    $n = ($row && $row['max_num']) ? (int)$row['max_num'] + 1 : 1;
    // Format dengan leading zero, misal PG001
    return "PG" . str_pad($n, 3, "0", STR_PAD_LEFT);
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

        // Prepared statements untuk cek, insert bisa, update copy buku
        $cek_stmt = $conn->prepare("SELECT 1 FROM bisa bs JOIN pengembalian p ON bs.no_pengembalian = p.no_pengembalian WHERE bs.no_copy_buku = ? AND p.no_peminjaman = ?");
        $insert_bisa = $conn->prepare("INSERT INTO bisa(no_pengembalian, no_copy_buku) VALUES (?, ?)");
        $update_copy = $conn->prepare("UPDATE copy_buku SET status_buku='tersedia' WHERE no_copy_buku = ?");

        foreach ($copy_buku as $cb) {
            // Cek apakah buku sudah dikembalikan di peminjaman ini
            $cek_sudah_kembali = $conn->prepare("SELECT 1 FROM bisa bs JOIN pengembalian p ON bs.no_pengembalian = p.no_pengembalian WHERE bs.no_copy_buku = ? AND p.no_peminjaman = ?");
            $cek_sudah_kembali->bind_param("ss", $cb, $no_peminjaman);
            $cek_sudah_kembali->execute();
            if ($cek_sudah_kembali->get_result()->num_rows > 0) {
                throw new Exception("Copy buku $cb sudah dikembalikan sebelumnya.");
            }

            // Cek apakah buku sedang dipinjam di peminjaman lain yang belum dikembalikan
            $cek_dipinjam_lain = $conn->prepare("
            SELECT 1
            FROM dapat d
            WHERE d.no_copy_buku = ?
              AND d.no_peminjaman != ?
              AND EXISTS (
                  SELECT 1
                  FROM peminjaman pm
                  WHERE pm.no_peminjaman = d.no_peminjaman
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM bisa bs
                  JOIN pengembalian p ON bs.no_pengembalian = p.no_pengembalian
                  WHERE bs.no_copy_buku = d.no_copy_buku
                    AND p.no_peminjaman = d.no_peminjaman
              )
            LIMIT 1
            ");
            $cek_dipinjam_lain->bind_param("ss", $cb, $no_peminjaman);
            $cek_dipinjam_lain->execute();
            if ($cek_dipinjam_lain->get_result()->num_rows > 0) {
                throw new Exception("Copy buku $cb masih sedang dipinjam dan belum dikembalikan di transaksi lain!");
            }

            // Insert bisa & update status buku...
            $insert_bisa->bind_param("ss", $no_pengembalian, $cb);
            if (!$insert_bisa->execute()) throw new Exception("Gagal simpan detail bisa: " . $insert_bisa->error);

            $update_copy->bind_param("s", $cb);
            if (!$update_copy->execute()) throw new Exception("Gagal update status buku: " . $update_copy->error);
        }

        // Hitung hari keterlambatan
        $no_peminjaman_esc = $conn->real_escape_string($no_peminjaman);
        $tgl_harus_kembali_row = $conn->query("SELECT tgl_harus_kembali FROM peminjaman WHERE no_peminjaman = '$no_peminjaman_esc'")->fetch_assoc();
        $hari_telat = 0;
        if ($tgl_harus_kembali_row) {
            $tgl_harus_kembali = new DateTime($tgl_harus_kembali_row['tgl_harus_kembali']);
            $tgl_kembali = new DateTime($tgl_pengembalian);
            $hari_telat = 0;
            if ($tgl_kembali > $tgl_harus_kembali) {
                $hari_telat = (int)$tgl_harus_kembali->diff($tgl_kembali)->days;
            }
        }

        // Ambil tarif denda keterlambatan
        $res_tarif = $conn->query("SELECT tarif_denda FROM denda WHERE id_denda = 'D1'");
        if ($res_tarif && $res_tarif->num_rows > 0) {
            $row_tarif = $res_tarif->fetch_assoc();
            $tarif_telat = isset($row_tarif['tarif_denda']) ? (int)$row_tarif['tarif_denda'] : 0;

            // Hitung hari keterlambatan
            $hari_telat = 0;
            if ($tgl_kembali > $tgl_harus_kembali) {
                $hari_telat = (int)$tgl_harus_kembali->diff($tgl_kembali)->days;
            }

            if (!empty($copy_buku) && $hari_telat > 0 && $tarif_telat > 0) {
                $jumlah_copy_telat = count($copy_buku);
                $subtotal_telat = $tarif_telat * $hari_telat * $jumlah_copy_telat;

                $stmt_telat = $conn->prepare("INSERT INTO pengembalian_denda(no_pengembalian, id_denda, jumlah_copy, subtotal) VALUES (?, 'D1', ?, ?)");
                $stmt_telat->bind_param("sii", $no_pengembalian, $jumlah_copy_telat, $subtotal_telat);
                if (!$stmt_telat->execute()) {
                    throw new Exception("Gagal simpan denda keterlambatan: " . $stmt_telat->error);
                }
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
            }
        }
        error_log("Berhasil commit transaksi. D1 dan denda lainnya disimpan untuk no_pengembalian: $no_pengembalian");
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
    WHERE NOT EXISTS (
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
<!-- Custom Style (ubah sesuai lokasi file style kamu) -->
<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet">

<style>
.list-group-item label {
    cursor: pointer;
}
</style>
</head>

<body class="p-3">

<div class="container my-5">
  <h3 class="text-dark mb-4"><i class="fa-solid fa-book text-primary"></i> Tambah Pengembalian Buku</h3>

  <div class="card-glass">

    <form method="POST" id="formPengembalian">
      <!-- Anggota -->
      <div class="mb-3">
        <label for="anggotaSelect" class="form-label">Anggota</label>
        <select name="id_anggota" id="anggotaSelect" class="form-select custom-glass-input" onchange="filterPeminjaman()" required>
          <option value="">-- Pilih Anggota --</option>
          <?php foreach ($anggotaData as $a): ?>
            <option value="<?= htmlspecialchars($a['id_anggota']) ?>"><?= htmlspecialchars($a['id_anggota'] . " - " . $a['nm_anggota']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Nomor Peminjaman -->
      <div class="mb-3">
        <label for="peminjamanSelect" class="form-label">Nomor Peminjaman</label>
        <select name="no_peminjaman" id="peminjamanSelect" class="form-select custom-glass-input" onchange="updateInfo()" required>
          <option value="">-- Pilih Nomor Peminjaman --</option>
        </select>
      </div>

      <!-- Tanggal Harus Kembali -->
      <div class="mb-3" style="max-width: 220px;">
        <label for="tgl_harus_kembali_display" class="form-label">Tanggal Harus Kembali</label>
        <input type="text" id="tgl_harus_kembali_display" class="form-control custom-glass-input readonly-blue" readonly />
      </div>

      <!-- Tanggal Pengembalian -->
      <div class="mb-3" style="max-width: 220px;">
        <label for="tglPengembalian" class="form-label">Tanggal Pengembalian</label>
        <input type="date" name="tgl_pengembalian" id="tglPengembalian" class="form-control custom-glass-input" onchange="updateDenda()" required />
      </div>

      <!-- Status Pengembalian -->
      <div class="mb-3">
        <label class="form-label">Status Pengembalian</label>
        <div id="status_pengembalian" class="fw-bold fs-5"></div>
      </div>

      <!-- Denda Telat -->
      <div class="mb-3">
        <label for="denda_telat_display" class="form-label">Denda Telat (per hari × per buku)</label>
        <input type="text" id="denda_telat_display" class="form-control custom-glass-input readonly-blue" readonly />
      </div>

      <!-- Denda Tambahan -->
      <div class="mb-3">
        <label for="id_denda_tambahan" class="form-label">Denda Tambahan (per buku)</label>
        <select name="id_denda_tambahan" id="id_denda_tambahan" class="form-select custom-glass-input" onchange="updateDenda()">
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

      <!-- Total Denda -->
      <div class="mb-3">
        <label for="total_denda_display" class="form-label">Total Denda</label>
        <input type="text" id="total_denda_display" class="form-control fw-bold custom-glass-input readonly-blue" readonly />
        <input type="hidden" name="tarif_denda" id="tarif_denda" />
      </div>

      <!-- Buku Dikembalikan -->
      <div class="mb-3">
        <label class="form-label">Buku Dikembalikan</label>
        <div id="tabelBuku"></div>
      </div>

      <!-- Tombol Simpan -->
      <button name="simpan" class="btn btn-primary btn-glow">
        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan
      </button>
         <a href="admin.php?page=perpus_utama&panggil=pengembalian.php" class="btn btn-secondary">
  <i class="fa-solid fa-xmark"></i> Batal
</a>
    </form>

  </div> <!-- /.card-glass -->
</div> <!-- /.container -->

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

  let html = '<ul class="list-group">';
  buku.forEach(b => {
    html += `<li class="list-group-item">
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
    statusDiv.innerHTML = tgl <= kembali
      ? "<span style='color:green'>Tepat Waktu</span>"
      : `<span style='color:red'>Terlambat ${hari} hari</span>`;
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

</script>

</body>
</html>
