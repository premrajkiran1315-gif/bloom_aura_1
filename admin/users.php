<?php
/**
 * bloom-aura/admin/users.php
 * Admin: view all customer accounts, search, filter, activate/deactivate.
 * UI pixel-matched to bloom_aura reference HTML — adm-cust-grid card layout.
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

/* ── Handle activate / deactivate ────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    csrf_validate();
    $targetId  = (int)($_POST['user_id']   ?? 0);
    $curStatus = (int)($_POST['is_active'] ?? 0);
    $newStatus = $curStatus === 1 ? 0 : 1;

    if ($targetId > 0 && $targetId !== (int)$_SESSION['admin_id']) {
        try {
            $pdo = getPDO();
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ? AND role = "customer"')
                ->execute([$newStatus, $targetId]);
            flash('Customer ' . ($newStatus ? 'activated' : 'deactivated') . '.', 'success');
        } catch (RuntimeException $e) {
            flash('Could not update customer.', 'error');
        }
    } else {
        flash('Cannot modify this account.', 'error');
    }
    header('Location: /bloom-aura/admin/users.php');
    exit;
}

/* ── Pagination + filters ────────────────────────────────────────────────── */
define('USERS_PER_PAGE', 24);
$page         = max(1, (int)($_GET['page']   ?? 1));
$search       = trim($_GET['q']              ?? '');
$filterActive = $_GET['active']              ?? '';   // '' | '1' | '0'

/* ── Fetch users ─────────────────────────────────────────────────────────── */
$users      = [];
$total      = 0;
$totalPages = 1;
$dbError    = '';

try {
    $pdo    = getPDO();
    $where  = ["u.role = 'customer'"];
    $params = [];

    if ($search !== '') {
        $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filterActive === '1') {
        $where[] = 'u.is_active = 1';
    } elseif ($filterActive === '0') {
        $where[] = 'u.is_active = 0';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSql");
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / USERS_PER_PAGE));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * USERS_PER_PAGE;

    $userStmt = $pdo->prepare(
        "SELECT u.id, u.name, u.email, u.is_active, u.created_at,
                COUNT(o.id)              AS order_count,
                COALESCE(SUM(o.total),0) AS total_spent
         FROM users u
         LEFT JOIN orders o ON o.user_id = u.id
         $whereSql
         GROUP BY u.id
         ORDER BY u.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $userStmt->execute(array_merge($params, [USERS_PER_PAGE, $offset]));
    $users = $userStmt->fetchAll();

} catch (RuntimeException $e) {
    $dbError = 'Could not load customers.';
}

