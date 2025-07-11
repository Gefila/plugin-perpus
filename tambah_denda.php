<?php
$conn = new mysqli("localhost", "root", "", "db_ti6b_uas");
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

$pengembalian = $conn->query("SELECT no_pengembalian FROM pengembalian 
                              WHERE status_denda = 'terdenda' 
                              AND no_pengembalian NOT IN (SELECT no_pengembalian FROM denda)");

function generateNoDenda($conn) {
    $result = $conn->query("SELECT no_denda FROM denda ORDER BY no_denda DESC LIMIT 1");
    if ($result->num_rows > 0) {
        $last = $result->fetch_assoc()['no_denda'];
        $num = (int)substr($last, 2);
        $num++;
        return 'DN' . str_pad($num, 2, '0', STR_PAD_LEFT);
    } else {
        return 'DN01';
    }
}

$editData = null;
if (isset($_GET['edit'])) {
    $idEdit = $conn->real_escape_string($_GET['edit']);
    $resultEdit = $conn->query("SELECT * FROM denda WHERE no_dneda = '$idEdit' ");
    if ($resultEdit && $resultEdit->num_rows > 0) {
        $editData = $resultEdit->fetch_assoc();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_pengembalian = $_POST['no_pengembalian'];
    $tarif = $_POST['tarif_denda'];
    $alasan = $_POST['alasan_denda'];
    $tgl = $_POST['tgl_denda']; // manual input dari form
    $no_denda = generateNoDenda($conn);

    $conn->query("INSERT INTO denda (no_denda, tgl_denda, tarif_denda, alasan_denda, no_pengembalian) 
                  VALUES ('$no_denda', '$tgl', '$tarif', '$alasan', '$no_pengembalian')");

    $conn->query("UPDATE pengembalian SET status_denda='terdenda' WHERE no_pengembalian='$no_pengembalian'");

    echo "<script>alert('Denda berhasil ditambahkan');location.href='admin.php?page=perpus_utama&panggil=denda.php';</script>";
}
?>

<h3>Tambah Denda</h3>
<form method="POST">
    <label>Pilih No Pengembalian</label>
    <select name="no_pengembalian" class="form-control" required>
        <option value="">-- Pilih --</option>
        <?php while($r = $pengembalian->fetch_assoc()): ?>
        <option value="<?= $r['no_pengembalian'] ?>"><?= $r['no_pengembalian'] ?></option>
        <?php endwhile; ?>
    </select>

    <label class="mt-2">Tarif Denda</label>
    <input type="number" name="tarif_denda" required class="form-control">

    <label class="mt-2">Alasan Denda</label>
    <input type="text" name="alasan_denda" required class="form-control">

    <label class="mt-2">Tanggal Denda</label>
    <input type="date" name="tgl_denda" required class="form-control">

    <br>
    <button type="submit" class="btn btn-success">Simpan</button>
</form>
