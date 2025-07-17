<?php
ob_start(); // Mulai output buffering

// Ambil data kategori
$kategori = $conn->query("SELECT * FROM kategori");
$coverPath = null;


// Fungsi generate ID Buku otomatis
function generateIdBuku($conn) {
    $result = $conn->query("SELECT id_buku FROM buku WHERE id_buku LIKE 'B%' ORDER BY CAST(SUBSTRING(id_buku, 2) AS UNSIGNED) DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $num = (int) substr($row['id_buku'], 1);
        $num++;
    } else {
        $num = 1;
    }
    return "B" . $num;
}

// Ambil data jika edit
$editData = null;
if (isset($_GET['edit'])) {
    $idEdit = $conn->real_escape_string($_GET['edit']);
    $resultEdit = $conn->query("SELECT * FROM buku WHERE id_buku = '$idEdit'");
    if ($resultEdit && $resultEdit->num_rows > 0) {
        $editData = $resultEdit->fetch_assoc();
    }
}

// Gunakan ID otomatis atau ID dari data edit
$id_buku_otomatis = $editData ? $editData['id_buku'] : generateIdBuku($conn);

// Simpan data jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $id_buku     = $_POST['id_buku'];
    $judul       = $_POST['judul_buku'];
    $pengarang   = $_POST['pengarang'];
    $tahun       = $_POST['thn_terbit'];
    $jumlah      = (int) $_POST['jml_buku'];
    $penerbit    = $_POST['penerbit'];
    $id_kategori = $_POST['id_kategori'];
    $harga_buku = (int) $_POST['harga_buku'];
    // Upload cover buku (gunakan wp_upload_dir)
    // Path folder plugin
    // Path folder plugin
    $plugin_path = plugin_dir_path(__FILE__);
    $plugin_url = plugin_dir_url(__FILE__);

    $cover_dir = $plugin_path . 'cover/';
    $cover_url = $plugin_url . 'cover/';

    if (!file_exists($cover_dir)) {
        mkdir($cover_dir, 0777, true);
    }

    $cover_filename = ''; // Default jika tidak ada file

    if (isset($_FILES['cover_buku']) && $_FILES['cover_buku']['error'] == 0) {
        $cover_filename = time() . '_' . basename($_FILES['cover_buku']['name']);
        $target_file = $cover_dir . $cover_filename;

        if (move_uploaded_file($_FILES['cover_buku']['tmp_name'], $target_file)) {
            // Jika edit dan ada cover lama, hapus file lama
            if ($editData && !empty($editData['cover_buku'])) {
                $old_cover_path = $cover_dir . $editData['cover_buku'];
                if (file_exists($old_cover_path)) {
                    unlink($old_cover_path); // hapus file lama
                }
            }
        } else {
            $cover_filename = $editData['cover_buku'] ?? ''; // fallback ke lama
        }
    } elseif ($editData && isset($editData['cover_buku'])) {
        $cover_filename = $editData['cover_buku']; // pakai file lama kalau tidak upload ulang
    }

    // Cek apakah data sudah ada (edit)
    $cek = $conn->query("SELECT * FROM buku WHERE id_buku = '$id_buku'");
    if ($cek && $cek->num_rows > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE buku SET judul_buku=?, pengarang=?, thn_terbit=?, jml_buku=?, penerbit=?, id_kategori=?, harga_buku=?, cover_buku=? WHERE id_buku=?");
        $stmt->bind_param("sssssssss", $judul, $pengarang, $tahun, $jumlah, $penerbit, $id_kategori, $harga_buku, $cover_filename, $id_buku);
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO buku (id_buku, judul_buku, pengarang, thn_terbit, jml_buku, penerbit, id_kategori, harga_buku, cover_buku) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $id_buku, $judul, $pengarang, $tahun, $jumlah, $penerbit, $id_kategori, $harga_buku, $cover_filename);
    }


    if ($stmt->execute()) {
        if ($cek->num_rows == 0) {
            // Tambah copy_buku baru (jika insert buku baru)
            for ($i = 1; $i <= $jumlah; $i++) {
                $no_copy = $id_buku . '-CB' . $i;
                $conn->query("INSERT INTO copy_buku (no_copy_buku, id_buku, status_buku) VALUES ('$no_copy', '$id_buku', 'tersedia')");
            }
        } else {
            // Update: Sesuaikan copy_buku
            $resultJumlah = $conn->query("SELECT jml_buku FROM buku WHERE id_buku = '$id_buku'");
            $jumlah_lama = $editData['jml_buku'] ?? 0;
            $jumlah_lama = (int)$jumlah_lama;

            if ($jumlah > $jumlah_lama) {
                for ($i = $jumlah_lama + 1; $i <= $jumlah; $i++) {
                    $no_copy = $id_buku . '-CB' . $i;
                    $conn->query("INSERT INTO copy_buku (no_copy_buku, id_buku, status_buku) VALUES ('$no_copy', '$id_buku', 'tersedia')");
                }
            } elseif ($jumlah < $jumlah_lama) {
                for ($i = $jumlah_lama; $i > $jumlah; $i--) {
                    $no_copy = $id_buku . '-CB' . $i;
                    $conn->query("DELETE FROM copy_buku WHERE no_copy_buku = '$no_copy'");
                }
            }
        }

        echo "<div class='alert alert-success'>Data buku berhasil disimpan.</div>";
        echo '<meta http-equiv="refresh" content="1;url=?page=perpus_utama&panggil=buku.php">';
    } else {
        echo "<div class='alert alert-danger'>Gagal menyimpan data: " . htmlspecialchars($stmt->error) . "</div>";
    }
}
?>

