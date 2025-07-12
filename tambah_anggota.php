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

$error = "";
$success = "";

// Proses simpan data saat form disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_anggota = $conn->real_escape_string($_POST['id_anggota']);
    $nm_anggota = $conn->real_escape_string($_POST['nm_anggota']);
    $kelas      = (int)$_POST['kelas'];
    $jenis_kelamin = $conn->real_escape_string($_POST['jenis_kelamin']);

    // Validasi sederhana
    if (empty($nm_anggota) || $kelas < 1 || $kelas > 6 || !in_array($jenis_kelamin, ['L', 'P'])) {
        $error = "Mohon isi semua data dengan benar.";
    } else {
        // Insert ke database
        $sql = "INSERT INTO anggota (id_anggota, nm_anggota, kelas, jenis_kelamin) VALUES ('$id_anggota', '$nm_anggota', $kelas, '$jenis_kelamin')";
        if ($conn->query($sql)) {
            $success = "Data anggota berhasil disimpan.";
            // generate id baru untuk form selanjutnya
            $id_anggota = generateIdAnggota($conn);
            // reset input lain
            $nm_anggota = "";
            $kelas = "";
            $jenis_kelamin = "";
        } else {
            $error = "Gagal menyimpan data: " . $conn->error;
        }
    }
} else {
    // Untuk form pertama kali, generate id anggota
    $id_anggota = generateIdAnggota($conn);
    $nm_anggota = "";
    $kelas = "";
    $jenis_kelamin = "";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Tambah Anggota</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
</head>
<body>
<div class="container my-4">
    <h3><i class="fas fa-user-edit"></i> Tambah Anggota Baru</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="id_anggota" class="form-label">ID Anggota</label>
            <input type="text" id="id_anggota" name="id_anggota" class="form-control" value="<?= htmlspecialchars($id_anggota) ?>" readonly>
            <small class="text-muted">ID akan digenerate otomatis</small>
        </div>

        <div class="mb-3">
            <label for="nm_anggota" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" id="nm_anggota" name="nm_anggota" class="form-control" required placeholder="Masukkan nama lengkap" value="<?= htmlspecialchars($nm_anggota) ?>">
        </div>

        <div class="mb-3">
            <label for="kelas" class="form-label">Kelas <span class="text-danger">*</span></label>
            <select id="kelas" name="kelas" class="form-select" required>
                <option value="">-- Pilih Kelas --</option>
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <option value="<?= $i ?>" <?= ($kelas == $i) ? "selected" : "" ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="jenis_kelamin" class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
            <select id="jenis_kelamin" name="jenis_kelamin" class="form-select" required>
                <option value="">-- Pilih Jenis Kelamin --</option>
                <option value="L" <?= ($jenis_kelamin == 'L') ? "selected" : "" ?>>Laki-laki</option>
                <option value="P" <?= ($jenis_kelamin == 'P') ? "selected" : "" ?>>Perempuan</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
        <a href="?page=perpus_utama&panggil=anggota.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
    </form>
</div>
</body>
</html>
