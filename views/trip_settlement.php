<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

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
    $_SESSION['flash_message'] = 'Please select or register a fishing trip for financial settlement.';
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

// Fetch financial components for this trip
$expStmt = $pdo->prepare("SELECT * FROM trip_expenses WHERE trip_id = ?");
$expStmt->execute([$tripId]);
$expenses = $expStmt->fetch() ?: ['total_expenses' => 0];

$unloadingStmt = $pdo->prepare("SELECT * FROM harbour_unloadings WHERE trip_id = ?");
$unloadingStmt->execute([$tripId]);
$unloading = $unloadingStmt->fetch() ?: ['total_labour_fee' => 0];

$dispatchStmt = $pdo->prepare("SELECT * FROM dispatches WHERE trip_id = ? ORDER BY id DESC LIMIT 1");
$dispatchStmt->execute([$tripId]);
$dispatch = $dispatchStmt->fetch() ?: ['lorry_hire_fee' => 0, 'helper_fee' => 0];

// Fetch existing settlement record if saved
$settlementStmt = $pdo->prepare("SELECT * FROM trip_settlements WHERE trip_id = ?");
$settlementStmt->execute([$tripId]);
$settlement = $settlementStmt->fetch() ?: [];

// Fetch direct buyers total revenue for this trip
$directBuyersStmt = $pdo->prepare("SELECT SUM(total_amount) as direct_total, SUM(total_weight_kg) as direct_weight, COUNT(*) as buyer_count FROM direct_buyers WHERE trip_id = ?");
$directBuyersStmt->execute([$tripId]);
$directBuyerSummary = $directBuyersStmt->fetch() ?: ['direct_total' => 0, 'direct_weight' => 0, 'buyer_count' => 0];
$directSalesTotal = floatval($directBuyerSummary['direct_total'] ?? 0);
$directBuyerCount = intval($directBuyerSummary['buyer_count'] ?? 0);

// Default revenue fallback from existing settlement or auto-calculated with direct sales
$defaultRevenue = $directSalesTotal > 0 ? (2450000.00 + $directSalesTotal) : 2450000.00;
$grossRevenue   = floatval($settlement['gross_revenue'] ?? $defaultRevenue);
$marketDeductionsTotal = floatval($settlement['market_deductions_total'] ?? 142500.00);

$tripExpensesTotal   = floatval($expenses['total_expenses'] ?? 0);
$harbourLabourTotal  = floatval($unloading['total_labour_fee'] ?? 0);
$lorryHireTotal      = floatval($dispatch['lorry_hire_fee'] ?? 0);
$helperFeeTotal      = floatval($dispatch['helper_fee'] ?? 0);


$ownerPercent = floatval($settlement['owner_share_percent'] ?? DEFAULT_OWNER_PERCENT);
$crewPercent  = floatval($settlement['crew_share_percent'] ?? DEFAULT_CREW_PERCENT);
$crewCount    = max(1, intval($trip['crew_count'] ?? 1));

