<?php
/**
 * bloom-aura/admin/categories.php
 * Admin: manage product categories (add / edit inline / delete).
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

// ── Admin guard ───────────────────────────────────────────────────────────────
if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php');
    exit;
}

$errors = [];
$editId = (int)($_GET['edit'] ?? 0); // If set, show edit form for that ID

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    // ── ADD new category ──
    if ($action === 'add') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '' || strlen($name) < 2) {
            $errors['add_name'] = 'Category name is required (min 2 chars).';
        }

        if (empty($errors)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            $slug = trim($slug, '-');
            try {
                $pdo = getPDO();
                // Ensure unique slug
                $check = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
                $check->execute([$slug]);
                if ($check->fetch()) $slug .= '-' . time();

                $pdo->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)')
                    ->execute([$name, $slug, $description]);
                flash('Category "' . htmlspecialchars($name) . '" added.', 'success');
            } catch (RuntimeException $e) {
                $errors['add_name'] = 'Category name may already exist. Try another name.';
            }
        }
        if (empty($errors)) {
            header('Location: /admin/categories.php');
            exit;
        }
    }

    // ── UPDATE category ──
    if ($action === 'update') {
        $catId       = (int)($_POST['category_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '' || strlen($name) < 2) {
            $errors['edit_name'] = 'Category name is required (min 2 chars).';
        }

        if (empty($errors) && $catId > 0) {
            try {
                $pdo = getPDO();
                $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?')
                    ->execute([$name, $description, $catId]);
                flash('Category updated.', 'success');
            } catch (RuntimeException $e) {
                $errors['edit_name'] = 'Could not update category.';
            }
        }
        if (empty($errors)) {
            header('Location: /admin/categories.php');
            exit;
        } else {
            $editId = $catId; // Keep edit form open
        }
    }

    // ── DELETE category ──
    if ($action === 'delete') {
        $catId = (int)($_POST['category_id'] ?? 0);
        if ($catId > 0) {
            try {
                $pdo = getPDO();
                // Check if any bouquets still use this category
                $usedStmt = $pdo->prepare('SELECT COUNT(*) FROM bouquets WHERE category_id = ?');
                $usedStmt->execute([$catId]);
                if ((int)$usedStmt->fetchColumn() > 0) {
                    flash('Cannot delete: bouquets are still linked to this category.', 'error');
                } else {
                    $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$catId]);
                    flash('Category deleted.', 'success');
                }
            } catch (RuntimeException $e) {
                flash('Could not delete category.', 'error');
            }
        }
        header('Location: /admin/categories.php');
        exit;
    }
}

// ── Fetch all categories ──────────────────────────────────────────────────────
$categories = [];
$error      = '';
try {
    $pdo = getPDO();
    $categories = $pdo->query(
        "SELECT c.*, COUNT(b.id) AS bouquet_count
         FROM categories c
         LEFT JOIN bouquets b ON b.category_id = c.id
         GROUP BY c.id
         ORDER BY c.name"
    )->fetchAll();
} catch (RuntimeException $e) {
    $error = 'Could not load categories.';
}

// Fetch edit target
$editTarget = null;
if ($editId > 0) {
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === $editId) {
            $editTarget = $cat;
            break;
        }
    }
}

$pageTitle = 'Manage Categories — Admin';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/admin/dashboard.php">Dashboard</a></li>
        <li aria-current="page">Categories</li>
    </ol>
</nav>

<div class="page-container admin-page">
    <h1 class="page-title">Categories</h1>

    <div class="admin-two-col">

        <!-- ── Add / Edit Form ── -->
        <div class="admin-card">
            <h2 class="admin-card-title">
                <?= $editTarget ? 'Edit Category' : 'Add New Category' ?>
            </h2>

            <form action="/admin/categories.php" method="POST" novalidate>
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="<?= $editTarget ? 'update' : 'add' ?>">
                <?php if ($editTarget): ?>
                    <input type="hidden" name="category_id" value="<?= (int)$editTarget['id'] ?>">
                <?php endif; ?>

                <div class="form-group <?= isset($errors['add_name'], $errors['edit_name']) ? 'has-error' : '' ?>">
                    <label for="cat-name">Name <span class="required">*</span></label>
                    <input type="text" id="cat-name" name="name" maxlength="100" required
                           value="<?= htmlspecialchars($_POST['name'] ?? $editTarget['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?php
                    $nameErr = $errors['add_name'] ?? $errors['edit_name'] ?? null;
                    if ($nameErr): ?>
                        <span class="field-error"><?= htmlspecialchars($nameErr, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="cat-desc">Description</label>
                    <textarea id="cat-desc" name="description" rows="3" maxlength="500"><?= htmlspecialchars($_POST['description'] ?? $editTarget['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $editTarget ? 'Save Changes' : 'Add Category' ?>
                    </button>
                    <?php if ($editTarget): ?>
                        <a href="/admin/categories.php" class="btn btn-ghost">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ── Categories Table ── -->
        <div class="admin-card">
            <h2 class="admin-card-title">All Categories (<?= count($categories) ?>)</h2>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif (empty($categories)): ?>
                <div class="empty-state-sm">No categories yet. Add one on the left.</div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Products</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr class="<?= (int)$cat['id'] === $editId ? 'table-row-active' : '' ?>">
                                <td><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><code><?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><?= (int)$cat['bouquet_count'] ?></td>
                                <td class="table-actions">
                                    <a href="/admin/categories.php?edit=<?= (int)$cat['id'] ?>"
                                       class="btn btn-outline btn-xs">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </a>
                                    <?php if ((int)$cat['bouquet_count'] === 0): ?>
                                        <form action="/admin/categories.php" method="POST"
                                              onsubmit="return confirm('Delete category «<?= htmlspecialchars(addslashes($cat['name']), ENT_QUOTES, 'UTF-8') ?>»?')">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-xs">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="btn-disabled-hint" title="Unlink all bouquets first">
                                            <i class="fa-solid fa-lock fa-xs"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div><!-- /.admin-two-col -->
</div><!-- /.page-container -->

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
