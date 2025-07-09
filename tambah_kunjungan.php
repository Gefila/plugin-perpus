<?php

$id_kunjungan = '';
$tgl_kunjungan = '';
$tujuan = '';
$id_anggota = '';
$isEdit = false;

// cek mode edit
if (isset($_GET['id_kunjungan'])) {
    $isEdit = true;
    $id_kunjungan = $_GET['id_kunjungan'];
    $result = $conn->query("SELECT * FROM kunjungan WHERE id_kunjungan='$id_kunjungan'");
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $tgl_kunjungan = $data['tgl_kunjungan'];
        $tujuan = $data['tujuan'];
        $id_anggota = $data['id_anggota'];
    }
}

// proses simpan data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tgl_kunjungan = $_POST['tgl_kunjungan'];
    $tujuan = $_POST['tujuan'];
    $id_anggota = $_POST['id_anggota'];

    if (!empty($_POST['id_kunjungan'])) {
        // update
        $id_kunjungan = $_POST['id_kunjungan'];
        $sql = "UPDATE kunjungan SET tgl_kunjungan='$tgl_kunjungan', tujuan='$tujuan', id_anggota='$id_anggota' WHERE id_kunjungan='$id_kunjungan'";
        $pesan = "Data kunjungan berhasil diubah.";
    } else {
        // insert
        $id_kunjungan = "KJ" . time();
        $sql = "INSERT INTO kunjungan (id_kunjungan, tgl_kunjungan, tujuan, id_anggota) VALUES ('$id_kunjungan', '$tgl_kunjungan', '$tujuan', '$id_anggota')";
        $pesan = "Data kunjungan berhasil ditambahkan.";
    }

    if ($conn->query($sql)) {
        echo '<div class="alert alert-success" role="alert">' . $pesan . '</div>';
    } else {
        echo '<div class="alert alert-danger" role="alert">Gagal menyimpan data: ' . htmlspecialchars($conn->error) . '</div>';
    }
}

// ambil data anggota untuk dropdown
$anggota_result = $conn->query("SELECT * FROM anggota ORDER BY nm_anggota ASC");
$anggotaData = [];
while ($row = $anggota_result->fetch_assoc()) {
    $anggotaData[$row['id_anggota']] = $row['nm_anggota'];
}
?>

<h2 class="text-center mb-4"><?= $isEdit ? 'Edit Kunjungan' : 'Tambah Kunjungan' ?></h2>

<form method="post" class="mb-5">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id_kunjungan" value="<?= htmlspecialchars($id_kunjungan) ?>">
    <?php endif; ?>

    <div class="form-row">
        <div class="form-group col-md-2">
            <label for="id_anggota">ID Pengunjung</label>
            <select name="id_anggota" id="id_anggota" class="form-control" required onchange="isiNama()">
                <option value="">-- Pilih ID --</option>
                <?php foreach ($anggotaData as $id => $nama): ?>
                    <option value="<?= $id ?>" <?= $id == $id_anggota ? 'selected' : '' ?>><?= $id ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group col-md-3">
            <label for="nm_anggota">Nama Pengunjung</label>
            <input type="text" class="form-control" id="nm_anggota" readonly value="<?= isset($anggotaData[$id_anggota]) ? htmlspecialchars($anggotaData[$id_anggota]) : '' ?>">
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
</script>
