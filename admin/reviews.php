<?php
/**
 * bloom-aura/admin/reviews.php
 * Admin: view all customer reviews, filter by rating, delete.
 * UI pixel-matched to bloom_aura reference HTML —
 * adm-review-summary + adm-rating-bars + adm-review-cards layout.
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

/* ── Handle DELETE ───────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_validate();
    $reviewId = (int)($_POST['review_id'] ?? 0);
    if ($reviewId > 0) {
        try {
            $pdo = getPDO();
            $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$reviewId]);
            flash('Review deleted.', 'success');
        } catch (RuntimeException $e) {
            flash('Could not delete review.', 'error');
        }
    }
    header('Location: /bloom-aura/admin/reviews.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
    exit;
}

/* ── Filters ─────────────────────────────────────────────────────────────── */
$filterRating = (int)($_GET['rating'] ?? 0);   // 0 = all, 1-5 = specific star
$filterRating = ($filterRating >= 1 && $filterRating <= 5) ? $filterRating : 0;

define('REVIEWS_PER_PAGE', 20);
$page = max(1, (int)($_GET['page'] ?? 1));

/* ── Fetch all reviews ───────────────────────────────────────────────────── */
$reviews    = [];
$total      = 0;
$totalPages = 1;
$dbError    = '';

/* Rating distribution (all reviews, not just filtered) */
$starCounts  = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$avgRating   = 0;
$totalReviews = 0;

try {
    $pdo = getPDO();

    /* ── Rating summary (always all reviews) ── */
    $summaryRows = $pdo->query(
        'SELECT rating, COUNT(*) AS cnt FROM reviews GROUP BY rating'
    )->fetchAll();

    foreach ($summaryRows as $row) {
        $starCounts[(int)$row['rating']] = (int)$row['cnt'];
        $totalReviews += (int)$row['cnt'];
    }

    if ($totalReviews > 0) {
        $weightedSum = 0;
        foreach ($starCounts as $star => $cnt) {
            $weightedSum += $star * $cnt;
        }
        $avgRating = round($weightedSum / $totalReviews, 1);
    }

    /* ── Filtered review list ── */
    $where  = [];
    $params = [];
    if ($filterRating > 0) {
        $where[]  = 'r.rating = ?';
        $params[] = $filterRating;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM reviews r $whereSql");
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / REVIEWS_PER_PAGE));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * REVIEWS_PER_PAGE;

    $stmt = $pdo->prepare(
        "SELECT r.id, r.rating, r.comment, r.created_at,
                u.name  AS reviewer_name,
                u.email AS reviewer_email,
                b.name  AS bouquet_name,
                b.slug  AS bouquet_slug
         FROM reviews r
         JOIN users   u ON u.id = r.user_id
         JOIN bouquets b ON b.id = r.bouquet_id
         $whereSql
         ORDER BY r.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute(array_merge($params, [REVIEWS_PER_PAGE, $offset]));
    $reviews = $stmt->fetchAll();

} catch (RuntimeException $e) {
    $dbError = 'Could not load reviews.';
}

