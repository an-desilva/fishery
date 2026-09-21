<?php
/**
 * Action Controller: Admin User Management (RBAC User Control)
 * Multi-Day Fishing Boat Catch, Sorting & Dispatch Logistics Management System
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce Master Admin Role Guard
requireAdmin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getDbConnection();

// CREATE USER HANDLER
if ($action === 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] === 'Admin' ? 'Admin' : 'Staff';

    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION['flash_message'] = 'Username, email, and password are required.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/admin_users.php');
        exit;
    }

    try {
        // Check if username or email already exists
        $chkStmt = $pdo->prepare("SELECT username, email FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1");
        $chkStmt->execute([$username, $email]);
        $existingUser = $chkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            if (strcasecmp($existingUser['username'], $username) === 0) {
                $_SESSION['flash_message'] = "Cannot create account: Username '{$username}' is already taken. Please choose a different username.";
            } else {
                $_SESSION['flash_message'] = "Cannot create account: Email address '{$email}' is already registered. Please use a different email address.";
            }
            $_SESSION['flash_type'] = 'error';
            header('Location: ../views/admin_users.php');
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$username, $email, $hash, $role]);

        $_SESSION['flash_message'] = "New {$role} user account '{$username}' created successfully.";
        $_SESSION['flash_type'] = 'success';
        header('Location: ../views/admin_users.php');
        exit;
    } catch (Exception $e) {
        if ($e instanceof PDOException && (strpos($e->getMessage(), '1062') !== false || $e->getCode() == 23000)) {
            $_SESSION['flash_message'] = "Cannot create account: Username '{$username}' or email address '{$email}' already exists.";
        } else {
            $_SESSION['flash_message'] = 'User Creation Error: ' . $e->getMessage();
        }
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/admin_users.php');
        exit;
    }
}

// TOGGLE ACTIVE STATUS HANDLER
if ($action === 'toggle_active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUserId = intval($_POST['user_id'] ?? 0);
    $currentUser  = getCurrentUser();

    if ($targetUserId === intval($currentUser['id'])) {
        $_SESSION['flash_message'] = 'You cannot disable your own active Admin account.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/admin_users.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $currStatus = $stmt->fetchColumn();

        $newStatus = ($currStatus == 1) ? 0 : 1;
        $uStmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $uStmt->execute([$newStatus, $targetUserId]);

        $_SESSION['flash_message'] = "User account status updated (" . ($newStatus ? 'Activated' : 'Disabled') . ").";
        $_SESSION['flash_type'] = 'success';
        header('Location: ../views/admin_users.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Database Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/admin_users.php');
        exit;
    }
}

// RESET PASSWORD HANDLER
if ($action === 'reset_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUserId = intval($_POST['user_id'] ?? 0);
    $newPassword  = $_POST['new_password'] ?? '';

    if ($targetUserId <= 0 || empty($newPassword)) {
        $_SESSION['flash_message'] = 'Please enter a valid new password.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/admin_users.php');
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);

    try {
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $targetUserId]);

        $_SESSION['flash_message'] = "Password reset successfully for user.";
        $_SESSION['flash_type'] = 'success';
        header('Location: ../views/admin_users.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Password Reset Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/admin_users.php');
        exit;
    }
}

header('Location: ../views/admin_users.php');
exit;
