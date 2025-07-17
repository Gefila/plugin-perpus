<?php

function generateNoPengembalian($conn) {
  $r = $conn->query("SELECT MAX(CAST(SUBSTRING(no_pengembalian,3) AS UNSIGNED)) AS max_num FROM pengembalian");
  $row = $r->fetch_assoc();
  $n = ($row && $row['max_num']) ? (int)$row['max_num'] + 1 : 1;
  return "PG" . $n;
}

// Cek mode edit
$editMode = false;
$editData = [];
$existingCopies = [];
$existingDendaTambahan = null;
if (isset($_GET['edit'])) {
  $editMode = true;
  $noPengembalian = $conn->real_escape_string($_GET['edit']);

  // Ambil data pengembalian utama & peminjaman terkait
  $stmt = $conn->prepare("SELECT pg.*, pm.tgl_harus_kembali, pm.id_anggota FROM pengembalian pg JOIN peminjaman pm USING(no_peminjaman) WHERE pg.no_pengembalian = ?");
  $stmt->bind_param("s", $noPengembalian);
  $stmt->execute();
  $editData = $stmt->get_result()->fetch_assoc() ?: [];
  $stmt->close();

  // Ambil daftar copy yang sudah dikembalikan
  $stmt2 = $conn->prepare("SELECT no_copy_buku FROM bisa WHERE no_pengembalian = ?");
  $stmt2->bind_param("s", $noPengembalian);
  $stmt2->execute();
  $res2 = $stmt2->get_result();
  while ($r = $res2->fetch_assoc()) $existingCopies[] = $r['no_copy_buku'];
  $stmt2->close();

  // Ambil id denda tambahan (bukan D1)
  $stmt3 = $conn->prepare("SELECT id_denda FROM pengembalian_denda WHERE no_pengembalian = ? AND id_denda != 'D1' LIMIT 1");
  $stmt3->bind_param("s", $noPengembalian);
  $stmt3->execute();
  $row3 = $stmt3->get_result()->fetch_assoc();
  $existingDendaTambahan = $row3['id_denda'] ?? null;
  $stmt3->close();
}

