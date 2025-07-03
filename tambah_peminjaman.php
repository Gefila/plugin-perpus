<?php
// koneksi database
// Pastikan $conn sudah terkoneksi sebelum kode ini

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    // ✅ Langkah 1: Validasi semua stok buku dulu
    foreach ($_POST['id_buku'] as $i => $id_buku) {
        $jumlah = (int)$_POST['jumlah'][$i];

        if (!is_numeric($jumlah) || (int)$jumlah < 1) {
            echo "<script>alert('Jumlah buku untuk ID $id_buku harus diisi dan minimal 1.'); window.history.back();</script>";
            exit;
        }

        $cek = $conn->query("SELECT jml_buku FROM buku WHERE id_buku = '$id_buku'");
        $data = $cek->fetch_assoc();
        $stok = (int)$data['jml_buku'];

        if ($stok < $jumlah) {
            echo "<script>alert('Stok buku ID $id_buku tidak mencukupi. Stok tersedia: $stok'); window.history.back();</script>";
            exit;
        }
    }

    // ✅ Langkah 2: Jika validasi stok lolos, baru insert peminjaman
    $query = "INSERT INTO peminjaman (no_peminjaman, tgl_peminjaman, tgl_harus_kembali, id_anggota)
              VALUES ('$no_peminjaman', '$tgl_pinjam', '$tgl_kembali', '$id_anggota')";

    if ($conn->query($query)) {
        foreach ($_POST['id_buku'] as $i => $id_buku) {
            $jumlah = (int)$_POST['jumlah'][$i];

            $copy = $conn->query("SELECT no_copy_buku FROM copy_buku 
                                  WHERE id_buku = '$id_buku' AND status_buku = 'tersedia'
                                  LIMIT $jumlah");

            while ($c = $copy->fetch_assoc()) {
                $no_copy = $c['no_copy_buku'];

                $conn->query("UPDATE copy_buku SET status_buku = 'dipinjam' WHERE no_copy_buku = '$no_copy'");
                $conn->query("INSERT INTO dapat (no_peminjaman, no_copy_buku, jml_pinjam) 
                              VALUES ('$no_peminjaman', '$no_copy', 1)");
            }
        }

        echo "<script>alert('Peminjaman berhasil disimpan!'); window.location.href='admin.php?page=perpus_utama&panggil=peminjaman.php';</script>";
    } else {
        echo "<script>alert('Gagal menyimpan data: " . $conn->error . "');</script>";
    }
}

$anggota_result = $conn->query("SELECT id_anggota, nm_anggota FROM anggota ORDER BY nm_anggota ASC");

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

<body class="p-3">

    <h3 style="text-align: center;">Tambah Peminjaman Buku</h3>

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
                        <td>
                            <select name="id_buku[]" class="form-select form-select-sm id-buku" required>
                                <option value="">PILIH</option>
                                <?php foreach ($bookData as $id => $data): ?>
                                    <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($id) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
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

        function getSelectedBookIds() {
            return [...document.querySelectorAll(".id-buku")].map(sel => sel.value).filter(val => val);
        }

        function updateDropdownOptions() {
            const allRows = [...document.querySelectorAll("#tabel_buku tbody tr")];

            // Kumpulkan semua id yang sudah dipilih di setiap baris
            const selectedIds = allRows.map(row => row.querySelector(".id-buku").value).filter(val => val);

            allRows.forEach(row => {
                const idSelect = row.querySelector(".id-buku");
                const judulSelect = row.querySelector(".judul-buku");
                const currentId = idSelect.value;

                // Buat daftar ID yang boleh muncul di baris ini
                const availableIds = Object.keys(bookData).filter(id => {
                    return !selectedIds.includes(id) || id === currentId;
                });

                // Isi dropdown ID
                idSelect.innerHTML = `<option value="">PILIH</option>` +
                    availableIds.map(id => {
                        const selected = id === currentId ? 'selected' : '';
                        return `<option value="${id}" ${selected}>${id}</option>`;
                    }).join('');

                // Isi dropdown Judul
                judulSelect.innerHTML = `<option value="">PILIH</option>` +
                    availableIds.map(id => {
                        const selected = id === currentId ? 'selected' : '';
                        return `<option value="${id}" ${selected}>${bookData[id].judul}</option>`;
                    }).join('');
            });
        }




        btnTambah.addEventListener("click", () => {
            const rowCount = tableBody.rows.length + 1;
            const row = tableBody.insertRow();
            row.innerHTML = `
                <td class="text-center">${rowCount}</td>
                <td>
                    <select name="id_buku[]" class="form-select form-select-sm id-buku" required>
                        <option value="">PILIH</option>
                        ${Object.entries(bookData).map(([id]) => `<option value="${id}">${id}</option>`).join('')}
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm judul-buku" required>
                        <option value="">PILIH</option>
                        ${Object.entries(bookData).map(([id, data]) => `<option value="${id}">${data.judul}</option>`).join('')}
                    </select>
                </td>
                <td><input type="number" name="jumlah[]" class="form-control form-control-sm jumlah-buku" min="1" required></td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm btn-hapus">-</button>
                </td>
            `;
            updateDropdownOptions();
        });

        tableBody.addEventListener("change", (e) => {
            const row = e.target.closest("tr");
            const idSelect = row.querySelector(".id-buku");
            const judulSelect = row.querySelector(".judul-buku");
            const jumlahInput = row.querySelector(".jumlah-buku");

            if (e.target.classList.contains("id-buku")) {
                judulSelect.value = idSelect.value;
            } else if (e.target.classList.contains("judul-buku")) {
                idSelect.value = judulSelect.value;
            }

            const id = idSelect.value;
            const stok = bookData[id]?.stok || 0;
            jumlahInput.max = stok;
            jumlahInput.value = jumlahInput.value > stok ? stok : jumlahInput.value;
            jumlahInput.placeholder = stok > 0 ? "max: " + stok : "stok habis";
            jumlahInput.readOnly = stok === 0;

            updateDropdownOptions();
        });

        tableBody.addEventListener("click", (e) => {
            if (e.target.classList.contains("btn-hapus")) {
                e.target.closest("tr").remove();
                updateNomor();
                updateDropdownOptions();
            }
        });

        function updateNomor() {
            [...tableBody.rows].forEach((row, i) => {
                row.cells[0].textContent = i + 1;
            });
        }
    </script>

</body>

</html>