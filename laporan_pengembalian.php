<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet" />

<div class="container my-5">
    <h2 class="text-center text-primary fw-bold mb-4">Laporan Pengembalian Buku</h2>

    <form action="<?= plugin_dir_url(__FILE__) ?>cetak_laporan_pengembalian.php" method="get" class="mb-4" target="_blank">
        <div class="row g-3 align-items-end justify-content-center">
            <div class="col-md-3">
                <label for="tgl_mulai" class="form-label text-primary fw-semibold">Dari Tanggal:</label>
                <input type="date" name="tgl_mulai" id="tgl_mulai" class="form-control" 
                    style="border: 2px solid #3498db; border-radius: 8px; background: #f0f6ff;" required>
            </div>
            <div class="col-md-3">
                <label for="tgl_selesai" class="form-label text-primary fw-semibold">Sampai Tanggal:</label>
                <input type="date" name="tgl_selesai" id="tgl_selesai" class="form-control" 
                    style="border: 2px solid #3498db; border-radius: 8px; background: #f0f6ff;" required>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary btn-glow" style="border-radius: 8px;">
                    <i class="fa fa-print me-1"></i> Cetak Laporan
                </button>
            </div>
        </div>
    </form>
</div>
