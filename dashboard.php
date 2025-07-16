<?php $plugin_url = plugin_dir_url(__FILE__); ?>

<div class="container mt-4">
    <h4 class="mb-3">DASHBOARD</h4>
    <nav aria-label="breadcrumb">
    </nav>

    <div class="row g-4 mt-2">
        <!-- Peminjaman -->
        <div class="col-md-4">
            <a href="admin.php?page=perpus_utama&panggil=peminjaman.php" class="text-decoration-none">
                <div class="card bg-primary text-white text-center shadow card-dashboard">
                    <div class="card-body py-5">
                        <i class="fas fa-book fa-2x mb-3"></i>
                        <h5 class="card-title">Peminjaman</h5>
                    </div>
                </div>
            </a>
        </div>

        <!-- Pengembalian -->
        <div class="col-md-4">
            <a href="admin.php?page=perpus_utama&panggil=pengembalian.php" class="text-decoration-none">
                <div class="card bg-success text-white text-center shadow card-dashboard">
                    <div class="card-body py-5">
                        <i class="fas fa-undo-alt fa-2x mb-3"></i>
                        <h5 class="card-title">Pengembalian</h5>
                    </div>
                </div>
            </a>
        </div>

        <!-- Kunjungan -->
        <div class="col-md-4">
            <a href="admin.php?page=perpus_utama&panggil=kunjungan.php" class="text-decoration-none">
                <div class="card bg-warning text-dark text-center shadow card-dashboard">
                    <div class="card-body py-5">
                        <i class="fas fa-user fa-2x mb-3"></i>
                        <h5 class="card-title">Kunjungan</h5>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<style>
    .card-dashboard {
        transition: transform 0.3s ease;
        border-radius: 15px;
    }
    .card-dashboard:hover {
        transform: scale(1.05);
    }
    .breadcrumb {
        background: none;
        padding-left: 0;
    }
</style>
