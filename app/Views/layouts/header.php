<?php
use App\Services\AuthService;
use App\Services\TranslationService;
use App\Services\WalletService;
use App\Services\NotificationService;

$currentUser = AuthService::user();
$currentRole = $currentUser['role'] ?? null;
$isDualRole = $currentUser ? AuthService::isDualRole((int)$currentUser['id']) : false;
$activeMode = AuthService::activePortalMode();
$profileBadge = 'Retailer';
if ($currentRole === 'ADMIN') {
    $profileBadge = 'Admin';
} elseif ($currentRole === 'DISTRIBUTOR') {
    $profileBadge = 'Distributor';
} elseif ($isDualRole) {
    $profileBadge = 'Dual Access';
} elseif ($currentRole === 'OPERATOR') {
    $profileBadge = 'Staff';
}
$walletBalance = $currentUser ? WalletService::getBalance($currentUser['id']) : 0.00;
$unreadNotifsCount = $currentUser ? NotificationService::getUnreadCount($currentUser['id']) : 0;
$recentNotifs = $currentUser ? NotificationService::getRecent($currentUser['id'], 5) : [];
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(__('app_name')) ?> - <?= e(__('app_tagline')) ?></title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link href="/assets/css/custom.css" rel="stylesheet">
    <script>
        try {
            if (window.innerWidth >= 992 && localStorage.getItem('wb_sidebar_collapsed') === '1') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch(e) {}
    </script>
</head>
<body>

<div class="wb-wrapper">

<?php if ($currentUser): ?>
    <!-- Mobile Backdrop -->
    <div class="wb-backdrop" id="wbBackdrop"></div>

    <!-- Responsive Left Sidebar -->
    <aside class="wb-sidebar" id="wbSidebar">
        <!-- Sidebar Brand -->
        <div class="wb-sidebar-brand">
            <a class="wb-brand" href="/">
                <i class="bi bi-shield-check text-warning fs-4"></i>
                <span class="fs-5">Welcome Bihar</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary role-badge"><?= e($profileBadge) ?></span>
                <button class="btn btn-sm btn-link text-white-50 d-lg-none p-0 fs-5" id="wbSidebarClose" type="button">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <!-- Sidebar Navigation Menu -->
        <nav class="wb-sidebar-nav">
            <div class="wb-nav-header">Main Navigation</div>

            <?php if ($currentRole === 'ADMIN'): ?>
                <a class="wb-sidebar-link <?= $currentUri === '/admin/dashboard' ? 'active' : '' ?>" href="/admin/dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <a class="wb-sidebar-link <?= (strpos($currentUri, '/admin/applications') === 0 || $currentUri === '/applications') ? 'active' : '' ?>" href="/admin/applications">
                    <i class="bi bi-folder2-open"></i>
                    <span>Applications</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/users') === 0 ? 'active' : '' ?>" href="/admin/users">
                    <i class="bi bi-people"></i>
                    <span>Users & Operators</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/staff-routing') === 0 ? 'active' : '' ?>" href="/admin/staff-routing">
                    <i class="bi bi-diagram-3 text-info"></i>
                    <span>Staff Routing</span>
                </a>
                <a class="wb-sidebar-link <?= (strpos($currentUri, '/admin/block-rates') === 0 || strpos($currentUri, '/admin/data-types/rate-rules') === 0) ? 'active' : '' ?>" href="/admin/block-rates">
                    <i class="bi bi-currency-rupee text-warning"></i>
                    <span>Block Rates & Pricing</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/application-code-forms') === 0 ? 'active' : '' ?>" href="/admin/application-code-forms">
                    <i class="bi bi-ui-checks-grid text-primary"></i>
                    <span>Application Code Forms</span>
                </a>
                <a class="wb-sidebar-link <?= (strpos($currentUri, '/admin/data-types') === 0 && strpos($currentUri, '/admin/data-types/rate-rules') === false) ? 'active' : '' ?>" href="/admin/data-types">
                    <i class="bi bi-sliders"></i>
                    <span>Service Management</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/locations') === 0 ? 'active' : '' ?>" href="/admin/locations">
                    <i class="bi bi-geo-alt"></i>
                    <span>Districts & Blocks</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/service-availability') === 0 ? 'active' : '' ?>" href="/admin/service-availability">
                    <i class="bi bi-toggle-on text-success"></i>
                    <span>Service Availability</span>
                </a>

                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/reconciliation') === 0 ? 'active' : '' ?>" href="/admin/reconciliation">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>Reconciliation</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/audit-logs') === 0 ? 'active' : '' ?>" href="/admin/audit-logs">
                    <i class="bi bi-journal-text"></i>
                    <span>Audit Trail</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/whatsapp') === 0 ? 'active' : '' ?>" href="/admin/whatsapp/logs">
                    <i class="bi bi-whatsapp text-success"></i>
                    <span>WhatsApp Delivery</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/settings') === 0 ? 'active' : '' ?>" href="/admin/settings">
                    <i class="bi bi-gear"></i>
                    <span>System Settings</span>
                </a>
                <div class="px-3 pt-2 pb-1">
                    <form method="POST" action="/admin/system-update" onsubmit="return confirm('Run 1-Click System & Database Update now? This will synchronize all database columns, tables, and migrations.')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-warning w-100 text-start d-flex align-items-center justify-content-between px-2.5 py-1.5 shadow-2xs" style="border-radius: 6px; font-size: 0.76rem; font-weight: 600;">
                            <span class="d-flex align-items-center gap-2">
                                <i class="bi bi-cloud-arrow-down-fill text-warning"></i>
                                <span>1-Click Update</span>
                            </span>
                            <span class="badge bg-warning text-dark py-0.5 px-1.5" style="font-size: 0.65rem;">Sync</span>
                        </button>
                    </form>
                </div>

            <?php elseif ($currentRole === 'DISTRIBUTOR'): ?>
                <a class="wb-sidebar-link <?= $currentUri === '/distributor/dashboard' ? 'active' : '' ?>" href="/distributor/dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/distributor/retailers') === 0 ? 'active' : '' ?>" href="/distributor/retailers/new">
                    <i class="bi bi-person-plus-fill"></i>
                    <span>Add Retailer</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/distributor/team') === 0 ? 'active' : '' ?>" href="/distributor/team">
                    <i class="bi bi-people"></i>
                    <span>Team & Scope</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/distributor/applications') === 0 ? 'active' : '' ?>" href="/distributor/applications">
                    <i class="bi bi-folder2-open"></i>
                    <span>Applications</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/wallet/passbook') === 0 ? 'active' : '' ?>" href="/wallet/passbook">
                    <i class="bi bi-wallet2"></i>
                    <span>Wallet Passbook</span>
                </a>

            <?php elseif ($currentRole === 'OPERATOR' || ($isDualRole && $activeMode === 'OPERATOR')): ?>
                <!-- Staff Mode Sidebar -->
                <div class="px-3 py-1 mb-2">
                    <span class="badge bg-warning text-dark py-1 px-2" style="font-size: 0.72rem; font-weight: 700;">
                        <i class="bi bi-briefcase me-1"></i> Staff Mode
                    </span>
                </div>
                <a class="wb-sidebar-link <?= $currentUri === '/admin/dashboard' ? 'active' : '' ?>" href="/admin/dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span>Staff Dashboard</span>
                </a>
                <a class="wb-sidebar-link <?= ($currentUri === '/admin/applications' && empty($_GET['status'])) ? 'active' : '' ?>" href="/admin/applications">
                    <i class="bi bi-folder2-open"></i>
                    <span>Assigned Applications</span>
                </a>
                <a class="wb-sidebar-link <?= ($currentUri === '/admin/applications' && ($_GET['status'] ?? '') === 'PENDING') ? 'active' : '' ?>" href="/admin/applications?status=PENDING">
                    <i class="bi bi-hourglass-split text-warning"></i>
                    <span>Pending</span>
                </a>
                <a class="wb-sidebar-link <?= ($currentUri === '/admin/applications' && ($_GET['status'] ?? '') === 'IN_PROCESS') ? 'active' : '' ?>" href="/admin/applications?status=IN_PROCESS">
                    <i class="bi bi-gear-wide-connected text-info"></i>
                    <span>In Process</span>
                </a>
                <a class="wb-sidebar-link <?= ($currentUri === '/admin/applications' && ($_GET['status'] ?? '') === 'APPROVED') ? 'active' : '' ?>" href="/admin/applications?status=APPROVED">
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <span>Approved</span>
                </a>
                <a class="wb-sidebar-link <?= ($currentUri === '/admin/applications' && ($_GET['status'] ?? '') === 'REJECTED') ? 'active' : '' ?>" href="/admin/applications?status=REJECTED">
                    <i class="bi bi-x-circle-fill text-danger"></i>
                    <span>Rejected</span>
                </a>
                <a class="wb-sidebar-link <?= $currentUri === '/user/profile' ? 'active' : '' ?>" href="/user/profile">
                    <i class="bi bi-person-gear"></i>
                    <span>Profile</span>
                </a>
                <a class="wb-sidebar-link text-danger" href="/logout">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>

            <?php elseif ($currentRole === 'USER'): ?>
                <!-- Retailer Mode Sidebar -->
                <?php if ($isDualRole): ?>
                    <div class="px-3 py-1 mb-2">
                        <span class="badge bg-primary text-white py-1 px-2" style="font-size: 0.72rem; font-weight: 700;">
                            <i class="bi bi-shop me-1"></i> Retailer Mode
                        </span>
                    </div>
                <?php endif; ?>
                <a class="wb-sidebar-link <?= $currentUri === '/user/dashboard' ? 'active' : '' ?>" href="/user/dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <a class="wb-sidebar-link <?= $currentUri === '/applications/new' ? 'active' : '' ?>" href="/applications/new">
                    <i class="bi bi-plus-circle"></i>
                    <span>New Application</span>
                </a>
                <a class="wb-sidebar-link <?= ($currentUri === '/applications' && $currentUri !== '/applications/new') ? 'active' : '' ?>" href="/applications">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Application List</span>
                </a>
                <a class="wb-sidebar-link <?= $currentUri === '/wallet/passbook' ? 'active' : '' ?>" href="/wallet/passbook">
                    <i class="bi bi-receipt"></i>
                    <span>Wallet Passbook</span>
                </a>
                <a class="wb-sidebar-link <?= $currentUri === '/wallet/topup' ? 'active' : '' ?>" href="/wallet/topup">
                    <i class="bi bi-qr-code-scan"></i>
                    <span>Top Up Wallet</span>
                </a>
                <a class="wb-sidebar-link <?= $currentUri === '/user/profile' ? 'active' : '' ?>" href="/user/profile">
                    <i class="bi bi-person-gear"></i>
                    <span>Profile</span>
                </a>
                <a class="wb-sidebar-link text-danger" href="/logout">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            <?php endif; ?>
        </nav>

        <!-- Sidebar User Footer -->
        <div class="wb-sidebar-footer">
            <div class="d-flex align-items-center gap-2 user-info">
                <i class="bi bi-person-circle fs-4 text-warning"></i>
                <div class="overflow-hidden">
                    <span class="user-name" title="<?= e($currentUser['name']) ?>"><?= e($currentUser['name']) ?></span>
                    <span class="user-role"><?= e($currentUser['center_name'] ?: $currentUser['role']) ?></span>
                </div>
            </div>
            <a href="/logout" class="btn btn-sm btn-outline-danger py-1 px-2" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="wb-main-wrapper" id="wbMainWrapper">
        <!-- Sleek Minimal Topbar -->
        <header class="wb-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="wb-topbar-toggle" id="wbSidebarToggle" type="button" aria-label="Toggle navigation" title="Toggle Sidebar">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="d-none d-md-block fw-bold text-dark fs-6">
                    <span>Welcome Bihar</span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">

                <?php if ($isDualRole): ?>
                    <!-- Header Mode Switcher (POST + CSRF) -->
                    <form method="POST" action="/switch-mode" class="d-inline-flex align-items-center bg-white border rounded-pill p-1 shadow-2xs me-1">
                        <?= csrf_field() ?>
                        <button type="submit" name="mode" value="RETAILER" class="btn btn-sm rounded-pill px-2.5 py-0.5 border-0 fw-semibold <?= $activeMode === 'RETAILER' ? 'bg-primary text-white shadow-xs' : 'text-secondary bg-transparent' ?>" style="font-size: 0.76rem;" title="Switch to Retailer Mode">
                            <i class="bi bi-shop me-1"></i> Retailer Mode
                        </button>
                        <button type="submit" name="mode" value="OPERATOR" class="btn btn-sm rounded-pill px-2.5 py-0.5 border-0 fw-semibold <?= $activeMode === 'OPERATOR' ? 'bg-warning text-dark shadow-xs' : 'text-secondary bg-transparent' ?>" style="font-size: 0.76rem;" title="Switch to Operator Mode">
                            <i class="bi bi-briefcase me-1"></i> Operator Mode
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Wallet Balance Badge -->
                <a href="/wallet/passbook" class="btn btn-sm btn-light border d-flex align-items-center gap-1 rounded-pill px-3 py-1 text-decoration-none shadow-sm" title="Wallet Balance">
                    <i class="bi bi-wallet2 text-success fw-bold"></i>
                    <span class="fw-bold text-dark">₹<?= number_format($walletBalance, 2) ?></span>
                </a>

                <?php if ($currentRole === 'USER' && $activeMode === 'RETAILER'): ?>
                    <a href="/wallet/topup" class="btn btn-sm btn-warning rounded-pill px-2 py-1 fw-semibold d-none d-sm-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-lg"></i>
                        <span>Top-Up</span>
                    </a>
                <?php endif; ?>

                <!-- Notification Bell Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border rounded-circle p-2 position-relative shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                        <i class="bi bi-bell-fill text-secondary fs-6"></i>
                        <?php if ($unreadNotifsCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                                <?= $unreadNotifsCount > 9 ? '9+' : $unreadNotifsCount ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-0" style="min-width: 300px; max-width: 360px;">
                        <li class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                            <span class="fw-bold small text-dark"><i class="bi bi-bell me-1"></i> Notifications</span>
                            <?php if ($unreadNotifsCount > 0): ?>
                                <span class="badge bg-primary"><?= $unreadNotifsCount ?> Unread</span>
                            <?php endif; ?>
                        </li>
                        <?php if (empty($recentNotifs)): ?>
                            <li class="p-3 text-center text-muted small">No notifications yet</li>
                        <?php else: ?>
                            <?php foreach ($recentNotifs as $n): ?>
                                <li>
                                    <a class="dropdown-item py-2 px-3 border-bottom <?= $n['is_read'] ? 'text-muted' : 'bg-light fw-semibold text-dark' ?>" href="<?= e($n['link'] ?: '#') ?>" style="white-space: normal;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small fw-bold text-primary"><?= e($n['title']) ?></span>
                                            <span class="text-muted" style="font-size: 0.68rem;"><?= date('d M, H:i', strtotime($n['created_at'])) ?></span>
                                        </div>
                                        <div class="small text-secondary" style="font-size: 0.78rem;"><?= e($n['message']) ?></div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- User Dropdown Menu -->
                <div class="dropdown ms-1">
                    <button class="btn btn-sm btn-light border rounded-pill px-2.5 py-1 fw-semibold d-flex align-items-center gap-1.5 shadow-sm" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-fill text-primary"></i>
                        <span class="d-none d-sm-inline"><?= e($currentUser['name']) ?></span>
                        <span class="badge bg-secondary rounded-pill py-0.5 px-1.5" style="font-size: 0.65rem; font-weight: 600;"><?= e($profileBadge) ?></span>
                        <i class="bi bi-chevron-down small text-muted"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li><h6 class="dropdown-header"><?= e($currentUser['email'] ?: $currentUser['mobile']) ?></h6></li>
                        <li><a class="dropdown-item" href="/user/profile"><i class="bi bi-person me-2"></i> <?= e(__('profile')) ?></a></li>
                        <?php if ($currentRole === 'USER' && $activeMode === 'RETAILER'): ?>
                            <li><a class="dropdown-item" href="/wallet/topup"><i class="bi bi-qr-code me-2"></i> <?= e(__('topup_wallet')) ?></a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/logout"><i class="bi bi-box-arrow-right me-2"></i> <?= e(__('logout')) ?></a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Body Content -->
        <main class="container-fluid px-3 px-lg-4 py-3 flex-grow-1">

<?php else: ?>
    <!-- Non-authenticated Layout (Login / Register) -->
    <div class="wb-main-wrapper no-sidebar" id="wbMainWrapper">
        <header class="wb-public-nav d-flex align-items-center justify-content-between px-3 px-md-4 py-2 border-bottom">
            <a class="wb-brand d-flex align-items-center gap-2 text-decoration-none" href="/">
                <span class="d-inline-flex align-items-center justify-content-center bg-warning text-dark rounded-circle" style="width: 38px; height: 38px;">
                    <i class="bi bi-shield-check fs-5"></i>
                </span>
                <span class="fs-5 fw-bold text-white tracking-tight">Welcome Bihar</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <?php if ($currentUri === '/login'): ?>
                    <a href="/register" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold shadow-sm">
                        <i class="bi bi-person-plus-fill me-1"></i> Register Here
                    </a>
                <?php else: ?>
                    <a href="/login" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i> <?= e(__('login')) ?>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <main class="<?= in_array($currentUri, ['/login', '/register'], true) ? 'p-0 flex-grow-1 d-flex flex-column w-100' : 'container-fluid px-3 px-lg-4 py-4 flex-grow-1' ?>">
<?php endif; ?>

    <!-- Flash Alerts -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <?= e($_SESSION['flash_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <?= e($_SESSION['flash_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_info'])): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
            <?= e($_SESSION['flash_info']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_info']); ?>
    <?php endif; ?>
