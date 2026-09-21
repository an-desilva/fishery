<?php
/**
 * Action Controller: Delete Market Bill (Bill Vault)
 * Multi-Day Fishing Boat Catch, Sorting & Dispatch Logistics Management System
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

// Authenticate session
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/index.php');
    exit;
}

$billId = intval($_POST['bill_id'] ?? 0);
$tripId = intval($_POST['trip_id'] ?? 0);

if ($billId <= 0) {
    $_SESSION['flash_message'] = 'Invalid Bill ID specified.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ../views/index.php');
    exit;
}

try {
    $pdo = getDbConnection();

    // Fetch bill details
    $stmt = $pdo->prepare("SELECT bill_id, trip_id, bill_title, file_path FROM trip_bills WHERE bill_id = ?");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();

    if (!$bill) {
        $_SESSION['flash_message'] = 'Bill record not found.';
        $_SESSION['flash_type'] = 'error';
        $redirectTripId = $tripId > 0 ? $tripId : '';
        header("Location: ../views/trip_bills.php?trip_id={$redirectTripId}");
        exit;
    }

    $targetTripId = $bill['trip_id'];

    // Check if trip is locked (only admins can delete if trip locked or check status)
    $lockStmt = $pdo->prepare("SELECT is_locked FROM trips WHERE id = ?");
    $lockStmt->execute([$targetTripId]);
    $isLocked = (int)$lockStmt->fetchColumn();

    if ($isLocked === 1 && !isAdmin()) {
        $_SESSION['flash_message'] = 'This trip is locked. Only administrators can delete archived market bills.';
        $_SESSION['flash_type'] = 'error';
        header("Location: ../views/trip_bills.php?trip_id={$targetTripId}");
        exit;
    }

    // Delete physical file if exists
    if (!empty($bill['file_path'])) {
        $fullPath = __DIR__ . '/../' . $bill['file_path'];
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    // Delete database record
    $deleteStmt = $pdo->prepare("DELETE FROM trip_bills WHERE bill_id = ?");
    $deleteStmt->execute([$billId]);

    $_SESSION['flash_message'] = "Market bill '{$bill['bill_title']}' has been permanently deleted.";
    $_SESSION['flash_type'] = 'success';
    header("Location: ../views/trip_bills.php?trip_id={$targetTripId}");
    exit;

} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Delete Failed: ' . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header("Location: ../views/trip_bills.php?trip_id={$tripId}");
    exit;
}