/* ── Flash messages ──────────────────────────────────────────────────────── */
$flashMessages = [];
if (!empty($_SESSION['flash'])) {
    $flashMessages = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

/* ── Avatar gradient colours (cycles like reference) ─────────────────────── */
$avatarColors = [
    'linear-gradient(135deg,#d63384,#ff4d94)',
    'linear-gradient(135deg,#6366f1,#a5b4fc)',
    'linear-gradient(135deg,#10b981,#6ee7b7)',
    'linear-gradient(135deg,#f59e0b,#fcd34d)',
    'linear-gradient(135deg,#ef4444,#fca5a5)',
    'linear-gradient(135deg,#8b5cf6,#c4b5fd)',
];

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');

/* ── Helper: build URL preserving filters ────────────────────────────────── */
function custUrl(array $overrides = []): string {
    global $search, $filterActive;
    $params = array_filter([
        'q'      => $overrides['q']      ?? $search,
        'active' => $overrides['active'] ?? $filterActive,
        'page'   => $overrides['page']   ?? null,
    ], fn($v) => $v !== '' && $v !== null);
    return '/bloom-aura/admin/users.php' . ($params ? '?' . http_build_query($params) : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers — Bloom Aura Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin_customers.css">
</head>
<body class="admin-body">

<div class="admin-layout">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">

        <!-- ── Topbar ── -->
        <div class="admin-topbar">
            <h1 class="admin-page-title">Customers</h1>
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
                    <h2 class="adm-section-title">Customer Accounts</h2>
                    <p class="adm-section-sub">Users who have registered and shopped with Bloom Aura.</p>
                </div>
                <?php if ($total > 0): ?>
                    <span class="adm-total-badge"><?= $total ?> customer<?= $total !== 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>

            <!-- ── Toolbar: filter tabs + search ── -->
            <div class="cust-toolbar">

                <!-- Filter tabs -->
                <div class="adm-filter-tabs">
                    <a href="<?= custUrl(['active' => '', 'page' => null]) ?>"
                       class="adm-filter-btn <?= $filterActive === '' ? 'active' : '' ?>">
                        All Customers
                    </a>
                    <a href="<?= custUrl(['active' => '1', 'page' => null]) ?>"
                       class="adm-filter-btn <?= $filterActive === '1' ? 'active' : '' ?>">
                        <i class="fa-solid fa-circle-check"></i> Active
                    </a>
                    <a href="<?= custUrl(['active' => '0', 'page' => null]) ?>"
                       class="adm-filter-btn <?= $filterActive === '0' ? 'active' : '' ?>">
                        <i class="fa-solid fa-ban"></i> Deactivated
                    </a>
                </div>

                <!-- Search form -->
                <form action="/bloom-aura/admin/users.php" method="GET" class="cust-search-form" role="search">
                    <?php if ($filterActive !== ''): ?>
                        <input type="hidden" name="active" value="<?= htmlspecialchars($filterActive, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <div class="cust-search-wrap">
                        <i class="fa-solid fa-magnifying-glass cust-search-icon" aria-hidden="true"></i>
                        <input
                            type="search"
                            name="q"
                            value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Search name or email…"
                            class="cust-search-input"
                            aria-label="Search customers"
                        >
                    </div>
                    <button type="submit" class="cust-search-btn">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="<?= custUrl(['q' => '', 'page' => null]) ?>" class="cust-clear-btn">
                            <i class="fa-solid fa-xmark"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>

            </div><!-- /.cust-toolbar -->

            <!-- ── States ── -->
            <?php if ($dbError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>

            <?php elseif (empty($users)): ?>
                <div class="adm-empty cust-empty">
                    <div class="ei">👥</div>
                    <h4><?= $search ? 'No customers match your search.' : 'No customers yet.' ?></h4>
                    <p>
                        <?php if ($search): ?>
                            Try a different name or email.
                            <a href="<?= custUrl(['q' => '', 'page' => null]) ?>">Clear search</a>
                        <?php else: ?>
                            Customer profiles appear here once users register and place orders.
                        <?php endif; ?>
                    </p>
                </div>

            <?php else: ?>

                <?php if ($total > USERS_PER_PAGE): ?>
                    <p class="cust-results-count">
                        Showing <?= (($page - 1) * USERS_PER_PAGE) + 1 ?>–<?= min($page * USERS_PER_PAGE, $total) ?>
                        of <?= $total ?> customers
                    </p>
                <?php endif; ?>

                <!-- ── Customer card grid ── -->
                <div class="adm-cust-grid">
                    <?php foreach ($users as $i => $u):
                        $initial  = strtoupper(mb_substr($u['name'], 0, 1));
                        $gradient = $avatarColors[$i % count($avatarColors)];
                        $isVip    = (int)$u['order_count'] >= 5;
                        $isActive = (int)$u['is_active'] === 1;
                        $joined   = date('d M Y', strtotime($u['created_at']));
                        $spent    = number_format((float)$u['total_spent'], 0);
                    ?>
                    <article class="adm-cust-card <?= !$isActive ? 'adm-cust-card--inactive' : '' ?>"
                             aria-label="Customer: <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Top: avatar + name + joined -->
                        <div class="adm-cust-top">
                            <div class="adm-cust-av" style="background:<?= $gradient ?>">
                                <?= $initial ?>
                            </div>
                            <div class="adm-cust-info">
                                <div class="adm-cust-name">
                                    <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($isVip): ?>
                                        <span class="adm-vip-badge">VIP</span>
                                    <?php endif; ?>
                                </div>
                                <div class="adm-cust-email">
                                    <?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="adm-cust-since">
                                    Joined <?= $joined ?>
                                </div>
                            </div>
                        </div>

                        <!-- Stats row: orders + spent -->
                        <div class="adm-cust-stat">
                            <div class="adm-cust-s">
                                <div class="sv"><?= (int)$u['order_count'] ?></div>
                                <div class="sl">Orders</div>
                            </div>
                            <div class="adm-cust-s">
                                <div class="sv">₹<?= $spent ?></div>
                                <div class="sl">Spent</div>
                            </div>
                            <div class="adm-cust-s">
                                <div class="sv <?= $isActive ? 'sv--active' : 'sv--inactive' ?>">
                                    <?= $isActive ? 'Active' : 'Off' ?>
                                </div>
                                <div class="sl">Status</div>
                            </div>
                        </div>

                        <!-- Status badge -->
                        <div class="adm-cust-status-row">
                            <span class="adm-cust-status-badge <?= $isActive ? 'badge--active' : 'badge--inactive' ?>">
                                <i class="fa-solid <?= $isActive ? 'fa-circle-check' : 'fa-ban' ?>"></i>
                                <?= $isActive ? 'Active' : 'Deactivated' ?>
                            </span>
                        </div>

                        <!-- Toggle action -->
                        <div class="adm-cust-actions">
                            <form action="/bloom-aura/admin/users.php" method="POST"
                                  onsubmit="return confirm('<?= $isActive
                                      ? 'Deactivate this customer? They will not be able to log in.'
                                      : 'Activate this customer account?' ?>')">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action"    value="toggle_active">
                                <input type="hidden" name="user_id"   value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= (int)$u['is_active'] ?>">
                                <button type="submit"
                                        class="cust-toggle-btn <?= $isActive ? 'cust-toggle-btn--deactivate' : 'cust-toggle-btn--activate' ?>">
                                    <i class="fa-solid <?= $isActive ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                    <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </div>

                    </article>
                    <?php endforeach; ?>
                </div><!-- /.adm-cust-grid -->

                <!-- ── Pagination ── -->
                <?php if ($totalPages > 1): ?>
                    <nav class="adm-pagination" aria-label="Customers pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?= custUrl(['page' => $page - 1]) ?>" class="adm-page-link">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <a href="<?= custUrl(['page' => $p]) ?>"
                               class="adm-page-link <?= $p === $page ? 'active' : '' ?>"
                               <?= $p === $page ? 'aria-current="page"' : '' ?>>
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= custUrl(['page' => $page + 1]) ?>" class="adm-page-link">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

            <?php endif; ?>

        </div><!-- /.admin-content -->
    </main>
</div><!-- /.admin-layout -->

<script src="/bloom-aura/assets/js/admin_customers.js" defer></script>

</body>
</html>