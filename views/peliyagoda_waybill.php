<?php
/**
 * View: Peliyagoda Central Wholesale Market Transport Waybill (PDF / A4 Printable)
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
$dispatchId = intval($_GET['dispatch_id'] ?? 0);
$tripId = intval($_GET['trip_id'] ?? 0);

if ($dispatchId <= 0 && $tripId > 0) {
    $latestStmt = $pdo->prepare("SELECT id FROM dispatches WHERE trip_id = ? ORDER BY id DESC LIMIT 1");
    $latestStmt->execute([$tripId]);
    $dispatchId = intval($latestStmt->fetchColumn());
}

if ($dispatchId <= 0) {
    $latestStmt = $pdo->query("SELECT id FROM dispatches ORDER BY id DESC LIMIT 1");
    $dispatchId = intval($latestStmt->fetchColumn());
}

$dispatch = null;
if ($dispatchId > 0) {
    $stmt = $pdo->prepare("
        SELECT d.*, t.boat_name, t.reg_number, t.skipper_name, t.crew_count, t.departure_date, t.arrival_date
        FROM dispatches d
        JOIN trips t ON d.trip_id = t.id
        WHERE d.id = ?
    ");
    $stmt->execute([$dispatchId]);
    $dispatch = $stmt->fetch();
}

if (!$dispatch) {
    $_SESSION['flash_message'] = 'No lorry dispatch waybills found. Please register catch dispatch first.';
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

// Fetch dispatch line items
$itemStmt = $pdo->prepare("SELECT * FROM dispatch_items WHERE dispatch_id = ?");
$itemStmt->execute([$dispatchId]);
$items = $itemStmt->fetchAll();

$totalWeightKg = 0;
$totalBoxes = 0;
foreach ($items as $item) {
    $totalWeightKg += $item['net_weight_kg'];
    $totalBoxes    += $item['box_count'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Print & Navigation Toolbar -->
<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <div>
        <h2 class="h3 fw-bold mb-1">
            <i class="fa-solid fa-truck-fast text-primary me-2"></i>Peliyagoda Transport Waybill
        </h2>
        <p class="text-muted small mb-0">Official transport & receiving waybill for lorry driver and Peliyagoda Central Wholesale Market checkpost.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="buyer_sales_note.php?trip_id=<?= $dispatch['trip_id'] ?>" class="btn btn-outline-success btn-sm font-semibold rounded-pill px-3">
            <i class="fa-solid fa-file-invoice-dollar me-1"></i> View Buyer Sales Note
        </a>
        <a href="catch_dispatch.php?trip_id=<?= $dispatch['trip_id'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fa-solid fa-pen me-1"></i> Edit Dispatch
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm print-keep">
            <i class="fa-solid fa-print me-1"></i> Print Waybill / Save PDF
        </button>
    </div>
</div>

<!-- Printable A4 Waybill Document Container -->
<div class="printable-invoice card border-2 border-dark shadow-sm bg-white p-4 mx-auto" style="max-width: 900px;">
    
    <!-- Invoice Header -->
    <div class="row align-items-center mb-4 pb-3 border-bottom border-2 border-dark">
        <div class="col-7 d-flex align-items-center gap-3">
            <img src="../assets/images/logo.jpg" alt="SeaLogix Logo" style="height: 56px; width: auto; object-fit: contain;" class="rounded border p-1 bg-white">
            <div>
                <h3 class="fw-extrabold text-dark mb-0 text-uppercase tracking-wider">
                    Peliyagoda Transport Waybill
                </h3>
                <div class="small fw-bold text-muted">Central Wholesale Market Logistics Note</div>
                <div class="small text-secondary">Harbour Landing & Lorry Transit Document</div>
            </div>
        </div>
        <div class="col-5 text-end">
            <span class="badge bg-dark text-white font-monospace fs-6 px-3 py-2">
                <?= htmlspecialchars($dispatch['dispatch_no']) ?>
            </span>
            <div class="small fw-semibold mt-1.5">Date: <?= formatDate($dispatch['dispatch_date'], 'd M Y, h:i A') ?></div>
        </div>
    </div>

    <!-- Details Grid: Fishing Vessel (BOAT NAME ONLY) & Lorry Transport -->
    <div class="row g-3 mb-4">
        <!-- Vessel Details (Boat Name ONLY) -->
        <div class="col-6">
            <div class="p-3 border rounded bg-light h-100 d-flex flex-column justify-content-center">
                <h6 class="fw-bold text-uppercase border-bottom pb-1 mb-2 text-dark">
                    <i class="fa-solid fa-ship me-1 text-primary"></i> Fishing Vessel Information
                </h6>
                <div class="fs-4 fw-extrabold text-dark py-2">
                    <?= htmlspecialchars($dispatch['boat_name']) ?>
                </div>
            </div>
        </div>

        <!-- Lorry Logistics Details -->
        <div class="col-6">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-uppercase border-bottom pb-1 mb-2 text-dark">
                    <i class="fa-solid fa-truck me-1 text-primary"></i> Lorry Transport & Driver Info
                </h6>
                <div class="small mb-1"><strong>Lorry Reg No:</strong> <span class="font-monospace fw-bold fs-6 text-dark"><?= htmlspecialchars($dispatch['lorry_number']) ?></span></div>
                <div class="small mb-1"><strong>Driver Name:</strong> <?= htmlspecialchars($dispatch['driver_name']) ?></div>
                <div class="small mb-1"><strong>Driver Phone:</strong> <span class="font-monospace"><?= htmlspecialchars($dispatch['driver_phone'] ?: 'N/A') ?></span></div>
                <div class="small mb-1"><strong>Lorry Helper:</strong> <?= htmlspecialchars($dispatch['helper_name'] ?: 'N/A') ?></div>
                <div class="small text-primary fw-bold"><strong>Destination:</strong> Peliyagoda Central Wholesale Market</div>
            </div>
        </div>
    </div>

    <!-- Packed Fish Crates Items Table -->
    <h6 class="fw-bold text-uppercase text-dark mb-2">
        <i class="fa-solid fa-boxes-packing me-1 text-primary"></i> Dispatch Catch Packing Table
    </h6>
    <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th style="width: 45px;" class="text-center">#</th>
                    <th>Quality Grade</th>
                    <th>Fish Species</th>
                    <th class="text-center" style="width: 90px;">Size</th>
                    <th class="text-end" style="width: 130px;">Crate / Box Count</th>
                    <th class="text-end" style="width: 150px;">Net Weight (Kg)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $item): ?>
                    <tr>
                        <td class="text-center font-monospace fw-bold"><?= $idx + 1 ?></td>
                        <td><?= getGradeBadge($item['quality_grade']) ?></td>
                        <td class="fw-bold text-dark"><?= htmlspecialchars($item['fish_species']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($item['size_category']) ?></span>
                        </td>
                        <td class="text-end fw-bold font-monospace"><?= intval($item['box_count']) ?> Boxes</td>
                        <td class="text-end fw-bold font-monospace text-dark"><?= formatWeight($item['net_weight_kg']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-extrabold border-top border-2 border-dark">
                    <td colspan="4" class="text-end text-uppercase">Total Lorry Transport Catch:</td>
                    <td class="text-end text-primary font-monospace fs-6"><?= number_format($totalBoxes) ?> Boxes</td>
                    <td class="text-end text-success font-monospace fs-6"><?= formatWeight($totalWeightKg) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if (!empty($dispatch['notes'])): ?>
        <!-- Special Instructions / Notes -->
        <div class="p-2.5 bg-light border rounded mb-4 extra-small">
            <strong class="text-dark">Waybill Notes / Special Instructions:</strong> <?= htmlspecialchars($dispatch['notes']) ?>
        </div>
    <?php endif; ?>

    <!-- Signatures Section -->
    <div class="row pt-4 mt-2 signature-box">
        <div class="col-4 text-center">
            <div class="border-top border-dark pt-2 mt-3">
                <span class="small fw-bold text-dark d-block">Harbour Checker Signature</span>
                <span class="text-muted extra-small">Date & Stamp</span>
            </div>
        </div>
        <div class="col-4 text-center">
            <div class="border-top border-dark pt-2 mt-3">
                <span class="small fw-bold text-dark d-block">Lorry Driver Signature</span>
                <span class="text-muted extra-small"><?= htmlspecialchars($dispatch['driver_name']) ?></span>
            </div>
        </div>
        <div class="col-4 text-center">
            <div class="border-top border-dark pt-2 mt-3">
                <span class="small fw-bold text-dark d-block">Peliyagoda Gate Checker</span>
                <span class="text-muted extra-small">Receiving Date & Time Stamp</span>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
