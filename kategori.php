<style>
.table-responsive::-webkit-scrollbar {
    height: 8px;
}
.table-responsive::-webkit-scrollbar-thumb {
    background: #bbb;
    border-radius: 4px;
}
.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #888;
}
</style>

<?php
// Hapus data jika ada permintaan
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $sqlDel = "DELETE FROM kategori WHERE id_kategori = '$idHapus'";
    if ($conn->query($sqlDel)) {
        echo '<div class="alert alert-success">✅ Data berhasil dihapus.</div>';
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=kategori.php">';
    } else {
        echo '<div class="alert alert-danger">❌ Gagal menghapus data.</div>';
    }
}

// Ambil data kategori
$result = $conn->query("SELECT * FROM kategori ORDER BY id_kategori");
?>

<!-- Container scrollable -->
<div class="container-fluid mt-4" style="overflow-x: auto;">
    <!-- Card mengikuti lebar tabel -->
    <div class="card shadow rounded-4" style="min-width: 1300px; width: max-content;">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center rounded-top-4">
            <h4 class="mb-0">
                <i class="fa fa-list-alt me-2"></i>Daftar Kategori
            </h4>
            <a href="admin.php?page=perpus_utama&panggil=tambah_kategori.php" class="btn btn-light btn-sm">
                <i class="fa fa-plus"></i> Tambah Kategori
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle table-bordered" style="min-width: 1200px; width: max-content;">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="min-width: 150px;">ID Kategori</th>
                            <th style="min-width: 300px;">Nama Kategori</th>
                            <th style="min-width: 200px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $id = htmlspecialchars($row['id_kategori']);
                                $nama = htmlspecialchars($row['nm_kategori']);
                                echo "<tr>
                                    <td>$no</td>
                                    <td>$id</td>
                                    <td>$nama</td>
                                    <td>
                                        <a href='?page=perpus_utama&panggil=tambah_kategori.php&edit=$id' class='btn btn-warning btn-sm me-1'>
                                            <i class='fa fa-edit'></i> Edit
                                        </a>
                                        <a href='?page=perpus_utama&panggil=kategori.php&hapus=$id' class='btn btn-danger btn-sm' onclick=\"return confirm('Yakin hapus data ini?')\">
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
</div>
