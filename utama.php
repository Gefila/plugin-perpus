<?php
/*
  Plugin Name: SI Perpustakaan
  Description: Plugin CRUD kelompok UAS, Terdiri dari 6 Ksatria yang ingin berusaha menjadi ahli coding
  Author: Junai,Gefila,Jonathan,Allan,Valen,Rado
  Plugin URI: https://id.wordpress.org/plugins/perpus
  Version: 1.0.0
*/

function perpus_modulku() {
    $plugin_url = plugin_dir_url(__FILE__);

    // Buat koneksi database sekali, pakai konstanta WP untuk host dan user database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }
?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        #wpcontent {
            padding: 0 !important;
        }
        .form-select {
            width: 100% !important;
        }
    </style>

    <nav class="navbar navbar-expand-sm bg-dark navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="<?= $plugin_url; ?>/pp1.png" alt="" style="width: 65px; height: 65px; border-radius: 50%;">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="collapsibleNavbar">
                <ul class="navbar-nav">

                    <!-- Dropdown Master -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Master</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="admin.php?page=perpus_utama&panggil=kategori.php">Entry Data Kategori</a></li>
                            <li><a class="dropdown-item" href="admin.php?page=perpus_utama&panggil=buku.php">Entry Data Buku</a></li>
                            <li><a class="dropdown-item" href="admin.php?page=perpus_utama&panggil=anggota.php">Entry Data Anggota</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown Transaksi -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Transaksi</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="admin.php?page=perpus_utama&panggil=peminjaman.php">Entry Peminjaman</a></li>
                            <li><a class="dropdown-item" href="admin.php?page=perpus_utama&panggil=pengembalian.php">Entry Pengembalian</a></li>
                            <li><a class="dropdown-item" href="admin.php?page=perpus_utama&panggil=denda.php">Entry Denda</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown Tambahan -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Cetak</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Cetak Laporan Peminja</a></li>
                            <li><a class="dropdown-item" href="#">Cetak Laporan Pengembalian</a></li>
                        </ul>
                    </li>

                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-3">
        <?php
        if (isset($_GET["panggil"])) {
            $file = sanitize_text_field($_GET["panggil"]);
            $path = plugin_dir_path(__FILE__) . $file;
            if (file_exists($path)) {
                include($path);
            } else {
                echo '<div class="alert alert-danger">File tidak ditemukan: ' . htmlspecialchars($file) . '</div>';
            }
        } else {
            echo '<h3>Selamat datang di Plugin Perpustakaan</h3>';
        }
        ?>
    </div>
<?php
}

function perpus_tambah_menu() {
    add_menu_page(
        'SI Perpustakaan 6 Ksatria', // Page title
        'Perpustakaan',              // Menu title
        'read',                      // Capability
        'perpus_utama',              // Menu slug
        'perpus_modulku',            // Callback function
        'dashicons-book-alt',        // Icon
        81                           // Position
    );

    add_submenu_page(
        'perpus_utama',
        'Entry Data Kategori',
        'Entry Data Kategori',
        'read',
        'perpus_utama&panggil=kategori.php',
        'perpus_modulku'
    );

    add_submenu_page(
        'perpus_utama',
        'Entry Data Buku',
        'Entry Data Buku',
        'read',
        'perpus_utama&panggil=buku.php',
        'perpus_modulku'
    );

    add_submenu_page(
        'perpus_utama',
        'Entry Data Anggota',
        'Entry Data Anggota',
        'read',
        'perpus_utama&panggil=anggota.php',
        'perpus_modulku'
    );

    add_submenu_page(
        'perpus_utama',
        'Entry Peminjaman',
        'Entry Peminjaman',
        'read',
        'perpus_utama&panggil=peminjaman.php',
        'perpus_modulku'
    );

    add_submenu_page(
        'perpus_utama',
        'Entry Pengembalian',
        'Entry Pengembalian',
        'read',
        'perpus_utama&panggil=pengembalian.php',
        'perpus_modulku'
    );

    add_submenu_page(
        'perpus_utama',
        'Entry Denda',
        'Entry Denda',
        'read',
        'perpus_utama&panggil=denda.php',
        'perpus_modulku'
    );
}
add_action('admin_menu', 'perpus_tambah_menu');
?>