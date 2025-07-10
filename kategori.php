<style>
/* Custom Scrollbar for Table */
.perpus-table-responsive::-webkit-scrollbar {
    height: 8px;
}
.perpus-table-responsive::-webkit-scrollbar-thumb {
    background: #bbb;
    border-radius: 4px;
}
.perpus-table-responsive::-webkit-scrollbar-thumb:hover {
    background: #888;
}

/* Main Container */
.perpus-table-container {
    width: 100%;
    overflow-x: auto;
    margin: 20px 0;
}

/* Card Styling */
.perpus-card {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    min-width: 100%;
}

.perpus-card-header {
    background-color: #4e73df;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: none;
}

.perpus-card-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.perpus-card-body {
    padding: 0;
}

/* Table Styling */
.perpus-table {
    width: 100%;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.perpus-table thead th {
    background-color: #f8f9fa;
    color: #5a5c69;
    font-weight: 600;
    padding: 12px 15px;
    border-bottom: 1px solid #e3e6f0;
    position: sticky;
    top: 0;
}

.perpus-table tbody td {
    padding: 12px 15px;
    vertical-align: middle;
    border-bottom: 1px solid #e3e6f0;
}

.perpus-table tbody tr:last-child td {
    border-bottom: none;
}

.perpus-table tbody tr:hover {
    background-color: #f8f9fa;
}

/* Button Styling */
.perpus-btn {
    padding: 6px 12px;
    font-size: 0.875rem;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.perpus-btn-light {
    background-color: #f8f9fa;
    color: #4e73df;
    border: 1px solid #d1d3e2;
}

.perpus-btn-light:hover {
    background-color: #e2e6ea;
    color: #4e73df;
}

.perpus-btn-warning {
    background-color: #f6c23e;
    color: #fff;
    border: none;
}

.perpus-btn-warning:hover {
    background-color: #e0b43a;
    color: #fff;
}

.perpus-btn-danger {
    background-color: #e74a3b;
    color: #fff;
    border: none;
}

.perpus-btn-danger:hover {
    background-color: #d43a2b;
    color: #fff;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .perpus-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .perpus-table thead th, 
    .perpus-table tbody td {
        padding: 8px 10px;
    }
    
    .perpus-btn {
        padding: 5px 8px;
        font-size: 0.75rem;
    }
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
<div class="perpus-table-container">
    <div class="perpus-card">
        <div class="perpus-card-header">
            <h4 class="perpus-card-title">
                <i class="fa fa-list-alt me-2"></i>Daftar Kategori Buku
            </h4>
            <a href="admin.php?page=perpus_utama&panggil=tambah_kategori.php" class="perpus-btn perpus-btn-light">
                <i class="fa fa-plus"></i> Tambah Kategori
            </a>
        </div>
        <div class="perpus-card-body">
            <div class="perpus-table-responsive">
                <table class="perpus-table">
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
                                        <a href='?page=perpus_utama&panggil=tambah_kategori.php&edit=$id' class='perpus-btn perpus-btn-warning me-1'>
                                            <i class='fa fa-edit'></i> Edit
                                        </a>
                                        <a href='?page=perpus_utama&panggil=kategori.php&hapus=$id' class='perpus-btn perpus-btn-danger' onclick=\"return confirm('Yakin hapus data ini?')\">
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