// Handle POST save settlement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settlement'])) {
    $grossRevenue          = max(0, floatval($_POST['gross_revenue'] ?? 0));
    $marketDeductionsTotal = max(0, floatval($_POST['market_deductions_total'] ?? 0));
    $ownerPercent          = max(0, min(100, floatval($_POST['owner_share_percent'] ?? 50)));
    $crewPercent           = 100.0 - $ownerPercent;
    $notes                 = trim($_POST['notes'] ?? '');
    $settleDate            = $_POST['settlement_date'] ?? date('Y-m-d');

    $totalDeductions = $tripExpensesTotal + $harbourLabourTotal + $lorryHireTotal + $helperFeeTotal + $marketDeductionsTotal;
    $netProfit = $grossRevenue - $totalDeductions;

    $ownerPayout = $netProfit > 0 ? ($netProfit * ($ownerPercent / 100.0)) : 0;
    $crewTotalPayout = $netProfit > 0 ? ($netProfit * ($crewPercent / 100.0)) : 0;
    $perCrewPayout = $crewTotalPayout / $crewCount;

    try {
        if ($settlement) {
            $uStmt = $pdo->prepare("UPDATE trip_settlements SET gross_revenue = ?, trip_expenses_total = ?, harbour_labour_total = ?, lorry_hire_total = ?, helper_fee_total = ?, market_deductions_total = ?, total_deductions = ?, net_profit = ?, owner_share_percent = ?, crew_share_percent = ?, owner_payout = ?, crew_total_payout = ?, crew_count = ?, per_crew_payout = ?, settlement_date = ?, notes = ? WHERE trip_id = ?");
            $uStmt->execute([$grossRevenue, $tripExpensesTotal, $harbourLabourTotal, $lorryHireTotal, $helperFeeTotal, $marketDeductionsTotal, $totalDeductions, $netProfit, $ownerPercent, $crewPercent, $ownerPayout, $crewTotalPayout, $crewCount, $perCrewPayout, $settleDate, $notes, $tripId]);
        } else {
            $iStmt = $pdo->prepare("INSERT INTO trip_settlements (trip_id, gross_revenue, trip_expenses_total, harbour_labour_total, lorry_hire_total, helper_fee_total, market_deductions_total, total_deductions, net_profit, owner_share_percent, crew_share_percent, owner_payout, crew_total_payout, crew_count, per_crew_payout, settlement_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $iStmt->execute([$tripId, $grossRevenue, $tripExpensesTotal, $harbourLabourTotal, $lorryHireTotal, $helperFeeTotal, $marketDeductionsTotal, $totalDeductions, $netProfit, $ownerPercent, $crewPercent, $ownerPayout, $crewTotalPayout, $crewCount, $perCrewPayout, $settleDate, $notes]);
        }

        // Update trip status to SETTLED
        $stStmt = $pdo->prepare("UPDATE trips SET status = 'SETTLED' WHERE id = ?");
        $stStmt->execute([$tripId]);

        $_SESSION['flash_message'] = "Financial settlement finalized for boat '{$trip['boat_name']}'. Net Profit: " . formatCurrency($netProfit);
        $_SESSION['flash_type'] = 'success';
        header("Location: trip_settlement.php?trip_id={$tripId}");
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Settlement Save Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
}

// Calculate current totals
$totalDeductions = $tripExpensesTotal + $harbourLabourTotal + $lorryHireTotal + $helperFeeTotal + $marketDeductionsTotal;
$netProfit = $grossRevenue - $totalDeductions;

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header & Actions -->
<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <div>
        <h2 class="h3 fw-bold mb-1">
            <i class="fa-solid fa-scale-balanced text-primary me-2"></i>Trip Profit & Share Settlement Statement
        </h2>
        <p class="text-muted small mb-0">Consolidated financial statement, expense deductions breakdown, and owner vs crew share calculation.</p>
    </div>
    <div class="d-flex items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
        <button onclick="window.print()" class="btn btn-dark btn-sm px-3 shadow-sm print-keep">
            <i class="fa-solid fa-print me-1"></i> Print Statement
        </button>
    </div>
</div>

<!-- Vessel Banner Card -->
<div class="card border-0 bg-primary bg-opacity-10 mb-4 printable-invoice">
    <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($trip['boat_name']) ?> <span class="badge bg-secondary font-monospace small"><?= htmlspecialchars($trip['reg_number']) ?></span></h4>
            <div class="text-muted small mt-1">
                Skipper: <strong><?= htmlspecialchars($trip['skipper_name']) ?></strong> | 
                Departure: <strong><?= formatDate($trip['departure_date']) ?></strong> | 
                Crew Members: <strong><?= $crewCount ?> Members</strong>
            </div>
        </div>
        <div>
            <?= getStatusBadge($trip['status']) ?>
        </div>
    </div>
</div>

<form method="POST" action="">
    <input type="hidden" name="save_settlement" value="1">

    <div class="row g-4">
        <!-- Itemized Deductions & Profit Balance Sheet -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm printable-invoice">
                <div class="card-header bg-dark text-white py-3">
                    <div class="fw-bold"><i class="fa-solid fa-receipt me-2"></i>Financial Profit & Loss Statement</div>
                </div>
                <div class="card-body p-4">
                    
                    <!-- Gross Revenue Entry Field -->
                    <div class="p-3 bg-success bg-opacity-10 rounded border border-success mb-4">
                        <label for="gross_revenue" class="form-label text-success fw-bold small text-uppercase d-flex justify-content-between align-items-center flex-wrap gap-1">
                            <span>1. Gross Catch Sales Revenue <span class="text-danger">*</span></span>
                            <?php if ($directSalesTotal > 0): ?>
                                <span class="badge bg-success text-white"><i class="fa-solid fa-users me-1"></i> Direct Buyers: <?= formatCurrency($directSalesTotal) ?> (<?= $directBuyerCount ?> Buyers)</span>
                            <?php endif; ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-success text-white fw-bold"><?= CURRENCY_SYMBOL ?></span>
                            <input type="number" step="0.01" min="0" class="form-control form-control-lg fw-bold text-success calc-trigger" id="gross_revenue" name="gross_revenue" required value="<?= htmlspecialchars($grossRevenue) ?>">
                        </div>
                        <div class="small text-muted mt-1">Total combined gross revenue from lorry market auction sales + direct harbor buyers.</div>
                    </div>


                    <!-- Itemized Cost Deductions List -->
                    <h6 class="fw-bold text-uppercase text-muted small mb-3"><i class="fa-solid fa-minus-circle me-1 text-danger"></i>2. Operational Expenses & Deductions</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Expense Category</th>
                                    <th class="text-end" style="width: 200px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><i class="fa-solid fa-gas-pump text-warning me-2"></i>Boat Departure Provisions (Diesel, Ice, Ration, Gas)</td>
                                    <td class="text-end font-monospace"><?= formatCurrency($tripExpensesTotal) ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-users-gear text-info me-2"></i>Harbour Unloading Labour Fees</td>
                                    <td class="text-end font-monospace"><?= formatCurrency($harbourLabourTotal) ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-truck text-primary me-2"></i>Lorry Transport Hire Fee</td>
                                    <td class="text-end font-monospace"><?= formatCurrency($lorryHireTotal) ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-user-ninja text-secondary me-2"></i>Lorry Helper Fee (ගෝලයා)</td>
                                    <td class="text-end font-monospace"><?= formatCurrency($helperFeeTotal) ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-shop text-danger me-2"></i>Market Commission & Wholesale Unloading Fees</td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                            <input type="number" step="0.01" min="0" class="form-control text-end font-monospace calc-trigger" id="market_deductions_total" name="market_deductions_total" value="<?= htmlspecialchars($marketDeductionsTotal) ?>">
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td class="text-uppercase">Total Consolidated Deductions:</td>
                                    <td class="text-end text-danger font-monospace fs-6" id="totalDeductionsDisplay"><?= formatCurrency($totalDeductions) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Final Net Profit Summary -->
                    <div class="p-3 bg-dark text-white rounded-3 d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <span class="text-slate-300 small fw-bold text-uppercase d-block">Final Net Profit Balance</span>
                            <span class="small text-slate-400">Gross Revenue minus Total Expenses & Deductions</span>
                        </div>
                        <div class="h3 fw-extrabold text-white mb-0" id="netProfitDisplay">
                            <?= formatCurrency($netProfit) ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Owner & Crew Payout Distribution Controls -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm printable-invoice">
                <div class="card-header bg-primary text-white py-3">
                    <div class="fw-bold"><i class="fa-solid fa-users me-2"></i>Owner & Crew Profit Share Payout</div>
                </div>
                <div class="card-body p-4">
                    
                    <!-- Percentage Split Controls -->
                    <div class="mb-4 p-3 bg-light rounded border no-print">
                        <label for="owner_share_percent" class="form-label fw-bold small text-muted d-flex justify-content-between">
                            <span>Boat Owner Share %</span>
                            <span id="ownerPctDisplay" class="fw-bold text-primary"><?= number_format($ownerPercent, 0) ?>%</span>
                        </label>
                        <input type="range" class="form-range calc-trigger" id="owner_share_percent" name="owner_share_percent" min="0" max="100" step="5" value="<?= htmlspecialchars($ownerPercent) ?>">
                        
                        <div class="d-flex justify-content-between small text-muted mt-1">
                            <span>Crew Share %: <strong id="crewPctDisplay" class="text-success"><?= number_format(100 - $ownerPercent, 0) ?>%</strong></span>
                            <span>Default: 50 / 50 Split</span>
                        </div>
                    </div>

                    <!-- Calculated Payout Cards -->
                    <div class="row g-3 mb-4">
                        <!-- Owner Payout -->
                        <div class="col-12">
                            <div class="p-3 border rounded bg-primary bg-opacity-10 border-primary">
                                <span class="text-primary small fw-bold text-uppercase d-block">Boat Owner Payout Share (<?= number_format($ownerPercent, 0) ?>%)</span>
                                <div class="h4 fw-extrabold text-primary mb-0" id="ownerPayoutDisplay">
                                    <?= formatCurrency($netProfit > 0 ? ($netProfit * ($ownerPercent / 100.0)) : 0) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Crew Total Payout -->
                        <div class="col-12">
                            <div class="p-3 border rounded bg-success bg-opacity-10 border-success">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="text-success small fw-bold text-uppercase d-block">Total Crew Payout Pool (<?= number_format(100 - $ownerPercent, 0) ?>%)</span>
                                        <span class="text-muted small">Divided among <?= $crewCount ?> registered crew members</span>
                                    </div>
                                    <div class="h4 fw-extrabold text-success mb-0" id="crewTotalPayoutDisplay">
                                        <?= formatCurrency($netProfit > 0 ? ($netProfit * ((100 - $ownerPercent) / 100.0)) : 0) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Individual Per-Crew Payout -->
                        <div class="col-12">
                            <div class="p-3 border rounded bg-light">
                                <span class="text-dark small fw-bold text-uppercase d-block"><i class="fa-solid fa-user text-success me-1"></i> Per Crew Member Payout</span>
                                <div class="h4 fw-bold text-dark mb-0" id="perCrewPayoutDisplay">
                                    <?= formatCurrency($netProfit > 0 ? (($netProfit * ((100 - $ownerPercent) / 100.0)) / $crewCount) : 0) ?>
                                </div>
                                <?php if (!empty($trip['crew_members'])): ?>
                                    <div class="small text-muted mt-2 border-top pt-2">
                                        <strong>Roster:</strong> <?= htmlspecialchars($trip['crew_members']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Settlement Date & Notes -->
                    <div class="mb-3 no-print">
                        <label for="settlement_date" class="form-label fw-bold small text-muted">Settlement Date</label>
                        <input type="date" class="form-control" id="settlement_date" name="settlement_date" required value="<?= htmlspecialchars($settlement['settlement_date'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="mb-4 no-print">
                        <label for="settlement_notes" class="form-label fw-bold small text-muted">Settlement Notes</label>
                        <textarea class="form-control" id="settlement_notes" name="notes" rows="2" placeholder="e.g. Cash payments disbursed at harbour office."><?= htmlspecialchars($settlement['notes'] ?? '') ?></textarea>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary w-100 font-semibold py-2 shadow-sm no-print">
                        <i class="fa-solid fa-circle-check me-1"></i> Finalize & Lock Settlement
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const grossInput = document.getElementById('gross_revenue');
        const marketDedInput = document.getElementById('market_deductions_total');
        const slider = document.getElementById('owner_share_percent');

        const totalDeductionsElem = document.getElementById('totalDeductionsDisplay');
        const netProfitElem = document.getElementById('netProfitDisplay');

        const ownerPctDisplay = document.getElementById('ownerPctDisplay');
        const crewPctDisplay  = document.getElementById('crewPctDisplay');
        const ownerPayoutDisplay = document.getElementById('ownerPayoutDisplay');
        const crewTotalPayoutDisplay = document.getElementById('crewTotalPayoutDisplay');
        const perCrewPayoutDisplay = document.getElementById('perCrewPayoutDisplay');

        const fixedExpenses = <?= floatval($tripExpensesTotal + $harbourLabourTotal + $lorryHireTotal + $helperFeeTotal) ?>;
        const crewCount = <?= intval($crewCount) ?>;

        function updatePayouts() {
            const gross = parseFloat(grossInput ? grossInput.value : 0) || 0;
            const marketDed = parseFloat(marketDedInput ? marketDedInput.value : 0) || 0;
            const totalDed = fixedExpenses + marketDed;
            const netProfit = gross - totalDed;

            const ownerPct = parseFloat(slider.value) || 50;
            const crewPct = 100 - ownerPct;

            const fmt = (val) => '<?= CURRENCY_SYMBOL ?> ' + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            if (totalDeductionsElem) totalDeductionsElem.textContent = fmt(totalDed);
            if (netProfitElem) netProfitElem.textContent = fmt(netProfit);

            if (ownerPctDisplay) ownerPctDisplay.textContent = ownerPct + '%';
            if (crewPctDisplay) crewPctDisplay.textContent = crewPct + '%';

            let ownerPayout = 0;
            let crewTotal = 0;
            let perCrew = 0;

            if (netProfit > 0) {
                ownerPayout = netProfit * (ownerPct / 100.0);
                crewTotal = netProfit * (crewPct / 100.0);
                perCrew = crewTotal / crewCount;
            }

            if (ownerPayoutDisplay) ownerPayoutDisplay.textContent = fmt(ownerPayout);
            if (crewTotalPayoutDisplay) crewTotalPayoutDisplay.textContent = fmt(crewTotal);
            if (perCrewPayoutDisplay) perCrewPayoutDisplay.textContent = fmt(perCrew);
        }

        document.querySelectorAll('.calc-trigger').forEach(function(el) {
            el.addEventListener('input', updatePayouts);
        });

        updatePayouts();
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
