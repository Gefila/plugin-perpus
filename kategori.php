<?php
// Hapus data jika ada permintaan
if (isset($_GET['hapus'])) {
    global $wpdb;
    $idHapus = esc_sql($_GET['hapus']);
    $wpdb->delete('kategori', ['id_kategori' => $idHapus]);

    echo '<div class="alert alert-success">✅ Data berhasil dihapus.</div>';
    echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=kategori.php">';
}

// Ambil data kategori
global $wpdb;
$results = $wpdb->get_results("SELECT * FROM kategori ORDER BY id_kategori");
?>

<!-- Bootstrap & Font Awesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<!-- Custom Style -->
<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet">

<div class="container my-4">
    <!-- Bagian Header: Judul dan Tombol -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">
                <i class="fa fa-list-alt me-2"></i>Daftar Kategori Buku
            </h4>
            <a href="?page=perpus_utama&panggil=tambah_kategori.php" class="btn btn-primary btn-glow">
                <i class="fa fa-plus"></i> Tambah Kategori
            </a>
        </div>
    <div class="card-glass p-4">
        <!-- Bagian Tabel -->
        <div class="table-responsive">
            <table class="table table-bordered shadow-sm mb-0">
                <thead>
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 20%;">ID Kategori</th>
                        <th style="width: 50%;">Nama Kategori</th>
                        <th style="width: 25%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($results)) {
                        $no = 1;
                        foreach ($results as $row) {
                            $id = esc_html($row->id_kategori);
                            $nama = esc_html($row->nm_kategori);
                            echo "<tr>
                                <td>{$no}</td>
                                <td><span class='badge-copy'>{$id}</span></td>
                                <td>{$nama}</td>
                                <td>
                                    <a href='?page=perpus_utama&panggil=tambah_kategori.php&edit={$id}' class='btn btn-warning btn-glow me-1'>
                                        <i class='fa fa-edit'></i> Edit
                                    </a>
                                    <a href='?page=perpus_utama&panggil=kategori.php&hapus={$id}' class='btn btn-danger btn-glow' onclick=\"return confirm('Yakin hapus data ini?')\">
                                        <i class='fa fa-trash'></i> Hapus
                                    </a>
                                </td>
                            </tr>";
                            $no++;
                        }
                    } else {
                        echo '<tr><td colspan="4" class="text-center text-muted">Tidak ada data kategori</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
