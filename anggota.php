<?php
// Hapus data anggota jika diminta
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

<!-- Bootstrap & Font Awesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

<!-- Custom Style -->
<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet" />

<div class="container my-4">
            <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="fas fa-users me-2 text-primary"></i>Daftar Anggota Perpustakaan
            </h2>
            <a href="admin.php?page=perpus_utama&panggil=tambah_anggota.php" class="btn btn-primary btn-glow">
                <i class="fas fa-plus"></i> Tambah Anggota
            </a>
        </div>
    <div class="card-glass p-4">
        <!-- Tabel -->
        <div class="table-responsive">
            <table class="table table-bordered shadow-sm mb-0 align-middle">
                <thead class="table-primary">
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 15%;">ID Anggota</th>
                        <th style="width: 35%;">Nama Anggota</th>
                        <th style="width: 15%;">Kelas</th>
                        <th style="width: 15%;">Jenis Kelamin</th>
                        <th style="width: 15%;">Aksi</th>
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
                                <td>$no</td>
                                <td>$id</td>
                                <td>$nama</td>
                                <td>$kelas</td>
                               <td><span class='perpus-gender-badge perpus-gender-$jk' style='background-color:" . ($jk === 'male' ? '#3498db' : '#f78fb3') . ";color:#fff;padding:4px 12px;border-radius:16px;display:inline-block;min-width:90px;'>$jkText</span></td>
                                <td>
                                    <a href='?page=perpus_utama&panggil=tambah_anggota.php&edit=$id' class='btn btn-warning btn-glow btn-sm me-1'>
                                        <i class='fas fa-edit'></i> Edit
                                    </a>
                                    <a href='?page=perpus_utama&panggil=anggota.php&hapus=$id' class='btn btn-danger btn-glow btn-sm' onclick=\"return confirm('Yakin hapus data anggota ini?')\">
                                        <i class='fas fa-trash '></i> Hapus
                                    </a>
                                </td>
                            </tr>";
                            $no++;
                        }
                    } else {
                        echo '<tr><td colspan="6" class="text-center text-muted py-4">
                                <i class="fas fa-user-slash" style="font-size: 2rem; margin-bottom: 10px;"></i><br>
                                Tidak ada data anggota yang ditemukan
                              </td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>
</div>
