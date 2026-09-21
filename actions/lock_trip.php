<?php
/**
 * Action Controller: Lock / Complete Trip Record (Admin Only)
 * Multi-Day Fishing Boat Catch, Sorting & Dispatch Logistics Management System
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce Master Admin Role Guard
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/index.php');
    exit;
}

$tripId = intval($_POST['trip_id'] ?? 0);

if ($tripId <= 0) {
    $_SESSION['flash_message'] = 'Invalid Fishing Trip ID specified.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ../views/index.php');
    exit;
}

try {
    $pdo = getDbConnection();

    // Check current lock status
    $stmt = $pdo->prepare("SELECT is_locked, boat_name, reg_number FROM trips WHERE id = ?");
    $stmt->execute([$tripId]);
    $trip = $stmt->fetch();

    if (!$trip) {
        $_SESSION['flash_message'] = 'Trip record not found.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/index.php');
        exit;
    }

    $newLockStatus = ($trip['is_locked'] == 1) ? 0 : 1;
    $newTripStatus = ($newLockStatus == 1) ? 'COMPLETED' : 'DISPATCHED';

    $update = $pdo->prepare("UPDATE trips SET is_locked = ?, status = ? WHERE id = ?");
    $update->execute([$newLockStatus, $newTripStatus, $tripId]);

    $statusMsg = $newLockStatus ? "completed and locked against further edits." : "unlocked for modification.";
    $_SESSION['flash_message'] = "Trip '{$trip['boat_name']}' ({$trip['reg_number']}) was {$statusMsg}";
    $_SESSION['flash_type'] = 'success';

    header("Location: ../views/trip_summary.php?trip_id={$tripId}");
    exit;

} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Database Error: ' . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header('Location: ../views/index.php');
    exit;
}
