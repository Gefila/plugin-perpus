<?php
$conn = new mysqli("localhost", "root", "", "db_ti6b_uas");
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);


if (isset($_GET['hapus'])) {
    $id = $conn->real_escape_string($_GET['hapus']);
    $conn->query("DELETE FROM denda WHERE no_denda = '$id'");

    echo "<script>
        alert('Data denda berhasil dihapus!');
        window.location.href='admin.php?page=perpus_utama&panggil=denda.php';
    </script>";
    exit;
}


$denda = $conn->query("SELECT d.*, a.nm_anggota FROM denda d 
    LEFT JOIN pengembalian p ON d.no_pengembalian = p.no_pengembalian
    LEFT JOIN peminjaman pm ON p.no_peminjaman = pm.no_peminjaman
    LEFT JOIN anggota a ON pm.id_anggota = a.id_anggota");
?>

<style>
/* Main Container */
.perpus-denda-container {
    max-width: 100%;
    margin: 20px auto;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Header Section */
.perpus-denda-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.perpus-denda-title {
    color: #2c3e50;
    font-size: 1.8rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Add Fine Button */
.perpus-add-denda-btn {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.perpus-add-denda-btn:hover {
    background: linear-gradient(135deg, #c0392b 0%, #a5281b 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
    color: white;
}

/* Table Styling */
.perpus-denda-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.perpus-denda-table thead th {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    border: none;
    position: sticky;
    top: 0;
}

.perpus-denda-table tbody td {
    padding: 12px 15px;
    border-bottom: 1px solid #f5d5d2;
    vertical-align: middle;
}

.perpus-denda-table tbody tr:last-child td {
    border-bottom: none;
}

.perpus-denda-table tbody tr:hover {
    background-color: #fef6f5;
}

/* Fine Amount Style */
.perpus-denda-amount {
    color: #e74c3c;
    font-weight: 600;
}

/* Action Button */
.perpus-denda-action {
    text-align: center;
}

.perpus-edit-denda-btn {
    background-color:rgb(52, 164, 238);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 5px;
    font-size: 0.85rem;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.perpus-delete-edit-btn:hover {
  background-color:rgb(10, 91, 196);
  color: white;
}

.perpus-delete-denda-btn {
    background-color: #e74c3c;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 5px;
    font-size: 0.85rem;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.perpus-delete-denda-btn:hover {
    background-color: #c0392b;
    color: white;
}

/* Empty State */
.perpus-denda-empty {
    text-align: center;
    padding: 30px;
    color: #7f8c8d;
    background-color: #f9f9f9;
    border-radius: 0 0 10px 10px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .perpus-denda-table thead {
        display: none;
    }
    
    .perpus-denda-table tbody tr {
        display: block;
        margin-bottom: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .perpus-denda-table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        border-bottom: 1px solid #eee;
    }
    
    .perpus-denda-table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #7f8c8d;
        margin-right: 15px;
    }
    
    .perpus-denda-action {
        justify-content: flex-end;
    }
}
</style>

<div class="perpus-denda-container">
    <div class="perpus-denda-header">
        <h2 class="perpus-denda-title">
            <i class="fas fa-money-bill-wave"></i> Data Denda Perpustakaan
        </h2>
        <a href="admin.php?page=perpus_utama&panggil=tambah_denda.php" class="perpus-add-denda-btn">
            <i class="fas fa-plus"></i> Tambah Denda
        </a>
    </div>

    <table class="perpus-denda-table">
        <thead>
            <tr>
                <th>No</th>
                <th>No Denda</th>
                <th>Nama Anggota</th>
                <th>Tarif</th>
                <th>Alasan</th>
                <th>Tanggal Pengembalian</th>
                <th class="perpus-denda-action">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; 
            if ($denda && $denda->num_rows > 0) {
                while ($row = $denda->fetch_assoc()): 
            ?>
                <tr>
                    <td data-label="No"><?= $no++ ?></td>
                    <td data-label="No Denda"><?= htmlspecialchars($row['no_denda']) ?></td>
                    <td data-label="Nama Anggota"><?= htmlspecialchars($row['nm_anggota']) ?></td>
                    <td data-label="Tarif" class="perpus-denda-amount">
                        Rp<?= number_format($row['tarif_denda'], 0, ',', '.') ?>
                    </td>
                    <td data-label="Alasan"><?= htmlspecialchars($row['alasan_denda']) ?></td>
                    <td data-label="Tanggal"><?= date('d-m-Y', strtotime($row['tgl_denda'])) ?></td>
                    <td data-label="Aksi" class="perpus-denda-action">
                   <a href="admin.php?page=perpus_utama&panggil=denda.php&hapus=<?= $row['no_denda'] ?>"
                           class="perpus-edit-denda-btn">
                           <i class="fa fa-edit"></i> Edit
                          </a>
                        <a href="admin.php?page=perpus_utama&panggil=denda.php&hapus=<?= $row['no_denda'] ?>"
                           class="perpus-delete-denda-btn"
                           onclick="return confirm('Yakin ingin menghapus data denda ini?')">
                            <i class="fas fa-trash"></i> Hapus
                        </a>
                    </td>
                </tr>
            <?php 
                endwhile;
            } else {
                echo '<tr><td colspan="7" class="perpus-denda-empty">
                        <i class="fas fa-money-bill-alt" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>Tidak ada data denda yang ditemukan</p>
                      </td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>
