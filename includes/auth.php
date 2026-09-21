<?php
/**
 * Authentication & Role-Based Access Control (RBAC) Guard
 * Multi-Day Fishing Boat Catch, Sorting & Dispatch Logistics Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn() {
    $user = getCurrentUser();
    return !empty($user) && !empty($user['id']) && (!isset($user['is_active']) || $user['is_active'] == 1);
}

function isAdmin() {
    $user = getCurrentUser();
    return isLoggedIn() && strtoupper($user['role'] ?? '') === 'ADMIN';
}

function isStaff() {
    $user = getCurrentUser();
    return isLoggedIn() && strtoupper($user['role'] ?? '') === 'STAFF';
}

function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['flash_message'] = 'Please log in to access the system.';
        $_SESSION['flash_type'] = 'error';
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        $_SESSION['flash_message'] = 'Access Denied: Master Admin privileges required.';
        $_SESSION['flash_type'] = 'error';
        header('Location: index.php');
        exit;
    }
}
