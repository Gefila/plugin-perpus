<?php

// Handle hapus data pengunjung
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $sqlDel = "DELETE FROM anggota WHERE id_anggota = '$idHapus'";
    if ($conn->query($sqlDel)) {
        echo '<div class="alert alert-success">Data anggota berhasil dihapus.</div>';
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=anggota.php">';
    } else {
        echo '<div class="alert alert-danger">Gagal menghapus data anggota.</div>';
    }
}

// Ambil semua data anggota
$result = $conn->query("SELECT * FROM anggota ORDER BY id_anggota");

?>

<style>
    .perpus-member-container{
        max-width: 100%;
        margin: 2px auto;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .perpus-member-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .perpus-member-title {
    color:rgb(42, 60, 78);
    font-size: 2.0rem;
    font-weight: 700;
    margin: 0;
}

/* Add Member Button */
    .perpus-add-btn {
        background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 9px;
        font-weight: 650;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .perpus-add-btn:hover {
        background: linear-gradient(135deg, #219955 0%, #27ae60 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        color: white;
    }

    /* Table Styling */
    .perpus-member-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .perpus-member-table thead th {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        border: none;
    }

    .perpus-member-table tbody td {
        padding: 12px 15px;
        border-bottom: 1px solid #ecf0f1;
        vertical-align: middle;
    }

    .perpus-member-table tbody tr:last-child td {
        border-bottom: none;
    }

    .perpus-member-table tbody tr:hover {
        background-color: #f8f9fa;
    }

    /* Gender Badges */
    .perpus-gender-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: capitalize;
    }

    .perpus-gender-male {
        background-color: #d6eaf8;
        color: #2874a6;
    }

    .perpus-gender-female {
        background-color: #fadbd8;
        color: #922b21;
    }

    /* Action Buttons */
    .perpus-action-group {
        display: flex;
        gap: 8px;
    }

    .perpus-edit-btn {
        background-color: #f39c12;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 5px;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }

    .perpus-edit-btn:hover {
        background-color: #d68910;
        color: white;
    }

    .perpus-delete-btn {
        background-color: #e74c3c;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 5px;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }

    .perpus-delete-btn:hover {
        background-color: #c0392b;
        color: white;
    }

    /* Empty State */
    .perpus-empty-state {
        text-align: center;
        padding: 30px;
        color: #7f8c8d;
        background-color: #f9f9f9;
        border-radius: 0 0 10px 10px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .perpus-member-table thead {
            display: none;
        }
        
        .perpus-member-table tbody tr {
            display: block;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .perpus-member-table tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .perpus-member-table tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: #7f8c8d;
            margin-right: 15px;
        }
        
        .perpus-action-group {
            justify-content: flex-end;
        }
    }
   
</style>
<div class="perpus-member-container">
    <div class="perpus-member-header">
        <h2 class="perpus-member-title">
            <i class="fas fa-users"></i> Daftar Anggota Perpustakaan
        </h2>
        <a href="admin.php?page=perpus_utama&panggil=tambah_anggota.php" class="perpus-add-btn">
            <i class="fas fa-plus"></i> Tambah Anggota
        </a>
    </div>

    <table class="perpus-member-table">
        <thead>
            <tr>
                <th>No</th>
                <th>ID Anggota</th>
                <th>Nama Anggota</th>
                <th>Kelas</th>
                <th>Jenis Kelamin</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $id     = htmlspecialchars($row['id_anggota']);
                    $nama   = htmlspecialchars($row['nm_anggota']);
                    $kelas  = htmlspecialchars($row['kelas']);
                    $jk     = $row['jenis_kelamin'] === 'L' ? 'male' : 'female';
                    $jkText = $row['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan';

                    echo "<tr>
                        <td data-label='No'>$no</td>
                        <td data-label='ID Anggota'>$id</td>
                        <td data-label='Nama Anggota'>$nama</td>
                        <td data-label='Kelas'>$kelas</td>
                        <td data-label='Jenis Kelamin'>
                            <span class='perpus-gender-badge perpus-gender-$jk'>$jkText</span>
                        </td>
                        <td data-label='Aksi'>
                            <div class='perpus-action-group'>
                                <a href='?page=perpus_utama&panggil=tambah_anggota.php&edit=$id' class='perpus-edit-btn'>
                                    <i class='fas fa-edit'></i> Edit
                                </a>
                                <a href='?page=perpus_utama&panggil=anggota.php&hapus=$id' class='perpus-delete-btn' onclick=\"return confirm('Yakin hapus data anggota ini?')\">
                                    <i class='fas fa-trash'></i> Hapus
                                </a>
                            </div>
                        </td>
                      </tr>";
                    $no++;
                }
            } else {
                echo '<tr><td colspan="6" class="perpus-empty-state">
                        <i class="fas fa-user-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>Tidak ada data anggota yang ditemukan</p>
                      </td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>