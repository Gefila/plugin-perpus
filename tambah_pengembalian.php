<?php
function generateNoPengembalian($conn) {
    $r = $conn->query("SELECT MAX(CAST(SUBSTRING(no_pengembalian,3) AS UNSIGNED)) AS max_num FROM pengembalian");
    $n = (int)$r->fetch_assoc()['max_num'] + 1;
    return "PG" . $n;
}

$denda_opsional = $conn->query("SELECT * FROM denda WHERE id_denda != 'D1'");
$tarif_telat = (int)$conn->query("SELECT tarif_denda FROM denda WHERE id_denda='D1'")->fetch_assoc()['tarif_denda'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan'])) {
    $no_pengembalian = generateNoPengembalian($conn);
    $tgl_pengembalian = $_POST['tgl_pengembalian'];
    $no_peminjaman = $_POST['no_peminjaman'];
    $tarif_denda = (int)$_POST['tarif_denda'];
    $alasan_denda = trim($_POST['alasan_denda']);
    $id_denda_tambahan = $_POST['id_denda_tambahan'] ?? null;
    $copy_buku = $_POST['no_copy_buku'] ?? [];

    if (empty($copy_buku)) {
        echo "<script>alert('Pilih minimal satu buku untuk dikembalikan!');history.back();</script>";
        exit;
    }

    if (!isset($_POST['tarif_denda']) || $_POST['tarif_denda'] === '') {
        echo "<script>alert('Denda belum dihitung! Harap isi tanggal dan centang buku.');history.back();</script>";
        exit;
    }

    $conn->begin_transaction();
    try {
        // Simpan pengembalian (satu kali saja, lengkap)
        $conn->query("INSERT INTO pengembalian 
            (no_pengembalian, tgl_pengembalian, no_peminjaman, id_denda, tarif_denda, alasan_denda) 
            VALUES 
            ('$no_pengembalian', '$tgl_pengembalian', '$no_peminjaman', 'D1', $tarif_denda, '$alasan_denda')");

        // Cek & simpan detail pengembalian
        $cek_stmt = $conn->prepare("
            SELECT 1 
            FROM bisa bs 
            JOIN pengembalian p ON bs.no_pengembalian = p.no_pengembalian 
            WHERE bs.no_copy_buku = ? AND p.no_peminjaman = ?
        ");
        $insert_bisa = $conn->prepare("INSERT INTO bisa(no_pengembalian, no_copy_buku) VALUES (?, ?)");
        $update_copy = $conn->prepare("UPDATE copy_buku SET status_buku='tersedia' WHERE no_copy_buku = ?");

        foreach ($copy_buku as $cb) {
            $cek_stmt->bind_param("ss", $cb, $no_peminjaman);
            $cek_stmt->execute();
            $res = $cek_stmt->get_result();
            if ($res->num_rows > 0) {
                throw new Exception("Copy buku $cb sudah dikembalikan sebelumnya.");
            }

            $insert_bisa->bind_param("ss", $no_pengembalian, $cb);
            $insert_bisa->execute();

            $update_copy->bind_param("s", $cb);
            $update_copy->execute();
        }

        // Simpan denda tambahan jika ada
        if (!empty($id_denda_tambahan)) {
            $conn->query("INSERT INTO pengembalian_denda(no_pengembalian, id_denda) VALUES('$no_pengembalian','$id_denda_tambahan')");
        }

        $conn->commit();
        echo "<script>alert('Pengembalian berhasil disimpan!');location='admin.php?page=perpus_utama&panggil=pengembalian.php';</script>";
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Gagal menyimpan data: " . addslashes($e->getMessage()) . "');history.back();</script>";
        exit;
    }
}

// Ambil data anggota dan peminjaman
$anggota = $conn->query("SELECT * FROM anggota ORDER BY nm_anggota");

// Ambil peminjaman yang masih memiliki buku belum dikembalikan
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
  <meta charset="UTF-8">
  <title>Tambah Pengembalian</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<h3>Tambah Pengembalian Buku</h3>
<form method="POST">
  <div class="mb-3">
    <label>Anggota</label>
    <select name="id_anggota" id="anggotaSelect" class="form-select" onchange="filterPeminjaman()" required>
      <option value="">-- Pilih Anggota --</option>
      <?php while($a=$anggota->fetch_assoc()): ?>
        <option value="<?= $a['id_anggota'] ?>"><?= $a['id_anggota'] ?> - <?= $a['nm_anggota'] ?></option>
      <?php endwhile; ?>
    </select>
  </div>

  <div class="mb-3">
    <label>Nomor Peminjaman</label>
    <select name="no_peminjaman" id="peminjamanSelect" class="form-select" onchange="updateInfo()" required></select>
  </div>

  <div class="mb-3">
    <label>Tanggal Harus Kembali</label>
    <input type="text" id="tgl_harus_kembali_display" class="form-control" readonly>
  </div>

  <div class="mb-3">
    <label>Tanggal Pengembalian</label>
    <input type="date" name="tgl_pengembalian" id="tglPengembalian" class="form-control" onchange="updateDenda()" required>
  </div>

  <div class="mb-3">
    <label>Status Pengembalian</label>
    <div id="status_pengembalian" class="fw-bold"></div>
  </div>

  <div class="mb-3">
    <label>Denda Telat (perhari x perbuku)</label>
    <input type="text" id="denda_telat_display" class="form-control" readonly>
  </div>

  <div class="mb-3">
    <label>Denda Tambahan (per buku)</label>
    <select name="id_denda_tambahan" id="id_denda_tambahan" class="form-select" onchange="updateDenda()">
      <option value="">-- Pilih Denda Tambahan --</option>
      <?php while($d=$denda_opsional->fetch_assoc()): ?>
        <option value="<?= $d['id_denda'] ?>">
          <?= $d['alasan_denda'] ?> - Rp<?= number_format($d['tarif_denda']) ?>
        </option>
      <?php endwhile; ?>
    </select>
  </div>

  <div class="mb-3">
    <label>Total Denda</label>
    <input type="text" id="total_denda_display" class="form-control" readonly>
    <input type="hidden" name="tarif_denda" id="tarif_denda">
    <input type="hidden" name="alasan_denda" id="alasan_denda">
  </div>

  <div class="mb-3">
    <label>Buku Dikembalikan</label>
    <div id="tabelBuku"></div>
  </div>

  <button name="simpan" class="btn btn-primary">Simpan</button>
</form>

<script>
const dataPeminjaman = <?= json_encode($peminjamanData) ?>;
const dataBuku = <?= json_encode($detailBuku) ?>;
const tarifPerHari = <?= $tarif_telat ?>;

function filterPeminjaman() {
  const anggota = document.getElementById('anggotaSelect').value;
  const select = document.getElementById('peminjamanSelect');
  select.innerHTML = '<option value="">-- Pilih --</option>';
  if (anggota in dataPeminjaman) {
    dataPeminjaman[anggota].forEach(p => {
      select.innerHTML += `<option value="${p.no_peminjaman}">${p.no_peminjaman}</option>`;
    });
  }
}

function updateInfo() {
  const no = document.getElementById('peminjamanSelect').value;
  const buku = dataBuku[no] || [];

  // Tampilkan daftar buku
  let html = '<ul class="list-group">';
  buku.forEach((b, i) => {
    html += `<li class="list-group-item">
      <label><input type="checkbox" name="no_copy_buku[]" value="${b.no_copy_buku}"> ${b.id_buku} - ${b.judul_buku} (${b.no_copy_buku})</label>
    </li>`;
  });
  html += '</ul>';
  document.getElementById('tabelBuku').innerHTML = html;

  // Event update denda
  setTimeout(() => {
    document.querySelectorAll('input[name="no_copy_buku[]"]').forEach(cb => {
      cb.addEventListener('change', updateDenda);
    });
  }, 10);

  // Tampilkan tanggal harus kembali
  let tglKembali = '';
  for (let anggota in dataPeminjaman) {
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
  const tgl = new Date(document.getElementById('tglPengembalian').value);
  const no = document.getElementById('peminjamanSelect').value;
  let kembali = null;

  for (let anggota in dataPeminjaman) {
    const data = dataPeminjaman[anggota].find(p => p.no_peminjaman === no);
    if (data) kembali = new Date(data.tgl_harus_kembali); 
  }

  let hari = 0;
  if (tgl && kembali) hari = Math.ceil((tgl - kembali) / (1000 * 60 * 60 * 24));
  if (hari < 0) hari = 0;

  const bukuChecked = document.querySelectorAll('input[name="no_copy_buku[]"]:checked');
  const jumlahBuku = bukuChecked.length;

  const dendaTelat = hari * tarifPerHari * jumlahBuku;

  const statusDiv = document.getElementById('status_pengembalian');
  if (tgl && kembali) {
    statusDiv.innerHTML = tgl <= kembali
      ? "<span style='color:green'>Tepat Waktu</span>"
      : `<span style='color:red'>Terlambat ${hari} hari</span>`;
  }

  const tambahan = document.getElementById('id_denda_tambahan');
  const tarifTambahan = tambahan.value ? parseInt(tambahan.options[tambahan.selectedIndex].text.split('Rp')[1].replace(/\D/g,'')) : 0;
  const totalTambahan = tarifTambahan * jumlahBuku;

  const totalDenda = dendaTelat + totalTambahan;

  document.getElementById('denda_telat_display').value = `Rp${dendaTelat.toLocaleString('id-ID')}`;
  document.getElementById('total_denda_display').value = `Rp${totalDenda.toLocaleString('id-ID')}`;
  document.getElementById('tarif_denda').value = dendaTelat;
  document.getElementById('alasan_denda').value = hari > 0 ? `Telat ${hari} hari × ${jumlahBuku} buku` : '';
}
</script>
</body>
</html>
