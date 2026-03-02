<?php
/**
 * bloom-aura/admin/categories.php
 * Admin: manage product categories (add / edit / delete).
 * UI pixel-matched to bloom_aura reference HTML.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: /bloom-aura/admin/login.php');
    exit;
}

$errors = [];
$editId = (int)($_GET['edit'] ?? 0);

/* ── Category emoji helper ────────────────────────────────────────────────── */
function categoryEmoji(string $name): string {
    $map = [
        'rose'        => '🌹', 'roses'       => '🌹',
        'lily'        => '🌷', 'lilies'      => '🌷',
        'tulip'       => '🌷', 'tulips'      => '🌷',
        'sunflower'   => '🌻', 'sunflowers'  => '🌻',
        'orchid'      => '🪻', 'orchids'     => '🪻',
        'mixed'       => '💐', 'bouquet'     => '💐',
        'seasonal'    => '💐', 'exotic'      => '🌺',
        'tropical'    => '🌺', 'wedding'     => '👰',
        'bridal'      => '👰', 'gift'        => '🎁',
        'gifts'       => '🎁', 'birthday'    => '🎂',
        'anniversary' => '💍', 'chocolate'   => '🍫',
        'chocolates'  => '🍫', 'indoor'      => '🪴',
        'plant'       => '🪴', 'plants'      => '🪴',
        'dried'       => '🌾', 'custom'      => '✨',
        'hamper'      => '🧺', 'luxury'      => '👑',
        'perfume'     => '🧴', 'carnation'   => '🌸',
        'daisy'       => '🌼', 'jasmine'     => '🌸',
    ];
    $lower = strtolower($name);
    foreach ($map as $keyword => $emoji) {
        if (str_contains($lower, $keyword)) return $emoji;
    }
    return '🏷️';
}

/* ── Handle POST ─────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    /* ADD */
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
                $pdo   = getPDO();
                $check = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
                $check->execute([$slug]);
                if ($check->fetch()) $slug .= '-' . time();

                $pdo->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)')
                    ->execute([$name, $slug, $description]);
                flash('Category "' . htmlspecialchars($name) . '" added.', 'success');
                header('Location: /bloom-aura/admin/categories.php');
                exit;
            } catch (RuntimeException $e) {
                $errors['add_name'] = 'Category name may already exist. Try another name.';
            }
        }
    }

    /* UPDATE */
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
                header('Location: /bloom-aura/admin/categories.php');
                exit;
            } catch (RuntimeException $e) {
                $errors['edit_name'] = 'Could not update category.';
            }
        }
        $editId = $catId;
    }

    /* DELETE */
    if ($action === 'delete') {
        $catId = (int)($_POST['category_id'] ?? 0);
        if ($catId > 0) {
            try {
                $pdo      = getPDO();
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
        header('Location: /bloom-aura/admin/categories.php');
        exit;
    }
}

/* ── Fetch categories ────────────────────────────────────────────────────── */
$categories = [];
$dbError    = '';
try {
    $pdo        = getPDO();
    $categories = $pdo->query(
        "SELECT c.*, COUNT(b.id) AS bouquet_count
         FROM categories c
         LEFT JOIN bouquets b ON b.category_id = c.id
         GROUP BY c.id
         ORDER BY c.name"
    )->fetchAll();
} catch (RuntimeException $e) {
    $dbError = 'Could not load categories.';
}

$editTarget = null;
if ($editId > 0) {
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === $editId) { $editTarget = $cat; break; }
    }
}

