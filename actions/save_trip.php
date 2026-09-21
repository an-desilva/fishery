<?php
/**
 * Action Controller: Save / Edit Trip Departure
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

$boatName      = trim($_POST['boat_name'] ?? '');
$regNumber     = trim($_POST['reg_number'] ?? '');
$skipperName   = trim($_POST['skipper_name'] ?? '');
$crewCount     = max(1, intval($_POST['crew_count'] ?? 1));
$crewMembers   = trim($_POST['crew_members'] ?? '');
$departureDate  = $_POST['departure_date'] ?? date('Y-m-d');
$arrivalDate    = !empty($_POST['arrival_date']) ? $_POST['arrival_date'] : null;
$tripId         = intval($_POST['trip_id'] ?? 0);
$currentUser    = getCurrentUser();

if (empty($boatName) || empty($regNumber) || empty($skipperName) || empty($departureDate)) {
    $_SESSION['flash_message'] = 'Please fill in all required boat registration and departure details.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../views/new_trip.php'));
    exit;
}

try {
    $pdo = getDbConnection();
    
    if ($tripId > 0) {
        // Check if trip is locked
        $lockCheck = $pdo->prepare("SELECT is_locked FROM trips WHERE id = ?");
        $lockCheck->execute([$tripId]);
        if ($lockCheck->fetchColumn() == 1) {
            $_SESSION['flash_message'] = 'This trip is completed and locked. It cannot be modified.';
            $_SESSION['flash_type'] = 'error';
            header('Location: ../views/index.php');
            exit;
        }

        $stmt = $pdo->prepare("UPDATE trips SET boat_name = ?, reg_number = ?, skipper_name = ?, crew_count = ?, crew_members = ?, departure_date = ?, arrival_date = ? WHERE id = ?");
        $stmt->execute([$boatName, $regNumber, $skipperName, $crewCount, $crewMembers, $departureDate, $arrivalDate, $tripId]);
        
        $_SESSION['flash_message'] = "Trip information updated successfully for boat '{$boatName}'.";
        $_SESSION['flash_type'] = 'success';
        
        // Redirect Admin to Expenses, Staff to Catch Dispatch
        if (isAdmin()) {
            header("Location: ../views/trip_expenses.php?trip_id={$tripId}");
        } else {
            header("Location: ../views/catch_dispatch.php?trip_id={$tripId}");
        }
        exit;
    } else {
        $stmt = $pdo->prepare("INSERT INTO trips (boat_name, reg_number, skipper_name, crew_count, crew_members, departure_date, arrival_date, status, is_locked, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'DEPARTED', 0, ?)");
        $stmt->execute([$boatName, $regNumber, $skipperName, $crewCount, $crewMembers, $departureDate, $arrivalDate, $currentUser['id'] ?? null]);
        $newTripId = $pdo->lastInsertId();
        
        $_SESSION['flash_message'] = "Trip registered for '{$boatName}' ({$regNumber}).";
        $_SESSION['flash_type'] = 'success';

        if (isAdmin()) {
            header("Location: ../views/trip_expenses.php?trip_id={$newTripId}");
        } else {
            header("Location: ../views/catch_dispatch.php?trip_id={$newTripId}");
        }
        exit;
    }
} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Database Error: ' . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../views/new_trip.php'));
    exit;
}
