<?php
// Proses simpan denda
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal     = $conn->real_escape_string($_POST['tanggal_denda']);
    $tarif       = (int) $_POST['tarif_denda'];
    $alasan      = $conn->real_escape_string($_POST['alasan']);
    $id_kembali  = $conn->real_escape_string($_POST['id_pengembalian']);

    $query = "INSERT INTO denda (tanggal_denda, harga_denda, alasan, id_pengembalian)
              VALUES ('$tanggal', $tarif, '$alasan', '$id_kembali')";

    if ($conn->query($query)) {
        echo '<div class="alert alert-success">Denda berhasil disimpan.</div>';
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=denda.php">';
    } else {
        echo '<div class="alert alert-danger">Gagal menyimpan denda: ' . $conn->error . '</div>';
    }
}

// Ambil data pengembalian untuk dropdown
$pengembalianResult = $conn->query("SELECT p.id_pengembalian, pj.id_peminjaman, a.nm_anggota 
                                    FROM pengembalian p 
                                    JOIN peminjaman pj ON p.id_peminjaman = pj.id_peminjaman 
                                    JOIN anggota a ON pj.id_anggota = a.id_anggota 
                                    ORDER BY p.id_pengembalian DESC");
?>

<div class="container mt-4">
    <h3 class="mb-4 text-center">Denda</h3>
    <form method="POST" class="border p-4 bg-light rounded">
        <div class="mb-3">
            <label>Tanggal Denda:</label>
            <input type="date" name="tanggal_denda" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Tarif Denda (Rp):</label>
            <input type="number" name="tarif_denda" class="form-control" required min="0">
        </div>

        <div class="mb-3">
            <label>Alasan Denda:</label>
            <textarea name="alasan" class="form-control" rows="3" required></textarea>
        </div>

        <div class="mb-3">
            <label>Nomor Pengembalian:</label>
            <select name="id_pengembalian" class="form-select" required>
                <option value="">-- Pilih Nomor Pengembalian --</option>
                <?php
                if ($pengembalianResult && $pengembalianResult->num_rows > 0) {
                    while ($row = $pengembalianResult->fetch_assoc()) {
                        $idPengembalian = htmlspecialchars($row['id_pengembalian']);
                        $idPeminjaman   = htmlspecialchars($row['id_peminjaman']);
                        $namaAnggota    = htmlspecialchars($row['nm_anggota']);
                        echo "<option value='$idPengembalian'>$idPengembalian - $idPeminjaman - $namaAnggota</option>";
                    }
                } else {
                    echo "<option disabled>Data pengembalian tidak ditemukan</option>";
                }
                ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="admin.php?page=perpus_utama&panggil=denda.php" class="btn btn-secondary">Batal</a>
    </form>
</div>