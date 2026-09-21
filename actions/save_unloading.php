<?php
/**
 * Action Controller: Save Harbour Unloading Labour Fee
 * Multi-Day Fishing Boat Catch, Sorting & Dispatch Logistics Management System
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/index.php');
    exit;
}

$tripId          = intval($_POST['trip_id'] ?? 0);
$rateType        = $_POST['rate_type'] ?? 'FIXED';
$rateAmount      = max(0, floatval($_POST['rate_amount'] ?? 0));
$totalUnloaders  = max(1, intval($_POST['total_unloaders'] ?? 1));
$totalBoxes      = max(0, intval($_POST['total_boxes'] ?? 0));
$totalLabourFee  = max(0, floatval($_POST['total_labour_fee'] ?? 0));
$notes           = trim($_POST['notes'] ?? '');

if ($tripId <= 0) {
    $_SESSION['flash_message'] = 'Invalid Trip ID specified.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ../views/index.php');
    exit;
}

try {
    $pdo = getDbConnection();

    // Check lock status
    $lockCheck = $pdo->prepare("SELECT is_locked FROM trips WHERE id = ?");
    $lockCheck->execute([$tripId]);
    if ($lockCheck->fetchColumn() == 1) {
        $_SESSION['flash_message'] = 'This trip is locked. Records cannot be modified.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/index.php');
        exit;
    }
    
    // Check existing harbour unloading record
    $check = $pdo->prepare("SELECT id FROM harbour_unloadings WHERE trip_id = ?");
    $check->execute([$tripId]);
    $existing = $check->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE harbour_unloadings SET rate_type = ?, rate_amount = ?, total_unloaders = ?, total_boxes = ?, total_labour_fee = ?, notes = ? WHERE trip_id = ?");
        $stmt->execute([$rateType, $rateAmount, $totalUnloaders, $totalBoxes, $totalLabourFee, $notes, $tripId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO harbour_unloadings (trip_id, rate_type, rate_amount, total_unloaders, total_boxes, total_labour_fee, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tripId, $rateType, $rateAmount, $totalUnloaders, $totalBoxes, $totalLabourFee, $notes]);
    }

    // Update trip status to LANDED if currently DEPARTED
    $updateStatus = $pdo->prepare("UPDATE trips SET status = 'LANDED' WHERE id = ? AND status = 'DEPARTED'");
    $updateStatus->execute([$tripId]);

    $_SESSION['flash_message'] = "Harbour unloading labour fee recorded successfully (" . formatCurrency($totalLabourFee) . ").";
    $_SESSION['flash_type'] = 'success';
    header("Location: ../views/catch_dispatch.php?trip_id={$tripId}");
    exit;

} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Database Error: ' . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header("Location: ../views/catch_dispatch.php?trip_id={$tripId}");
    exit;
}
