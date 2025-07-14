<?php

// Ambil data anggota lebih awal, karena dibutuhkan sebelum proses POST
$anggota_result = $conn->query("SELECT * FROM anggota ORDER BY id_anggota ASC");
$anggotaData = [];
while ($row = $anggota_result->fetch_assoc()) {
    $anggotaData[$row['id_anggota']] = $row['nm_anggota'];
}

// Variabel awal
$id_kunjungan = '';
$tgl_kunjungan = '';
$tujuan = '';
$id_anggota = '';
$nama_pengunjung = '';
$isEdit = false;

// Cek mode edit
if (isset($_GET['id_kunjungan'])) {
    $isEdit = true;
    $id_kunjungan = $_GET['id_kunjungan'];
    $result = $conn->query("SELECT * FROM kunjungan WHERE id_kunjungan='$id_kunjungan'");
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $tgl_kunjungan = $data['tgl_kunjungan'];
        $tujuan = $data['tujuan'];
        $id_anggota = $data['id_anggota'];
        $nama_pengunjung = $data['nama_pengunjung'];
    }
}

// Proses simpan data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tgl_kunjungan = $_POST['tgl_kunjungan'];
    $tujuan = $_POST['tujuan'];
    $id_anggota = $_POST['id_anggota'];
    $nama_manual = $_POST['nama_pengunjung_manual'];
    
    if (!empty($_POST['id_kunjungan'])) {
        // MODE UPDATE
        $id_kunjungan = $_POST['id_kunjungan'];

        // Jika anggota, ambil nama dari array anggota
        if ($id_anggota) {
            $nama_pengunjung = isset($anggotaData[$id_anggota]) ? $anggotaData[$id_anggota] : '';
        } else {
            $nama_pengunjung = $nama_manual;
        }

        $sql = "UPDATE kunjungan SET 
                    tgl_kunjungan='$tgl_kunjungan', 
                    tujuan='$tujuan',
                    id_anggota=" . ($id_anggota ? "'$id_anggota'" : "NULL") . ",
                    nama_pengunjung=" . ($nama_pengunjung ? "'$nama_pengunjung'" : "NULL") . "
                WHERE id_kunjungan='$id_kunjungan'";
        $pesan = "Data kunjungan berhasil diubah.";
    } else {
        // MODE INSERT
        $result = $conn->query("SELECT id_kunjungan FROM kunjungan WHERE id_kunjungan LIKE 'KJ%' ORDER BY CAST(SUBSTRING(id_kunjungan, 3) AS UNSIGNED) DESC LIMIT 1");
        $num = ($result && $result->num_rows > 0) ? ((int)substr($result->fetch_assoc()['id_kunjungan'], 2) + 1) : 1;
        $id_kunjungan = "KJ" . $num;

        // Cek apakah anggota atau non-anggota
        if ($id_anggota) {
            $nama_pengunjung = isset($anggotaData[$id_anggota]) ? $anggotaData[$id_anggota] : '';
        } else {
            $nama_pengunjung = $nama_manual;
        }

        $sql = "INSERT INTO kunjungan 
                    (id_kunjungan, tgl_kunjungan, tujuan, id_anggota, nama_pengunjung)
                VALUES 
                    ('$id_kunjungan', '$tgl_kunjungan', '$tujuan', " . 
                    ($id_anggota ? "'$id_anggota'" : "NULL") . ", " . 
                    ($nama_pengunjung ? "'$nama_pengunjung'" : "NULL") . ")";
        $pesan = "Data kunjungan berhasil ditambahkan.";
    }

    // Jalankan SQL
    if ($conn->query($sql)) {
        echo '<div class="alert alert-success" role="alert">' . $pesan . '</div>';
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=kunjungan.php">';
        exit;
    } else {
        echo '<div class="alert alert-danger" role="alert">Gagal menyimpan data: ' . htmlspecialchars($conn->error) . '</div>';
    }
}
?>

