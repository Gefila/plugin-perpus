<?php
$editMode = false;
$editData = null;

if (isset($_GET['edit'])) {
    $editMode = true;
    $no_pengembalian_edit = $conn->real_escape_string($_GET['edit']);

    // Ambil data pengembalian untuk ditampilkan di form
    $res = $conn->query("
        SELECT p.*, pm.id_anggota
        FROM pengembalian p
        JOIN peminjaman pm ON p.no_peminjaman = pm.no_peminjaman
        WHERE p.no_pengembalian = '$no_pengembalian_edit'
    ");

    if ($res && $res->num_rows > 0) {
        $editData = $res->fetch_assoc();
        $no_pinjam = $editData['no_peminjaman'];
        $res_kembali = $conn->query("SELECT tgl_harus_kembali FROM peminjaman WHERE no_peminjaman = '$no_pinjam'");
        if ($res_kembali && $res_kembali->num_rows > 0) {
            $editData['tgl_harus_kembali'] = $res_kembali->fetch_assoc()['tgl_harus_kembali'];
        }

    } else {
        echo "<script>alert('Data pengembalian tidak ditemukan!');location='admin.php?page=perpus_utama&panggil=pengembalian.php';</script>";
        exit;
    }
}

function generateNoPengembalian($conn) {
    $r = $conn->query("SELECT MAX(CAST(SUBSTRING(no_pengembalian,3) AS UNSIGNED)) AS max_num FROM pengembalian");
    $row = $r->fetch_assoc();
    $n = ($row && $row['max_num']) ? (int)$row['max_num'] + 1 : 1;
    return "PG" . $n;
}

// Proses form submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tgl_pengembalian = $_POST['tgl_pengembalian'] ?? '';
    $id_denda_tambahan = $_POST['id_denda_tambahan'] ?? null;

    // MODE EDIT
    if (isset($_POST['edit_mode']) && $_POST['edit_mode'] == '1') {
        $no_pengembalian_edit = $_POST['no_pengembalian'] ?? '';
        if (!$no_pengembalian_edit || !$tgl_pengembalian) {
            echo "<script>alert('Data tidak lengkap untuk proses edit!');history.back();</script>";
            exit;
        }

        $conn->begin_transaction();
        try {
            // Update tanggal pengembalian
            $conn->query("UPDATE pengembalian SET tgl_pengembalian = '$tgl_pengembalian' WHERE no_pengembalian = '$no_pengembalian_edit'");

            // Hapus denda tambahan lama
            $conn->query("DELETE FROM pengembalian_denda WHERE no_pengembalian = '$no_pengembalian_edit' AND id_denda = 'D1'");

            $jumlah_copy = $conn->query("SELECT COUNT(*) AS jml FROM bisa WHERE no_pengembalian = '$no_pengembalian_edit'")->fetch_assoc()['jml'];
           // Ambil no_peminjaman dari pengembalian
            $res_peminjaman = $conn->query("SELECT no_peminjaman FROM pengembalian WHERE no_pengembalian = '$no_pengembalian_edit'");
            if (!$res_peminjaman || $res_peminjaman->num_rows === 0) {
                throw new Exception("Tidak dapat menemukan no_peminjaman dari no_pengembalian: $no_pengembalian_edit");
            }
            $no_peminjaman_edit = $res_peminjaman->fetch_assoc()['no_peminjaman'];

            $res_kembali = $conn->query("SELECT tgl_harus_kembali FROM peminjaman WHERE no_peminjaman = '$no_peminjaman_edit'");

            $res_tarif = $conn->query("SELECT tarif_denda FROM denda WHERE id_denda = 'D1'");

            if ($res_kembali && $res_tarif && $res_kembali->num_rows > 0 && $res_tarif->num_rows > 0) {
                $tgl_harus = new DateTime($res_kembali->fetch_assoc()['tgl_harus_kembali']);
                $tgl_pengembalian_dt = new DateTime($tgl_pengembalian);
                $hari_telat = max(0, $tgl_harus->diff($tgl_pengembalian_dt)->days);
                $tarif_d1 = (int)$res_tarif->fetch_assoc()['tarif_denda'];

                if ($hari_telat > 0 && $tarif_d1 > 0) {
                    $subtotal_telat = $hari_telat * $tarif_d1 * $jumlah_copy;
                    $stmt_telat = $conn->prepare("INSERT INTO pengembalian_denda(no_pengembalian, id_denda, jumlah_copy, subtotal) VALUES (?, 'D1', ?, ?)");
                    $stmt_telat->bind_param("sii", $no_pengembalian_edit, $jumlah_copy, $subtotal_telat);
                    $stmt_telat->execute();
                    $stmt_telat->close();
                }
            }


            $conn->commit();
            echo "<script>alert('Data pengembalian berhasil diperbarui!');location='admin.php?page=perpus_utama&panggil=pengembalian.php';</script>";
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>alert('Gagal update data: " . addslashes($e->getMessage()) . "');history.back();</script>";
            exit;
        }
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
  <?php if ($editMode): ?>
    <input type="hidden" name="edit_mode" value="1">
    <input type="hidden" name="no_pengembalian" value="<?= htmlspecialchars($editData['no_pengembalian']) ?>">
    <input type="hidden" id="hidden_tgl_harus_kembali" value="<?= htmlspecialchars($editData['tgl_harus_kembali']) ?>">
  <?php endif; ?>

    <!-- Anggota (Disabled saat edit) -->
    <div class="mb-3">
      <label for="anggotaSelect" class="form-label">Anggota</label>
      <select name="id_anggota" id="anggotaSelect" class="form-select custom-glass-input" onchange="filterPeminjaman()" <?= $editMode ? 'disabled' : 'required' ?>>
        <option value="">-- Pilih Anggota --</option>
        <?php foreach ($anggotaData as $a): ?>
          <option value="<?= htmlspecialchars($a['id_anggota']) ?>"
            <?= $editMode && $editData['id_anggota'] == $a['id_anggota'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($a['id_anggota'] . " - " . $a['nm_anggota']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Nomor Peminjaman (Disabled saat edit) -->
    <div class="mb-3">
      <label for="peminjamanSelect" class="form-label">Nomor Peminjaman</label>
      <select name="no_peminjaman" id="peminjamanSelect" class="form-select custom-glass-input" onchange="updateInfo()" <?= $editMode ? 'disabled' : 'required' ?>>
        <option value="">-- Pilih Nomor Peminjaman --</option>
        <?php if ($editMode): ?>
          <option value="<?= htmlspecialchars($editData['no_peminjaman']) ?>" selected>
            <?= htmlspecialchars($editData['no_peminjaman']) ?>
          </option>
        <?php endif; ?>
      </select>
    </div>

    <!-- Tanggal Harus Kembali (Read Only) -->
    <div class="mb-3" style="max-width: 220px;">
      <label for="tgl_harus_kembali_display" class="form-label">Tanggal Harus Kembali</label>
          <input type="text" id="tgl_harus_kembali_display" class="form-control custom-glass-input readonly-blue"
       value="<?= ($editMode && !empty($editData['tgl_harus_kembali']) && strtotime($editData['tgl_harus_kembali'])) ? date('d F Y', strtotime($editData['tgl_harus_kembali'])) : '' ?>" readonly />
    </div>

    <!-- Tanggal Pengembalian -->
    <div class="mb-3" style="max-width: 220px;">
      <label for="tglPengembalian" class="form-label">Tanggal Pengembalian</label>
      <input type="date" name="tgl_pengembalian" id="tglPengembalian"
            value="<?= $editMode ? htmlspecialchars($editData['tgl_pengembalian']) : '' ?>"
            class="form-control custom-glass-input" onchange="updateDenda()" required />
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
          <option value="<?= htmlspecialchars($d['id_denda']) ?>"
            <?= ($editMode && $conn->query("SELECT 1 FROM pengembalian_denda WHERE no_pengembalian = '" . $editData['no_pengembalian'] . "' AND id_denda = '" . $d['id_denda'] . "'")->num_rows > 0) ? 'selected' : '' ?>>
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

    <!-- Buku Dikembalikan (Non-editable saat edit) -->
    <div class="mb-3">
      <label class="form-label">Buku Dikembalikan</label>
      <div id="tabelBuku">
        <?php if ($editMode): ?>
          <?php
          $copy_result = $conn->query("
              SELECT b.id_buku, b.judul_buku, cb.no_copy_buku
              FROM bisa bs
              JOIN copy_buku cb ON bs.no_copy_buku = cb.no_copy_buku
              JOIN buku b ON cb.id_buku = b.id_buku
              WHERE bs.no_pengembalian = '" . $editData['no_pengembalian'] . "'
          ");
          if ($copy_result && $copy_result->num_rows > 0):
            echo '<ul class="list-group">';
            while ($b = $copy_result->fetch_assoc()):
          ?>
              <li class="list-group-item">
                <label>
                  <input type="checkbox" name="no_copy_buku[]" value="<?= $b['no_copy_buku'] ?>" checked onchange="updateDenda()">
                  <?= $b['id_buku'] ?> - <?= $b['judul_buku'] ?> (Copy: <?= $b['no_copy_buku'] ?>)
                </label>
              </li>
          <?php
            endwhile;
            echo '</ul>';
          endif;
          ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tombol Simpan -->
    <button name="simpan" class="btn btn-primary btn-glow">
      <i class="fa-solid fa-floppy-disk me-1"></i> <?= $editMode ? 'Simpan Perubahan' : 'Simpan' ?>
    </button>
    <a href="admin.php?page=perpus_utama&panggil=pengembalian.php" class="btn btn-secondary">
      <i class="fa-solid fa-xmark"></i> Batal
    </a>
  </form>
  

  </div> <!-- /.card-glass -->
</div> <!-- /.container -->

<script>
const dataPeminjaman = <?= json_encode($peminjamanData) ?>;
const noPeminjamanEdit = <?= json_encode($editMode ? $editData['no_peminjaman'] : null) ?>;

<?php if ($editMode): ?>
  // Inject data tgl_harus_kembali agar tetap bisa terbaca saat updateDenda dipanggil
  if (!(dataPeminjaman["<?= $editData['id_anggota'] ?>"])) dataPeminjaman["<?= $editData['id_anggota'] ?>"] = [];
  dataPeminjaman["<?= $editData['id_anggota'] ?>"].push({
    no_peminjaman: "<?= $editData['no_peminjaman'] ?>",
    tgl_harus_kembali: "<?= $editData['tgl_harus_kembali'] ?>",
  });
<?php endif; ?>

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
  console.log("Tanggal pengembalian (input):", tglValue);

  if (!tglValue) {
    console.log("⛔ Tidak ada tanggal pengembalian. Denda tidak dihitung.");
    resetDenda();
    return;
  }

  let no = document.getElementById('peminjamanSelect').value;
  if (!no && noPeminjamanEdit) {
    no = noPeminjamanEdit;
  }

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

  // Fallback jika tidak ketemu di dataPeminjaman
  if (!kembali && document.getElementById('hidden_tgl_harus_kembali')) {
    kembali = new Date(document.getElementById('hidden_tgl_harus_kembali').value);
    console.log("🛠️ Fallback: pakai hidden_tgl_harus_kembali =", kembali.toISOString().split('T')[0]);
  }

  if (!kembali) {
    resetDenda();
    return;
  }


  const tgl = new Date(tglValue);
  let hari = Math.ceil((tgl - kembali) / (1000 * 60 * 60 * 24));
  if (hari < 0) hari = 0;
  console.log("📆 Hari keterlambatan:", hari);

  // Hitung jumlah buku yang diceklist
  const bukuChecked = document.querySelectorAll('input[name="no_copy_buku[]"]:checked');
  const jumlahBuku = bukuChecked.length;
  console.log("📚 Jumlah buku dicentang:", jumlahBuku);

  // Hitung denda keterlambatan
  const dendaTelat = hari * tarifPerHari * jumlahBuku;
  console.log("💸 Denda telat:", dendaTelat);

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
  console.log("➕ Denda tambahan:", totalTambahan);

  // Total denda
  const totalDenda = dendaTelat + totalTambahan;
  console.log("💰 Total denda:", totalDenda);

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

window.addEventListener('load', function () {
  if (<?= $editMode ? 'true' : 'false' ?>) {
    updateDenda();
  }
});

</script>

</body>
</html>
