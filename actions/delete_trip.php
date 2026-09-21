<?php
/**
 * Action Controller: Delete Trip
 * Multi-Day Fishing Boat & Catch Logistics System
 */

session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/index.php');
    exit;
}

$tripId = intval($_POST['trip_id'] ?? 0);

if ($tripId <= 0) {
    $_SESSION['flash_message'] = 'Invalid Trip ID.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ../views/index.php');
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Get boat name for notification message
    $nameStmt = $pdo->prepare("SELECT boat_name, reg_number FROM trips WHERE id = ?");
    $nameStmt->execute([$tripId]);
    $trip = $nameStmt->fetch();

    if ($trip) {
        $stmt = $pdo->prepare("DELETE FROM trips WHERE id = ?");
        $stmt->execute([$tripId]);

        $_SESSION['flash_message'] = "Trip record for '{$trip['boat_name']}' ({$trip['reg_number']}) was permanently deleted.";
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = "Trip record not found.";
        $_SESSION['flash_type'] = 'error';
    }

    header('Location: ../views/index.php');
    exit;

} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Database Error: ' . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header('Location: ../views/index.php');
    exit;
}
