<?php
// koneksi database

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Fungsi generate no_peminjaman otomatis
    function generateNoPeminjaman($conn) {
        $result = $conn->query("SELECT MAX(CAST(SUBSTRING(no_peminjaman, 3) AS UNSIGNED)) AS max_num FROM peminjaman");
        $row = $result->fetch_assoc();
        $next = (int)$row['max_num'] + 1;
        return "PJ" . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    $tgl_pinjam = $_POST['tgl_pinjam'];
    $tgl_kembali = $_POST['tgl_kembali'];
    $id_anggota = $_POST['id_anggota'];
    $no_peminjaman = generateNoPeminjaman($conn);

    // Simpan ke tabel peminjaman
    $query = "INSERT INTO peminjaman (no_peminjaman, tgl_peminjaman, tgl_harus_kembali, id_anggota)
              VALUES ('$no_peminjaman', '$tgl_pinjam', '$tgl_kembali', '$id_anggota')";

    if ($conn->query($query)) {
        // Proses detail pinjaman di tabel dapat
        foreach ($_POST['id_buku'] as $i => $id_buku) {
            $jumlah = (int)$_POST['jumlah'][$i];

            // Ambil copy buku yang tersedia sebanyak $jumlah
            $copy = $conn->query("SELECT no_copy_buku FROM copy_buku 
                                  WHERE id_buku = '$id_buku' AND status_buku = 'tersedia'
                                  LIMIT $jumlah");

            if ($copy->num_rows < $jumlah) {
                echo "<script>alert('Copy buku untuk buku ID $id_buku tidak cukup tersedia'); window.history.back();</script>";
                // Batalkan transaksi peminjaman (optional, bisa tambahkan transaksi MySQL)
                exit;
            }

            while ($c = $copy->fetch_assoc()) {
                $no_copy = $c['no_copy_buku'];

                // Update status copy buku jadi 'dipinjam'
                $conn->query("UPDATE copy_buku SET status_buku = 'dipinjam' WHERE no_copy_buku = '$no_copy'");

                // Insert detail ke tabel dapat
                $conn->query("INSERT INTO dapat (no_peminjaman, no_copy_buku, jml_pinjam) 
                              VALUES ('$no_peminjaman', '$no_copy', 1)");
            }
        }

        echo "<script>alert('Peminjaman berhasil disimpan!'); window.location.href='admin.php?page=perpus_utama&panggil=peminjaman.php';</script>";
    } else {
        echo "<script>alert('Gagal menyimpan data: " . $conn->error . "');</script>";
    }
}

// Ambil data anggota untuk dropdown
$anggota_result = $conn->query("SELECT id_anggota, nm_anggota FROM anggota ORDER BY nm_anggota ASC");

// Ambil data buku + stok tersedia
$buku_result = $conn->query("SELECT buku.id_buku, judul_buku,
    (SELECT COUNT(*) FROM copy_buku WHERE id_buku = buku.id_buku AND status_buku = 'tersedia') AS stok
FROM buku ORDER BY judul_buku ASC");

$bookData = [];
while ($b = $buku_result->fetch_assoc()) {
    $bookData[$b['id_buku']] = [
        'judul' => $b['judul_buku'],
        'stok' => (int)$b['stok']
    ];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Tambah Peminjaman Buku</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-3">

    <h3>Tambah Peminjaman Buku</h3>

    <form method="POST" class="container">

        <div class="mb-3 w-auto">
            <label class="form-label">Tanggal Pinjam</label>
            <input type="date" name="tgl_pinjam" class="form-control form-control-sm" required>
        </div>

        <div class="mb-3 w-auto">
            <label class="form-label">Tanggal Kembali</label>
            <input type="date" name="tgl_kembali" class="form-control form-control-sm" required>
        </div>

        <div class="mb-3 w-auto">
            <label class="form-label">Nama Anggota</label>
            <select name="id_anggota" class="form-select" required>
                <option value="">-- Pilih Anggota --</option>
                <?php while ($a = $anggota_result->fetch_assoc()) : ?>
                    <option value="<?= htmlspecialchars($a['id_anggota']) ?>"><?= htmlspecialchars($a['nm_anggota']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3 bg-light p-3 rounded">
            <table class="table table-bordered" id="tabel_buku">
                <thead>
                    <tr class="table-secondary text-center">
                        <th>No</th>
                        <th>ID Buku</th>
                        <th>Judul Buku</th>
                        <th>Jumlah</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">1</td>
                        <td><input type="text" name="id_buku[]" class="form-control form-control-sm id-buku" readonly></td>
                        <td>
                            <select class="form-select form-select-sm judul-buku" required>
                                <option value="">PILIH</option>
                                <?php foreach ($bookData as $id => $data): ?>
                                    <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($data['judul']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" name="jumlah[]" class="form-control form-control-sm jumlah-buku" min="1" required></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm btn-hapus">-</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button type="button" id="btn-tambah" class="btn btn-success btn-sm">Tambah Baris</button>
        </div>

        <button type="submit" class="btn btn-primary">Simpan Peminjaman</button>
        <a href="admin.php?page=perpus_utama&panggil=peminjaman.php" class="btn btn-secondary">Batal</a>
    </form>

    <script>
        const bookData = <?= json_encode($bookData) ?>;
        const tableBody = document.querySelector("#tabel_buku tbody");
        const btnTambah = document.getElementById("btn-tambah");

        // Fungsi ambil semua id buku yang sudah dipilih
        function getSelectedBookIds() {
            return [...document.querySelectorAll(".judul-buku")].map(sel => sel.value).filter(val => val);
        }

        // Update opsi dropdown agar tidak ada buku yang dipilih lebih dari sekali
        function updateDropdownOptions() {
            const selectedIds = getSelectedBookIds();

            document.querySelectorAll(".judul-buku").forEach(select => {
                const current = select.value;
                select.innerHTML = `<option value="">PILIH</option>` + Object.entries(bookData)
                    .filter(([id]) => id === current || !selectedIds.includes(id))
                    .map(([id, data]) => `<option value="${id}">${data.judul}</option>`)
                    .join('');
                select.value = current;
            });
        }

        // Tambah baris baru tabel
        btnTambah.addEventListener("click", () => {
            const rowCount = tableBody.rows.length + 1;
            const row = tableBody.insertRow();
            row.innerHTML = `
        <td class="text-center">${rowCount}</td>
        <td><input type="text" name="id_buku[]" class="form-control form-control-sm id-buku" readonly></td>
        <td>
            <select class="form-select form-select-sm judul-buku" required>
                <option value="">PILIH</option>
                ${Object.entries(bookData)
                    .filter(([id]) => !getSelectedBookIds().includes(id))
                    .map(([id, data]) => `<option value="${id}">${data.judul}</option>`).join('')}
            </select>
        </td>
        <td><input type="number" name="jumlah[]" class="form-control form-control-sm jumlah-buku" min="1" required></td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm btn-hapus">-</button>
        </td>
    `;
            updateDropdownOptions();
        });

        // Saat dropdown judul buku berubah, sinkronkan id buku dan batas jumlah maksimal
        tableBody.addEventListener("change", (e) => {
            if (e.target.classList.contains("judul-buku")) {
                const select = e.target;
                const id = select.value;
                const row = select.closest("tr");
                const idInput = row.querySelector(".id-buku");
                const jumlahInput = row.querySelector(".jumlah-buku");

                idInput.value = id;
                const stok = bookData[id]?.stok || 0;
                jumlahInput.max = stok;
                jumlahInput.value = jumlahInput.value > stok ? stok : jumlahInput.value;
                jumlahInput.placeholder = stok > 0 ? "max: " + stok : "stok habis";
                jumlahInput.disabled = stok === 0;

                updateDropdownOptions();
            }
        });

        // Hapus baris tabel
        tableBody.addEventListener("click", (e) => {
            if (e.target.classList.contains("btn-hapus")) {
                e.target.closest("tr").remove();
                updateNomor();
                updateDropdownOptions();
            }
        });

        // Update nomor urut baris tabel
        function updateNomor() {
            [...tableBody.rows].forEach((row, i) => {
                row.cells[0].textContent = i + 1;
            });
        }
    </script>

</body>

</html>