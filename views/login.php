<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Check if any users exist in database
$pdo = getDbConnection();
$userCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = intval($stmt->fetchColumn());
} catch (Exception $e) {
    $userCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    
    <!-- Favicon & Custom Logo -->
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light min-vh-100 d-flex flex-column justify-content-center py-5">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            
            <!-- Logo Brand Header -->
            <div class="text-center mb-4">
                <div class="logo-container mb-3">
                    <img src="../assets/images/logo.jpg" alt="SeaLogix Logo" style="max-height: 120px; width: auto; object-fit: contain;" class="rounded-3">
                </div>
                <h3 class="fw-extrabold text-dark mb-0">SeaLogix Logistics</h3>
                <p class="text-muted small">Multi-Day Fishing Boat Catch & Dispatch System</p>
            </div>

            <!-- Flash Notifications -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= $_SESSION['flash_type'] === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show shadow-sm border-0" role="alert">
                    <i class="fa-solid <?= $_SESSION['flash_type'] === 'error' ? 'fa-circle-exclamation me-1' : 'fa-circle-check me-1' ?>"></i>
                    <span class="small fw-semibold"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <!-- Login Form Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-dark text-white text-center py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-lock me-2"></i>Sign In to Account</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="../actions/auth_action.php">
                        <input type="hidden" name="action" value="login">

                        <div class="mb-3">
                            <label for="username" class="form-label fw-bold small text-muted">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-user text-primary"></i></span>
                                <input type="text" class="form-control" id="username" name="username" required placeholder="admin or staff">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-bold small text-muted">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-key text-secondary"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 font-semibold py-2 shadow-sm mb-3">
                            <i class="fa-solid fa-right-to-bracket me-1"></i> Sign In
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($userCount === 0): ?>
                <div class="text-center mt-3">
                    <a href="register.php" class="text-decoration-none small fw-bold text-primary">
                        <i class="fa-solid fa-user-plus me-1"></i> Perform Initial Master Admin Setup
                    </a>
                </div>
            <?php endif; ?>

            <div class="text-center text-muted small mt-4">
                &copy; <?= date('Y') ?> SeaLogix. All rights reserved.
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
