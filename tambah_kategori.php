<?php
// Proses hapus jika ada parameter ?hapus
if (isset($_GET['hapus'])) {
    $idHapus = $conn->real_escape_string($_GET['hapus']);
    $conn->query("DELETE FROM buku WHERE id_buku = '$idHapus'");
    echo "<script>window.location.href='admin.php?page=perpus_utama&panggil=buku.php';</script>";
}

// Ambil parameter pencarian
$searchKeyword = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';

// Query untuk mendapatkan buku yang sudah dikelompokkan
$sql = "SELECT buku.*, kategori.nm_kategori 
        FROM buku 
        LEFT JOIN kategori ON buku.id_kategori = kategori.id_kategori 
        WHERE 1=1";

// Tambahkan kondisi pencarian jika ada keyword
if (!empty($searchKeyword)) {
    $sql .= " AND (judul_buku LIKE '%$searchKeyword%' 
                OR pengarang LIKE '%$searchKeyword%' 
                OR penerbit LIKE '%$searchKeyword%')";
}

// Tambahkan filter kategori jika dipilih
if (!empty($categoryFilter) && $categoryFilter != 'all') {
    $sql .= " AND kategori.nm_kategori = '$categoryFilter'";
}

$sql .= " ORDER BY kategori.nm_kategori, buku.judul_buku";

$result = $conn->query($sql);

// Ambil semua kategori untuk dropdown filter
$categories = $conn->query("SELECT DISTINCT nm_kategori FROM kategori ORDER BY nm_kategori");

// Kelompokkan buku berdasarkan kategori
$groupedBooks = [];
while ($row = $result->fetch_assoc()) {
    $category = $row['nm_kategori'] ?: 'Uncategorized';
    if (!isset($groupedBooks[$category])) {
        $groupedBooks[$category] = [];
    }
    $groupedBooks[$category][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Buku</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .search-section {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .category-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 0;
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .category-header {
            background-color: #4e73df;
            color: white;
            padding: 12px 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .category-badge {
            background-color: rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 0.8rem;
        }
        
        .books-container {
            padding: 15px;
        }
        
        .book-card {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 12px;
            border-left: 4px solid #4e73df;
            transition: all 0.2s;
        }
        
        .book-card:hover {
            background-color: #e9ecef;
            transform: translateX(5px);
        }
        
        .book-title {
            font-weight: bold;
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 5px;
        }
        
        .book-author {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        
        .book-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .book-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            color: #555;
        }
        
        .stock-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .stock-available {
            background-color: #28a745;
        }
        
        .stock-limited {
            background-color: #ffc107;
        }
        
        .stock-empty {
            background-color: #dc3545;
        }
        
        .pagination-info {
            font-size: 0.9rem;
            color: #666;
            text-align: center;
            margin-top: 20px;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .action-buttons {
            margin-top: 10px;
            display: flex;
            gap: 8px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header Section -->
        <div class="header-section">
            <h2 class="mb-3">Daftar Buku</h2>
            <a href="admin.php?page=perpus_utama&panggil=tambah_buku.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Tambah Buku
            </a>
        </div>
        
        <!-- Search Section -->
        <div class="search-section">
            <h5 class="mb-3">Cari buku...</h5>
            <form method="GET" action="admin.php">
                <input type="hidden" name="page" value="perpus_utama">
                <input type="hidden" name="panggil" value="buku.php">
                <div class="row">
                    <div class="col-md-8">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" name="search" placeholder="Judul, Pengarang, Penerbit..." 
                                   value="<?= htmlspecialchars($searchKeyword) ?>">
                            <button class="btn btn-primary" type="submit">Cari</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="category">
                            <option value="all" <?= empty($categoryFilter) || $categoryFilter == 'all' ? 'selected' : '' ?>>Semua Kategori</option>
                            <?php while ($cat = $categories->fetch_assoc()) : ?>
                                <option value="<?= htmlspecialchars($cat['nm_kategori']) ?>" 
                                    <?= $categoryFilter == $cat['nm_kategori'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nm_kategori']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Book List Grouped by Category -->
        <div class="mb-4">
            <?php if (!empty($groupedBooks)) : ?>
                <?php foreach ($groupedBooks as $category => $books) : ?>
                    <div class="category-container">
                        <div class="category-header">
                            <span><?= htmlspecialchars($category) ?></span>
                            <span class="category-badge"><?= count($books) ?> buku</span>
                        </div>
                        <div class="books-container">
                            <?php foreach ($books as $row) : ?>
                                <?php
                                // Tentukan kelas stok
                                $stockClass = 'stock-available';
                                if ($row['jml_buku'] == 0) {
                                    $stockClass = 'stock-empty';
                                } elseif ($row['jml_buku'] <= 5) {
                                    $stockClass = 'stock-limited';
                                }
                                ?>
                                <div class="book-card">
                                    <div class="book-title"><?= htmlspecialchars($row['judul_buku']) ?></div>
                                    <div class="book-author"><?= htmlspecialchars($row['pengarang']) ?></div>
                                    
                                    <div class="book-meta">
                                        <div class="book-meta-item">
                                            <i class="bi bi-calendar"></i>
                                            <?= $row['thn_terbit'] ?>
                                        </div>
                                        <div class="book-meta-item">
                                            <span class="stock-indicator <?= $stockClass ?>"></span>
                                            <?= $row['jml_buku'] ?> tersedia
                                        </div>
                                        <div class="book-meta-item">
                                            <i class="bi bi-building"></i>
                                            <?= htmlspecialchars($row['penerbit']) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <a href="admin.php?page=perpus_utama&panggil=tambah_buku.php&edit=<?= $row['id_buku'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <a href="admin.php?page=perpus_utama&panggil=buku.php&hapus=<?= $row['id_buku'] ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Yakin ingin menghapus buku ini?')">
                                            <i class="bi bi-trash"></i> Hapus
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="no-results">
                    <i class="bi bi-book" style="font-size: 3rem; opacity: 0.5;"></i>
                    <h4 class="mt-3">Tidak ada buku yang ditemukan</h4>
                    <p>Coba kata kunci pencarian yang berbeda atau pilih kategori lain</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination Info -->
        <div class="pagination-info">
            Total <?= $result->num_rows ?> buku ditemukan
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Submit form saat filter kategori berubah
        document.querySelector('select[name="category"]').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>