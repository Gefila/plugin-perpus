<?php

// Generate nomor pengembalian
function generateNoPengembalian($conn) {
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(no_pengembalian, 3) AS UNSIGNED)) AS max_num FROM pengembalian");
    $row = $result->fetch_assoc();
    $next = (int)$row['max_num'] + 1;
    return "PG" . str_pad($next, 4, '0', STR_PAD_LEFT);
}

// Proses Simpan
if (isset($_POST['simpan'])) {
    $tgl_pengembalian = $_POST['tgl_pengembalian'];
    $id_anggota = $_POST['id_anggota'];
    $no_peminjaman = $_POST['no_peminjaman'];
    $no_pengembalian = generateNoPengembalian($conn);

    // Simpan ke pengembalian
    $conn->query("INSERT INTO pengembalian (no_pengembalian, no_peminjaman, tgl_pengembalian) 
                  VALUES ('$no_pengembalian', '$no_peminjaman', '$tgl_pengembalian')");

    foreach ($_POST['no_copy_buku'] as $i => $no_copy) {
        $jumlah_kembali = (int)$_POST['jumlah_kembali'][$i];
        $jumlah_max = (int)$_POST['jumlah_max'][$i];

        if ($jumlah_kembali > $jumlah_max) {
            $jumlah_kembali = $jumlah_max; // Batasi ke maksimal
        }

        // Simpan ke bisa
        $conn->query("INSERT INTO bisa (no_pengembalian, no_copy_buku, jml_kembali)
                      VALUES ('$no_pengembalian', '$no_copy', $jumlah_kembali)");

        // Update status copy_buku
        $conn->query("UPDATE copy_buku SET status_buku = 'tersedia' 
                      WHERE no_copy_buku = '$no_copy'");
    }

    echo "<script>alert('Pengembalian berhasil disimpan!'); 
          window.location.href='admin.php?page=perpus_utama&panggil=pengembalian.php';</script>";
    exit;
}

// Ambil data anggota
$anggota_result = $conn->query("SELECT id_anggota, nm_anggota FROM anggota ORDER BY nm_anggota ASC");

// Ambil semua nomor peminjaman dan data anggota
$peminjaman_result = $conn->query("
    SELECT p.no_peminjaman, p.id_anggota, a.nm_anggota 
    FROM peminjaman p 
    JOIN anggota a ON p.id_anggota = a.id_anggota
");

// Ambil semua detail buku dari semua peminjaman
$detail_buku_query = $conn->query("
    SELECT 
        d.no_peminjaman,
        d.no_copy_buku, 
        b.id_buku, 
        b.judul_buku, 
        d.jml_pinjam
    FROM dapat d
    JOIN copy_buku cb ON d.no_copy_buku = cb.no_copy_buku
    JOIN buku b ON cb.id_buku = b.id_buku
");

$detail_buku = [];
while ($row = $detail_buku_query->fetch_assoc()) {
    $detail_buku[$row['no_peminjaman']][] = $row;
}
?>


<h3>Tambah Pengembalian Buku</h3>

<form method="POST">
    <div class="mb-3" style="width: 200px;">
        <label>Tanggal Pengembalian</label>
        <input type="date" name="tgl_pengembalian" class="form-control form-control-sm" required>
    </div>

    <div class="mb-3">
        <label>Nama Anggota</label>
        <select name="id_anggota" class="form-select" required>
            <option value="">-- Pilih Anggota --</option>
            <?php while ($a = $anggota_result->fetch_assoc()): ?>
                <option value="<?= $a['id_anggota'] ?>">
                    <?= $a['id_anggota'] ?> - <?= $a['nm_anggota'] ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Nomor Peminjaman</label>
        <select name="no_peminjaman" class="form-select" id="peminjamanSelect" required onchange="tampilkanTabel()">
            <option value="">-- Pilih Nomor Peminjaman --</option>
            <?php while ($p = $peminjaman_result->fetch_assoc()): ?>
                <option value="<?= $p['no_peminjaman'] ?>">
                    <?= $p['no_peminjaman'] ?> (<?= $p['id_anggota'] ?> - <?= $p['nm_anggota'] ?>)
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <!-- ✅ Table header sudah tampil sejak awal -->
    <div class="mb-3 bg-light p-3 rounded">
        <table class="table table-bordered">
            <thead>
                <tr class="table-secondary text-center">
                    <th>No</th>
                    <th>ID Buku</th>
                    <th>Judul Buku</th>
                    <th>No Copy Buku</th>
                    <th>Jumlah Kembali</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="tabelPengembalianBody">
                <!-- ✅ Body akan diisi otomatis oleh JavaScript -->
            </tbody>
        </table>
    </div>

    <button type="submit" name="simpan" class="btn btn-primary">Simpan Pengembalian</button>
    <a href="admin.php?page=perpus_utama&panggil=pengembalian.php" class="btn btn-secondary">Batal</a>
</form>

<script>
    // ✅ Data buku per nomor peminjaman
    const dataBuku = <?= json_encode($detail_buku) ?>;

    function tampilkanTabel() {
        const noPeminjaman = document.getElementById('peminjamanSelect').value;
        const tbody = document.getElementById('tabelPengembalianBody');

        tbody.innerHTML = '';

        if (!noPeminjaman || !dataBuku[noPeminjaman]) {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="6" class="text-center text-danger">Tidak ada data buku untuk nomor peminjaman ini.</td>`;
            tbody.appendChild(tr);
            return;
        }

        dataBuku[noPeminjaman].forEach((buku, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
            <td class="text-center">${index + 1}</td>
            <td>${buku.id_buku}</td>
            <td>${buku.judul_buku}</td>
            <td>
                <input type="hidden" name="no_copy_buku[]" value="${buku.no_copy_buku}">
                ${buku.no_copy_buku}
            </td>
            <td>
                <input type="number" 
                       name="jumlah_kembali[]" 
                       class="form-control form-control-sm" 
                       min="1" 
                       max="${buku.jml_pinjam}" 
                       value="${buku.jml_pinjam}" 
                       required>
                <input type="hidden" name="jumlah_max[]" value="${buku.jml_pinjam}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm btn-hapus">-</button>
            </td>
        `;

            tbody.appendChild(tr);
        });

        // Tambahkan event tombol hapus
        tbody.querySelectorAll('.btn-hapus').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.target.closest('tr').remove();
            });
        });
    }
</script>