/* ── Flash messages ──────────────────────────────────────────────────────── */
$flashMessages = [];
if (!empty($_SESSION['flash'])) {
    $flashMessages = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories — Bloom Aura Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin_categories.css">
</head>
<body class="admin-body">

<div class="admin-layout">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">

        <!-- ── Topbar ── -->
        <div class="admin-topbar">
            <h1 class="admin-page-title">Categories</h1>
            <div class="admin-topbar-right">
                <span class="admin-greeting">Hello, <?= $adminName ?> 👑</span>
                <a href="/bloom-aura/admin/logout.php" class="adm-logout-top-btn">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>

        <!-- ── Content ── -->
        <div class="admin-content">

            <!-- Flash messages -->
            <?php foreach ($flashMessages as $flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>" role="alert">
                    <?= htmlspecialchars($flash['msg'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endforeach; ?>

            <!-- Page header -->
            <div class="adm-page-header">
                <div>
                    <h2 class="adm-section-title">Manage Categories</h2>
                    <p class="adm-section-sub">Organise your bouquet catalogue with product categories.</p>
                </div>
                <?php if (!empty($categories)): ?>
                    <span class="adm-total-badge"><?= count($categories) ?> categor<?= count($categories) !== 1 ? 'ies' : 'y' ?></span>
                <?php endif; ?>
            </div>

            <!-- ── Two-column layout: form left, grid right ── -->
            <div class="cat-layout">

                <!-- ADD / EDIT FORM -->
                <aside class="cat-form-panel">
                    <div class="cat-form-card">
                        <div class="cat-form-header">
                            <span class="cat-form-icon"><?= $editTarget ? '✏️' : '➕' ?></span>
                            <h3><?= $editTarget ? 'Edit Category' : 'Add New Category' ?></h3>
                        </div>

                        <form action="/bloom-aura/admin/categories.php" method="POST" novalidate>
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action"
                                   value="<?= $editTarget ? 'update' : 'add' ?>">
                            <?php if ($editTarget): ?>
                                <input type="hidden" name="category_id"
                                       value="<?= (int)$editTarget['id'] ?>">
                            <?php endif; ?>

                            <!-- Name -->
                            <?php $nameErr = $errors['add_name'] ?? $errors['edit_name'] ?? null; ?>
                            <div class="cat-field <?= $nameErr ? 'has-error' : '' ?>">
                                <label for="cat-name">
                                    Category Name <span class="cat-required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="cat-name"
                                    name="name"
                                    maxlength="100"
                                    required
                                    placeholder="e.g. Red Roses"
                                    value="<?= htmlspecialchars(
                                        $_POST['name'] ?? $editTarget['name'] ?? '',
                                        ENT_QUOTES, 'UTF-8'
                                    ) ?>"
                                >
                                <?php if ($nameErr): ?>
                                    <span class="cat-field-error">
                                        <i class="fa-solid fa-circle-exclamation"></i>
                                        <?= htmlspecialchars($nameErr, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Description -->
                            <div class="cat-field">
                                <label for="cat-desc">Description <span class="cat-optional">(optional)</span></label>
                                <textarea
                                    id="cat-desc"
                                    name="description"
                                    rows="3"
                                    maxlength="500"
                                    placeholder="Briefly describe this category…"
                                ><?= htmlspecialchars(
                                    $_POST['description'] ?? $editTarget['description'] ?? '',
                                    ENT_QUOTES, 'UTF-8'
                                ) ?></textarea>
                            </div>

                            <!-- Actions -->
                            <div class="cat-form-actions">
                                <button type="submit" class="cat-btn-submit">
                                    <i class="fa-solid <?= $editTarget ? 'fa-floppy-disk' : 'fa-plus' ?>"></i>
                                    <?= $editTarget ? 'Save Changes' : 'Add Category' ?>
                                </button>
                                <?php if ($editTarget): ?>
                                    <a href="/bloom-aura/admin/categories.php"
                                       class="cat-btn-cancel">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- Hint -->
                        <p class="cat-form-hint">
                            <i class="fa-solid fa-circle-info"></i>
                            A URL-safe slug is generated automatically from the name.
                        </p>
                    </div>
                </aside>

                <!-- CATEGORY GRID -->
                <section class="cat-grid-panel">

                    <?php if ($dbError): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php elseif (empty($categories)): ?>
                        <!-- Empty state -->
                        <div class="cat-empty">
                            <div class="cat-empty-icon">🏷️</div>
                            <h4>No categories yet</h4>
                            <p>Use the form on the left to add your first product category.</p>
                        </div>
                    <?php else: ?>
                        <div class="cat-grid">
                            <?php foreach ($categories as $cat):
                                $emoji   = categoryEmoji($cat['name']);
                                $count   = (int)$cat['bouquet_count'];
                                $isEdit  = (int)$cat['id'] === $editId;
                            ?>
                            <article class="cat-card <?= $isEdit ? 'cat-card--editing' : '' ?>"
                                     aria-label="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?> category">

                                <!-- Emoji icon -->
                                <div class="cat-card-emoji"><?= $emoji ?></div>

                                <!-- Name -->
                                <div class="cat-card-name">
                                    <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <!-- Slug -->
                                <div class="cat-card-slug">
                                    <code><?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?></code>
                                </div>

                                <!-- Product count badge -->
                                <div class="cat-card-count">
                                    <span class="cat-count-badge <?= $count > 0 ? 'cat-count-badge--has' : 'cat-count-badge--empty' ?>">
                                        <?= $count ?> product<?= $count !== 1 ? 's' : '' ?>
                                    </span>
                                </div>

                                <!-- Description -->
                                <?php if (!empty($cat['description'])): ?>
                                    <p class="cat-card-desc">
                                        <?= htmlspecialchars(
                                            mb_strimwidth($cat['description'], 0, 72, '…'),
                                            ENT_QUOTES, 'UTF-8'
                                        ) ?>
                                    </p>
                                <?php endif; ?>

                                <!-- Actions -->
                                <div class="cat-card-actions">
                                    <a href="/bloom-aura/admin/categories.php?edit=<?= (int)$cat['id'] ?>"
                                       class="cat-action-edit"
                                       aria-label="Edit <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </a>

                                    <?php if ($count === 0): ?>
                                        <form action="/bloom-aura/admin/categories.php"
                                              method="POST"
                                              onsubmit="return confirm('Delete category «<?= htmlspecialchars(addslashes($cat['name']), ENT_QUOTES, 'UTF-8') ?>»? This cannot be undone.')">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                                            <button type="submit" class="cat-action-delete"
                                                    aria-label="Delete <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="cat-action-locked"
                                              title="Unlink all <?= $count ?> product<?= $count !== 1 ? 's' : '' ?> first">
                                            <i class="fa-solid fa-lock"></i> Locked
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($isEdit): ?>
                                    <div class="cat-card-editing-pill">Editing</div>
                                <?php endif; ?>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </section>

            </div><!-- /.cat-layout -->

        </div><!-- /.admin-content -->
    </main>
</div><!-- /.admin-layout -->

<script src="/bloom-aura/assets/js/admin_categories.js" defer></script>

</body>
</html>