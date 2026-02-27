<?php
/**
 * bloom-aura/admin/add-bouquet.php
 * Admin: create a new bouquet with image upload.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

// ── Admin guard ───────────────────────────────────────────────────────────────
if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php');
    exit;
}

$errors = [];
$old    = [];

// ── Fetch categories ──────────────────────────────────────────────────────────
$categories = [];
try {
    $pdo = getPDO();
    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
} catch (RuntimeException $e) {}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = trim($_POST['price'] ?? '');
    $stock       = trim($_POST['stock'] ?? '');
    $categoryId  = (int)($_POST['category_id'] ?? 0);
    $old         = compact('name', 'description', 'price', 'stock', 'categoryId');

    // Validation
    if ($name === '' || strlen($name) < 2)   $errors['name']        = 'Name is required (min 2 chars).';
    if (!is_numeric($price) || $price <= 0)  $errors['price']       = 'Enter a valid price greater than 0.';
    if (!ctype_digit($stock))                $errors['stock']       = 'Stock must be a whole number.';
    if ($categoryId <= 0)                    $errors['category_id'] = 'Please select a category.';

    // Image upload
    $imageName = '';
    if (!empty($_FILES['image']['name'])) {
        $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize  = 2 * 1024 * 1024; // 2 MB
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($mimeType, $allowed, true) || !in_array($ext, $allowedExts, true)) {
            $errors['image'] = 'Only JPEG, PNG, or WebP images are allowed.';
        } elseif ($_FILES['image']['size'] > $maxSize) {
            $errors['image'] = 'Image must be under 2 MB.';
        } else {
            // Generate safe random filename
            $imageName = bin2hex(random_bytes(12)) . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/bouquets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
        }
    } else {
        $errors['image'] = 'Please upload a bouquet image.';
    }

    // Generate unique slug from name
    if (empty($errors['name'])) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
    }

    if (empty($errors)) {
        try {
            $pdo = getPDO();

            // Check unique slug
            $slugCheck = $pdo->prepare('SELECT id FROM bouquets WHERE slug = ?');
            $slugCheck->execute([$slug]);
            if ($slugCheck->fetch()) {
                $slug .= '-' . time(); // Make unique by appending timestamp
            }

            // Move uploaded file
            if (!move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../uploads/bouquets/' . $imageName)) {
                throw new RuntimeException('File upload failed.');
            }

            $pdo->prepare(
                "INSERT INTO bouquets (name, slug, description, price, stock, category_id, image, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            )->execute([$name, $slug, $description, $price, (int)$stock, $categoryId, $imageName]);

            flash('Bouquet "' . htmlspecialchars($name) . '" added successfully! 🌸', 'success');
            header('Location: /admin/bouquets.php');
            exit;

        } catch (RuntimeException $e) {
            // Clean up uploaded file on DB error
            if ($imageName && file_exists(__DIR__ . '/../uploads/bouquets/' . $imageName)) {
                @unlink(__DIR__ . '/../uploads/bouquets/' . $imageName);
            }
            $errors['db'] = 'Could not save bouquet. Please try again.';
        }
    }
}

$pageTitle = 'Add Bouquet — Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/admin/dashboard.php">Dashboard</a></li>
        <li><a href="/admin/bouquets.php">Bouquets</a></li>
        <li aria-current="page">Add New</li>
    </ol>
</nav>

<div class="page-container admin-page">
    <h1 class="page-title">Add New Bouquet</h1>

    <?php if (!empty($errors['db'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errors['db'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form action="/admin/add-bouquet.php" method="POST" enctype="multipart/form-data"
          class="admin-form" novalidate>
        <?php csrf_field(); ?>

        <div class="admin-form-grid">
            <div class="admin-form-main">

                <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
                    <label for="name">Bouquet Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" maxlength="200"
                           value="<?= htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    <?php if (isset($errors['name'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['category_id']) ? 'has-error' : '' ?>">
                    <label for="category_id">Category <span class="required">*</span></label>
                    <select id="category_id" name="category_id" required>
                        <option value="">— Select Category —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"
                                <?= ($old['categoryId'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category_id'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['category_id'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4" maxlength="2000"><?= htmlspecialchars($old['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group <?= isset($errors['price']) ? 'has-error' : '' ?>">
                        <label for="price">Price (₹) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" min="1" step="0.01"
                               value="<?= htmlspecialchars($old['price'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        <?php if (isset($errors['price'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['price'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group <?= isset($errors['stock']) ? 'has-error' : '' ?>">
                        <label for="stock">Stock Quantity <span class="required">*</span></label>
                        <input type="number" id="stock" name="stock" min="0" step="1"
                               value="<?= htmlspecialchars($old['stock'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        <?php if (isset($errors['stock'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['stock'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Image upload panel -->
            <div class="admin-form-sidebar">
                <div class="form-group <?= isset($errors['image']) ? 'has-error' : '' ?>">
                    <label for="image">Product Image <span class="required">*</span></label>
                    <div class="image-upload-box" id="image-upload-box">
                        <img id="image-preview" src="" alt="" hidden>
                        <div class="upload-placeholder" id="upload-placeholder">
                            <i class="fa-solid fa-cloud-arrow-up fa-2x"></i>
                            <p>Click or drag to upload</p>
                            <span>JPEG, PNG, WebP — max 2 MB</span>
                        </div>
                        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                               class="file-input" required>
                    </div>
                    <?php if (isset($errors['image'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['image'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Bouquet
            </button>
            <a href="/admin/bouquets.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div><!-- /.page-container -->

<script>
// Live image preview on file select
document.getElementById('image').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('image-preview');
        const placeholder = document.getElementById('upload-placeholder');
        preview.src = e.target.result;
        preview.hidden = false;
        placeholder.hidden = true;
    };
    reader.readAsDataURL(file);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
