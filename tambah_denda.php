<?php
// Fungsi Generate ID Otomatis
function generateIdDenda($conn) {
    $result = $conn->query("SELECT id_denda FROM denda ORDER BY CAST(SUBSTRING(id_denda, 2) AS UNSIGNED) DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $num = (int) substr($row['id_denda'], 1) + 1;
    } else {
        $num = 1;
    }
    return "D" . $num;
}

$idDenda = $tarifDenda = $alasanDenda = '';
$isEdit = false;

// Cek apakah sedang dalam mode edit
if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT * FROM denda WHERE id_denda = '$id'");
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $idDenda = $data['id_denda'];
        $tarifDenda = $data['tarif_denda'];
        $alasanDenda = $data['alasan_denda'];
        $isEdit = true;
    }
} else {
    $idDenda = generateIdDenda($conn); // untuk tambah baru
}

// Proses simpan data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idDenda = $conn->real_escape_string($_POST['idDenda']);
    $tarifDenda = $conn->real_escape_string($_POST['tarifDenda']);
    $alasanDenda = $conn->real_escape_string($_POST['alasanDenda']);

    if (empty($idDenda) || empty($tarifDenda) || empty($alasanDenda)) {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Semua field harus diisi.</div>';
    } else {
        if (isset($_POST['editMode']) && $_POST['editMode'] === '1') {
            // Mode Edit
            $sql = "UPDATE denda SET tarif_denda='$tarifDenda', alasan_denda='$alasanDenda' WHERE id_denda='$idDenda'";
        } else {
            // Mode Tambah
            $sql = "INSERT INTO denda (id_denda, tarif_denda, alasan_denda) VALUES ('$idDenda', '$tarifDenda', '$alasanDenda')";
        }

        if ($conn->query($sql)) {
            echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Data berhasil disimpan.</div>';
            echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=denda.php">';
            exit;
        } else {
            echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Gagal menyimpan data: ' . $conn->error . '</div>';
        }
    }
}
?>

<!-- Bootstrap & Font Awesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
<!-- Custom CSS -->
<link href="<?php echo plugins_url('perpus-style.css', __FILE__); ?>" rel="stylesheet" />

<div class="container my-5">
    <div class="card shadow-lg">
        <div class="card-header bg-danger text-white">
            <h4 class="mb-0">
                <i class="fas fa-money-bill-wave me-2"></i>
                <?= $isEdit ? 'Edit Denda' : 'Tambah Denda Baru' ?>
            </h4>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="editMode" value="<?= $isEdit ? '1' : '0' ?>">

                <div class="mb-3">
                    <label for="idDenda" class="form-label fw-semibold text-danger">ID Denda</label>
                    <input type="text" class="form-control" id="idDenda" name="idDenda" value="<?= htmlspecialchars($idDenda) ?>" readonly>
                    <div class="form-text">ID otomatis</div>
                </div>

                <div class="mb-3">
                    <label for="tarifDenda" class="form-label fw-semibold text-danger">Tarif Denda</label>
                    <input type="text" class="form-control" id="tarifDenda" name="tarifDenda" maxlength="10" placeholder="Contoh: 5000" value="<?= htmlspecialchars($tarifDenda) ?>" required>
                    <div class="form-text">Masukkan angka tanpa titik/koma</div>
                </div>

                <div class="mb-3">
                    <label for="alasanDenda" class="form-label fw-semibold text-danger">Alasan Denda</label>
                    <textarea class="form-control" id="alasanDenda" name="alasanDenda" rows="3" placeholder="Tulis alasan dikenakan denda" required><?= htmlspecialchars($alasanDenda) ?></textarea>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="?page=perpus_utama&panggil=denda.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