// Proses form submit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan'])) {
  if (isset($_POST['edit_mode'])) {
    // MODE EDIT
    $no_pengembalian = $_POST['no_pengembalian'];
    $tgl_pengembalian = $_POST['tgl_pengembalian'] ?? '';
    $id_denda_tambahan = $_POST['id_denda_tambahan'] ?: null;

    if (!$tgl_pengembalian) {
      echo "<script>alert('Tanggal pengembalian harus diisi!');history.back();</script>";
      exit;
    }

    $conn->begin_transaction();
    try {
      // Update tanggal
      $upd = $conn->prepare("UPDATE pengembalian SET tgl_pengembalian = ? WHERE no_pengembalian = ?");
      $upd->bind_param("ss", $tgl_pengembalian, $no_pengembalian);
      $upd->execute();
      $upd->close();

      // Hapus denda lama
      $del = $conn->prepare("DELETE FROM pengembalian_denda WHERE no_pengembalian = ?");
      $del->bind_param("s", $no_pengembalian);
      $del->execute();
      $del->close();

      // Hitung ulang denda
      $hari_telat = 0;
      $tgl_harus = new DateTime($editData['tgl_harus_kembali']);
      $tgl_kembali = new DateTime($tgl_pengembalian);
      if ($tgl_kembali > $tgl_harus) {
        $hari_telat = (int)$tgl_harus->diff($tgl_kembali)->days;
      }
      $tarif = (int)($conn->query("SELECT tarif_denda FROM denda WHERE id_denda='D1'")->fetch_assoc()['tarif_denda'] ?? 0);
      if ($hari_telat > 0 && $tarif > 0) {
        $jumlah_copy = count($existingCopies);
        $subtotal = $tarif * $hari_telat * $jumlah_copy;
        $ins1 = $conn->prepare("INSERT INTO pengembalian_denda(no_pengembalian, id_denda, jumlah_copy, subtotal) VALUES (?, 'D1', ?, ?)");
        $ins1->bind_param("sii", $no_pengembalian, $jumlah_copy, $subtotal);
        $ins1->execute();
        $ins1->close();
      }

      // Denda tambahan
if ($id_denda_tambahan) {
    $stmt_tarif2 = $conn->prepare("SELECT tarif_denda FROM denda WHERE id_denda=?");
    $stmt_tarif2->bind_param("s", $id_denda_tambahan);
    $stmt_tarif2->execute();
    $result2 = $stmt_tarif2->get_result();

    if ($result2 && $row2 = $result2->fetch_assoc()) {
        $tarif_persen = (float)$row2['tarif_denda']; // tarif dalam persen
        $total_denda_tambahan = 0;

        foreach ($existingCopies as $cb) {
            $q = $conn->prepare("SELECT b.harga_buku 
                FROM copy_buku cb 
                JOIN buku b ON cb.id_buku = b.id_buku
                WHERE cb.no_copy_buku = ?");
            $q->bind_param("s", $cb);
            $q->execute();
            $res = $q->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                $harga = (int)$row['harga_buku'];
                $denda = ($tarif_persen / 100) * $harga;
                $total_denda_tambahan += $denda;
            }
            $q->close();
        }

        $jumlah_copy = count($existingCopies);
        $total_denda_tambahan = round($total_denda_tambahan);

        $ins2 = $conn->prepare("INSERT INTO pengembalian_denda(no_pengembalian, id_denda, jumlah_copy, subtotal) VALUES (?, ?, ?, ?)");
        $ins2->bind_param("ssii", $no_pengembalian, $id_denda_tambahan, $jumlah_copy, $total_denda_tambahan);
        $ins2->execute();
        $ins2->close();
    }

    $stmt_tarif2->close();
}

      $conn->commit();
      echo "<script>alert('Pengembalian berhasil diperbarui!');location='admin.php?page=perpus_utama&panggil=pengembalian.php';</script>";
      exit;
    } catch (Exception $ex) {
      $conn->rollback();
      echo "<script>alert('Gagal update: " . addslashes($ex->getMessage()) . "');history.back();</script>";
      exit;
    }
  } else {
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
          $result_tarif = $stmt_tarif->get_result();

          if ($result_tarif && $row_tarif = $result_tarif->fetch_assoc()) {
            $tarif_persen = (float)$row_tarif['tarif_denda']; // contoh: 10 berarti 10%

            $total_denda_tambahan = 0;
            foreach ($copy_buku as $cb) {
              $q = $conn->prepare("SELECT b.harga_buku 
                FROM copy_buku cb JOIN buku b ON cb.id_buku = b.id_buku
                WHERE cb.no_copy_buku = ?");
              $q->bind_param("s", $cb);
              $q->execute();
              $res = $q->get_result();
              if ($res && $row = $res->fetch_assoc()) {
                $harga = (int)$row['harga_buku'];
                $denda = ($tarif_persen / 100) * $harga;
                $total_denda_tambahan += $denda;
              }
              $q->close();
            }

            $jumlah_copy = count($copy_buku);
            $stmt_denda = $conn->prepare("INSERT INTO pengembalian_denda(no_pengembalian, id_denda, jumlah_copy, subtotal) VALUES (?, ?, ?, ?)");
            $total_denda_tambahan = round($total_denda_tambahan);
            $stmt_denda->bind_param("ssii", $no_pengembalian, $id_denda_tambahan, $jumlah_copy, $total_denda_tambahan);
            $stmt_denda->execute();
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
    SELECT d.no_peminjaman, d.no_copy_buku, b.id_buku, b.judul_buku, b.harga_buku
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
if ($editMode && !empty($editData['no_peminjaman'])) {
    $detailBuku = [];  // kosongkan dulu
    $stmtX = $conn->prepare("
        SELECT d.no_peminjaman, d.no_copy_buku, b.id_buku, b.judul_buku, b.harga_buku
        FROM dapat d
        JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
        JOIN buku b ON cb.id_buku = b.id_buku
        WHERE d.no_peminjaman = ?
    ");
    $stmtX->bind_param("s", $editData['no_peminjaman']);
    $stmtX->execute();
    $resX = $stmtX->get_result();
    while ($rowX = $resX->fetch_assoc()) {
        $detailBuku[$rowX['no_peminjaman']][] = $rowX;
    }
    $stmtX->close();
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
        <!-- jQuery (jika belum ada) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Select2 CSS dan JS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
        <?php echo $editMode ? 'Edit Pengembalian Buku' : 'Tambah Pengembalian Buku'; ?>
      </h2>
    </div>
    <div class="perpus-form-body">
      <form method="POST" id="formPengembalian">
        <?php if ($editMode): ?>
          <input type="hidden" name="edit_mode" value="1">
          <input type="hidden" name="no_pengembalian" value="<?php echo esc_attr($editData['no_pengembalian']); ?>">
        <?php endif; ?>

        <!-- Anggota -->
        <div class="perpus-input-group">
    <label for="anggotaSelect">Anggota</label>
    <div class="perpus-select-wrapper">
        <select name="id_anggota" id="anggotaSelect" class="form-select" style="width: 100%" onchange="filterPeminjaman()" required <?= $editMode ? 'disabled' : ''; ?>>
            <option value="">-- Pilih Anggota --</option>
            <?php foreach ($anggotaData as $a): ?>
                <option value="<?= esc_attr($a['id_anggota']) ?>"
                    <?= $editMode && $a['id_anggota'] == $editData['id_anggota'] ? 'selected' : '' ?>>
                    <?= esc_html($a['id_anggota'] . " - " . $a['nm_anggota']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

        <!-- Nomor Peminjaman -->
        <div class="perpus-input-group">
          <label for="peminjamanSelect">Nomor Peminjaman</label>
          <div class="perpus-select-wrapper">
            <select name="no_peminjaman" id="peminjamanSelect" class="form-select" onchange="updateInfo()" required <?php echo $editMode ? 'disabled' : ''; ?>>
              <option value="">-- Pilih Nomor Peminjaman --</option>
              <?php if ($editMode): ?>
                <option value="<?= esc_attr($editData['no_peminjaman']) ?>" selected><?= esc_html($editData['no_peminjaman']) ?></option>
              <?php endif; ?>
            </select>
          </div>
        </div>

        <div class="row">
          <!-- Tanggal Harus Kembali -->
          <div class="col-md-6">
            <div class="perpus-input-group">
              <label for="tgl_harus_kembali_display">Tanggal Harus Kembali</label>
              <div class="perpus-input-wrapper">
                <span class="perpus-input-icon"><i class="fas fa-calendar-day"></i></span>
                <input type="text" id="tgl_harus_kembali_display" class="perpus-input-field readonly-blue" readonly value="<?= $editMode ? esc_attr($editData['tgl_harus_kembali']) : '' ?>" />
              </div>
            </div>
          </div>

          <!-- Tanggal Pengembalian -->
          <div class="col-md-6">
            <div class="perpus-input-group">
              <label for="tglPengembalian">Tanggal Pengembalian</label>
              <div class="perpus-input-wrapper">
                <span class="perpus-input-icon"><i class="fas fa-calendar-check"></i></span>
                <input type="date" name="tgl_pengembalian" id="tglPengembalian" class="perpus-input-field" value="<?= $editMode ? esc_attr($editData['tgl_pengembalian']) : date('Y-m-d') ?>" required />
              </div>
            </div>
          </div>
        </div>

        <!-- Status -->
        <div class="perpus-input-group">
          <label>Status Pengembalian</label>
          <div id="status_pengembalian" class="status-display"></div>
        </div>

        <!-- Denda Telat -->
        <div class="perpus-input-group">
          <label for="denda_telat_display">Denda Telat</label>
          <div class="perpus-input-wrapper">
            <span class="perpus-input-icon"><i class="fas fa-exclamation-triangle"></i></span>
            <input type="text" id="denda_telat_display" class="perpus-input-field readonly-blue" readonly />
          </div>
        </div>

        <!-- Denda Tambahan -->
        <div class="perpus-input-group">
          <label for="id_denda_tambahan">Denda Tambahan</label>
          <div class="perpus-select-wrapper">
            <select name="id_denda_tambahan" id="id_denda_tambahan" class="form-select">
              <option value="">-- None --</option>
              <?php
              $denda_opsional->data_seek(0);
              while ($d = $denda_opsional->fetch_assoc()):
              ?>
                <option value="<?= htmlspecialchars($d['id_denda']) ?>"
                  <?= $editMode && isset($editData['id_denda_tambahan']) && $editData['id_denda_tambahan'] == $d['id_denda'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($d['alasan_denda']) ?> - <?= $d['tarif_denda'] ?>%
                </option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <!-- Total Denda -->
        <div class="perpus-input-group">
          <label for="total_denda_display">Total Denda</label>
          <div class="perpus-input-wrapper">
            <span class="perpus-input-icon"><i class="fas fa-money-bill-wave"></i></span>
            <input type="text" id="total_denda_display" class="perpus-input-field readonly-blue fw-bold" readonly />
            <input type="hidden" name="tarif_denda" id="tarif_denda" />
          </div>
        </div>

        <!-- Daftar Buku (readonly jika editMode) -->
        <div class="perpus-input-group">
          <label>Buku Dikembalikan</label>
          <div id="tabelBuku" class="perpus-list-group">
            <?php if ($editMode && !empty($existingCopies)): ?>
              <ul class="list-group">
                <?php foreach ($existingCopies as $copy): ?>
                  <li class="list-group-item"><?= esc_html($copy) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>

        <!-- Tombol -->
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
    const peminjamanData = <?= json_encode($peminjamanData) ?>;
    const detailBuku = <?= json_encode($detailBuku) ?>;

    const editMode = <?= $editMode ? 'true' : 'false' ?>;
    const existingCopies = <?= json_encode($existingCopies) ?>;
    const tglHarus = <?= json_encode($editMode && !empty($editData['tgl_harus_kembali']) ? $editData['tgl_harus_kembali'] : null) ?>;
    let tglHarusDate = tglHarus ? new Date(tglHarus) : null; // diganti dari const ke let
    const tarifPerHari = <?= $tarif_telat ?> || 0;

    function updateDenda() {
      const tglVal = document.getElementById('tglPengembalian').value;
      if (!tglVal) {
        clearDendaFields();
        return;
      }

      const tgl = new Date(tglVal);

      // Tambahan logika jika tglHarusDate null (mode tambah)
      if (!tglHarusDate || isNaN(tglHarusDate)) {
        const tglHarusField = document.getElementById('tgl_harus_kembali_display');
        if (tglHarusField && tglHarusField.value) {
          const tglHarusBaru = new Date(tglHarusField.value);
          if (!isNaN(tglHarusBaru)) {
            tglHarusDate = tglHarusBaru;
          }
        }
      }

      let hari = 0;
      if (tglHarusDate instanceof Date && !isNaN(tglHarusDate)) {
        hari = Math.ceil((tgl - tglHarusDate) / (1000 * 60 * 60 * 24));
      }
      if (hari < 0) hari = 0;

      let jumlahBuku = 0;
      let copyDipilih = [];

      if (editMode) {
        copyDipilih = existingCopies;
      } else {
        copyDipilih = Array.from(document.querySelectorAll('input[name="no_copy_buku[]"]:checked')).map(cb => cb.value);
      }
      jumlahBuku = copyDipilih.length;

      const dendaTelat = hari * tarifPerHari * jumlahBuku;

      // Denda tambahan berbasis persentase harga buku
      let totalTambahan = 0;
      const tambahanSelect = document.getElementById('id_denda_tambahan');
      if (tambahanSelect && tambahanSelect.value) {
        const selectedText = tambahanSelect.selectedOptions[0].text || '';
        const match = selectedText.match(/(\d+)%/);
        if (match) {
          const tarifPersen = parseFloat(match[1]) || 0;

          // Cari harga tiap buku terpilih
          const peminjamanSelect = document.getElementById('peminjamanSelect');
          const noPeminjaman = peminjamanSelect ? peminjamanSelect.value : null;

          if (noPeminjaman && detailBuku[noPeminjaman]) {
            detailBuku[noPeminjaman].forEach(b => {
              if (copyDipilih.includes(b.no_copy_buku)) {
                const harga = parseFloat(b.harga_buku) || 0;
                totalTambahan += (tarifPersen / 100) * harga;
              }
            });
          }
        }
      }

      const total = Math.round(dendaTelat + totalTambahan);

      const statusElem = document.getElementById('status_pengembalian');
      if (hari > 0) {
        statusElem.innerHTML = `<i class="fas fa-exclamation-circle text-danger"></i> Terlambat ${hari} hari`;
        statusElem.classList.add('status-telat');
        statusElem.classList.remove('status-tepat');
      } else {
        statusElem.innerHTML = `<i class="fas fa-check-circle text-success"></i> Tidak ada keterlambatan`;
        statusElem.classList.add('status-tepat');
        statusElem.classList.remove('status-telat');
      }

      const dendaTelatField = document.getElementById('denda_telat_display');
      const totalDendaField = document.getElementById('total_denda_display');
      const tarifDendaHidden = document.getElementById('tarif_denda');

      if (dendaTelatField) dendaTelatField.value = dendaTelat.toLocaleString('id-ID', {
        style: 'currency',
        currency: 'IDR'
      });
      if (totalDendaField) totalDendaField.value = total.toLocaleString('id-ID', {
        style: 'currency',
        currency: 'IDR'
      });
      if (tarifDendaHidden) tarifDendaHidden.value = total;

      console.log('tglPengembalian:', tgl);
      console.log('tglHarusDate:', tglHarusDate);
      console.log('hari telat:', hari);
      console.log('jumlah buku:', jumlahBuku);
      console.log('dendaTelat:', dendaTelat);
      console.log('dendaTambahan:', totalTambahan);
      console.log('total:', total);
    }

    function clearDendaFields() {
      document.getElementById('status_pengembalian').innerHTML = '';
      document.getElementById('denda_telat_display').value = '';
      document.getElementById('total_denda_display').value = '';
      document.getElementById('tarif_denda').value = '';
    }

    function filterPeminjaman() {
      const anggotaSelect = document.getElementById('anggotaSelect');
      const peminjamanSelect = document.getElementById('peminjamanSelect');
      const selectedId = anggotaSelect.value;

      peminjamanSelect.innerHTML = '<option value="">-- Pilih Nomor Peminjaman --</option>';

      if (selectedId && peminjamanData[selectedId]) {
        peminjamanData[selectedId].forEach(p => {
          const option = document.createElement('option');
          option.value = p.no_peminjaman;
          option.textContent = p.no_peminjaman;
          peminjamanSelect.appendChild(option);
        });
      }

      document.getElementById('tgl_harus_kembali_display').value = '';
      document.getElementById('tabelBuku').innerHTML = '';
      clearDendaFields();
    }

    function updateInfo() {
      const peminjamanSelect = document.getElementById('peminjamanSelect');
      const anggotaSelect = document.getElementById('anggotaSelect');
      const selectedNo = peminjamanSelect.value;
      const anggotaId = anggotaSelect.value;

      const tglHarusField = document.getElementById('tgl_harus_kembali_display');
      const daftarBukuDiv = document.getElementById('tabelBuku');

      tglHarusField.value = '';
      daftarBukuDiv.innerHTML = '';

      if (selectedNo && anggotaId && peminjamanData[anggotaId]) {
        const data = peminjamanData[anggotaId].find(p => p.no_peminjaman === selectedNo);
        if (data) {
          tglHarusField.value = data.tgl_harus_kembali;
        }
      }

      if (selectedNo && detailBuku[selectedNo]) {
        detailBuku[selectedNo].forEach(buku => {
          const div = document.createElement('div');
          div.className = 'perpus-list-group-item';
          div.innerHTML = `
        <label>
          <input type="checkbox" name="no_copy_buku[]" value="${buku.no_copy_buku}" checked />
          ${buku.no_copy_buku} - ${buku.judul_buku}
        </label>
      `;
          daftarBukuDiv.appendChild(div);

          const checkbox = div.querySelector('input[type="checkbox"]');
          checkbox.addEventListener('change', updateDenda);
        });
      }

      updateDenda();
    }

document.addEventListener('DOMContentLoaded', () => {
  const anggotaSelect        = document.getElementById('anggotaSelect');
  const peminjamanSelect     = document.getElementById('peminjamanSelect');
  const tanggalPengembalian  = document.getElementById('tglPengembalian');
  const dendaTambahanSelect  = document.getElementById('id_denda_tambahan');
  const tabelBuku            = document.getElementById('tabelBuku');
  const tglHarusDisplay      = document.getElementById('tgl_harus_kembali_display');

  // PASANG LISTENER YANG DIBUTUHKAN
  if (anggotaSelect) anggotaSelect.addEventListener('change', filterPeminjaman);
  if (peminjamanSelect) peminjamanSelect.addEventListener('change', updateInfo);
  if (tanggalPengembalian) tanggalPengembalian.addEventListener('change', updateDenda);
  if (dendaTambahanSelect) dendaTambahanSelect.addEventListener('change', updateDenda);  // ← INI YANG PENTING

  // Inisialisasi Select2
  if (window.jQuery && $('#anggotaSelect').length) {
    $('#anggotaSelect').select2({
      placeholder: "-- Pilih Id & Anggota --",
      allowClear: true,
      width: '100%'
    });
  }

  if (editMode && tglHarusDisplay && tglHarusDisplay.value) {
    const tmp = new Date(tglHarusDisplay.value);
    if (!isNaN(tmp)) tglHarusDate = tmp;
  }

  // Hitung denda saat form dimuat
  updateDenda();
});


  </script>

</body>

</html>