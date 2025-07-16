<?php

// Generate ID anggota otomatis (contoh sederhana)
function generateIdAnggota($conn) {
    $result = $conn->query("SELECT id_anggota FROM anggota ORDER BY id_anggota DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastId = $row['id_anggota']; // misal format A1, A2, dst
        $num = (int)substr($lastId, 1);
        $num++;
        return "A" . $num;
    } else {
        return "A1";
    }
}

$isEdit = isset($_GET['edit']) && !empty($_GET['edit']);
$error = "";
$success = "";

if ($isEdit) {
    $id_edit = $conn->real_escape_string($_GET['edit']);
    $query_edit = $conn->query("SELECT * FROM anggota WHERE id_anggota = '$id_edit'");

    if ($query_edit && $query_edit->num_rows > 0) {
        $data = $query_edit->fetch_assoc();
        $id_anggota = $data['id_anggota'];
        $nm_anggota = $data['nm_anggota'];
        $kelas = $data['kelas'];
        $jenis_kelamin = $data['jenis_kelamin'];
    } else {
        $error = "Data anggota tidak ditemukan.";
        $isEdit = false;
    }
} else {
    // Mode Tambah
    $id_anggota = generateIdAnggota($conn);
    $nm_anggota = "";
    $kelas = "";
    $jenis_kelamin = "";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_anggota = $conn->real_escape_string($_POST['id_anggota']);
    $nm_anggota = $conn->real_escape_string($_POST['nm_anggota']);
    $kelas = (int)$_POST['kelas'];
    $jenis_kelamin = $conn->real_escape_string($_POST['jenis_kelamin']);

    if (empty($nm_anggota) || $kelas < 1 || $kelas > 7 || !in_array($jenis_kelamin, ['L', 'P'])) {
        $error = "Mohon isi semua data dengan benar.";
    } else {
        if ($isEdit) {
            $sql = "UPDATE anggota SET nm_anggota='$nm_anggota', kelas=$kelas, jenis_kelamin='$jenis_kelamin' WHERE id_anggota='$id_anggota'";
        } else {
            $sql = "INSERT INTO anggota (id_anggota, nm_anggota, kelas, jenis_kelamin) VALUES ('$id_anggota', '$nm_anggota', $kelas, '$jenis_kelamin')";
        }

        if ($conn->query($sql)) {
            $success = $isEdit ? "Data anggota berhasil diupdate." : "Data anggota berhasil disimpan.";
            echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=anggota.php">';
        } else {
            $error = "Gagal menyimpan data: " . $conn->error;
        }
    }
}
?>

<style>
/* Main Form Container */
.perpus-form-container {
    max-width: 700px;
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

/* Select Styles */
.perpus-select-wrapper {
    position: relative;
}

.perpus-select-wrapper select {
    appearance: none;
    padding: 0.75rem 2.5rem 0.75rem 1rem;
    border: 1px solid #d1d3e2;
    border-radius: 8px;
    width: 100%;
    background-color: white;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1em;
    transition: all 0.3s ease;
}

.perpus-select-wrapper select:focus {
    border-color: #4e73df;
    box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.25);
    outline: none;
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
                <i class="fas fa-user-plus"></i>
                <?= $isEdit ? 'Edit Anggota' : 'Tambah Anggota Baru' ?>
            </h2>
    </div>
    
    <div class="perpus-form-body">
        <?php if ($error): ?>
            <div class="perpus-alert perpus-alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="perpus-alert perpus-alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $success ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="perpus-input-group">
                <label for="id_anggota">ID Anggota</label>
                <div class="perpus-input-wrapper">
                    <span class="perpus-input-icon">
                        <i class="fas fa-id-card"></i>
                    </span>
                    <input type="text" class="perpus-input-field" id="id_anggota" name="id_anggota" 
                           value="<?= htmlspecialchars($id_anggota) ?>" readonly>
                </div>
                <small class="text-muted">ID akan digenerate otomatis</small>
            </div>
            
            <div class="perpus-input-group">
                <label for="nm_anggota">Nama Lengkap <span class="text-danger">*</span></label>
                <div class="perpus-input-wrapper">
                    <span class="perpus-input-icon">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="text" class="perpus-input-field" id="nm_anggota" name="nm_anggota" required
                           placeholder="Masukkan nama lengkap" value="<?= htmlspecialchars($nm_anggota) ?>">
                </div>
            </div>
            
            <div class="perpus-input-group">
                <label for="kelas">Kelas</label>
                <select name="kelas" id="kelas" required>
                    <option value="">-- Pilih Kelas --</option>
                    <?php
                    for ($i = 1; $i <= 6; $i++) {
                        // Saat edit, tandai selected jika nilai cocok
                        $selected = (isset($data['kelas']) && $data['kelas'] == $i) ? 'selected' : '';
                        echo "<option value=\"$i\" $selected>Kelas $i</option>";
                    }
                    // Tambahan Guru / Staf
                    $selected = (isset($data['kelas']) && $data['kelas'] == 7) ? 'selected' : '';
                    echo "<option value=\"7\" $selected>Guru / Staf</option>";
                    ?>
                </select>
            </div>
            
            <div class="perpus-input-group">
                <label for="jenis_kelamin">Jenis Kelamin <span class="text-danger">*</span></label>
                <div class="perpus-select-wrapper">
                    <select id="jenis_kelamin" name="jenis_kelamin" class="form-select" required>
                        <option value="">-- Pilih Jenis Kelamin --</option>
                        <option value="L" <?= ($jenis_kelamin == 'L') ? "selected" : "" ?>>
                            Laki-laki
                        </option>
                        <option value="P" <?= ($jenis_kelamin == 'P') ? "selected" : "" ?>>
                            Perempuan
                        </option>
                    </select>
                </div>
            </div>
            
            <div class="perpus-btn-group">
                <button type="submit" class="perpus-btn perpus-btn-primary">
                    <i class="fas fa-save"></i> <?= $isEdit ? 'Update' : 'Simpan' ?>
                </button>
                <a href="?page=perpus_utama&panggil=anggota.php" class="perpus-btn perpus-btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