/* ── Flash messages ──────────────────────────────────────────────────────── */
$flashMessages = [];
if (!empty($_SESSION['flash'])) {
    $flashMessages = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

/* ── Star render helper ──────────────────────────────────────────────────── */
function renderStars(int $rating, int $total = 5): string {
    $html = '';
    for ($i = 1; $i <= $total; $i++) {
        $html .= $i <= $rating ? '★' : '☆';
    }
    return $html;
}

/* ── URL helper ──────────────────────────────────────────────────────────── */
function revUrl(array $overrides = []): string {
    global $filterRating;
    $params = array_filter([
        'rating' => $overrides['rating'] ?? ($filterRating ?: null),
        'page'   => $overrides['page']   ?? null,
    ], fn($v) => $v !== null && $v !== 0 && $v !== '');
    return '/bloom-aura/admin/reviews.php' . ($params ? '?' . http_build_query($params) : '');
}

/* ── Avatar gradient colours ─────────────────────────────────────────────── */
$avatarColors = [
    'linear-gradient(135deg,#d63384,#ff4d94)',
    'linear-gradient(135deg,#6366f1,#a5b4fc)',
    'linear-gradient(135deg,#10b981,#6ee7b7)',
    'linear-gradient(135deg,#f59e0b,#fcd34d)',
    'linear-gradient(135deg,#ef4444,#fca5a5)',
    'linear-gradient(135deg,#8b5cf6,#c4b5fd)',
];

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews — Bloom Aura Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin_reviews.css">
</head>
<body class="admin-body">

<div class="admin-layout">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">

        <!-- ── Topbar ── -->
        <div class="admin-topbar">
            <h1 class="admin-page-title">Reviews</h1>
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
                    <h2 class="adm-section-title">Customer Reviews</h2>
                    <p class="adm-section-sub">Feedback submitted by customers across all products.</p>
                </div>
                <?php if ($totalReviews > 0): ?>
                    <span class="adm-total-badge"><?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>

            <?php if ($dbError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>

            <?php elseif ($totalReviews === 0): ?>
                <!-- Empty state -->
                <div class="rev-empty">
                    <div class="rev-empty-icon">⭐</div>
                    <h4>No reviews yet</h4>
                    <p>Customer reviews will appear here once they submit feedback on products.</p>
                </div>

            <?php else: ?>

                <!-- ══════════════════════════════════════════
                     RATING SUMMARY — matches reference exactly:
                     adm-review-summary > adm-big-rating + adm-rating-bars
                ═══════════════════════════════════════════ -->
                <div class="adm-review-summary">

                    <!-- Big average score -->
                    <div class="adm-big-rating">
                        <div class="num"><?= number_format($avgRating, 1) ?></div>
                        <div class="stars">
                            <?php
                            $rounded = (int)round($avgRating);
                            echo str_repeat('★', $rounded);
                            echo str_repeat('☆', 5 - $rounded);
                            ?>
                        </div>
                        <div class="count">from <?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?></div>
                    </div>

                    <!-- Rating bars -->
                    <div class="adm-rating-bars">
                        <?php foreach ([5,4,3,2,1] as $star): ?>
                            <?php $cnt = $starCounts[$star]; ?>
                            <?php $pct = $totalReviews > 0 ? round(($cnt / $totalReviews) * 100) : 0; ?>
                            <a class="adm-rb-row <?= $filterRating === $star ? 'adm-rb-row--active' : '' ?>"
                               href="<?= revUrl(['rating' => $filterRating === $star ? 0 : $star, 'page' => null]) ?>"
                               title="Filter to <?= $star ?>-star reviews">
                                <span class="adm-rb-lbl"><?= $star ?>★</span>
                                <div class="adm-rb-track">
                                    <div class="adm-rb-fill" style="width:<?= $pct ?>%"></div>
                                </div>
                                <span class="adm-rb-count"><?= $cnt ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                </div><!-- /.adm-review-summary -->

                <!-- ── Filter tabs ── -->
                <div class="rev-toolbar">
                    <div class="adm-filter-tabs">
                        <a href="<?= revUrl(['rating' => 0, 'page' => null]) ?>"
                           class="adm-filter-btn <?= $filterRating === 0 ? 'active' : '' ?>">
                            All Reviews
                        </a>
                        <?php foreach ([5,4,3,2,1] as $star): ?>
                            <a href="<?= revUrl(['rating' => $filterRating === $star ? 0 : $star, 'page' => null]) ?>"
                               class="adm-filter-btn <?= $filterRating === $star ? 'active' : '' ?>">
                                <?= str_repeat('★', $star) ?> (<?= $starCounts[$star] ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($total > 0): ?>
                        <span class="rev-filter-count">
                            <?= $total ?> result<?= $total !== 1 ? 's' : '' ?>
                            <?= $filterRating ? "for {$filterRating}★" : '' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- ══════════════════════════════════════════
                     REVIEW CARDS — matches reference exactly:
                     adm-review-cards > adm-review-card
                ═══════════════════════════════════════════ -->
                <?php if (empty($reviews)): ?>
                    <div class="rev-empty rev-empty--inline">
                        <div class="rev-empty-icon">🔍</div>
                        <h4>No <?= $filterRating ?>-star reviews found.</h4>
                        <p><a href="<?= revUrl(['rating' => 0]) ?>">Clear filter</a></p>
                    </div>
                <?php else: ?>
                    <div class="adm-review-cards">
                        <?php foreach ($reviews as $i => $rev):
                            $gradient = $avatarColors[$i % count($avatarColors)];
                            $initial  = strtoupper(mb_substr($rev['reviewer_name'], 0, 1));
                        ?>
                        <article class="adm-review-card"
                                 aria-label="Review by <?= htmlspecialchars($rev['reviewer_name'], ENT_QUOTES, 'UTF-8') ?>">

                            <!-- Top row: reviewer info + stars + date -->
                            <div class="adm-review-top">
                                <div class="rev-reviewer-left">
                                    <!-- Avatar -->
                                    <div class="rev-avatar" style="background:<?= $gradient ?>"
                                         aria-hidden="true"><?= $initial ?></div>
                                    <div>
                                        <div class="adm-reviewer">
                                            <?= htmlspecialchars($rev['reviewer_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="adm-review-date">
                                            <?= date('d M Y', strtotime($rev['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="adm-review-stars" aria-label="<?= $rev['rating'] ?> out of 5 stars">
                                    <?= renderStars((int)$rev['rating']) ?>
                                </div>
                            </div>

                            <!-- Review body -->
                            <?php if (!empty($rev['comment'])): ?>
                                <div class="adm-review-text">
                                    "<?= nl2br(htmlspecialchars($rev['comment'], ENT_QUOTES, 'UTF-8')) ?>"
                                </div>
                            <?php endif; ?>

                            <!-- Product chip + delete action -->
                            <div class="rev-card-footer">
                                <div class="rev-chips">
                                    <a href="/bloom-aura/pages/product.php?slug=<?= urlencode($rev['bouquet_slug']) ?>"
                                       class="adm-review-chip"
                                       target="_blank"
                                       rel="noopener">
                                        🌸 <?= htmlspecialchars($rev['bouquet_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <span class="adm-review-chip rev-rating-chip rev-chip-<?= $rev['rating'] ?>">
                                        <?= $rev['rating'] ?>★
                                    </span>
                                </div>

                                <!-- Delete -->
                                <form action="/bloom-aura/admin/reviews.php" method="POST"
                                      onsubmit="return confirm('Delete this review by <?= htmlspecialchars(addslashes($rev['reviewer_name']), ENT_QUOTES, 'UTF-8') ?>? This cannot be undone.')">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action"    value="delete">
                                    <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                                    <button type="submit" class="rev-delete-btn"
                                            aria-label="Delete review by <?= htmlspecialchars($rev['reviewer_name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>

                        </article>
                        <?php endforeach; ?>
                    </div><!-- /.adm-review-cards -->

                    <!-- ── Pagination ── -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="adm-pagination" aria-label="Reviews pagination">
                            <?php if ($page > 1): ?>
                                <a href="<?= revUrl(['page' => $page - 1]) ?>" class="adm-page-link">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <a href="<?= revUrl(['page' => $p]) ?>"
                                   class="adm-page-link <?= $p === $page ? 'active' : '' ?>"
                                   <?= $p === $page ? 'aria-current="page"' : '' ?>>
                                    <?= $p ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="<?= revUrl(['page' => $page + 1]) ?>" class="adm-page-link">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>

                <?php endif; ?>

            <?php endif; ?>

        </div><!-- /.admin-content -->
    </main>
</div><!-- /.admin-layout -->

<script src="/bloom-aura/assets/js/admin_reviews.js" defer></script>

</body>
</html>