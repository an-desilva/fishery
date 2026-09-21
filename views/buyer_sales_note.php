<?php
/**
 * View: Direct Harbor Buyers Sales Note (PDF / A4 Printable)
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
    $_SESSION['flash_message'] = 'Please select a valid fishing trip to view buyer sales note.';
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

// Fetch direct buyers & items for this trip
$buyerStmt = $pdo->prepare("SELECT * FROM direct_buyers WHERE trip_id = ? ORDER BY id ASC");
$buyerStmt->execute([$tripId]);
$directBuyers = $buyerStmt->fetchAll();

foreach ($directBuyers as &$b) {
    $bItemStmt = $pdo->prepare("SELECT * FROM direct_buyer_items WHERE buyer_id = ? ORDER BY id ASC");
    $bItemStmt->execute([$b['id']]);
    $b['items'] = $bItemStmt->fetchAll();
}
unset($b);

$grandDirectWeight = 0;
$grandDirectBoxes  = 0;
$grandDirectAmount = 0;

foreach ($directBuyers as $b) {
    $grandDirectWeight += floatval($b['total_weight_kg']);
    $grandDirectBoxes  += intval($b['total_boxes']);
    $grandDirectAmount += floatval($b['total_amount']);
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Print & Navigation Toolbar -->
<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <div>
        <h2 class="h3 fw-bold mb-1">
            <i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Direct Harbor Buyers Sales Note
        </h2>
        <p class="text-muted small mb-0">Official sales note & receipt summary dedicated exclusively for direct and secondary harbor buyers.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="peliyagoda_waybill.php?trip_id=<?= $trip['id'] ?>" class="btn btn-outline-primary btn-sm font-semibold rounded-pill px-3">
            <i class="fa-solid fa-truck-fast me-1"></i> View Peliyagoda Waybill
        </a>
        <a href="catch_dispatch.php?trip_id=<?= $trip['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fa-solid fa-pen me-1"></i> Edit Dispatch
        </a>
        <button onclick="window.print()" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm print-keep">
            <i class="fa-solid fa-print me-1"></i> Print Sales Note / Save PDF
        </button>
    </div>
</div>

<!-- Printable A4 Buyer Sales Note Container -->
<div class="printable-invoice card border-2 border-dark shadow-sm bg-white p-4 mx-auto" style="max-width: 900px;">
    
    <!-- Invoice Header -->
    <div class="row align-items-center mb-4 pb-3 border-bottom border-2 border-dark">
        <div class="col-7 d-flex align-items-center gap-3">
            <img src="../assets/images/logo.jpg" alt="SeaLogix Logo" style="height: 56px; width: auto; object-fit: contain;" class="rounded border p-1 bg-white">
            <div>
                <h3 class="fw-extrabold text-dark mb-0 text-uppercase tracking-wider">
                    Direct Harbor Buyers Sales Note
                </h3>
                <div class="small fw-bold text-muted">Dockside Fish Sales & Buyer Statement (අමතර ගැනුම්කරුවන්ගේ සටහන)</div>
                <div class="small text-secondary">Harbour Direct Sales Settlement</div>
            </div>
        </div>
        <div class="col-5 text-end">
            <span class="badge bg-success text-white font-monospace fs-6 px-3 py-2">
                SALES-NOTE-<?= sprintf('%04d', $trip['id']) ?>
            </span>
            <div class="small fw-semibold mt-1.5">Date: <?= date('d M Y, h:i A') ?></div>
        </div>
    </div>

    <!-- Vessel Banner (BOAT NAME ONLY) -->
    <div class="p-3 border rounded bg-light mb-4 d-flex align-items-center justify-content-between">
        <div>
            <h6 class="fw-bold text-uppercase text-muted extra-small mb-1">
                <i class="fa-solid fa-ship me-1 text-success"></i> Fishing Vessel
            </h6>
            <div class="fs-4 fw-extrabold text-dark">
                <?= htmlspecialchars($trip['boat_name']) ?>
            </div>
        </div>
        <div class="text-end">
            <span class="badge bg-dark fs-6 px-3 py-2">
                <i class="fa-solid fa-users me-1 text-info"></i> <?= count($directBuyers) ?> Direct Buyer(s)
            </span>
        </div>
    </div>

    <?php if (empty($directBuyers)): ?>
        <div class="alert alert-warning text-center p-4">
            <i class="fa-solid fa-circle-info fa-2x mb-2 text-warning"></i>
            <h6 class="fw-bold">No Direct Harbor Buyer Sales Recorded</h6>
            <p class="small mb-0">Use the Catch Packing & Transport Logistics form to add direct harbor buyers and generate a sales note.</p>
        </div>
    <?php else: ?>

        <!-- Multi-Buyer Breakdown Cards & Itemized Tables -->
        <h6 class="fw-bold text-uppercase text-dark mb-3">
            <i class="fa-solid fa-receipt me-1 text-success"></i> Multi-Buyer Itemized Fish Sales Statement
        </h6>

        <?php foreach ($directBuyers as $bIdx => $buyer): ?>
            <div class="card border border-dark mb-4 overflow-hidden">
                <!-- Buyer Header Card -->
                <div class="card-header bg-dark text-white py-2 d-flex flex-wrap align-items-center justify-content-between">
                    <span class="fw-bold">
                        <i class="fa-solid fa-user-tag text-info me-1.5"></i> Buyer #<?= $bIdx + 1 ?>: <?= htmlspecialchars($buyer['buyer_name']) ?>
                    </span>
                    <div class="d-flex align-items-center gap-3 extra-small">
                        <span><i class="fa-solid fa-phone me-1 text-secondary"></i> Phone: <strong><?= htmlspecialchars($buyer['contact_number'] ?: 'N/A') ?></strong></span>
                        <span><i class="fa-solid fa-truck-pickup me-1 text-secondary"></i> Vehicle: <strong><?= htmlspecialchars($buyer['vehicle_no'] ?: 'N/A') ?></strong></span>
                        <span class="badge bg-primary font-monospace"><?= htmlspecialchars($buyer['payment_type']) ?></span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered align-middle mb-0 extra-small">
                            <thead class="table-light fw-bold">
                                <tr>
                                    <th style="width: 35px;" class="text-center">#</th>
                                    <th>Quality Grade</th>
                                    <th>Fish Species</th>
                                    <th class="text-center" style="width: 70px;">Size</th>
                                    <th class="text-end" style="width: 100px;">Weight (Kg)</th>
                                    <th class="text-end" style="width: 80px;">Boxes</th>
                                    <th class="text-end" style="width: 110px;">Rate / Kg (<?= CURRENCY_SYMBOL ?>)</th>
                                    <th class="text-end" style="width: 120px;">Line Total (<?= CURRENCY_SYMBOL ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($buyer['items'] as $iIdx => $it): ?>
                                    <tr>
                                        <td class="text-center font-monospace"><?= $iIdx + 1 ?></td>
                                        <td><?= getGradeBadge($it['quality_grade']) ?></td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($it['fish_species']) ?></td>
                                        <td class="text-center"><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($it['size_category']) ?></span></td>
                                        <td class="text-end font-monospace fw-bold"><?= number_format($it['net_weight_kg'], 2) ?> Kg</td>
                                        <td class="text-end font-monospace"><?= intval($it['box_count']) ?></td>
                                        <td class="text-end font-monospace"><?= number_format($it['rate_per_kg'], 2) ?></td>
                                        <td class="text-end font-monospace fw-bold text-success"><?= number_format($it['total_price'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light fw-extrabold">
                                <tr>
                                    <td colspan="4" class="text-end text-uppercase">Buyer Subtotal:</td>
                                    <td class="text-end text-primary font-monospace"><?= formatWeight($buyer['total_weight_kg']) ?></td>
                                    <td class="text-end text-primary font-monospace"><?= intval($buyer['total_boxes']) ?> Boxes</td>
                                    <td class="text-end text-uppercase">Subtotal Amount:</td>
                                    <td class="text-end text-success font-monospace fs-6"><?= formatCurrency($buyer['total_amount']) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Direct Buyers Grand Summary Banner -->
        <div class="p-3 bg-dark text-white rounded border border-dark mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="text-slate-300 small fw-bold text-uppercase d-block">Overall Direct Harbor Sales Grand Total</span>
                <span class="small text-slate-400">Total Weight: <strong><?= formatWeight($grandDirectWeight) ?></strong> &bull; Total Crates: <strong><?= number_format($grandDirectBoxes) ?> Boxes</strong></span>
            </div>
            <div class="h3 fw-extrabold text-success mb-0">
                <?= formatCurrency($grandDirectAmount) ?>
            </div>
        </div>

    <?php endif; ?>

    <!-- Official Sales Signatures -->
    <div class="row pt-4 mt-2 signature-box">
        <div class="col-6 text-center">
            <div class="border-top border-dark pt-2 mt-4">
                <span class="small fw-bold text-dark d-block">Harbour Sales Clerk / Master Admin Signature</span>
                <span class="text-muted extra-small">Date & Official Stamp</span>
            </div>
        </div>
        <div class="col-6 text-center">
            <div class="border-top border-dark pt-2 mt-4">
                <span class="small fw-bold text-dark d-block">Buyer Signature / Acknowledgement</span>
                <span class="text-muted extra-small">Received Goods & Paid / Approved Settlement</span>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
