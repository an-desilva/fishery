<?php
/**
 * Action Controller: Authentication (Login, Logout, Initial Setup)
 * Multi-Day Fishing Boat Catch, Sorting & Dispatch Logistics Management System
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$pdo = getDbConnection();

// LOGIN HANDLER
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['flash_message'] = 'Please enter both username and password.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/login.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session user array
            $_SESSION['user'] = [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role'     => $user['role'],
                'is_active'=> $user['is_active']
            ];

            $_SESSION['flash_message'] = "Welcome back, " . htmlspecialchars($user['username']) . " (" . $user['role'] . ")!";
            $_SESSION['flash_type'] = 'success';
            header('Location: ../views/index.php');
            exit;
        } else {
            $_SESSION['flash_message'] = 'Invalid username/password combination or account is disabled.';
            $_SESSION['flash_type'] = 'error';
            header('Location: ../views/login.php');
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Database Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/login.php');
        exit;
    }
}

// LOGOUT HANDLER
if ($action === 'logout') {
    unset($_SESSION['user']);
    session_destroy();
    session_start();
    $_SESSION['flash_message'] = 'You have been logged out securely.';
    $_SESSION['flash_type'] = 'success';
    header('Location: ../views/login.php');
    exit;
}

// INITIAL ADMIN REGISTRATION HANDLER (Disabled once an Admin exists)
if ($action === 'register_initial' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $countStmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = intval($countStmt->fetchColumn());

    if ($userCount > 0) {
        $_SESSION['flash_message'] = 'Initial admin registration is disabled. Please contact system admin.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/login.php');
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION['flash_message'] = 'Please fill in all registration fields.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/register.php');
        exit;
    }

    try {
        $chkStmt = $pdo->prepare("SELECT username, email FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1");
        $chkStmt->execute([$username, $email]);
        $existingUser = $chkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            if (strcasecmp($existingUser['username'], $username) === 0) {
                $_SESSION['flash_message'] = "Registration Failed: Username '{$username}' is already taken. Please choose a different username.";
            } else {
                $_SESSION['flash_message'] = "Registration Failed: Email address '{$email}' is already registered. Please use a different email address.";
            }
            $_SESSION['flash_type'] = 'error';
            header('Location: ../views/register.php');
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, is_active) VALUES (?, ?, ?, 'Admin', 1)");
        $stmt->execute([$username, $email, $hash]);

        $_SESSION['flash_message'] = 'Master Admin account created! Please log in now.';
        $_SESSION['flash_type'] = 'success';
        header('Location: ../views/login.php');
        exit;
    } catch (Exception $e) {
        if ($e instanceof PDOException && (strpos($e->getMessage(), '1062') !== false || $e->getCode() == 23000)) {
            $_SESSION['flash_message'] = "Registration Failed: Username '{$username}' or email address '{$email}' already exists.";
        } else {
            $_SESSION['flash_message'] = 'Registration Error: ' . $e->getMessage();
        }
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/register.php');
        exit;
    }
}

header('Location: ../views/login.php');
exit;