<style>
/* Main Form Container */
.perpus-form-container {
    max-width: 900px;
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

/* Checkbox Style */
.perpus-checkbox-container {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 1.5rem 0;
    cursor: pointer;
    user-select: none;
}

.perpus-checkbox-container input {
    width: 18px;
    height: 18px;
    accent-color: #4e73df;
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

/* .perpus-input-group input[type="date"] {
    max-width: 180px;
    min-width: 120px;
    width: 100%;
}

.perpus-input-group label[for="tgl_kunjungan"] + .perpus-input-wrapper {
    max-width: 220px;
} */

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

.perpus-btn-warning {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    color: white;
}

.perpus-btn-warning:hover {
    background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
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
            <i class="fas fa-user-clock"></i>
            <?= $isEdit ? 'Edit Kunjungan' : 'Tambah Kunjungan' ?>
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
            <div class="perpus-checkbox-container">
                <input type="checkbox" id="is_non_anggota" onclick="toggleMode()" <?= !$id_anggota ? '' : '' ?>>
                <label for="is_non_anggota">Non-Anggota</label>
            </div>
            
            <div class="perpus-form-row">
                <div class="perpus-input-group" id="form_id_anggota">
                    <label for="id_anggota">ID Pengunjung</label>
                    <div class="perpus-select-wrapper">
                        <select name="id_anggota" id="id_anggota" onchange="isiNama()">
                            <option value="">-- Pilih ID --</option>
                            <?php foreach ($anggotaData as $id => $nama): ?>
                                <option value="<?= $id ?>" <?= $id == $id_anggota ? 'selected' : '' ?>><?= $id ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="perpus-input-group" id="form_nama_anggota">
                    <label for="nm_anggota">Nama Pengunjung (Anggota)</label>
                    <div class="perpus-input-wrapper">
                        <span class="perpus-input-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" class="perpus-input-field" id="nm_anggota" readonly 
                               value="<?= isset($anggotaData[$id_anggota]) ? htmlspecialchars($anggotaData[$id_anggota]) : '' ?>">
                    </div>
                </div>
                
                <div class="perpus-input-group" id="form_nama_manual" style="display:<?= !$id_anggota ? 'block' : 'none' ?>;">
                    <label for="nama_pengunjung_manual">Nama Pengunjung (Non-Anggota)</label>
                    <div class="perpus-input-wrapper">
                        <span class="perpus-input-icon">
                            <i class="fas fa-user-edit"></i>
                        </span>
                        <input type="text" class="perpus-input-field" name="nama_pengunjung_manual" id="nama_pengunjung_manual" 
                               value="<?= htmlspecialchars($nama_pengunjung) ?>">
                    </div>
                </div>
            </div>
            
            <div class="perpus-form-row">
                <div class="perpus-input-group">
                    <label for="tgl_kunjungan">Tanggal Kunjungan</label>
                    <div class="perpus-input-wrapper" style="max-width: 220px;">
                        <span class="perpus-input-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </span>
                        <input type="date" class="perpus-input-field" style="max-width: 180px; min-width: 120px; width: 100%;" name="tgl_kunjungan" id="tgl_kunjungan" required 
                               value="<?= htmlspecialchars($tgl_kunjungan) ?>">
                    </div>
                </div>
                
                <div class="perpus-input-group">
                    <label for="tujuan">Tujuan</label>
                    <div class="perpus-select-wrapper">
                        <select name="tujuan" id="tujuan" required>
                            <option value="">-- Pilih Tujuan --</option>
                            <option value="Membaca" <?= $tujuan == 'Membaca' ? 'selected' : '' ?>>Membaca</option>
                            <option value="Mengerjakan Tugas" <?= $tujuan == 'Mengerjakan Tugas' ? 'selected' : '' ?>>Mengerjakan Tugas</option>
                            <option value="Rekreasi" <?= $tujuan == 'Rekreasi' ? 'selected' : '' ?>>Rekreasi</option>
                            <option value="Meminjam Buku" <?= $tujuan == 'Meminjam Buku' ? 'selected' : '' ?>>Meminjam Buku</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="perpus-btn-group">
                <a href="?page=perpus_utama&panggil=kunjungan.php" class="perpus-btn perpus-btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
                <button type="submit" class="perpus-btn <?= $isEdit ? 'perpus-btn-warning' : 'perpus-btn-primary' ?>">
                    <i class="fas fa-save"></i> <?= $isEdit ? 'Update' : 'Simpan' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const anggotaData = <?php echo json_encode($anggotaData); ?>;

function isiNama() {
    const id = document.getElementById("id_anggota").value;
    document.getElementById("nm_anggota").value = anggotaData[id] || "";
}

function toggleMode() {
    const isNon = document.getElementById("is_non_anggota").checked;
    document.getElementById("form_id_anggota").style.display = isNon ? "none" : "block";
    document.getElementById("form_nama_anggota").style.display = isNon ? "none" : "block";
    document.getElementById("form_nama_manual").style.display = isNon ? "block" : "none";
    if (isNon) {
        document.getElementById("id_anggota").value = "";
        document.getElementById("nm_anggota").value = "";
    }
}

// Atur tampilan awal berdasarkan checkbox
window.onload = function() {
    toggleMode();
    <?php if (!$isEdit): ?>
        document.getElementById('tgl_kunjungan').valueAsDate = new Date();
    <?php endif; ?>
};
</script>