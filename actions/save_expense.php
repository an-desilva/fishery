<?php
/**
 * Action Controller: Save Departure Expenses (Admin Restricted)
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

$tripId          = intval($_POST['trip_id'] ?? 0);
$dieselCost      = max(0, floatval($_POST['diesel_cost'] ?? 0));
$iceCost         = max(0, floatval($_POST['ice_cost'] ?? 0));
$rationCost      = max(0, floatval($_POST['ration_cost'] ?? 0));
$gasCost         = max(0, floatval($_POST['gas_cost'] ?? 0));
$maintenanceCost = max(0, floatval($_POST['maintenance_cost'] ?? 0));
$baitCost        = max(0, floatval($_POST['bait_cost'] ?? 0));
$otherCost       = max(0, floatval($_POST['other_cost'] ?? 0));
$notes           = trim($_POST['notes'] ?? '');

if ($tripId <= 0) {
    $_SESSION['flash_message'] = 'Invalid Fishing Trip ID specified.';
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
        $_SESSION['flash_message'] = 'This trip is locked. Expenses cannot be modified.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/index.php');
        exit;
    }
    
    // Check if expense record exists for this trip
    $checkStmt = $pdo->prepare("SELECT id FROM trip_expenses WHERE trip_id = ?");
    $checkStmt->execute([$tripId]);
    $existing = $checkStmt->fetch();

    $totalExpenses = $dieselCost + $iceCost + $rationCost + $gasCost + $maintenanceCost + $baitCost + $otherCost;

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE trip_expenses SET diesel_cost = ?, ice_cost = ?, ration_cost = ?, gas_cost = ?, maintenance_cost = ?, bait_cost = ?, other_cost = ?, total_expenses = ?, notes = ? WHERE trip_id = ?");
        $stmt->execute([$dieselCost, $iceCost, $rationCost, $gasCost, $maintenanceCost, $baitCost, $otherCost, $totalExpenses, $notes, $tripId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO trip_expenses (trip_id, diesel_cost, ice_cost, ration_cost, gas_cost, maintenance_cost, bait_cost, other_cost, total_expenses, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tripId, $dieselCost, $iceCost, $rationCost, $gasCost, $maintenanceCost, $baitCost, $otherCost, $totalExpenses, $notes]);
    }

    $_SESSION['flash_message'] = "Departure expenses saved successfully. Total expenses: " . formatCurrency($totalExpenses);
    $_SESSION['flash_type'] = 'success';
    header("Location: ../views/catch_dispatch.php?trip_id={$tripId}");
    exit;

} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Database Error: ' . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header("Location: ../views/trip_expenses.php?trip_id={$tripId}");
    exit;
}
