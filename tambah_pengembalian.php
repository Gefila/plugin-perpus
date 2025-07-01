<div class="container mt-4">
    <h3 class="mb-4 text-center">Tambah Pengembalian</h3>
    <form method="POST" action="" class="border p-4 bg-light rounded">
        <div class="row mb-3">
            <div class="col-md-4">
                <label>Tanggal Pengembalian:</label>
                <input type="date" name="tanggal_kembali" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label>No. Peminjaman:</label>
                <input type="text" name="id_peminjaman" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label>ID Anggota:</label>
                <input type="text" name="id_anggota" class="form-control" required>
            </div>
        </div>

        <hr>

        <h5>Daftar Buku Dikembalikan</h5>
        <table class="table table-bordered mt-3">
            <thead class="table-secondary">
                <tr>
                    <th>No. ID Buku</th>
                    <th>Judul Buku</th>
                    <th>Jumlah</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="buku-list">
                <!-- Baris buku akan ditambahkan lewat JS -->
            </tbody>
        </table>

        <div class="d-flex justify-content-end">
            <button type="button" class="btn btn-success mb-3" onclick="tambahBuku()">
                <i class="fas fa-plus"></i> Tambah Buku
            </button>
        </div>

        <button type="submit" class="btn btn-primary">Simpan Pengembalian</button>
        <a href="admin.php?page=perpus_utama&panggil=pengembalian.php" class="btn btn-secondary">Batal</a>
    </form>
</div>

<script>
let bukuIndex = 0;

function tambahBuku() {
    const tbody = document.getElementById('buku-list');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" name="buku[${bukuIndex}][id_buku]" class="form-control" required></td>
        <td><input type="text" name="buku[${bukuIndex}][judul_buku]" class="form-control" required></td>
        <td><input type="number" name="buku[${bukuIndex}][jumlah]" class="form-control" min="1" required></td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="hapusBarisIni(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(row);
    bukuIndex++;
}

function hapusBarisIni(btn) {
    const row = btn.closest('tr');
    row.remove();
}
</script>