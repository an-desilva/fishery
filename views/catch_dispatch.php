<?php
/**
 * View: Catch Packing & Transport Logistics
 * Multi-Day Fishing Boat Catch, Sorting & Dispatch Logistics Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$pdo = getDbConnection();
$tripId = intval($_GET['trip_id'] ?? 0);

if ($tripId <= 0) {
    $latestStmt = $pdo->query("SELECT id FROM trips ORDER BY id DESC LIMIT 1");
    $tripId = intval($latestStmt->fetchColumn());
}

$trip = null;
if ($tripId > 0) {
    $tripStmt = $pdo->prepare("SELECT * FROM trips WHERE id = ?");
    $tripStmt->execute([$tripId]);
    $trip = $tripStmt->fetch();
}

if (!$trip) {
    $_SESSION['flash_message'] = 'Please select or register a fishing trip for catch packing.';
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

// Fetch harbour unloading details if saved
$unloadingStmt = $pdo->prepare("SELECT * FROM harbour_unloadings WHERE trip_id = ?");
$unloadingStmt->execute([$tripId]);
$unloading = $unloadingStmt->fetch() ?: [];

// Fetch existing dispatch if any
$dispatchStmt = $pdo->prepare("SELECT * FROM dispatches WHERE trip_id = ? ORDER BY id DESC LIMIT 1");
$dispatchStmt->execute([$tripId]);
$dispatch = $dispatchStmt->fetch() ?: [];

// Fetch dispatch items if dispatch exists
$dispatchItems = [];
if (!empty($dispatch)) {
    $itemStmt = $pdo->prepare("SELECT * FROM dispatch_items WHERE dispatch_id = ?");
    $itemStmt->execute([$dispatch['id']]);
    $dispatchItems = $itemStmt->fetchAll();
}

// Fetch direct buyers & items for this trip if any
$buyerStmt = $pdo->prepare("SELECT * FROM direct_buyers WHERE trip_id = ? ORDER BY id ASC");
$buyerStmt->execute([$tripId]);
$directBuyers = $buyerStmt->fetchAll();

foreach ($directBuyers as &$b) {
    $bItemStmt = $pdo->prepare("SELECT * FROM direct_buyer_items WHERE buyer_id = ? ORDER BY id ASC");
    $bItemStmt->execute([$b['id']]);
    $b['items'] = $bItemStmt->fetchAll();
}
unset($b);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Page Header (Full Width Top Row - Left Title, Right Vessel Info & Actions) -->
<div class="row align-items-center mb-4 gy-3">
    <!-- Top Left: Title & Subtitle -->
    <div class="col-xl-5 col-lg-6">
        <h2 class="h3 fw-extrabold text-dark mb-1 d-flex align-items-center gap-2">
            <span class="bg-success text-white rounded-3 px-2.5 py-1.5 shadow-sm fs-5">
                <i class="fa-solid fa-truck-ramp-box"></i>
            </span>
            Catch Packing & Transport Logistics
        </h2>
        <p class="text-muted mb-0 small">
            Harbour unloading labour fees, catch crate sorting by species & grade, and lorry transport dispatch details.
        </p>
    </div>

    <!-- Top Right: Boat Info Chip & Quick Action Buttons -->
    <div class="col-xl-7 col-lg-6 text-lg-end">
        <div class="d-flex flex-wrap align-items-center justify-content-lg-end gap-2">
            <!-- Vessel Info Badge Chip -->
            <div class="bg-white border shadow-sm rounded-pill px-3 py-1.5 d-inline-flex align-items-center gap-2 extra-small text-start">
                <span class="fw-bold text-dark fs-6"><i class="fa-solid fa-ship text-primary me-1"></i><?= htmlspecialchars($trip['boat_name']) ?></span>
                <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($trip['reg_number']) ?></span>
                <span class="text-muted border-start ps-2 d-none d-sm-inline">
                    Skipper: <strong><?= htmlspecialchars($trip['skipper_name']) ?></strong> (<?= intval($trip['crew_count']) ?> Crew)
                </span>
                <span class="ms-1"><?= getStatusBadge($trip['status']) ?></span>
            </div>

            <!-- Action Buttons -->
            <a href="trip_bills.php?trip_id=<?= $trip['id'] ?>" class="btn btn-outline-warning text-dark btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-vault me-1"></i> Market Bill Vault
            </a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>
</div>

<!-- 2. 2-Column Dashboard Grid Layout -->
<div class="row g-4">

    <!-- SECTION 1: Harbour Unloading Labour Fee (Left Column - 4 Cols / 33%) -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
            <div class="card-header bg-dark text-white py-3">
                <h5 class="card-title h6 mb-0 fw-bold d-flex align-items-center">
                    <i class="fa-solid fa-users-gear text-info me-2"></i>1. Harbour Unloading Labour Fee
                </h5>
            </div>
            <div class="card-body p-3.5">
                <form method="POST" action="../actions/save_unloading.php">
                    <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">

                    <div class="mb-3">
                        <label for="rate_type" class="form-label fw-semibold text-secondary small">Labour Rate Basis <span class="text-danger">*</span></label>
                        <select class="form-select font-semibold" id="rate_type" name="rate_type">
                            <option value="PER_BOX" <?= ($unloading['rate_type'] ?? 'PER_BOX') === 'PER_BOX' ? 'selected' : '' ?>>Per Crate / Box Rate (පෙට්ටියකට ගාස්තුව)</option>
                            <option value="FIXED" <?= ($unloading['rate_type'] ?? '') === 'FIXED' ? 'selected' : '' ?>>Fixed Bulk Rate (තනි ගාස්තුව)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="rate_amount" class="form-label fw-semibold text-secondary small">Rate Amount per Box / Fixed <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary"><?= CURRENCY_SYMBOL ?></span>
                            <input type="number" step="0.01" min="0" class="form-control fw-bold" id="rate_amount" name="rate_amount" value="<?= htmlspecialchars($unloading['rate_amount'] ?? '250.00') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="total_unloaders" class="form-label fw-semibold text-secondary small">Number of Labourers <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-users"></i></span>
                            <input type="number" min="1" class="form-control fw-semibold" id="total_unloaders" name="total_unloaders" value="<?= htmlspecialchars($unloading['total_unloaders'] ?? 6) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="total_labour_fee" class="form-label fw-semibold text-secondary small">Total Calculated Labour Fee</label>
                        <div class="input-group">
                            <span class="input-group-text bg-emerald-50 text-success fw-bold"><?= CURRENCY_SYMBOL ?></span>
                            <input type="number" step="0.01" min="0" class="form-control bg-light fw-extrabold text-success fs-5" id="total_labour_fee" name="total_labour_fee" readonly value="<?= htmlspecialchars($unloading['total_labour_fee'] ?? '0.00') ?>">
                        </div>
                    </div>

                    <div class="mb-3.5">
                        <label for="unloading_notes" class="form-label fw-semibold text-secondary small">Unloading Harbour Notes</label>
                        <textarea class="form-control" id="unloading_notes" name="notes" rows="3" placeholder="e.g. Unloaded at Dikowita Pier #4 by local team"><?= htmlspecialchars($unloading['notes'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 py-2.5 rounded-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" <?= !empty($trip['is_locked']) && $trip['is_locked'] == 1 ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-floppy-disk"></i> Save Unloading Labour Fee
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- SECTION 2 & 3: Catch Packing Crates & Transport Logistics (Right Column - 8 Cols / 66%) -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-3 bg-white">
            <div class="card-header bg-dark text-white py-3 d-flex align-items-center justify-content-between">
                <h5 class="card-title h6 mb-0 fw-bold d-flex align-items-center">
                    <i class="fa-solid fa-boxes-packing text-success me-2"></i>2. Catch Packing & Transport Logistics
                </h5>
                <span class="badge bg-success text-white fw-semibold px-2.5 py-1">Active Waybill Form</span>
            </div>
            <div class="card-body p-3.5">
                <form method="POST" action="../actions/save_dispatch.php" id="dispatchForm">
                    <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                    <input type="hidden" id="grandTotalKgInput" name="grand_total_kg" value="0">
                    <input type="hidden" id="grandTotalBoxesInput" name="grand_total_boxes" value="0">

                    <!-- Transport Logistics Header Grid (Spacious 3-Column Layout) -->
                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <h6 class="fw-bold text-dark mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-truck text-primary me-2"></i>Lorry Transport & Driver Information
                        </h6>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="lorry_number" class="form-label fw-semibold text-secondary small">Lorry Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control font-monospace fw-bold" id="lorry_number" name="lorry_number" required placeholder="e.g. WP LE-4892" value="<?= htmlspecialchars($dispatch['lorry_number'] ?? '') ?>">
                            </div>

                            <div class="col-md-4">
                                <label for="driver_name" class="form-label fw-semibold text-secondary small">Driver Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control fw-semibold" id="driver_name" name="driver_name" required placeholder="e.g. Dhammika Bandara" value="<?= htmlspecialchars($dispatch['driver_name'] ?? '') ?>">
                            </div>

                            <div class="col-md-4">
                                <label for="driver_phone" class="form-label fw-semibold text-secondary small">Driver Phone Number</label>
                                <input type="text" class="form-control font-monospace" id="driver_phone" name="driver_phone" placeholder="e.g. 0771234567" value="<?= htmlspecialchars($dispatch['driver_phone'] ?? '') ?>">
                            </div>

                            <div class="col-md-3">
                                <label for="helper_name" class="form-label fw-semibold text-secondary small">Lorry Helper Name (ගෝලයා)</label>
                                <input type="text" class="form-control" id="helper_name" name="helper_name" placeholder="e.g. Saman Kumara" value="<?= htmlspecialchars($dispatch['helper_name'] ?? '') ?>">
                            </div>

                            <div class="col-md-3">
                                <label for="lorry_hire_fee" class="form-label fw-semibold text-secondary small">Lorry Hire Fee <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-secondary"><?= CURRENCY_SYMBOL ?></span>
                                    <input type="number" step="0.01" min="0" class="form-control fw-bold" id="lorry_hire_fee" name="lorry_hire_fee" required value="<?= htmlspecialchars($dispatch['lorry_hire_fee'] ?? '35000.00') ?>">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label for="helper_fee" class="form-label fw-semibold text-secondary small">Helper Fee (ගෝලයා)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-secondary"><?= CURRENCY_SYMBOL ?></span>
                                    <input type="number" step="0.01" min="0" class="form-control fw-bold" id="helper_fee" name="helper_fee" value="<?= htmlspecialchars($dispatch['helper_fee'] ?? '5000.00') ?>">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label for="transit_allowance" class="form-label fw-semibold text-secondary small">Transit Toll Allowance</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-secondary"><?= CURRENCY_SYMBOL ?></span>
                                    <input type="number" step="0.01" min="0" class="form-control fw-bold" id="transit_allowance" name="transit_allowance" value="<?= htmlspecialchars($dispatch['transit_allowance'] ?? '2500.00') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Catch Crates Packing Table Section -->
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                            <i class="fa-solid fa-fish text-success me-2"></i>Catch Crates & Fish Boxes Table
                        </h6>
                        <button type="button" id="addRowBtn" class="btn btn-outline-success btn-sm font-semibold rounded-pill px-3">
                            <i class="fa-solid fa-plus me-1"></i> Add Fish Box Row
                        </button>
                    </div>

                    <!-- Full-Width Responsive Table -->
                    <div class="table-responsive mb-3 rounded-2 border">
                        <table class="table table-hover table-striped align-middle mb-0" id="catchTable">
                            <thead class="table-dark">
                                <tr>
                                    <th style="min-width: 170px;">Quality Grade</th>
                                    <th style="min-width: 210px;">Fish Species</th>
                                    <th style="min-width: 120px;">Size</th>
                                    <th style="min-width: 130px;">Weight (Kg)</th>
                                    <th style="min-width: 110px;">Boxes</th>
                                    <th style="width: 60px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="catchTableBody">
                                <?php if (!empty($dispatchItems)): ?>
                                    <?php foreach ($dispatchItems as $idx => $item): ?>
                                        <tr>
                                            <td>
                                                <select name="items[<?= $idx ?>][quality_grade]" class="form-select form-select-sm font-semibold">
                                                    <?php foreach (QUALITY_GRADES as $gKey => $gVal): ?>
                                                        <option value="<?= $gKey ?>" <?= $item['quality_grade'] === $gKey ? 'selected' : '' ?>><?= $gVal ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="items[<?= $idx ?>][fish_species]" class="form-select form-select-sm font-semibold">
                                                    <?php foreach (FISH_SPECIES as $fKey => $fVal): ?>
                                                        <option value="<?= $fKey ?>" <?= $item['fish_species'] === $fKey ? 'selected' : '' ?>><?= $fVal ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="items[<?= $idx ?>][size_category]" class="form-select form-select-sm font-semibold">
                                                    <?php foreach (SIZE_CATEGORIES as $sKey => $sVal): ?>
                                                        <option value="<?= $sKey ?>" <?= $item['size_category'] === $sKey ? 'selected' : '' ?>><?= $sVal ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" name="items[<?= $idx ?>][net_weight_kg]" class="form-control form-select-sm row-weight fw-bold" value="<?= htmlspecialchars($item['net_weight_kg']) ?>">
                                            </td>
                                            <td>
                                                <input type="number" min="1" name="items[<?= $idx ?>][box_count]" class="form-control form-select-sm row-boxes fw-bold" value="<?= htmlspecialchars($item['box_count']) ?>">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn p-1.5 rounded-circle" title="Remove row">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Default empty template row -->
                                    <tr>
                                        <td>
                                            <select name="items[0][quality_grade]" class="form-select form-select-sm font-semibold">
                                                <?php foreach (QUALITY_GRADES as $gKey => $gVal): ?>
                                                    <option value="<?= $gKey ?>"><?= $gVal ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="items[0][fish_species]" class="form-select form-select-sm font-semibold">
                                                <?php foreach (FISH_SPECIES as $fKey => $fVal): ?>
                                                    <option value="<?= $fKey ?>"><?= $fVal ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="items[0][size_category]" class="form-select form-select-sm font-semibold">
                                                <?php foreach (SIZE_CATEGORIES as $sKey => $sVal): ?>
                                                    <option value="<?= $sKey ?>"><?= $sVal ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" name="items[0][net_weight_kg]" class="form-control form-select-sm row-weight fw-bold" value="0.00">
                                        </td>
                                        <td>
                                            <input type="number" min="1" name="items[0][box_count]" class="form-control form-select-sm row-boxes fw-bold" value="1">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn p-1.5 rounded-circle" title="Remove row">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Dynamic Grand Totals Highlight Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <div class="p-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-primary extra-small fw-bold text-uppercase d-block">Lorry Packed Weight</span>
                                    <h3 class="fw-extrabold text-dark mb-0" id="totalWeightKg">0.00 Kg</h3>
                                </div>
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="fa-solid fa-weight-hanging"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-success extra-small fw-bold text-uppercase d-block">Lorry Box Count</span>
                                    <h3 class="fw-extrabold text-dark mb-0" id="totalBoxCount">0 Boxes</h3>
                                </div>
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="fa-solid fa-boxes-stacked"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: Direct & Secondary Fish Buyers (අමතර ගැනුම්කරුවන්) -->
                    <div class="mt-4 pt-4 border-top">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="fw-bold mb-1 text-dark d-flex align-items-center">
                                    <i class="fa-solid fa-users-viewfinder text-primary me-2 fs-5"></i>3. Direct & Secondary Fish Buyers (අමතර ගැනුම්කරුවන්)
                                </h6>
                                <p class="text-muted extra-small mb-0">Record harbor sales to direct & secondary buyers alongside lorry transport dispatch.</p>
                            </div>
                            <button type="button" id="addBuyerBtn" class="btn btn-outline-primary btn-sm font-semibold rounded-pill px-3 shadow-sm">
                                <i class="fa-solid fa-plus me-1"></i> Add New Buyer
                            </button>
                        </div>

                        <!-- Multi-Buyer Container -->
                        <div id="buyersContainer" class="d-flex flex-column gap-3 mb-4">
                            <?php if (!empty($directBuyers)): ?>
                                <?php foreach ($directBuyers as $bIdx => $buyer): ?>
                                    <div class="card border rounded-3 buyer-card bg-light bg-opacity-50 shadow-sm">
                                        <div class="card-header bg-dark text-white py-2.5 d-flex align-items-center justify-content-between">
                                            <span class="fw-bold small"><i class="fa-solid fa-user-tag text-info me-1.5"></i> Buyer Card #<span class="buyer-number"><?= $bIdx + 1 ?></span></span>
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-buyer-btn py-0 px-2 rounded-pill extra-small text-white border-danger">
                                                <i class="fa-solid fa-trash-can me-1"></i> Remove Buyer
                                            </button>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold text-secondary extra-small mb-1">Buyer Name <span class="text-danger">*</span></label>
                                                    <input type="text" name="buyers[<?= $bIdx ?>][buyer_name]" class="form-control form-control-sm fw-bold buyer-name-input" value="<?= htmlspecialchars($buyer['buyer_name']) ?>" required placeholder="e.g. Nimal Fisheries">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold text-secondary extra-small mb-1">Contact Phone</label>
                                                    <input type="text" name="buyers[<?= $bIdx ?>][contact_number]" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($buyer['contact_number'] ?? '') ?>" placeholder="e.g. 0712345678">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold text-secondary extra-small mb-1">Vehicle / Tuk-Tuk No</label>
                                                    <input type="text" name="buyers[<?= $bIdx ?>][vehicle_no]" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($buyer['vehicle_no'] ?? '') ?>" placeholder="e.g. WP ND-1234">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold text-secondary extra-small mb-1">Payment Method</label>
                                                    <select name="buyers[<?= $bIdx ?>][payment_type]" class="form-select form-select-sm font-semibold">
                                                        <?php foreach (PAYMENT_TYPES as $pKey => $pVal): ?>
                                                            <option value="<?= $pKey ?>" <?= ($buyer['payment_type'] ?? 'CASH') === $pKey ? 'selected' : '' ?>><?= $pVal ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Buyer Fish Table -->
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="fw-bold extra-small text-uppercase text-secondary"><i class="fa-solid fa-fish me-1 text-primary"></i> Direct Sales Fish Rows</span>
                                                <button type="button" class="btn btn-outline-success btn-sm add-buyer-fish-row-btn py-0 px-2.5 rounded-pill extra-small">
                                                    <i class="fa-solid fa-plus me-1"></i> Add Fish Row
                                                </button>
                                            </div>
                                            <div class="table-responsive rounded-2 border bg-white mb-2">
                                                <table class="table table-sm table-hover align-middle mb-0 buyer-fish-table">
                                                    <thead class="table-light extra-small">
                                                        <tr>
                                                            <th style="min-width: 140px;">Quality Grade</th>
                                                            <th style="min-width: 170px;">Fish Species</th>
                                                            <th style="min-width: 90px;">Size</th>
                                                            <th style="min-width: 110px;">Weight (Kg)</th>
                                                            <th style="min-width: 90px;">Boxes</th>
                                                            <th style="min-width: 110px;">Rate / Kg (<?= CURRENCY_SYMBOL ?>)</th>
                                                            <th style="min-width: 120px;">Line Total (<?= CURRENCY_SYMBOL ?>)</th>
                                                            <th style="width: 40px;" class="text-center"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php $bItems = $buyer['items'] ?? []; ?>
                                                        <?php if (!empty($bItems)): ?>
                                                            <?php foreach ($bItems as $iIdx => $it): ?>
                                                                <tr>
                                                                    <td>
                                                                        <select name="buyers[<?= $bIdx ?>][items][<?= $iIdx ?>][quality_grade]" class="form-select form-select-sm extra-small">
                                                                            <?php foreach (QUALITY_GRADES as $gKey => $gVal): ?>
                                                                                <option value="<?= $gKey ?>" <?= $it['quality_grade'] === $gKey ? 'selected' : '' ?>><?= $gVal ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </td>
                                                                    <td>
                                                                        <select name="buyers[<?= $bIdx ?>][items][<?= $iIdx ?>][fish_species]" class="form-select form-select-sm extra-small">
                                                                            <?php foreach (FISH_SPECIES as $fKey => $fVal): ?>
                                                                                <option value="<?= $fKey ?>" <?= $it['fish_species'] === $fKey ? 'selected' : '' ?>><?= $fVal ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </td>
                                                                    <td>
                                                                        <select name="buyers[<?= $bIdx ?>][items][<?= $iIdx ?>][size_category]" class="form-select form-select-sm extra-small">
                                                                            <?php foreach (SIZE_CATEGORIES as $sKey => $sVal): ?>
                                                                                <option value="<?= $sKey ?>" <?= $it['size_category'] === $sKey ? 'selected' : '' ?>><?= $sVal ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </td>
                                                                    <td><input type="number" step="0.01" min="0" name="buyers[<?= $bIdx ?>][items][<?= $iIdx ?>][net_weight_kg]" class="form-control form-control-sm buyer-item-weight fw-bold" value="<?= htmlspecialchars($it['net_weight_kg']) ?>"></td>
                                                                    <td><input type="number" min="1" name="buyers[<?= $bIdx ?>][items][<?= $iIdx ?>][box_count]" class="form-control form-control-sm buyer-item-boxes fw-bold" value="<?= htmlspecialchars($it['box_count']) ?>"></td>
                                                                    <td><input type="number" step="0.01" min="0" name="buyers[<?= $bIdx ?>][items][<?= $iIdx ?>][rate_per_kg]" class="form-control form-control-sm buyer-item-rate fw-bold" value="<?= htmlspecialchars($it['rate_per_kg']) ?>"></td>
                                                                    <td><input type="text" class="form-control form-control-sm buyer-item-total fw-extrabold text-success bg-light" readonly value="<?= number_format($it['total_price'], 2) ?>"></td>
                                                                    <td class="text-center">
                                                                        <button type="button" class="btn btn-outline-danger btn-sm remove-buyer-fish-row-btn p-1 rounded-circle"><i class="fa-solid fa-xmark"></i></button>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <!-- Buyer Sub-Totals Footer -->
                                            <div class="p-2 rounded bg-white border d-flex flex-wrap align-items-center justify-content-between gap-2 extra-small">
                                                <div>
                                                    <span class="text-muted me-2">Buyer Weight: <strong class="text-dark buyer-subtotal-weight">0.00 Kg</strong></span>
                                                    <span class="text-muted">Buyer Boxes: <strong class="text-dark buyer-subtotal-boxes">0 Boxes</strong></span>
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-uppercase text-secondary me-1">Buyer Total Sales:</span>
                                                    <strong class="text-success fs-6 buyer-subtotal-amount"><?= CURRENCY_SYMBOL ?> 0.00</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Multi-Buyer Totals & Landed Reconciliation Summary Bar -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="p-3 bg-info bg-opacity-10 border border-info border-opacity-25 rounded-3">
                                    <span class="text-dark extra-small fw-bold text-uppercase d-block">Direct Buyers Sales Value</span>
                                    <h4 class="fw-extrabold text-success mb-0" id="totalDirectRevenueDisplay"><?= CURRENCY_SYMBOL ?> 0.00</h4>
                                    <input type="hidden" id="grandTotalDirectRevenueInput" name="grand_total_direct_revenue" value="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-secondary bg-opacity-10 border border-secondary border-opacity-25 rounded-3">
                                    <span class="text-secondary extra-small fw-bold text-uppercase d-block">Direct Buyers Weight & Boxes</span>
                                    <h4 class="fw-extrabold text-dark mb-0" id="totalDirectWeightDisplay">0.00 Kg (0 Boxes)</h4>
                                    <input type="hidden" id="grandTotalDirectWeightInput" name="grand_total_direct_weight" value="0">
                                    <input type="hidden" id="grandTotalDirectBoxesInput" name="grand_total_direct_boxes" value="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3">
                                    <span class="text-dark extra-small fw-bold text-uppercase d-block"><i class="fa-solid fa-scale-balanced me-1 text-warning"></i>Total Landed Catch Reconciliation</span>
                                    <h4 class="fw-extrabold text-dark mb-0" id="totalLandedCatchWeightDisplay">0.00 Kg</h4>
                                    <div class="extra-small text-muted" id="landedCatchBreakdownText">Lorry: 0 Kg + Direct: 0 Kg</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Action Buttons -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                        </a>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="submit" name="target_doc" value="waybill" class="btn btn-primary rounded-pill font-semibold px-4 py-2.5 shadow-sm d-flex align-items-center gap-2" <?= !empty($trip['is_locked']) && $trip['is_locked'] == 1 ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-truck-fast fs-5"></i> Generate Peliyagoda Waybill (PDF)
                            </button>
                            <button type="submit" name="target_doc" value="buyer_note" class="btn btn-success rounded-pill font-semibold px-4 py-2.5 shadow-sm d-flex align-items-center gap-2" <?= !empty($trip['is_locked']) && $trip['is_locked'] == 1 ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-file-invoice-dollar fs-5"></i> Generate Buyer Sales Note (PDF)
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>

</div>

<script src="../assets/js/dispatch_calculator.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>