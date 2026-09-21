<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$currentUser = getCurrentUser();
$pdo = getDbConnection();

try {
    // Fetch all trips with aggregate statistics
    $stmt = $pdo->query("
        SELECT 
            t.*,
            COALESCE(te.total_expenses, 0) as total_expenses,
            COALESCE(hu.total_labour_fee, 0) as harbour_fee,
            COALESCE(hu.total_boxes, 0) as total_boxes,
            COALESCE(d.id, 0) as dispatch_id,
            COALESCE(d.dispatch_no, '') as dispatch_no,
            COALESCE(d.total_transport_cost, 0) as transport_fee,
            COALESCE((SELECT SUM(net_weight_kg) FROM dispatch_items WHERE trip_id = t.id), 0) as total_weight_kg,
            (COALESCE(te.total_expenses, 0) + COALESCE(hu.total_labour_fee, 0) + COALESCE(d.total_transport_cost, 0)) as total_op_cost
        FROM trips t
        LEFT JOIN trip_expenses te ON t.id = te.trip_id
        LEFT JOIN harbour_unloadings hu ON t.id = hu.trip_id
        LEFT JOIN dispatches d ON t.id = d.trip_id
        ORDER BY t.id DESC
    ");
    $trips = $stmt->fetchAll();

    // Calculate metrics
    $activeCount = 0;
    $dispatchedCount = 0;
    $completedCount = 0;
    $grandTotalWeight = 0;
    $grandTotalBoxes = 0;
    $grandTotalOpExpenses = 0;

    foreach ($trips as $t) {
        if ($t['status'] === 'DEPARTED') $activeCount++;
        if ($t['status'] === 'LANDED' || $t['status'] === 'DISPATCHED') $dispatchedCount++;
        if ($t['status'] === 'COMPLETED') $completedCount++;
        
        $grandTotalWeight += $t['total_weight_kg'];
        $grandTotalBoxes  += $t['total_boxes'];
        $grandTotalOpExpenses += $t['total_op_cost'];
    }

} catch (Exception $e) {
    $trips = [];
    $activeCount = 0;
    $dispatchedCount = 0;
    $completedCount = 0;
    $grandTotalWeight = 0;
    $grandTotalBoxes = 0;
    $grandTotalOpExpenses = 0;
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Dashboard Top Banner -->
<div class="row align-items-center mb-4 gy-3">
    <div class="col-md-7">
        <h2 class="h3 fw-extrabold text-dark mb-1">
            <i class="fa-solid fa-gauge-high text-primary me-2"></i>Catch & Dispatch Operations Dashboard
        </h2>
        <p class="text-muted mb-0 small">
            Logged in as <strong><?= htmlspecialchars($currentUser['username']) ?></strong> (<?= getRoleBadge($currentUser['role']) ?>). Multi-day fishing vessel tracking, catch sorting, and transport logistics.
        </p>
    </div>
    <div class="col-md-5 text-md-end">
        <a href="new_trip.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fa-solid fa-plus-circle me-1"></i> Register New Departure
        </a>
    </div>
</div>

<!-- KPI Summary Cards (Role-Aware) -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-hover border-0 shadow-sm bg-white">
            <div class="card-body p-3.5 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Active At Sea</span>
                    <h3 class="h4 fw-bold mb-0 text-primary"><?= number_format($activeCount) ?> Vessels</h3>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-ship"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-hover border-0 shadow-sm bg-white">
            <div class="card-body p-3.5 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Total Landed Weight</span>
                    <h3 class="h4 fw-bold mb-0 text-success"><?= formatWeight($grandTotalWeight) ?></h3>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-weight-hanging"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-hover border-0 shadow-sm bg-white">
            <div class="card-body p-3.5 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Dispatched Crates</span>
                    <h3 class="h4 fw-bold mb-0 text-info"><?= number_format($grandTotalBoxes) ?> Boxes</h3>
                </div>
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Only: Total Operational Expenses Metric -->
    <?php if (isAdmin()): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-hover border-0 shadow-sm bg-white">
                <div class="card-body p-3.5 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Total Op Expenses</span>
                        <h3 class="h4 fw-bold mb-0 text-danger"><?= formatCurrency($grandTotalOpExpenses) ?></h3>
                    </div>
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-hover border-0 shadow-sm bg-white">
                <div class="card-body p-3.5 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Dispatched Trips</span>
                        <h3 class="h4 fw-bold mb-0 text-dark"><?= number_format($dispatchedCount) ?> Trips</h3>
                    </div>
                    <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Trips Management Table Card -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0 fw-bold text-dark">
            <i class="fa-solid fa-list-check me-2 text-primary"></i>Fishing Trips & Operations
        </h5>
        <span class="badge bg-light text-dark border"><?= count($trips) ?> Registered Trips</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($trips)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-anchor text-muted display-4 mb-3"></i>
                <h5 class="text-muted">No fishing trips registered yet.</h5>
                <p class="text-muted small">Click the button below to record your first multi-day fishing departure.</p>
                <a href="new_trip.php" class="btn btn-primary btn-sm rounded-pill px-3">
                    <i class="fa-solid fa-plus me-1"></i> Register New Departure
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Trip / Boat Info</th>
                            <th>Skipper & Crew</th>
                            <th>Departure Date</th>
                            <th>Status & Lock</th>
                            <th>Catch Packing</th>
                            <?php if (isAdmin()): ?>
                                <th>Total Operational Cost</th>
                            <?php endif; ?>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $t): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($t['boat_name']) ?></div>
                                    <span class="badge bg-secondary-subtle text-secondary border font-monospace small">
                                        <?= htmlspecialchars($t['reg_number']) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="fw-semibold text-dark small"><i class="fa-solid fa-user-tie text-muted me-1"></i><?= htmlspecialchars($t['skipper_name']) ?></div>
                                    <span class="text-muted small"><i class="fa-solid fa-users me-1"></i><?= intval($t['crew_count']) ?> Crew Members</span>
                                </td>

                                <td>
                                    <div class="small fw-semibold"><i class="fa-regular fa-calendar me-1 text-primary"></i><?= formatDate($t['departure_date']) ?></div>
                                    <?php if (!empty($t['arrival_date'])): ?>
                                        <div class="small text-muted"><i class="fa-solid fa-flag-checkered me-1 text-success"></i><?= formatDate($t['arrival_date']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= getStatusBadge($t['status']) ?>
                                    <?php if ($t['is_locked'] == 1): ?>
                                        <span class="badge bg-dark ms-1" title="Trip is locked against edits"><i class="fa-solid fa-lock"></i> Locked</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($t['total_boxes'] > 0): ?>
                                        <div class="fw-bold text-success small"><?= intval($t['total_boxes']) ?> Boxes</div>
                                        <span class="text-muted small"><?= formatWeight($t['total_weight_kg']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Pending Sorting</span>
                                    <?php endif; ?>
                                </td>

                                <?php if (isAdmin()): ?>
                                    <td>
                                        <div class="fw-bold text-danger small"><?= formatCurrency($t['total_op_cost']) ?></div>
                                        <a href="trip_summary.php?trip_id=<?= $t['id'] ?>" class="text-decoration-none small text-danger">
                                            <i class="fa-solid fa-calculator me-1"></i>View Summary
                                        </a>
                                    </td>
                                <?php endif; ?>

                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if (isAdmin()): ?>
                                            <a href="trip_expenses.php?trip_id=<?= $t['id'] ?>" class="btn btn-outline-secondary" title="Departure Expenses">
                                                <i class="fa-solid fa-gas-pump"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="catch_dispatch.php?trip_id=<?= $t['id'] ?>" class="btn btn-outline-primary" title="Catch Packing & Logistics">
                                            <i class="fa-solid fa-truck-ramp-box"></i>
                                        </a>
                                        <a href="trip_bills.php?trip_id=<?= $t['id'] ?>" class="btn btn-outline-warning text-dark" title="Market Bills & Vault">
                                            <i class="fa-solid fa-vault"></i>
                                        </a>

                                        <?php if ($t['dispatch_id'] > 0): ?>
                                            <a href="peliyagoda_waybill.php?dispatch_id=<?= $t['dispatch_id'] ?>" class="btn btn-outline-info" title="Peliyagoda Transport Waybill">
                                                <i class="fa-solid fa-truck-fast"></i>
                                            </a>
                                            <a href="buyer_sales_note.php?trip_id=<?= $t['id'] ?>" class="btn btn-outline-success" title="Buyer Sales Note">
                                                <i class="fa-solid fa-file-invoice-dollar"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (isAdmin()): ?>
                                            <a href="trip_summary.php?trip_id=<?= $t['id'] ?>" class="btn btn-outline-dark" title="Expense Summary & Lock">
                                                <i class="fa-solid fa-lock"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $t['id'] ?>" title="Delete Trip">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (isAdmin()): ?>
                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade text-start" id="deleteModal<?= $t['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $t['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title h6" id="deleteModalLabel<?= $t['id'] ?>">
                                                            <i class="fa-solid fa-triangle-exclamation me-2"></i>Confirm Delete Trip
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="mb-2">Are you sure you want to delete the trip record for <strong><?= htmlspecialchars($t['boat_name']) ?></strong> (<?= htmlspecialchars($t['reg_number']) ?>)?</p>
                                                        <p class="text-danger small mb-0"><i class="fa-solid fa-circle-exclamation me-1"></i>This will permanently remove all associated departure expenses, catch boxes, and dispatch notes.</p>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="POST" action="../actions/delete_trip.php" class="d-inline">
                                                            <input type="hidden" name="trip_id" value="<?= $t['id'] ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                <i class="fa-solid fa-trash-can me-1"></i> Permanently Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