<style>
    /* Style untuk Form Tambah/Edit Buku */
    .perpus-form-container {
        max-width: 800px;
        margin: 2rem auto;
        background-color: white;
        border-radius: 0.5rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        overflow: hidden;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    .perpus-form-header {
        background-color: #4e73df;
        color: white;
        padding: 1.5rem;
        text-align: center;
        margin-bottom: 2rem;
    }

    .perpus-form-header h3 {
        margin: 0;
        font-weight: 600;
        font-size: 1.5rem;
    }

    .perpus-form-body {
        padding: 0 2rem 2rem;
    }

    .perpus-form-label {
        font-weight: 600;
        color: #5a5c69;
        margin-bottom: 0.5rem;
        display: block;
    }

    .perpus-form-control,
    .perpus-form-select {
        border-radius: 0.35rem;
        padding: 0.75rem 1rem;
        border: 1px solid #d1d3e2;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        width: 100%;
        margin-bottom: 1rem;
        background-color: #fff;
        color: #6e707e;
    }

    .perpus-form-control:focus,
    .perpus-form-select:focus {
        border-color: #4e73df;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        outline: 0;
    }

    .perpus-btn-primary {
        background-color: #4e73df;
        border-color: #4e73df;
        color: white;
        padding: 0.5rem 1.5rem;
        font-weight: 600;
        border-radius: 0.35rem;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
    }

    .perpus-btn-primary:hover {
        background-color: #2e59d9;
        border-color: #2e59d9;
        color: white;
    }

    .perpus-btn-secondary {
        border-radius: 0.35rem;
        padding: 0.5rem 1.5rem;
        font-weight: 600;
        background-color: #858796;
        border-color: #858796;
        color: white;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
    }

    .perpus-btn-secondary:hover {
        background-color: #6c757d;
        border-color: #6c757d;
        color: white;
    }

    .perpus-form-group {
        margin-bottom: 1.5rem;
    }

    .perpus-form-row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -15px;
        margin-left: -15px;
    }

    .perpus-form-col {
        flex: 0 0 50%;
        max-width: 50%;
        padding-right: 15px;
        padding-left: 15px;
        box-sizing: border-box;
    }

    .perpus-required {
        color: #e74a3b;
    }

    .perpus-alert {
        position: relative;
        padding: 0.75rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
        border-radius: 0.35rem;
    }

    .perpus-alert-success {
        color: #1cc88a;
        background-color: #d1f3e6;
        border-color: #b8efdb;
    }

    .perpus-alert-danger {
        color: #e74a3b;
        background-color: #fadbd8;
        border-color: #f8cac5;
    }

    @media (max-width: 768px) {
        .perpus-form-col {
            flex: 0 0 100%;
            max-width: 100%;
        }

        .perpus-form-body {
            padding: 0 1rem 1rem;
        }
    }
