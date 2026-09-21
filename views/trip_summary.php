<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce Master Admin Access Guard
requireAdmin();

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
    $_SESSION['flash_message'] = 'Please select a valid fishing trip to view expense summary.';
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

$isLocked = !empty($trip['is_locked']) && $trip['is_locked'] == 1;

// Fetch financial expense components
$expStmt = $pdo->prepare("SELECT * FROM trip_expenses WHERE trip_id = ?");
$expStmt->execute([$tripId]);
$expenses = $expStmt->fetch() ?: ['total_expenses' => 0, 'diesel_cost' => 0, 'ice_cost' => 0, 'ration_cost' => 0, 'gas_cost' => 0, 'maintenance_cost' => 0, 'bait_cost' => 0, 'other_cost' => 0];

$unloadingStmt = $pdo->prepare("SELECT * FROM harbour_unloadings WHERE trip_id = ?");
$unloadingStmt->execute([$tripId]);
$unloading = $unloadingStmt->fetch() ?: ['total_labour_fee' => 0];

$dispatchStmt = $pdo->prepare("SELECT * FROM dispatches WHERE trip_id = ? ORDER BY id DESC LIMIT 1");
$dispatchStmt->execute([$tripId]);
$dispatch = $dispatchStmt->fetch() ?: ['lorry_hire_fee' => 0, 'helper_fee' => 0, 'transit_allowance' => 0, 'total_transport_cost' => 0];

// Fetch direct buyers summary
$buyerSummaryStmt = $pdo->prepare("SELECT SUM(total_amount) as direct_total, SUM(total_weight_kg) as direct_weight, SUM(total_boxes) as direct_boxes, COUNT(*) as buyer_count FROM direct_buyers WHERE trip_id = ?");
$buyerSummaryStmt->execute([$tripId]);
$buyerSummary = $buyerSummaryStmt->fetch() ?: ['direct_total' => 0, 'direct_weight' => 0, 'direct_boxes' => 0, 'buyer_count' => 0];

$totalOpExpenses = floatval($expenses['total_expenses'] ?? 0) + floatval($unloading['total_labour_fee'] ?? 0) + floatval($dispatch['total_transport_cost'] ?? 0);

require_once __DIR__ . '/../includes/header.php';

?>

<!-- Page Header & Actions -->
<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <div>
        <h2 class="h3 fw-bold mb-1">
            <i class="fa-solid fa-calculator text-danger me-2"></i>Trip Operational Expenses & Lock Statement
        </h2>
        <p class="text-muted small mb-0">Consolidated operational expense statement (Master Admin View) and record completion locking control.</p>
    </div>
    <div class="d-flex items-center gap-2">
        <a href="trip_bills.php?trip_id=<?= $trip['id'] ?>" class="btn btn-outline-warning text-dark btn-sm print-keep">
            <i class="fa-solid fa-vault me-1"></i> Bill Vault
        </a>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
        <button onclick="window.print()" class="btn btn-dark btn-sm px-3 shadow-sm print-keep">
            <i class="fa-solid fa-print me-1"></i> Print Statement
        </button>
    </div>

</div>

