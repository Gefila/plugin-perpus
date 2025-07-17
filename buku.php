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

// Query dasar
$sql = "SELECT buku.*, kategori.nm_kategori 
        FROM buku 
        LEFT JOIN kategori ON buku.id_kategori = kategori.id_kategori 
        WHERE 1=1";

        $sql = "SELECT 
            buku.*, 
            kategori.nm_kategori,
            (
                SELECT COUNT(*) 
                FROM copy_buku 
                WHERE copy_buku.id_buku = buku.id_buku 
                  AND copy_buku.status_buku = 'tersedia'
            ) AS jml_tersedia
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

$sql .= " ORDER BY id_buku ASC";

$result = $conn->query($sql);

$groupedBooks = [];
while ($row = $result->fetch_assoc()) {
    $category = $row['nm_kategori'] ?: 'Uncategorized';
    if (!isset($groupedBooks[$category])) {
        $groupedBooks[$category] = [];
    }
    $groupedBooks[$category][] = $row;
}


// Ambil semua kategori untuk dropdown filter
$categories = $conn->query("SELECT DISTINCT nm_kategori FROM kategori ORDER BY nm_kategori");
?>


<style>
    body {
        background: linear-gradient(to right, #eef3ff, #dce7ff);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .header-section {
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }

    .search-section {
        background-color: #fff;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }

    .book-card {
        background-color: #fff;
        border-radius: 8px;
        position: relative;
        padding-left: 15px;
        margin-left: 10px;
        border-left: 4px solid #4b7bec; /* Garis biru sama untuk semua */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 15px;
        margin-bottom: 15px;
        transition: transform 0.2s;
    }

    

    .book-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .book-title {
        font-weight: bold;
        font-size: 1.1rem;
        color: #333;
    }

    .category-container {
        margin-bottom: 30px;
    }

    .category-header {
        background-color: #acf2ffff; /* Warna biru muda */
        padding: 10px 15px;
        border-radius: 6px;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 4px solid #4b7bec; /* Garis biru */
    }

    .category-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #333;
        margin: 0;
    }

    .category-badge {
        background-color: #4b7bec; /* Warna biru */
        color: white;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
    }

    .book-author {
        color: #666;
        font-size: 0.9rem;
    }

    .book-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 8px;
    }

    .book-meta-item {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.85rem;
        color: #555;
    }

    .stock-indicator {
        width: 16px;
        height: 16px;
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
    }

    .no-results {
        text-align: center;
        padding: 40px;
        color: #666;
    }
</style>

<div class="container py-4">
    <!-- Header Section -->
    <div class="header-section">
        <h2 class="mb-3">
            <i class="fas fa-book text-primary"></i> Daftar Buku Perpustakaan
        </h2>
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

    <!-- Book List -->
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
                            <div class="book-card">
 <div class="d-flex align-items-start gap-3">
    <!-- Gambar Cover -->
    <div>
        <?php if (!empty($row['cover_buku'])): ?>
            <?php 
                $plugin_url = plugin_dir_url(__FILE__);
                $cover_url = $plugin_url . 'cover/' . $row['cover_buku'];
            ?>
            <img src="<?= $cover_url ?>" alt="Cover Buku" width="80" style="object-fit: cover; border-radius: 4px;">
        <?php else: ?>
            <div style="width:80px; height:100px; background:#eee; display:flex; align-items:center; justify-content:center; border-radius:4px; color:#999;">
                No Cover
            </div>
        <?php endif; ?>
    </div>

    <!-- Info Buku -->
    <div class="flex-grow-1">
        <div class="book-title"><?= htmlspecialchars($row['judul_buku']) ?></div>
        <div class="book-author"><?= htmlspecialchars($row['pengarang']) ?></div>
        
        <div class="book-meta">
            <div class="book-meta-item">
                <i class="bi bi-calendar"></i>
                <?= $row['thn_terbit'] ?>
            </div>
            <div class="book-meta-item">
                <i class="fas fa-book text-secondary"></i>
                <?= $row['jml_buku'] ?> Jumlah Buku
            </div>
            <div class="book-meta-item">
                <i class="fas fa-check-circle text-success"></i>
                <?= $row['jml_tersedia'] ?> tersedia
            </div>
            <div class="book-meta-item">
                <i class="bi bi-building"></i>
                <?= htmlspecialchars($row['penerbit']) ?>
            </div>
            <div class="book-meta-item">
                <i class="bi bi-cash-coin"></i>
                Rp <?= number_format($row['harga_buku'], 0, ',', '.') ?>
            </div>
        </div>
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
    <div class="pagination-info text-center">
        Total <?= $result->num_rows ?> Jenis buku
    </div>
</div>