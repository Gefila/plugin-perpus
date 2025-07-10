<?php

// Fungsi generate ID kategori otomatis
function generateIdKategori($conn) {
    $result = $conn->query("SELECT id_kategori FROM kategori WHERE id_kategori LIKE 'K%' ORDER BY CAST(SUBSTRING(id_kategori, 2) AS UNSIGNED) DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastId = $row['id_kategori']; // contoh: K12
        $num = (int) substr($lastId, 1);
        $num++;
    } else {
        $num = 1;
    }
    return "K" . $num;
}

// Ambil data edit jika ada
$editData = null;
if (isset($_GET['edit'])) {
    $idEdit = $conn->real_escape_string($_GET['edit']);
    $resultEdit = $conn->query("SELECT * FROM kategori WHERE id_kategori = '$idEdit'");
    if ($resultEdit && $resultEdit->num_rows > 0) {
        $editData = $resultEdit->fetch_assoc();
    }
}

// Jika bukan edit, generate ID baru
$idKategoriOtomatis = $editData ? $editData['id_kategori'] : generateIdKategori($conn);

// Handle simpan data (insert atau update)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Saat submit, jika edit maka ambil dari form, jika tambah pakai ID otomatis
    $idKategori = $editData ? $conn->real_escape_string($_POST['idKategori']) : $idKategoriOtomatis;
    $nmKategori = $conn->real_escape_string($_POST['nmKategori']);

    if (empty($idKategori)) {
        echo '<div class="alert alert-danger">ID Kategori harus diisi.</div>';
    } else {
        $cek = $conn->query("SELECT * FROM kategori WHERE id_kategori = '$idKategori'");
        if ($cek->num_rows > 0) {
            // Update
            $sql = "UPDATE kategori SET nm_kategori = '$nmKategori' WHERE id_kategori = '$idKategori'";
        } else {
            // Insert baru
            $sql = "INSERT INTO kategori (id_kategori, nm_kategori) VALUES ('$idKategori', '$nmKategori')";
        }

        if ($conn->query($sql)) {
            echo '<div class="alert alert-success">Data berhasil disimpan.</div>';
            echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=kategori.php">';
            exit;
        } else {
            echo '<div class="alert alert-danger">Gagal menyimpan data: ' . $conn->error . '</div>';
        }
    }
}
?>

<style>
/* Main Form Container */
.perpus-form-container {
    max-width: 600px;
    margin: 2rem auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

/* Form Header */
.perpus-form-header {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
    padding: 1.5rem;
    text-align: center;
    margin-bottom: 2rem;
}

.perpus-form-header h2 {
    margin: 0;
    font-weight: 600;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

/* Form Body */
.perpus-form-body {
    padding: 0 2rem 2rem;
}

/* Input Groups */
.perpus-input-group {
    margin-bottom: 1.5rem;
    position: relative;
}

.perpus-input-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #4e73df;
}

.perpus-input-wrapper {
    display: flex;
    border: 1px solid #d1d3e2;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.perpus-input-wrapper:focus-within {
    border-color: #4e73df;
    box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.25);
}

.perpus-input-icon {
    padding: 0.75rem 1rem;
    background-color: #f8f9fc;
    color: #4e73df;
    display: flex;
    align-items: center;
    border-right: 1px solid #d1d3e2;
}

.perpus-input-field {
    flex: 1;
    padding: 0.75rem 1rem;
    border: none;
    outline: none;
    background-color: white;
}

.perpus-input-field:focus {
    box-shadow: none;
}

/* Button Styles */
.perpus-btn-group {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.perpus-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.perpus-btn-primary {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
}

.perpus-btn-primary:hover {
    background: linear-gradient(135deg, #3e63cf 0%, #123aae 100%);
    transform: translateY(-2px);
}

.perpus-btn-secondary {
    background: #f8f9fc;
    color: #4e73df;
    border: 1px solid #d1d3e2;
}

.perpus-btn-secondary:hover {
    background: #e2e6ea;
    color: #4e73df;
}

/* Alert Messages */
.perpus-alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.perpus-alert-success {
    background-color: #d1f3e6;
    color: #1cc88a;
    border-left: 4px solid #1cc88a;
}

.perpus-alert-danger {
    background-color: #fadbd8;
    color: #e74a3b;
    border-left: 4px solid #e74a3b;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .perpus-form-container {
        margin: 1rem;
    }
    
    .perpus-form-body {
        padding: 0 1.5rem 1.5rem;
    }
    
    .perpus-btn-group {
        flex-direction: column;
    }
    
    .perpus-btn {
        width: 100%;
    }
}
</style>

<div class="perpus-form-container">
    <div class="perpus-form-header">
        <h2>
            <i class="fas fa-tag"></i>
            <?= isset($editData) ? "Edit Kategori Buku" : "Tambah Kategori Baru" ?>
        </h2>
    </div>
    
    <div class="perpus-form-body">
        <?php if (isset($_SESSION['message'])) : ?>
            <div class="perpus-alert perpus-alert-<?= $_SESSION['message_type'] ?>">
                <i class="fas <?= $_SESSION['message_type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <form method="POST">
            <div class="perpus-input-group">
                <label for="idKategori">ID Kategori</label>
                <div class="perpus-input-wrapper">
                    <span class="perpus-input-icon">
                        <i class="fas fa-id-card"></i>
                    </span>
                    <input type="text" maxlength="4" class="perpus-input-field" id="idKategori" name="idKategori" required
                        value="<?= htmlspecialchars($idKategoriOtomatis) ?>" readonly>
                </div>
                <small class="text-muted">ID akan digenerate otomatis</small>
            </div>
            
            <div class="perpus-input-group">
                <label for="nmKategori">Nama Kategori</label>
                <div class="perpus-input-wrapper">
                    <span class="perpus-input-icon">
                        <i class="fas fa-book-open"></i>
                    </span>
                    <input type="text" maxlength="30" class="perpus-input-field" id="nmKategori" name="nmKategori" required
                        value="<?= isset($editData['nm_kategori']) ? htmlspecialchars($editData['nm_kategori']) : '' ?>"
                        placeholder="Masukkan nama kategori">
                </div>
                <small class="text-muted">Maksimal 30 karakter</small>
            </div>
            
            <div class="perpus-btn-group">
                <button type="submit" class="perpus-btn perpus-btn-primary">
                    <i class="fas fa-save"></i>
                    <?= isset($editData) ? 'Update' : 'Simpan' ?>
                </button>
                <a href="?page=perpus_utama&panggil=kategori.php" class="perpus-btn perpus-btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>