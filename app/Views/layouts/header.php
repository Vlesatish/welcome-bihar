<?php
use App\Services\AuthService;
use App\Services\WalletService;

$currentUser = AuthService::user();
$currentRole = $currentUser['role'] ?? null;
$walletBalance = $currentUser ? WalletService::getBalance($currentUser['id']) : 0.00;
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
                <span class="badge bg-primary role-badge"><?= e($currentRole) ?></span>
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
                <a class="wb-sidebar-link <?= strpos($currentUri, '/applications') === 0 ? 'active' : '' ?>" href="/applications">
                    <i class="bi bi-folder2-open"></i>
                    <span>Applications</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/users') === 0 ? 'active' : '' ?>" href="/admin/users">
                    <i class="bi bi-people"></i>
                    <span>Users &amp; Operators</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/data-types') === 0 ? 'active' : '' ?>" href="/admin/data-types">
                    <i class="bi bi-card-checklist"></i>
                    <span>Data Types</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/locations') === 0 ? 'active' : '' ?>" href="/admin/locations">
                    <i class="bi bi-geo-alt"></i>
                    <span>Districts &amp; Blocks</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/reconciliation') === 0 ? 'active' : '' ?>" href="/admin/reconciliation">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>Reconciliation</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/audit-logs') === 0 ? 'active' : '' ?>" href="/admin/audit-logs">
                    <i class="bi bi-journal-text"></i>
                    <span>Audit Trail</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/admin/settings') === 0 ? 'active' : '' ?>" href="/admin/settings">
                    <i class="bi bi-gear"></i>
                    <span>System Settings</span>
                </a>

            <?php elseif ($currentRole === 'DISTRIBUTOR'): ?>
                <a class="wb-sidebar-link <?= $currentUri === '/distributor/dashboard' ? 'active' : '' ?>" href="/distributor/dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/distributor/team') === 0 ? 'active' : '' ?>" href="/distributor/team">
                    <i class="bi bi-people"></i>
                    <span>Team &amp; Scope</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/distributor/applications') === 0 ? 'active' : '' ?>" href="/distributor/applications">
                    <i class="bi bi-folder2-open"></i>
                    <span>Applications</span>
                </a>
                <a class="wb-sidebar-link <?= strpos($currentUri, '/wallet/passbook') === 0 ? 'active' : '' ?>" href="/wallet/passbook">
                    <i class="bi bi-wallet2"></i>
                    <span>Wallet Passbook</span>
                </a>

            <?php elseif ($currentRole === 'USER'): ?>
                <a class="wb-sidebar-link <?= $currentUri === '/user/dashboard' ? 'active' : '' ?>" href="/user/dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <a class="wb-sidebar-link <?= $currentUri === '/applications/new' ? 'active' : '' ?>" href="/applications/new">
                    <i class="bi bi-plus-circle"></i>
                    <span>New Application</span>
                </a>
                <a class="wb-sidebar-link <?= $currentUri === '/applications' ? 'active' : '' ?>" href="/applications">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>My Applications</span>
                </a>
                <a class="wb-sidebar-link <?= $currentUri === '/wallet/passbook' ? 'active' : '' ?>" href="/wallet/passbook">
                    <i class="bi bi-receipt"></i>
                    <span>Wallet Passbook</span>
                </a>
                <a class="wb-sidebar-link <?= $currentUri === '/wallet/topup' ? 'active' : '' ?>" href="/wallet/topup">
                    <i class="bi bi-qr-code-scan"></i>
                    <span>Top Up Wallet</span>
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
                <div class="d-none d-md-block text-muted small fw-semibold">
                    <span>Bihar Unified Citizen Services</span>
                    <span class="mx-2 text-black-50">&bull;</span>
                    <span class="text-secondary"><?= e(__('district')) ?> &amp; <?= e(__('block')) ?></span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Wallet Balance Badge -->
                <a href="/wallet/passbook" class="btn btn-sm btn-light border d-flex align-items-center gap-1 rounded-pill px-3 py-1 text-decoration-none shadow-sm" title="Wallet Balance">
                    <i class="bi bi-wallet2 text-success fw-bold"></i>
                    <span class="fw-bold text-dark">₹<?= number_format($walletBalance, 2) ?></span>
                </a>

                <?php if ($currentRole === 'USER'): ?>
                    <a href="/wallet/topup" class="btn btn-sm btn-warning rounded-pill px-2 py-1 fw-semibold d-none d-sm-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-lg"></i>
                        <span>Top-Up</span>
                    </a>
                <?php endif; ?>

                <!-- User Dropdown Menu -->
                <div class="dropdown ms-1">
                    <button class="btn btn-sm btn-light border rounded-pill px-3 py-1 fw-semibold d-flex align-items-center gap-2 shadow-sm" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-fill text-primary"></i>
                        <span class="d-none d-sm-inline"><?= e($currentUser['name']) ?></span>
                        <i class="bi bi-chevron-down small text-muted"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li><h6 class="dropdown-header"><?= e($currentUser['email']) ?></h6></li>
                        <li><a class="dropdown-item" href="/user/profile"><i class="bi bi-person me-2"></i> Profile</a></li>
                        <?php if ($currentRole === 'USER'): ?>
                            <li><a class="dropdown-item" href="/wallet/topup"><i class="bi bi-qr-code me-2"></i> Top Up Wallet</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/logout"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Body Content -->
        <main class="container-fluid px-3 px-lg-4 py-3 flex-grow-1">

<?php else: ?>
    <!-- Non-authenticated Layout (Login / Register) -->
    <div class="wb-main-wrapper no-sidebar" id="wbMainWrapper">
        <header class="wb-public-nav d-flex align-items-center justify-content-between">
            <a class="wb-brand" href="/">
                <i class="bi bi-shield-check text-warning fs-3"></i>
                <span class="fs-4">Welcome Bihar</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="/login" class="btn btn-sm btn-outline-light rounded-pill px-3">Login</a>
                <a href="/register" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold">Register Kiosk</a>
            </div>
        </header>

        <main class="container-fluid px-3 px-lg-4 py-4 flex-grow-1">
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
