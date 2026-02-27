<?php
/**
 * bloom-aura/pages/wishlist.php
 * Shows saved wishlist items. Allows removing items or adding to cart.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/auth_check.php';

$userId = (int)$_SESSION['user_id'];

// ── Handle POST (remove / add to cart) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action    = $_POST['action']     ?? '';
    $bouquetId = (int)($_POST['bouquet_id'] ?? 0);

    if ($bouquetId > 0) {
        try {
            $pdo = getPDO();

            if ($action === 'remove') {
                $pdo->prepare('DELETE FROM wishlist WHERE user_id = ? AND bouquet_id = ?')
                    ->execute([$userId, $bouquetId]);
                flash('Removed from wishlist.', 'info');
            }

            if ($action === 'move_to_cart') {
                // Verify product exists and is in stock
                $stmt = $pdo->prepare('SELECT name, price, stock, image FROM bouquets WHERE id = ?');
                $stmt->execute([$bouquetId]);
                $product = $stmt->fetch();

                if ($product && $product['stock'] > 0) {
                    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
                    $cart = &$_SESSION['cart'];
                    if (isset($cart[$bouquetId])) {
                        $cart[$bouquetId]['qty'] = min($cart[$bouquetId]['qty'] + 1, $product['stock']);
                    } else {
                        $cart[$bouquetId] = [
                            'id'    => $bouquetId,
                            'name'  => $product['name'],
                            'price' => $product['price'],
                            'image' => $product['image'],
                            'qty'   => 1,
                        ];
                    }
                    // Remove from wishlist after moving
                    $pdo->prepare('DELETE FROM wishlist WHERE user_id = ? AND bouquet_id = ?')
                        ->execute([$userId, $bouquetId]);
                    flash(htmlspecialchars($product['name']) . ' moved to cart! 🛒', 'success');
                } else {
                    flash('This item is out of stock.', 'error');
                }
            }

        } catch (RuntimeException $e) {
            flash('Could not update wishlist. Please try again.', 'error');
        }
    }

    header('Location: /pages/wishlist.php');
    exit;
}

// ── Fetch wishlist items ──────────────────────────────────────────────────────
$items = [];
$error = '';

try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare(
        "SELECT w.bouquet_id, w.added_at,
                b.name, b.slug, b.price, b.stock, b.image,
                c.name AS category_name,
                ROUND(AVG(r.rating), 1) AS avg_rating,
                COUNT(r.id) AS review_count
         FROM wishlist w
         JOIN bouquets b ON b.id = w.bouquet_id
         LEFT JOIN categories c ON c.id = b.category_id
         LEFT JOIN reviews r ON r.bouquet_id = b.id
         WHERE w.user_id = ?
         GROUP BY w.bouquet_id, w.added_at, b.name, b.slug, b.price, b.stock, b.image, c.name
         ORDER BY w.added_at DESC"
    );
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll();
} catch (RuntimeException $e) {
    $error = 'Unable to load your wishlist. Please try again later.';
}

$pageTitle = 'My Wishlist — Bloom Aura';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/">Home</a></li>
        <li><a href="/pages/profile.php">My Account</a></li>
        <li aria-current="page">Wishlist</li>
    </ol>
</nav>

<div class="page-container">
    <h1 class="page-title">
        <i class="fa-solid fa-heart" style="color: var(--color-primary);"></i> My Wishlist
        <?php if (!empty($items)): ?>
            <span class="page-title-count"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></span>
        <?php endif; ?>
    </h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

    <?php elseif (empty($items)): ?>
        <div class="empty-state">
            <div class="empty-icon">💐</div>
            <h2>Your wishlist is empty</h2>
            <p>Browse our bouquets and tap the heart icon to save your favourites here.</p>
            <a href="/pages/shop.php" class="btn btn-primary">Start Shopping</a>
        </div>

    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($items as $item): ?>
                <article class="product-card">
                    <a href="/pages/product.php?slug=<?= urlencode($item['slug']) ?>" class="card-img-wrap">
                        <img
                            src="/uploads/bouquets/<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                            loading="lazy" width="300" height="250"
                        >
                        <?php if ($item['stock'] <= 0): ?>
                            <span class="badge badge-oos">Out of Stock</span>
                        <?php endif; ?>
                    </a>
                    <div class="card-body">
                        <p class="card-category"><?= htmlspecialchars($item['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                        <h2 class="card-title">
                            <a href="/pages/product.php?slug=<?= urlencode($item['slug']) ?>">
                                <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </h2>
                        <?php if ($item['avg_rating']): ?>
                            <div class="card-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star <?= $item['avg_rating'] >= $i ? 'full' : 'empty' ?>">★</span>
                                <?php endfor; ?>
                                <span class="review-count">(<?= $item['review_count'] ?>)</span>
                            </div>
                        <?php endif; ?>
                        <p class="wishlist-saved-date">
                            Saved on <?= date('d M Y', strtotime($item['added_at'])) ?>
                        </p>
                        <div class="card-footer">
                            <span class="price">₹<?= number_format($item['price'], 2) ?></span>
                        </div>
                        <div class="wishlist-actions">
                            <?php if ($item['stock'] > 0): ?>
                                <form action="/pages/wishlist.php" method="POST">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="move_to_cart">
                                    <input type="hidden" name="bouquet_id" value="<?= (int)$item['bouquet_id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-basket-shopping"></i> Add to Cart
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-disabled btn-sm" disabled>Out of Stock</button>
                            <?php endif; ?>
                            <form action="/pages/wishlist.php" method="POST">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="bouquet_id" value="<?= (int)$item['bouquet_id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" title="Remove from wishlist">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div><!-- /.page-container -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
