<?php
/**
 * Action Controller: Save Catch Dispatch, Transport Logistics & Direct Buyers
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

$tripId           = intval($_POST['trip_id'] ?? 0);
$lorryNumber      = trim($_POST['lorry_number'] ?? '');
$driverName       = trim($_POST['driver_name'] ?? '');
$driverPhone      = trim($_POST['driver_phone'] ?? '');
$helperName       = trim($_POST['helper_name'] ?? '');
$lorryHireFee     = max(0, floatval($_POST['lorry_hire_fee'] ?? 0));
$helperFee        = max(0, floatval($_POST['helper_fee'] ?? 0));
$transitAllowance = max(0, floatval($_POST['transit_allowance'] ?? 0));
$dispatchDate     = $_POST['dispatch_date'] ?? date('Y-m-d H:i:s');
$notes            = trim($_POST['notes'] ?? '');

$items            = $_POST['items'] ?? []; // Array of lorry catch box items
$buyers           = $_POST['buyers'] ?? []; // Array of direct/secondary buyers

if ($tripId <= 0 || empty($lorryNumber) || empty($driverName) || empty($items)) {
    $_SESSION['flash_message'] = 'Please specify lorry details, driver name, and add at least one catch box item.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../views/catch_dispatch.php'));
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

    $pdo->beginTransaction();

    // Generate unique dispatch number (e.g. DISP-20260816-001)
    $dispatchNo = 'DISP-' . date('Ymd') . '-' . sprintf('%03d', rand(1, 999));

    // Clear previous dispatch if re-saving for clean update
    $existingDispatch = $pdo->prepare("SELECT id FROM dispatches WHERE trip_id = ?");
    $existingDispatch->execute([$tripId]);
    $oldDispatchId = $existingDispatch->fetchColumn();

    if ($oldDispatchId) {
        $delOldItems = $pdo->prepare("DELETE FROM dispatch_items WHERE dispatch_id = ?");
        $delOldItems->execute([$oldDispatchId]);
        $delOldDisp = $pdo->prepare("DELETE FROM dispatches WHERE id = ?");
        $delOldDisp->execute([$oldDispatchId]);
    }

    // Insert dispatch header
    $stmt = $pdo->prepare("INSERT INTO dispatches (trip_id, dispatch_no, lorry_number, driver_name, driver_phone, helper_name, lorry_hire_fee, helper_fee, transit_allowance, dispatch_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tripId, $dispatchNo, $lorryNumber, $driverName, $driverPhone, $helperName, $lorryHireFee, $helperFee, $transitAllowance, $dispatchDate, $notes]);
    $dispatchId = $pdo->lastInsertId();

    // Insert dispatch box items
    $itemStmt = $pdo->prepare("INSERT INTO dispatch_items (dispatch_id, trip_id, quality_grade, fish_species, size_category, box_count, net_weight_kg) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $totalLorryBoxesCount = 0;
    $totalLorryWeightKg = 0;

    foreach ($items as $item) {
        $qualityGrade = trim($item['quality_grade'] ?? 'Grade 1 (①)');
        $fishSpecies  = trim($item['fish_species'] ?? 'Yellowfin Tuna (Kelawalla)');
        $sizeCategory = trim($item['size_category'] ?? 'L');
        $boxCount     = max(1, intval($item['box_count'] ?? 1));
        $netWeightKg  = max(0, floatval($item['net_weight_kg'] ?? 0));

        $itemStmt->execute([$dispatchId, $tripId, $qualityGrade, $fishSpecies, $sizeCategory, $boxCount, $netWeightKg]);

        $totalLorryBoxesCount += $boxCount;
        $totalLorryWeightKg   += $netWeightKg;
    }

    // ==========================================
    // SAVE DIRECT & SECONDARY BUYERS (අමතර ගැනුම්කරුවන්)
    // ==========================================
    
    // Clear previous direct buyers & items for this trip inside transaction
    $delItems = $pdo->prepare("DELETE FROM direct_buyer_items WHERE trip_id = ?");
    $delItems->execute([$tripId]);
    $delBuyers = $pdo->prepare("DELETE FROM direct_buyers WHERE trip_id = ?");
    $delBuyers->execute([$tripId]);

    // Also remove old auto-generated direct buyer bills from vault to avoid duplicates
    $delBuyerBills = $pdo->prepare("DELETE FROM trip_bills WHERE trip_id = ? AND file_type = 'SLIP' AND bill_title LIKE 'Direct Buyer Slip:%'");
    $delBuyerBills->execute([$tripId]);

    $totalDirectBoxesCount = 0;
    $totalDirectWeightKg = 0;
    $totalDirectSalesAmount = 0;

    $buyerInsertStmt = $pdo->prepare("INSERT INTO direct_buyers (trip_id, buyer_name, contact_number, vehicle_no, payment_type, total_weight_kg, total_boxes, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $buyerItemInsertStmt = $pdo->prepare("INSERT INTO direct_buyer_items (buyer_id, trip_id, quality_grade, fish_species, size_category, net_weight_kg, box_count, rate_per_kg, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Preparation for Bill Vault auto-entry
    $billInsertStmt = $pdo->prepare("INSERT INTO trip_bills (trip_id, bill_title, file_path, file_type, file_size_kb, uploaded_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $currentUserId = $_SESSION['user_id'] ?? null;

    foreach ($buyers as $buyerData) {
        $buyerName = trim($buyerData['buyer_name'] ?? '');
        if (empty($buyerName)) continue;

        $contactNum  = trim($buyerData['contact_number'] ?? '');
        $vehicleNo   = trim($buyerData['vehicle_no'] ?? '');
        $paymentType = trim($buyerData['payment_type'] ?? 'CASH');

        $bItems = $buyerData['items'] ?? [];
        $bTotalWeight = 0;
        $bTotalBoxes  = 0;
        $bTotalAmount = 0;

        $processedItems = [];
        foreach ($bItems as $it) {
            $qGrade   = trim($it['quality_grade'] ?? 'Grade 1 (①)');
            $fSpecies = trim($it['fish_species'] ?? 'Yellowfin Tuna (Kelawalla)');
            $sCat     = trim($it['size_category'] ?? 'L');
            $wKg      = max(0, floatval($it['net_weight_kg'] ?? 0));
            $bCount   = max(1, intval($it['box_count'] ?? 1));
            $rKg      = max(0, floatval($it['rate_per_kg'] ?? 0));
            $linePrice = $wKg * $rKg;

            $bTotalWeight += $wKg;
            $bTotalBoxes  += $bCount;
            $bTotalAmount += $linePrice;

            $processedItems[] = [
                'quality_grade' => $qGrade,
                'fish_species'  => $fSpecies,
                'size_category' => $sCat,
                'net_weight_kg' => $wKg,
                'box_count'     => $bCount,
                'rate_per_kg'   => $rKg,
                'total_price'   => $linePrice
            ];
        }

        // Insert buyer record
        $buyerInsertStmt->execute([$tripId, $buyerName, $contactNum, $vehicleNo, $paymentType, $bTotalWeight, $bTotalBoxes, $bTotalAmount]);
        $buyerId = $pdo->lastInsertId();

        // Insert buyer item rows
        foreach ($processedItems as $pIt) {
            $buyerItemInsertStmt->execute([
                $buyerId,
                $tripId,
                $pIt['quality_grade'],
                $pIt['fish_species'],
                $pIt['size_category'],
                $pIt['net_weight_kg'],
                $pIt['box_count'],
                $pIt['rate_per_kg'],
                $pIt['total_price']
            ]);
        }

        $totalDirectWeightKg    += $bTotalWeight;
        $totalDirectBoxesCount  += $bTotalBoxes;
        $totalDirectSalesAmount += $bTotalAmount;

        // Automatically record Direct Buyer Sales Slip into Market Bill Vault
        $billTitle = "Direct Buyer Slip: " . $buyerName . " (" . CURRENCY_SYMBOL . " " . number_format($bTotalAmount, 2) . ")";
        $billNotes = "Direct Harbor Sale | Buyer: {$buyerName} | Phone: {$contactNum} | Vehicle: {$vehicleNo} | Payment: {$paymentType} | Total: " . CURRENCY_SYMBOL . " " . number_format($bTotalAmount, 2) . " ({$bTotalWeight} Kg)";
        $billInsertStmt->execute([$tripId, $billTitle, 'views/catch_dispatch.php', 'SLIP', 15, $currentUserId, $billNotes]);
    }

    // Total Landed Boxes (Lorry Boxes + Direct Buyer Boxes)
    $grandTotalLandedBoxes = $totalLorryBoxesCount + $totalDirectBoxesCount;
    $grandTotalLandedWeight = $totalLorryWeightKg + $totalDirectWeightKg;

    // Update harbour unloading total boxes
    $updateHarbour = $pdo->prepare("UPDATE harbour_unloadings SET total_boxes = ? WHERE trip_id = ?");
    $updateHarbour->execute([$grandTotalLandedBoxes, $tripId]);

    // Update trip status to DISPATCHED
    $updateStatus = $pdo->prepare("UPDATE trips SET status = 'DISPATCHED' WHERE id = ?");
    $updateStatus->execute([$tripId]);

    $targetDoc = $_POST['target_doc'] ?? 'waybill';

    $pdo->commit();

    $buyerCount = count(array_filter($buyers, function($b) { return !empty($b['buyer_name']); }));
    $_SESSION['flash_message'] = "Catch record saved successfully! Total Landed: {$grandTotalLandedBoxes} Boxes ({$grandTotalLandedWeight} Kg). Lorry: {$totalLorryBoxesCount} Boxes. Direct Buyers ({$buyerCount}): " . CURRENCY_SYMBOL . " " . number_format($totalDirectSalesAmount, 2) . ".";
    $_SESSION['flash_type'] = 'success';

    if ($targetDoc === 'buyer_note') {
        header("Location: ../views/buyer_sales_note.php?trip_id={$tripId}");
    } else {
        header("Location: ../views/peliyagoda_waybill.php?dispatch_id={$dispatchId}");
    }
    exit;


} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_message'] = 'Transaction Failed: ' . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header("Location: ../views/catch_dispatch.php?trip_id={$tripId}");
    exit;
}