</style>

<div class="perpus-form-container">
    <div class="perpus-form-header">
        <h3><?= $editData ? 'Edit Data Buku' : 'Tambah Buku Baru' ?></h3>
    </div>

    <div class="perpus-form-body">
        <?php if (isset($_SESSION['message'])) : ?>
            <div class="perpus-alert perpus-alert-<?= $_SESSION['message_type'] ?>">
                <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="perpus-form-row">
                <div class="perpus-form-col">
                    <div class="perpus-form-group">
                        <label class="perpus-form-label">ID Buku</label>
                        <input type="text" name="id_buku" class="perpus-form-control" value="<?= htmlspecialchars($id_buku_otomatis) ?>" readonly>
                    </div>

                    <div class="perpus-form-group">
                        <label class="perpus-form-label">Judul Buku <span class="perpus-required">*</span></label>
                        <input type="text" name="judul_buku" class="perpus-form-control" required value="<?= isset($editData['judul_buku']) ? htmlspecialchars($editData['judul_buku']) : '' ?>">
                    </div>
                    <div class="perpus-form-group">
                        <label class="perpus-form-label">Harga Buku (Rp) <span class="perpus-required">*</span></label>
                        <input type="number" name="harga_buku" class="perpus-form-control" min="0" required value="<?= isset($editData['harga_buku']) ? htmlspecialchars($editData['harga_buku']) : '' ?>">
                    </div>

                    <div class="perpus-form-group">
                        <label class="perpus-form-label">Cover Buku (JPG/PNG)</label>
                        <input type="file" name="cover_buku" class="perpus-form-control" accept="image/*">
                        <?php if (!empty($editData['cover_buku'])) : ?>
                        <?php 
                            $plugin_url = plugin_dir_url(__FILE__);
                            $cover_url = $plugin_url . 'cover/' . htmlspecialchars($editData['cover_buku']);
                        ?>
                            <small>Cover lama: <a href="<?= $cover_url ?>" target="_blank">Lihat</a></small>
                        <?php endif; ?>
                    </div>

                    <div class="perpus-form-group">
                        <label class="perpus-form-label">Pengarang</label>
                        <input type="text" name="pengarang" class="perpus-form-control" value="<?= isset($editData['pengarang']) ? htmlspecialchars($editData['pengarang']) : '' ?>">
                    </div>
                </div>

                <div class="perpus-form-col">
                    <div class="perpus-form-group">
                        <label class="perpus-form-label">Tahun Terbit</label>
                        <input type="number" name="thn_terbit" class="perpus-form-control" value="<?= isset($editData['thn_terbit']) ? htmlspecialchars($editData['thn_terbit']) : '' ?>">
                    </div>

                    <div class="perpus-form-group">
                        <label class="perpus-form-label">Jumlah Buku <span class="perpus-required">*</span></label>
                        <input type="number" name="jml_buku" class="perpus-form-control" min="1" required value="<?= isset($editData['jml_buku']) ? htmlspecialchars($editData['jml_buku']) : '1' ?>">
                    </div>

                    <div class="perpus-form-group">
                        <label class="perpus-form-label">Penerbit</label>
                        <input type="text" name="penerbit" class="perpus-form-control" value="<?= isset($editData['penerbit']) ? htmlspecialchars($editData['penerbit']) : '' ?>">
                    </div>
                </div>
            </div>

            <div class="perpus-form-group">
                <label class="perpus-form-label">Kategori <span class="perpus-required">*</span></label>
                <select name="id_kategori" class="perpus-form-select" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php while ($row = $kategori->fetch_assoc()) : ?>
                        <option value="<?= $row['id_kategori'] ?>" <?= (isset($editData['id_kategori']) && $editData['id_kategori'] == $row['id_kategori']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['nm_kategori']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div style="display: flex; justify-content: space-between; margin-top: 2rem;">
                <a href="?page=perpus_utama&panggil=buku.php" class="perpus-btn-secondary">Kembali</a>
                <button type="submit" class="perpus-btn-primary bg-warning text-white">
                    <i class="fas fa-save"></i> <?= $editData ? 'Update' : 'Simpan' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php ob_end_flush(); ?>