<!-- Vessel Banner Card -->
<div class="card border-0 bg-danger bg-opacity-10 mb-4 printable-invoice">
    <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($trip['boat_name']) ?> <span class="badge bg-secondary font-monospace small"><?= htmlspecialchars($trip['reg_number']) ?></span></h4>
            <div class="text-muted small mt-1">
                Skipper: <strong><?= htmlspecialchars($trip['skipper_name']) ?></strong> | 
                Departure: <strong><?= formatDate($trip['departure_date']) ?></strong> | 
                Crew Members: <strong><?= intval($trip['crew_count']) ?> Members</strong>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?= getStatusBadge($trip['status']) ?>
            <?php if ($isLocked): ?>
                <span class="badge bg-dark fs-6 px-3 py-2"><i class="fa-solid fa-lock me-1"></i> Trip Locked</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Operational Expense Breakdown Table -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm printable-invoice">
            <div class="card-header bg-dark text-white py-3">
                <div class="fw-bold"><i class="fa-solid fa-receipt me-2"></i>Operational Expense Breakdown</div>
            </div>
            <div class="card-body p-4">
                
                <!-- Departure Provisions Subtable -->
                <h6 class="fw-bold text-uppercase text-muted small mb-2"><i class="fa-solid fa-gas-pump me-1 text-warning"></i>1. Boat Departure Provisions</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Provision Category</th>
                                <th class="text-end" style="width: 180px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Diesel Fuel Expense (ඩීසල්)</td>
                                <td class="text-end font-monospace"><?= formatCurrency($expenses['diesel_cost'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td>Ice Blocks Expense (අයිස්)</td>
                                <td class="text-end font-monospace"><?= formatCurrency($expenses['ice_cost'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td>Food Rations & Provisions (කෑම/රේෂන්)</td>
                                <td class="text-end font-monospace"><?= formatCurrency($expenses['ration_cost'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td>Cooking Gas (ගෑස්)</td>
                                <td class="text-end font-monospace"><?= formatCurrency($expenses['gas_cost'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td>Bait Expense (ඇම)</td>
                                <td class="text-end font-monospace"><?= formatCurrency($expenses['bait_cost'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td>Pre-Trip Maintenance & Repairs</td>
                                <td class="text-end font-monospace"><?= formatCurrency($expenses['maintenance_cost'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td>Miscellaneous Cost</td>
                                <td class="text-end font-monospace"><?= formatCurrency($expenses['other_cost'] ?? 0) ?></td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td>Provisions Subtotal:</td>
                                <td class="text-end text-dark font-monospace"><?= formatCurrency($expenses['total_expenses'] ?? 0) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Harbour & Transport Logistics Subtable -->
                <h6 class="fw-bold text-uppercase text-muted small mb-2"><i class="fa-solid fa-truck me-1 text-primary"></i>2. Harbour Landing & Lorry Logistics Costs</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Logistics Category</th>
                                <th class="text-end" style="width: 180px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Harbour Unloading Labour Fees paid to unloaders</td>
                                <td class="text-end font-monospace"><?= formatCurrency($unloading['total_labour_fee'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td>Lorry Transport Hire Fee</td>
                                <td class="text-end font-monospace"><?= formatCurrency($dispatch['lorry_hire_fee'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td>Lorry Helper Fee (ගෝලයාගේ ගාස්තුව)</td>
                                <td class="text-end font-monospace"><?= formatCurrency($dispatch['helper_fee'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td>Transit Allowance & Pier Charges</td>
                                <td class="text-end font-monospace"><?= formatCurrency($dispatch['transit_allowance'] ?? 0) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Direct Buyers Revenue Summary -->
                <h6 class="fw-bold text-uppercase text-muted small mb-2"><i class="fa-solid fa-users me-1 text-success"></i>3. Direct & Secondary Buyers Sales (අමතර ගැනුම්කරුවන්)</h6>
                <div class="p-3 bg-success bg-opacity-10 border border-success rounded-3 d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <span class="text-success small fw-bold text-uppercase d-block">Direct Buyers Harbor Revenue</span>
                        <span class="small text-muted">Total Sales from <?= intval($buyerSummary['buyer_count']) ?> Direct Buyer(s) (<?= number_format($buyerSummary['direct_weight'] ?? 0, 2) ?> Kg / <?= intval($buyerSummary['direct_boxes'] ?? 0) ?> Boxes)</span>
                    </div>
                    <div class="h4 fw-extrabold text-success mb-0">
                        <?= formatCurrency($buyerSummary['direct_total'] ?? 0) ?>
                    </div>
                </div>


                <!-- Grand Total Operational Expenses Summary -->
                <div class="p-3 bg-danger bg-opacity-10 border border-danger rounded-3 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-danger small fw-bold text-uppercase d-block">Grand Total Operational Cost</span>
                        <span class="small text-muted">Provisions + Labour + Transport Fees</span>
                    </div>
                    <div class="h3 fw-extrabold text-danger mb-0">
                        <?= formatCurrency($totalOpExpenses) ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Admin Trip Completion & Lock Control -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm no-print">
            <div class="card-header bg-dark text-white py-3">
                <div class="fw-bold"><i class="fa-solid fa-lock me-2"></i>Trip Record Control</div>
            </div>
            <div class="card-body p-4 text-center">
                
                <?php if ($isLocked): ?>
                    <div class="w-12 h-12 rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center p-3 mb-3">
                        <i class="fa-solid fa-lock fs-3"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Trip Record is Locked</h5>
                    <p class="text-muted small mb-4">This trip is completed and locked. Data cannot be edited by staff or clerks.</p>
                <?php else: ?>
                    <div class="w-12 h-12 rounded-circle bg-warning text-dark d-inline-flex align-items-center justify-content-center p-3 mb-3">
                        <i class="fa-solid fa-lock-open fs-3"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Trip Record is Active</h5>
                    <p class="text-muted small mb-4">Once catch sorting and lorry dispatch is completed, lock the trip to finalize all records.</p>
                <?php endif; ?>

                <form method="POST" action="../actions/lock_trip.php">
                    <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                    <button type="submit" class="btn btn-<?= $isLocked ? 'outline-secondary' : 'danger' ?> w-100 font-semibold py-2 shadow-sm">
                        <i class="fa-solid <?= $isLocked ? 'fa-lock-open me-1' : 'fa-lock me-1' ?>"></i> 
                        <?= $isLocked ? 'Unlock Trip Record' : 'Mark Completed & Lock Trip' ?>
                    </button>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
