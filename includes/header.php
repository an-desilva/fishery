<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    
    <!-- Favicon & Custom Logo -->
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome Icons CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Application CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- Header / Navigation Bar -->
<header class="no-print sticky-top bg-dark border-bottom border-secondary shadow-sm">
    <nav class="navbar navbar-expand-xl navbar-dark bg-dark py-1">
        <div class="container-fluid px-2 px-md-3">
            <!-- Brand Logo & Name -->
            <a class="navbar-brand d-flex align-items-center gap-2 me-2 me-xl-3 text-nowrap" href="index.php">
                <img src="../assets/images/logo.jpg" alt="SeaLogix Logo" class="brand-logo rounded-2 bg-white p-1" style="height: 38px; width: auto; object-fit: contain;">
                <span class="fw-extrabold tracking-tight text-white fs-6">SeaLogix</span>
            </a>

            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler border-0 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <?php if (isLoggedIn()): ?>
                    <ul class="navbar-nav me-auto mb-2 mb-xl-0 align-items-center gap-1 py-1 py-xl-0" style="font-size: 0.84rem;">
                        <li class="nav-item">
                            <a class="nav-link text-nowrap px-2 py-1 rounded-2 d-inline-flex align-items-center gap-1.5 <?= $currentPage === 'index.php' ? 'active bg-primary text-white fw-bold' : '' ?>" href="index.php">
                                <i class="fa-solid fa-gauge-high text-primary"></i> <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-nowrap px-2 py-1 rounded-2 d-inline-flex align-items-center gap-1.5 <?= $currentPage === 'new_trip.php' ? 'active bg-primary text-white fw-bold' : '' ?>" href="new_trip.php">
                                <i class="fa-solid fa-plus-circle text-info"></i> <span>Start Trip</span>
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link text-nowrap px-2 py-1 rounded-2 d-inline-flex align-items-center gap-1.5 <?= $currentPage === 'trip_expenses.php' ? 'active bg-primary text-white fw-bold' : '' ?>" href="trip_expenses.php">
                                    <i class="fa-solid fa-gas-pump text-warning"></i> <span>Expenses</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link text-nowrap px-2 py-1 rounded-2 d-inline-flex align-items-center gap-1.5 <?= $currentPage === 'catch_dispatch.php' ? 'active bg-primary text-white fw-bold' : '' ?>" href="catch_dispatch.php">
                                <i class="fa-solid fa-truck-ramp-box text-success"></i> <span>Catch Dispatch</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-nowrap px-2 py-1 rounded-2 d-inline-flex align-items-center gap-1.5 <?= $currentPage === 'trip_bills.php' ? 'active bg-primary text-white fw-bold' : '' ?>" href="trip_bills.php">
                                <i class="fa-solid fa-receipt text-warning"></i> <span>Bill Vault</span>
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link text-nowrap px-2 py-1 rounded-2 d-inline-flex align-items-center gap-1.5 <?= $currentPage === 'trip_summary.php' ? 'active bg-primary text-white fw-bold' : '' ?>" href="trip_summary.php">
                                    <i class="fa-solid fa-calculator text-danger"></i> <span>Summary</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-nowrap px-2 py-1 rounded-2 d-inline-flex align-items-center gap-1.5 <?= $currentPage === 'admin_users.php' ? 'active bg-primary text-white fw-bold' : '' ?>" href="admin_users.php">
                                    <i class="fa-solid fa-users-gear text-info"></i> <span>Users</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <!-- Far Right: Compact User Profile & Logout Button -->
                    <div class="d-flex align-items-center gap-2 ms-auto text-nowrap flex-shrink-0">
                        <div class="text-light text-end me-1 d-none d-xxl-block extra-small">
                            <span class="fw-bold me-1"><i class="fa-solid fa-user-circle me-1 text-primary"></i><?= htmlspecialchars($currentUser['username'] ?? 'User') ?></span>
                            <?= getRoleBadge($currentUser['role'] ?? 'Staff') ?>
                        </div>
                        <a href="../actions/auth_action.php?action=logout" class="btn btn-outline-light btn-sm rounded-pill px-2.5 py-1 extra-small">
                            <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                        </a>
                    </div>
                <?php else: ?>
                    <div class="ms-auto">
                        <a href="login.php" class="btn btn-primary btn-sm px-3 shadow-sm">
                            <i class="fa-solid fa-right-to-bracket me-1"></i> Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

<!-- Alert Banner Notification (Flash Messages) -->

<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="container-fluid px-3 px-lg-5 mt-3 no-print alert-banner">
        <div class="alert alert-<?= $_SESSION['flash_type'] === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show shadow-sm border-0 d-flex items-center justify-content-between" role="alert">
            <div>
                <i class="fa-solid <?= $_SESSION['flash_type'] === 'error' ? 'fa-circle-exclamation me-2' : 'fa-circle-check me-2' ?>"></i>
                <strong><?= htmlspecialchars($_SESSION['flash_message']) ?></strong>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>

<!-- Main Application Body Container -->
<main class="main-content container-fluid px-3 px-lg-5 py-4">
