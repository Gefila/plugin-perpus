<?php
// [Keep all your existing PHP code exactly the same until the style section]
?>

<!-- Replace the existing style section with this: -->
<style>
/* Main Form Container */
.perpus-form-container {
    max-width: 600px;
    margin: 2rem auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

/* Form Header - Changed to red gradient to match denda theme */
.perpus-form-header {
    background: linear-gradient(135deg, #0d3ec5ff 0%, #0746ceff 100%);
    color: white;
    padding: 1.5rem;
    text-align: center;
    margin-bottom: 2rem;
}

.perpus-form-header h2 {
    margin: 0;
    font-weight: 600;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

/* Form Body */
.perpus-form-body {
    padding: 0 2rem 2rem;
}

/* Input Groups */
.perpus-input-group {
    margin-bottom: 1.5rem;
    position: relative;
}

.perpus-input-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #095bd6ff; /* Changed to red to match denda theme */
}

.perpus-input-wrapper {
    display: flex;
    border: 1px solid #d1d3e2;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.perpus-input-wrapper:focus-within {
    border-color: #0759f0ff; /* Changed to red */
    box-shadow: 0 0 0 3px rgba(231, 74, 59, 0.25); /* Changed to red */
}

.perpus-input-icon {
    padding: 0.75rem 1rem;
    background-color: #f8f9fc;
    color: #092bf0ff; /* Changed to red */
    display: flex;
    align-items: center;
    border-right: 1px solid #d1d3e2;
}

.perpus-input-field {
    flex: 1;
    padding: 0.75rem 1rem;
    border: none;
    outline: none;
    background-color: white;
}

.perpus-input-field:focus {
    box-shadow: none;
}

/* Button Styles */
.perpus-btn-group {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.perpus-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

/* Changed to red gradient */
.perpus-btn-primary {
    background: linear-gradient(135deg, #0545bdff 0%, #0905e4ff 100%);
    color: white;
}

.perpus-btn-primary:hover {
    background: linear-gradient(135deg, #4c08e9ff 0%, #2b05b8ff 100%);
    transform: translateY(-2px);
}

.perpus-btn-secondary {
    background: #f8f9fc;
    color: #3911ebff; /* Changed to red */
    border: 1px solid #d1d3e2;
}

.perpus-btn-secondary:hover {
    background: #e2e6ea;
    color: #0d2de2ff; /* Changed to red */
}

/* Alert Messages */
.perpus-alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.perpus-alert-success {
    background-color: #d1f3e6;
    color: #13f00bff;
    border-left: 4px solid #1cc88a;
}

.perpus-alert-danger {
    background-color: #fadbd8;
    color: #db1b09ff;
    border-left: 4px solid #e74a3b;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .perpus-form-container {
        margin: 1rem;
    }
    
    .perpus-form-body {
        padding: 0 1.5rem 1.5rem;
    }
    
    .perpus-btn-group {
        flex-direction: column;
    }
    
    .perpus-btn {
        width: 100%;
    }
}
</style>

<!-- Replace the HTML form section with this: -->
<div class="perpus-form-container">
    <div class="perpus-form-header">
        <h2>
            <i class="fas fa-money-bill-wave"></i>
            <?= $isEdit ? 'Edit Denda' : 'Tambah Denda Baru' ?>
        </h2>
    </div>
    
    <div class="perpus-form-body">
        <form method="POST">
            <input type="hidden" name="editMode" value="<?= $isEdit ? '1' : '0' ?>">

            <div class="perpus-input-group">
                <label for="idDenda">ID Denda</label>
                <div class="perpus-input-wrapper">
                    <span class="perpus-input-icon">
                        <i class="fas fa-id-card"></i>
                    </span>
                    <input type="text" class="perpus-input-field" id="idDenda" name="idDenda" 
                           value="<?= htmlspecialchars($idDenda) ?>" readonly>
                </div>
                <small class="text-muted">ID otomatis</small>
            </div>

            <div class="perpus-input-group">
                <label for="tarifDenda">Tarif Denda</label>
                <div class="perpus-input-wrapper">
                    <span class="perpus-input-icon">
                        <i class="fas fa-coins"></i>
                    </span>
                    <input type="text" class="perpus-input-field" id="tarifDenda" name="tarifDenda" 
                           maxlength="10" placeholder="Contoh: 5000" 
                           value="<?= htmlspecialchars($tarifDenda) ?>" required>
                </div>
                <small class="text-muted">Masukkan angka tanpa titik/koma</small>
            </div>

            <div class="perpus-input-group">
                <label for="alasanDenda">Alasan Denda</label>
                <div class="perpus-input-wrapper">
                    <span class="perpus-input-icon">
                        <i class="fas fa-comment"></i>
                    </span>
                    <textarea class="perpus-input-field" id="alasanDenda" name="alasanDenda" 
                              rows="3" placeholder="Tulis alasan dikenakan denda" 
                              required><?= htmlspecialchars($alasanDenda) ?></textarea>
                </div>
            </div>
            
            <div class="perpus-btn-group">
                <button type="submit" class="perpus-btn perpus-btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <a href="?page=perpus_utama&panggil=denda.php" class="perpus-btn perpus-btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>