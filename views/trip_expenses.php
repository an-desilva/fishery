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
    $_SESSION['flash_message'] = 'Please select or register a fishing trip to enter expenses.';
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

// Check lock status
if (!empty($trip['is_locked']) && $trip['is_locked'] == 1) {
    $_SESSION['flash_message'] = 'This trip is completed and locked against modification.';
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

// Fetch existing trip expenses
$expStmt = $pdo->prepare("SELECT * FROM trip_expenses WHERE trip_id = ?");
$expStmt->execute([$tripId]);
$expenses = $expStmt->fetch() ?: [];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <!-- Page Title & Navigation -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1">
                    <i class="fa-solid fa-gas-pump text-warning me-2"></i>Trip Departure Expenses (Master Admin)
                </h2>
                <p class="text-muted small mb-0">Record pre-departure provisions: Diesel fuel, ice blocks, food ration, gas, bait, and boat maintenance.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>

        <!-- Trip Summary Header Card -->
        <div class="card border-0 bg-primary bg-opacity-10 mb-4">
            <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary text-white">
                        <i class="fa-solid fa-ship"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($trip['boat_name']) ?> <span class="badge bg-secondary font-monospace small"><?= htmlspecialchars($trip['reg_number']) ?></span></h5>
                        <span class="text-muted small">Skipper: <strong><?= htmlspecialchars($trip['skipper_name']) ?></strong> | Departure: <strong><?= formatDate($trip['departure_date']) ?></strong></span>
                    </div>
                </div>
                <div>
                    <?= getStatusBadge($trip['status']) ?>
                </div>
            </div>
        </div>

        <!-- Expenses Form Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-dark py-3">
                <div class="fw-bold"><i class="fa-solid fa-receipt me-2"></i>Itemized Departure Expense Form</div>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="../actions/save_expense.php">
                    <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">

                    <div class="row g-3">
                        <!-- Diesel Fuel Cost -->
                        <div class="col-md-6">
                            <label for="diesel_cost" class="form-label fw-bold small text-muted">Diesel Fuel Expense (ඩීසල්) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                <input type="number" step="0.01" min="0" class="form-control expense-calc" id="diesel_cost" name="diesel_cost" required value="<?= htmlspecialchars($expenses['diesel_cost'] ?? '0.00') ?>">
                            </div>
                        </div>

                        <!-- Ice Cost -->
                        <div class="col-md-6">
                            <label for="ice_cost" class="form-label fw-bold small text-muted">Ice Blocks Expense (අයිස්) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                <input type="number" step="0.01" min="0" class="form-control expense-calc" id="ice_cost" name="ice_cost" required value="<?= htmlspecialchars($expenses['ice_cost'] ?? '0.00') ?>">
                            </div>
                        </div>

                        <!-- Ration / Food Cost -->
                        <div class="col-md-6">
                            <label for="ration_cost" class="form-label fw-bold small text-muted">Food Rations & Provisions (කෑම/රේෂන්) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                <input type="number" step="0.01" min="0" class="form-control expense-calc" id="ration_cost" name="ration_cost" required value="<?= htmlspecialchars($expenses['ration_cost'] ?? '0.00') ?>">
                            </div>
                        </div>

                        <!-- LP Gas Cost -->
                        <div class="col-md-6">
                            <label for="gas_cost" class="form-label fw-bold small text-muted">Cooking Gas (ගෑස්) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                <input type="number" step="0.01" min="0" class="form-control expense-calc" id="gas_cost" name="gas_cost" required value="<?= htmlspecialchars($expenses['gas_cost'] ?? '0.00') ?>">
                            </div>
                        </div>

                        <!-- Bait Cost -->
                        <div class="col-md-6">
                            <label for="bait_cost" class="form-label fw-bold small text-muted">Bait Cost (ඇම)</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                <input type="number" step="0.01" min="0" class="form-control expense-calc" id="bait_cost" name="bait_cost" value="<?= htmlspecialchars($expenses['bait_cost'] ?? '0.00') ?>">
                            </div>
                        </div>

                        <!-- Engine Maintenance Cost -->
                        <div class="col-md-6">
                            <label for="maintenance_cost" class="form-label fw-bold small text-muted">Pre-Trip Maintenance & Repairs</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                <input type="number" step="0.01" min="0" class="form-control expense-calc" id="maintenance_cost" name="maintenance_cost" value="<?= htmlspecialchars($expenses['maintenance_cost'] ?? '0.00') ?>">
                            </div>
                        </div>

                        <!-- Other Expenses -->
                        <div class="col-12">
                            <label for="other_cost" class="form-label fw-bold small text-muted">Miscellaneous Departure Cost</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                <input type="number" step="0.01" min="0" class="form-control expense-calc" id="other_cost" name="other_cost" value="<?= htmlspecialchars($expenses['other_cost'] ?? '0.00') ?>">
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="col-12">
                            <label for="notes" class="form-label fw-bold small text-muted">Departure Expense Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="e.g. Purchased 3,500L diesel at Dikowita pier, 120 ice blocks."><?= htmlspecialchars($expenses['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Real-Time Total Expenses Summary Box -->
                    <div class="p-3 bg-light rounded-3 border mt-4 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-bold uppercase">Total Departure Expenses Subtotal</span>
                            <div class="h3 fw-extrabold text-dark mb-0" id="totalExpenseDisplay">
                                <?= formatCurrency($expenses['total_expenses'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="text-muted small text-end">
                            <i class="fa-solid fa-calculator me-1"></i> Auto-calculated in real-time
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Submit Buttons -->
                    <div class="d-flex align-items-center justify-content-between">
                        <a href="new_trip.php?trip_id=<?= $trip['id'] ?>" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-arrow-left me-1"></i> Edit Trip Registration
                        </a>
                        <button type="submit" class="btn btn-warning text-dark font-semibold px-4 shadow-sm">
                            <i class="fa-solid fa-arrow-right me-1"></i> Save & Continue to Catch Packing
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.expense-calc');
        const display = document.getElementById('totalExpenseDisplay');

        function updateExpenseTotal() {
            let total = 0;
            inputs.forEach(function(input) {
                total += parseFloat(input.value) || 0;
            });
            display.textContent = '<?= CURRENCY_SYMBOL ?> ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        inputs.forEach(function(input) {
            input.addEventListener('input', updateExpenseTotal);
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
