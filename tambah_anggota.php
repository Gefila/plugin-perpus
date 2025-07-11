<?php

// Fungsi generate ID anggota otomatis (opsional)
function generateIdAnggota($conn) {
    $result = $conn->query("SELECT id_anggota FROM anggota WHERE id_anggota LIKE 'A%' ORDER BY CAST(SUBSTRING(id_anggota, 2) AS UNSIGNED) DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $num = (int) substr($row['id_anggota'], 1);
        $num++;
    } else {
        $num = 1;
    }
    return "A" . $num;
}

// Ambil data jika mode edit
$editData = null;
if (isset($_GET['edit'])) {
    $idEdit = $conn->real_escape_string($_GET['edit']);
    $resultEdit = $conn->query("SELECT * FROM anggota WHERE id_anggota = '$idEdit'");
    if ($resultEdit && $resultEdit->num_rows > 0) {
        $editData = $resultEdit->fetch_assoc();
    }
}

$idAnggota = $editData ? $editData['id_anggota'] : generateIdAnggota($conn);

// Proses simpan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id     = $_POST['id_anggota'];
    $nama   = $_POST['nm_anggota'];
    $kelas  = $_POST['kelas'];
    $jk     = $_POST['jenis_kelamin'];

    // Cek apakah data sudah ada
    $cek = $conn->query("SELECT * FROM anggota WHERE id_anggota = '$id'");
    if ($cek->num_rows > 0) {
        // Update
        $sql = "UPDATE anggota SET nm_anggota='$nama', kelas='$kelas', jenis_kelamin='$jk' WHERE id_anggota='$id'";
    } else {
        // Tambah baru
        $sql = "INSERT INTO anggota (id_anggota, nm_anggota, kelas, jenis_kelamin) VALUES ('$id', '$nama', '$kelas', '$jk')";
    }

    if ($conn->query($sql)) {
        echo '<div class="alert alert-success">Data berhasil disimpan.</div>';
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=anggota.php">';
    } else {
        echo '<div class="alert alert-danger">Gagal menyimpan data: ' . $conn->error . '</div>';
    }
}
?>

<style>
.perpus-member-form-container {
    max-width: 750px;
    margin: 2rem auto;
    background: white;
    border-radius: 13px;
    box-shadow: 0 4px 20px rgba(19, 19, 19, 0.08);
    overflow: hidden;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.perpus-member-form-header {
    background: linear-gradient(135deg,rgb(59, 165, 236) 0%, #2980b9 100%);
    color: white;
    padding: 1.5rem;
    text-align: center;
    margin-bottom: 1.5rem;
}

.perpus-member-form-header h3 {
    margin: 0;
    font-weight: 600;
    font-size: 1.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.perpus-member-form-body {
    padding: 0 2rem 2rem;
}

..perpus-form-group {
    margin-bottom: 1.5rem;
}

.perpus-form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #2c3e50;
}

.perpus-form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #d1d3e2;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background-color: #f8f9fa;
}

.perpus-form-input:focus {
    border-color:rgb(49, 148, 214);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    outline: none;
    background-color: white;
}
.perpus-form-select:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    outline: none;
}

.perpus-form-btn-group {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.perpus-form-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: none;
}

.perpus-form-btn-primary {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    color: white;
}

.perpus-form-btn-primary:hover {
    background: linear-gradient(135deg, #219955 0%, #27ae60 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.perpus-form-btn-secondary {
    background: #f8f9fa;
    color: #7f8c8d;
    border: 1px solid #d1d3e2;
}

.perpus-form-btn-secondary:hover {
    background: #e2e6ea;
    color: #5a6268;
}

/* Alert Messages */
.perpus-form-alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.perpus-form-alert-success {
    background-color: #d1f3e6;
    color: #1cc88a;
    border-left: 4px solid #1cc88a;
}

.perpus-form-alert-danger {
    background-color: #fadbd8;
    color: #e74a3b;
    border-left: 4px solid #e74a3b;
}

/* Responsive Design */
@media (max-width: 768px) {
    .perpus-member-form-container {
        margin: 1rem;
    }
    
    .perpus-member-form-body {
        padding: 0 1.5rem 1.5rem;
    }
    
    .perpus-form-btn-group {
        flex-direction: column;
    }
    
    .perpus-form-btn {
        width: 100%;
    }
}


    </style>

<div class="perpus-member-form-container">
    <div class="perpus-member-form-header">
        <h3>
            <i class="fas fa-user-edit"></i>
            <?= $editData ? 'Edit Data Anggota' : 'Tambah Anggota Baru' ?>
        </h3>
    </div>
    
    <div class="perpus-member-form-body">
        <?php if (isset($_SESSION['message'])) : ?>
            <div class="perpus-form-alert perpus-form-alert-<?= $_SESSION['message_type'] ?>">
                <i class="fas <?= $_SESSION['message_type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <form method="POST">
            <div class="perpus-form-group">
                <label class="perpus-form-label">ID Anggota</label>
                <input type="text" name="id_anggota" class="perpus-form-input" 
                       value="<?= htmlspecialchars($idAnggota) ?>" readonly>
                <small class="text-muted">ID akan digenerate otomatis</small>
            </div>
            
            <div class="perpus-form-group">
                <label class="perpus-form-label">Nama Lengkap <span class="text-danger">*</span></label>
                <input type="text" name="nm_anggota" class="perpus-form-input" required
                       value="<?= $editData ? htmlspecialchars($editData['nm_anggota']) : '' ?>"
                       placeholder="Masukkan nama lengkap">
            </div>
            
            <div class="perpus-form-group">
                <label class="perpus-form-label">Kelas <span class="text-danger">*</span></label>
                <input type="number" name="kelas" class="perpus-form-input" required
                       value="<?= $editData ? htmlspecialchars($editData['kelas']) : '' ?>"
                       placeholder="Masukkan kelas (angka saja)">
            </div>
            
            <div class="perpus-form-group">
                <label class="perpus-form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                <select name="jenis_kelamin" class="perpus-form-select" required>
                    <option value="">-- Pilih Jenis Kelamin --</option>
                    <option value="L" <?= ($editData && $editData['jenis_kelamin'] == 'L') ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= ($editData && $editData['jenis_kelamin'] == 'P') ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </div>
            
            <div class="perpus-form-btn-group">
                <button type="submit" class="perpus-form-btn perpus-form-btn-primary">
                    <i class="fas fa-save"></i>
                    <?= $editData ? 'Update' : 'Simpan' ?>
                </button>
                <a href="?page=perpus_utama&panggil=anggota.php" class="perpus-form-btn perpus-form-btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>