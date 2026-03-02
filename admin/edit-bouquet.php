<?php
/**
 * bloom-aura/admin/edit-bouquet.php
 * Admin: edit an existing bouquet. Optionally replace its image.
 * FIX: All header() redirects and form actions now use /bloom-aura/ prefix.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

// ── Admin guard ───────────────────────────────────────────────────────────────
if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: /bloom-aura/admin/login.php');  // ← FIXED
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /bloom-aura/admin/products.php');  // ← FIXED
    exit;
}

$errors     = [];
$categories = [];

try {
    $pdo        = getPDO();
    $stmt       = $pdo->prepare('SELECT * FROM bouquets WHERE id = ?');
    $stmt->execute([$id]);
    $bouquet    = $stmt->fetch();
    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
} catch (RuntimeException $e) {
    $bouquet = null;
}

if (!$bouquet) {
    flash('Bouquet not found.', 'error');
    header('Location: /bloom-aura/admin/products.php');  // ← FIXED
    exit;
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = trim($_POST['price']       ?? '');
    $stock       = trim($_POST['stock']       ?? '');
    $categoryId  = (int)($_POST['category_id'] ?? 0);

    if ($name === '' || strlen($name) < 2)   $errors['name']        = 'Name is required (min 2 chars).';
    if (!is_numeric($price) || $price <= 0)  $errors['price']       = 'Enter a valid price greater than 0.';
    if (!ctype_digit($stock))                $errors['stock']       = 'Stock must be a whole number.';
    if ($categoryId <= 0)                    $errors['category_id'] = 'Please select a category.';

    // Optional image replacement
    $newImage = $bouquet['image']; // Keep existing by default
    if (!empty($_FILES['image']['name'])) {
        $allowed     = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize     = 2 * 1024 * 1024;
        $finfo       = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType    = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        $ext         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($mimeType, $allowed, true) || !in_array($ext, $allowedExts, true)) {
            $errors['image'] = 'Only JPEG, PNG, or WebP images are allowed.';
        } elseif ($_FILES['image']['size'] > $maxSize) {
            $errors['image'] = 'Image must be under 2 MB.';
        } else {
            $newImage = bin2hex(random_bytes(12)) . '.' . $ext;
        }
    }

    if (empty($errors)) {
        try {
            $pdo = getPDO();

            // Move new image if uploaded
            if ($newImage !== $bouquet['image']) {
                $uploadDir = __DIR__ . '/../uploads/bouquets/';
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newImage)) {
                    throw new RuntimeException('File upload failed.');
                }
                // Delete old image
                $oldPath = $uploadDir . $bouquet['image'];
                if ($bouquet['image'] && file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $pdo->prepare(
                "UPDATE bouquets
                 SET name = ?, description = ?, price = ?, stock = ?, category_id = ?, image = ?
                 WHERE id = ?"
            )->execute([$name, $description, $price, (int)$stock, $categoryId, $newImage, $id]);

            flash('Bouquet updated successfully! ✅', 'success');
            header('Location: /bloom-aura/admin/products.php');  // ← FIXED
            exit;

        } catch (RuntimeException $e) {
            if ($newImage !== $bouquet['image']) {
                @unlink(__DIR__ . '/../uploads/bouquets/' . $newImage);
            }
            $errors['db'] = 'Could not update bouquet. Please try again.';
        }
    }
}

$pageTitle = 'Edit Bouquet — Admin';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/bloom-aura/admin/dashboard.php">Dashboard</a></li>
        <li><a href="/bloom-aura/admin/products.php">Products</a></li>
        <li aria-current="page">Edit</li>
    </ol>
</nav>

<div class="page-container admin-page">
    <h1 class="page-title">Edit Bouquet</h1>

    <?php if (!empty($errors['db'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errors['db'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- FORM ACTION ← FIXED path -->
    <form action="/bloom-aura/admin/edit-bouquet.php?id=<?= $id ?>" method="POST"
          enctype="multipart/form-data" class="admin-form" novalidate>
        <?php csrf_field(); ?>

        <div class="admin-form-grid">
            <div class="admin-form-main">

                <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
                    <label for="name">Bouquet Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" maxlength="200" required
                           value="<?= htmlspecialchars($_POST['name'] ?? $bouquet['name'], ENT_QUOTES, 'UTF-8') ?>">
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
                                <?= ((int)($_POST['category_id'] ?? $bouquet['category_id'])) === (int)$cat['id'] ? 'selected' : '' ?>>
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
                    <textarea id="description" name="description" rows="4" maxlength="2000"><?=
                        htmlspecialchars($_POST['description'] ?? $bouquet['description'] ?? '', ENT_QUOTES, 'UTF-8')
                    ?></textarea>
                </div>

                <div class="admin-two-col">
                    <div class="form-group <?= isset($errors['price']) ? 'has-error' : '' ?>">
                        <label for="price">Price (₹) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" min="1" step="0.01" required
                               value="<?= htmlspecialchars($_POST['price'] ?? $bouquet['price'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (isset($errors['price'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['price'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group <?= isset($errors['stock']) ? 'has-error' : '' ?>">
                        <label for="stock">Stock Quantity <span class="required">*</span></label>
                        <input type="number" id="stock" name="stock" min="0" step="1" required
                               value="<?= htmlspecialchars($_POST['stock'] ?? $bouquet['stock'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (isset($errors['stock'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['stock'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /.admin-form-main -->

            <!-- Image panel -->
            <div class="admin-form-sidebar">
                <div class="form-group <?= isset($errors['image']) ? 'has-error' : '' ?>">
                    <label for="image">Product Image</label>
                    <p class="field-hint">Leave empty to keep the current image.</p>

                    <!-- Current image preview -->
                    <?php if ($bouquet['image']): ?>
                        <img src="/bloom-aura/uploads/bouquets/<?= htmlspecialchars($bouquet['image'], ENT_QUOTES, 'UTF-8') ?>"
                             alt="Current image" id="image-preview"
                             class="admin-img-preview" width="200" height="200">
                    <?php else: ?>
                        <img id="image-preview" src="" alt="" hidden class="admin-img-preview">
                    <?php endif; ?>

                    <input type="file" id="image" name="image"
                           accept="image/jpeg,image/png,image/webp" class="file-input">
                    <?php if (isset($errors['image'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['image'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
            </div><!-- /.admin-form-sidebar -->

        </div><!-- /.admin-form-grid -->

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
            <!-- CANCEL link ← FIXED path -->
            <a href="/bloom-aura/admin/products.php" class="btn btn-ghost">Cancel</a>
        </div>

    </form>
</div><!-- /.page-container -->

<script>
document.getElementById('image').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('image-preview');
        preview.src    = e.target.result;
        preview.hidden = false;
    };
    reader.readAsDataURL(file);
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>