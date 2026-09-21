<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

$pdo = getDbConnection();
$countStmt = $pdo->query("SELECT COUNT(*) FROM users");
$userCount = intval($countStmt->fetchColumn());

if ($userCount > 0) {
    $_SESSION['flash_message'] = 'Public registration is disabled. Please contact system admin.';
    $_SESSION['flash_type'] = 'error';
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initial Admin Setup - <?= APP_NAME ?></title>
    
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
            
            <div class="text-center mb-4">
                <div class="logo-container mb-3">
                    <img src="../assets/images/logo.jpg" alt="SeaLogix Logo" style="max-height: 110px; width: auto; object-fit: contain;" class="rounded-3">
                </div>
                <h3 class="fw-extrabold text-dark mb-0">Initial Master Admin Setup</h3>
                <p class="text-muted small">Create the primary lead manager account for SeaLogix.</p>
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

            <!-- Registration Form Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-danger text-white text-center py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="fa-solid fa-user-plus me-2"></i>Master Admin Setup</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="../actions/auth_action.php">
                        <input type="hidden" name="action" value="register_initial">

                        <div class="mb-3">
                            <label for="username" class="form-label fw-bold small text-muted">Admin Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required placeholder="admin">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold small text-muted">Admin Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="admin@sealogix.com">
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-bold small text-muted">Master Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                        </div>

                        <button type="submit" class="btn btn-danger w-100 font-semibold py-2 shadow-sm">
                            <i class="fa-solid fa-circle-check me-1"></i> Register Master Admin
                        </button>
                    </form>
                </div>
            </div>

            <div class="text-center mt-3">
                <a href="login.php" class="text-decoration-none small text-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Return to Login
                </a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
