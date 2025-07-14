<?php

// Ambil data anggota lebih awal, karena dibutuhkan sebelum proses POST
$anggota_result = $conn->query("SELECT * FROM anggota ORDER BY nm_anggota ASC");
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
/* Container Utama */
body {
    background: linear-gradient(120deg, #e0ecff 0%, #f8fcff 100%);
    min-height: 100vh;
}
.form-container {
    max-width: 900px;
    margin: 2rem auto;
    padding: 2rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Header Form */
.form-header {
    text-align: center;
    margin-bottom: 2rem;
    color: #2c3e50;
}

.form-header h2 {
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

/* Baris Form */
.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Grup Input */
.form-group {
    flex: 1;
    min-width: 200px;
}

/* Label */
.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #34495e;
    font-size: 0.95rem;
}

/* Input & Select */
.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #d1d3e2;
    border-radius: 6px;
    font-size: 1rem;
    transition: border-color 0.3s;
    background-color: #f8fafc;
}

.form-control:focus {
    border-color: #3498db;
    outline: none;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

/* Checkbox */
.checkbox-container {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 1.8rem;
    cursor: pointer;
}

.checkbox-container input {
    width: 18px;
    height: 18px;
    accent-color: #3498db;
}

/* Tombol */
.btn-group {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background-color: #3498db;
    color: white;
}

.btn-primary:hover {
    background-color: #2980b9;
}

.btn-warning {
    background-color: #f39c12;
    color: white;
}

.btn-warning:hover {
    background-color: #e67e22;
}

.btn-secondary {
    background-color: #f8f9fa;
    color: #7f8c8d;
    border: 1px solid #d1d3e2;
}

.btn-secondary:hover {
    background-color: #e2e6ea;
}

/* Responsif */
@media (max-width: 768px) {
    .form-group {
        flex: 100%;
    }
    
    .checkbox-container {
        margin-top: 0;
    }
    
    .btn-group {
        flex-direction: column;
    }
}
</style>



<h2 class="text-center mb-4"><i class="fa-solid fa-user-clock text-primary me-2"></i><?= $isEdit ? 'Edit Kunjungan' : 'Tambah Kunjungan' ?></h2>

<form method="post" class="mb-5">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id_kunjungan" value="<?= htmlspecialchars($id_kunjungan) ?>">
    <?php endif; ?>

    <div class="form-row">
        <div class="form-group col-md-2">
            <label for="is_non_anggota">
                <input type="checkbox" id="is_non_anggota" onclick="toggleMode()" <?= !$id_anggota ? '' : '' ?>>
                Non-Anggota
            </label>
        </div>

        <div class="form-group col-md-3" id="form_id_anggota">
            <label for="id_anggota">ID Pengunjung</label>
            <select name="id_anggota" id="id_anggota" class="form-control" onchange="isiNama()">
                <option value="">-- Pilih ID --</option>
                <?php foreach ($anggotaData as $id => $nama): ?>
                    <option value="<?= $id ?>" <?= $id == $id_anggota ? 'selected' : '' ?>><?= $id ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group col-md-3" id="form_nama_anggota">
            <label for="nm_anggota">Nama Pengunjung (Anggota)</label>
            <input type="text" class="form-control" id="nm_anggota" readonly value="<?= isset($anggotaData[$id_anggota]) ? htmlspecialchars($anggotaData[$id_anggota]) : '' ?>">
        </div>

        <div class="form-group col-md-3" id="form_nama_manual" style="display:none;">
            <label for="nama_pengunjung_manual">Nama Pengunjung (Non-Anggota)</label>
            <input type="text" class="form-control" name="nama_pengunjung_manual" id="nama_pengunjung_manual" value="<?= htmlspecialchars($nama_pengunjung) ?>">
        </div>

        <div class="form-group col-md-3">
            <label for="tgl_kunjungan">Tanggal Kunjungan</label>
            <input type="date" name="tgl_kunjungan" id="tgl_kunjungan" class="form-control" required value="<?= htmlspecialchars($tgl_kunjungan) ?>">
        </div>

        <div class="form-group col-md-3">
            <label for="tujuan">Tujuan</label>
            <select name="tujuan" id="tujuan" class="form-control" required>
                <option value="">-- Pilih Tujuan --</option>
                <option value="Membaca" <?= $tujuan == 'Membaca' ? 'selected' : '' ?>>Membaca</option>
                <option value="Mengerjakan Tugas" <?= $tujuan == 'Mengerjakan Tugas' ? 'selected' : '' ?>>Mengerjakan Tugas</option>
                <option value="Rekreasi" <?= $tujuan == 'Rekreasi' ? 'selected' : '' ?>>Rekreasi</option>
                 <option value="Meminjam Buku" <?= $tujuan == 'Meminjam Buku' ? 'selected' : '' ?>>Meminjam Buku</option>
            </select>
            </select>
        </div>

        <div class="form-group col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-<?= $isEdit ? 'warning' : 'primary' ?> btn-block"><?= $isEdit ? 'Update' : 'Simpan' ?></button>
        </div>
    </div>
</form>

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
window.onload = toggleMode;
</script>
