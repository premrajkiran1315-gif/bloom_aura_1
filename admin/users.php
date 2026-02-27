<?php
/**
 * bloom-aura/admin/users.php
 * Admin: view all customer accounts, search, activate/deactivate.
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

// ── Handle toggle active/inactive ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    csrf_validate();
    $targetId  = (int)($_POST['user_id'] ?? 0);
    $newStatus = (int)($_POST['is_active'] ?? 0) === 1 ? 0 : 1; // flip

    // Prevent admin deactivating themselves
    if ($targetId > 0 && $targetId !== (int)$_SESSION['admin_id']) {
        try {
            $pdo = getPDO();
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ? AND role = "customer"')
                ->execute([$newStatus, $targetId]);
            flash('User account ' . ($newStatus ? 'activated' : 'deactivated') . '.', 'success');
        } catch (RuntimeException $e) {
            flash('Could not update user.', 'error');
        }
    } else {
        flash('Cannot modify this account.', 'error');
    }
    header('Location: /admin/users.php');
    exit;
}

// ── Pagination + search ───────────────────────────────────────────────────────
define('USERS_PER_PAGE', 20);
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * USERS_PER_PAGE;
$search = trim($_GET['q'] ?? '');
$filterActive = $_GET['active'] ?? '';  // '' | '1' | '0'

// ── Fetch users ───────────────────────────────────────────────────────────────
$users      = [];
$total      = 0;
$totalPages = 1;
$error      = '';

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
        $where[]  = 'u.is_active = 1';
    } elseif ($filterActive === '0') {
        $where[]  = 'u.is_active = 0';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSql");
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / USERS_PER_PAGE));

    $userStmt = $pdo->prepare(
        "SELECT u.id, u.name, u.email, u.is_active, u.created_at,
                COUNT(o.id)             AS order_count,
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
    $error = 'Could not load users.';
}

$pageTitle = 'Manage Users — Admin';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/admin/dashboard.php">Dashboard</a></li>
        <li aria-current="page">Users</li>
    </ol>
</nav>

<div class="page-container admin-page">
    <h1 class="page-title">Customer Accounts</h1>

    <!-- Search + filter bar -->
    <div class="admin-filter-bar">
        <a href="/admin/users.php"
           class="filter-tab <?= $filterActive === '' ? 'active' : '' ?>">All</a>
        <a href="/admin/users.php?active=1<?= $search ? '&q=' . urlencode($search) : '' ?>"
           class="filter-tab <?= $filterActive === '1' ? 'active' : '' ?>">Active</a>
        <a href="/admin/users.php?active=0<?= $search ? '&q=' . urlencode($search) : '' ?>"
           class="filter-tab <?= $filterActive === '0' ? 'active' : '' ?>">Deactivated</a>

        <form action="/admin/users.php" method="GET" class="admin-id-search">
            <?php if ($filterActive !== ''): ?>
                <input type="hidden" name="active" value="<?= htmlspecialchars($filterActive, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <input type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Search name or email…" class="admin-search-input">
            <button type="submit" class="btn btn-outline btn-sm">Search</button>
            <?php if ($search): ?>
                <a href="/admin/users.php<?= $filterActive ? '?active=' . urlencode($filterActive) : '' ?>"
                   class="btn btn-ghost btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (empty($users)): ?>
        <div class="empty-state">
            <div class="empty-icon">👥</div>
            <h2>No users found</h2>
            <p><?= $search ? 'Try a different search.' : 'No customers have registered yet.' ?></p>
        </div>
    <?php else: ?>
        <p class="results-count"><?= $total ?> customer<?= $total !== 1 ? 's' : '' ?></p>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Joined</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="user-avatar-inline">
                                    <span class="avatar-circle" style="background: hsl(<?= (crc32($u['name']) % 360 + 360) % 360 ?>, 55%, 60%)">
                                        <?= strtoupper(mb_substr($u['name'], 0, 1)) ?>
                                    </span>
                                    <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            <td><?= (int)$u['order_count'] ?></td>
                            <td>₹<?= number_format($u['total_spent'], 0) ?></td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="status-badge status-delivered">Active</span>
                                <?php else: ?>
                                    <span class="status-badge status-cancelled">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="/admin/users.php" method="POST"
                                      onsubmit="return confirm('Are you sure you want to <?= $u['is_active'] ? 'deactivate' : 'activate' ?> this account?')">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= (int)$u['is_active'] ?>">
                                    <button type="submit"
                                            class="btn btn-xs <?= $u['is_active'] ? 'btn-danger' : 'btn-outline' ?>">
                                        <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
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
            <nav class="pagination" aria-label="Users pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&active=<?= urlencode($filterActive) ?>"
                       class="page-link <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div><!-- /.page-container -->

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
