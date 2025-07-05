<div class="container mt-4">
    <h3 class="text-center mb-4">LAPORAN PENGEMBALIAN BUKU</h3>

    <form action="admin.php" method="get" class="text-center">
        <input type="hidden" name="page" value="perpus_utama">
        <input type="hidden" name="panggil" value="cetak_laporan_pengembalian.php">

        <div class="row justify-content-center mb-3">
            <div class="col-md-2">
                <label><strong>Periode Transaksi:</strong></label>
            </div>
            <div class="col-md-3">
                <input type="date" name="tgl_mulai" class="form-control" required>
            </div>
            <div class="col-md-1 text-center">
                <span>s/d</span>
            </div>
            <div class="col-md-3">
                <input type="date" name="tgl_selesai" class="form-control" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-2">TAMPILKAN</button>
    </form>
</div>
