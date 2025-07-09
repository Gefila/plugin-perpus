<?php

// Proses simpan data kunjungan
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_kunjungan = "KJ" . time(); // ID kunjungan otomatis dengan prefix dan timestamp
    $tgl_kunjungan = $_POST['tgl_kunjungan'];
    $tujuan = $_POST['tujuan'];
    $id_anggota = $_POST['id_anggota'];

    $sqlInsert = "INSERT INTO kunjungan (id_kunjungan, tgl_kunjungan, tujuan, id_anggota)
                  VALUES ('$id_kunjungan', '$tgl_kunjungan', '$tujuan', '$id_anggota')";

    if ($conn->query($sqlInsert)) {
        echo '
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Data kunjungan berhasil ditambahkan.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>';
    } else {
        echo '
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            Gagal menyimpan data: ' . htmlspecialchars($conn->error) . '
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>';
    }
}

// Ambil data anggota untuk dropdown
$anggota_result = $conn->query("SELECT * FROM anggota ORDER BY nm_anggota ASC");

// Ambil data kunjungan untuk ditampilkan
$kunjungan_result = $conn->query("SELECT k.*, a.nm_anggota FROM kunjungan k 
                                  JOIN anggota a ON k.id_anggota = a.id_anggota 
                                  ORDER BY k.tgl_kunjungan DESC");
?>

<h2 class="text-center mb-4">Form Entri Kunjungan</h2>

<form method="post" class="mb-5">
    <div class="form-row">
        <!-- ID Pengunjung -->
        <div class="form-group col-md-2">
            <label for="id_anggota">ID Pengunjung</label>
            <select name="id_anggota" id="id_anggota" class="form-control" required onchange="isiNama()">
                <option value="">-- Pilih ID --</option>
                <?php
                $anggotaData = [];
                $anggota_result = $conn->query("SELECT * FROM anggota ORDER BY nm_anggota ASC");
                while ($row = $anggota_result->fetch_assoc()) {
                    echo "<option value='{$row['id_anggota']}'>{$row['id_anggota']}</option>";
                    $anggotaData[$row['id_anggota']] = $row['nm_anggota'];
                }
                ?>
            </select>
        </div>

        <!-- Nama Pengunjung -->
        <div class="form-group col-md-3">
            <label for="nm_anggota">Nama Pengunjung</label>
            <input type="text" class="form-control" id="nm_anggota" readonly>
        </div>

        <!-- Tanggal Kunjungan -->
        <div class="form-group col-md-3">
            <label for="tgl_kunjungan">Tanggal Kunjungan</label>
            <input type="date" name="tgl_kunjungan" id="tgl_kunjungan" class="form-control" required>
        </div>

        <!-- Tujuan -->
        <div class="form-group col-md-3">
            <label for="tujuan">Tujuan</label>
            <select name="tujuan" id="tujuan" class="form-control" required>
                <option value="">-- Pilih Tujuan --</option>
                <option value="Membaca">Membaca</option>
                <option value="Mengerjakan Tugas">Mengerjakan Tugas</option>
                <option value="Rekreasi">Rekreasi</option>
            </select>
        </div>
        <br>
        <!-- Tombol Submit -->
        <div class="form-group col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-block">Simpan</button>
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
