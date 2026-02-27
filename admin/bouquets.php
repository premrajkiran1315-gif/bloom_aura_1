<?php
/**
 * bloom-aura/admin/bouquets.php
 * Admin: list all bouquets, search, delete.
 * Add/Edit links go to add-bouquet.php and edit-bouquet.php.
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

// ── Handle DELETE ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_validate();
    $id = (int)($_POST['bouquet_id'] ?? 0);
    if ($id > 0) {
        try {
            $pdo = getPDO();
            // Fetch image filename to delete the file
            $imgStmt = $pdo->prepare('SELECT image FROM bouquets WHERE id = ?');
            $imgStmt->execute([$id]);
            $imgRow = $imgStmt->fetch();

            $pdo->prepare('DELETE FROM bouquets WHERE id = ?')->execute([$id]);

            // Delete uploaded image if it exists
            if ($imgRow && $imgRow['image']) {
                $imgPath = __DIR__ . '/../uploads/bouquets/' . $imgRow['image'];
                if (file_exists($imgPath)) {
                    @unlink($imgPath);
                }
            }
            flash('Bouquet deleted successfully.', 'success');
        } catch (RuntimeException $e) {
            flash('Could not delete bouquet. It may be linked to existing orders.', 'error');
        }
    }
    header('Location: /admin/bouquets.php');
    exit;
}

// ── Fetch bouquets with pagination + search ───────────────────────────────────
define('PER_PAGE', 15);
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * PER_PAGE;
$search = trim($_GET['q'] ?? '');

$bouquets   = [];
$totalPages = 1;
$error      = '';

try {
    $pdo    = getPDO();
    $where  = $search !== '' ? 'WHERE b.name LIKE ? OR c.name LIKE ?' : '';
    $params = $search !== '' ? ["%$search%", "%$search%"] : [];

    $countSql = "SELECT COUNT(*) FROM bouquets b LEFT JOIN categories c ON c.id = b.category_id $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / PER_PAGE));

    $sql = "SELECT b.id, b.name, b.price, b.stock, b.image, b.created_at,
                   c.name AS category_name
            FROM bouquets b
            LEFT JOIN categories c ON c.id = b.category_id
            $where
            ORDER BY b.id DESC
            LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [PER_PAGE, $offset]));
    $bouquets = $stmt->fetchAll();
} catch (RuntimeException $e) {
    $error = 'Could not load bouquets.';
}

$pageTitle = 'Manage Bouquets — Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container admin-page">

    <div class="admin-page-header">
        <h1 class="page-title">Bouquets</h1>
        <a href="/admin/add-bouquet.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Add New Bouquet
        </a>
    </div>

    <!-- Search -->
    <form action="/admin/bouquets.php" method="GET" class="admin-search-form">
        <input type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
               placeholder="Search bouquets or categories…" class="admin-search-input">
        <button type="submit" class="btn btn-outline">Search</button>
        <?php if ($search): ?>
            <a href="/admin/bouquets.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (empty($bouquets)): ?>
        <div class="empty-state">
            <div class="empty-icon">🌷</div>
            <h2>No bouquets found</h2>
            <p><?= $search ? 'Try a different search term.' : 'Add your first bouquet to get started.' ?></p>
            <a href="/admin/add-bouquet.php" class="btn btn-primary">Add Bouquet</a>
        </div>
    <?php else: ?>
        <p class="results-count"><?= $total ?> bouquet<?= $total !== 1 ? 's' : '' ?></p>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bouquets as $b): ?>
                        <tr>
                            <td>
                                <img
                                    src="/uploads/bouquets/<?= htmlspecialchars($b['image'], ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    class="admin-thumb" width="56" height="56" loading="lazy"
                                >
                            </td>
                            <td>
                                <a href="/pages/product.php?slug=<?= urlencode($b['name']) ?>" target="_blank" class="table-product-name">
                                    <?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($b['category_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>₹<?= number_format($b['price'], 2) ?></td>
                            <td>
                                <?php if ($b['stock'] <= 0): ?>
                                    <span class="badge badge-oos">Out of Stock</span>
                                <?php elseif ($b['stock'] <= 5): ?>
                                    <span class="badge badge-low"><?= (int)$b['stock'] ?></span>
                                <?php else: ?>
                                    <span class="badge badge-ok"><?= (int)$b['stock'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                            <td class="table-actions">
                                <a href="/admin/edit-bouquet.php?id=<?= (int)$b['id'] ?>" class="btn btn-outline btn-xs">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <form action="/admin/bouquets.php" method="POST"
                                      onsubmit="return confirm('Delete «<?= htmlspecialchars(addslashes($b['name']), ENT_QUOTES, 'UTF-8') ?>»? This cannot be undone.')">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="bouquet_id" value="<?= (int)$b['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-xs">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Bouquets pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>"
                       class="page-link <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div><!-- /.page-container -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
