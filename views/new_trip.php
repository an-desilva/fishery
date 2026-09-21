<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$tripId = intval($_GET['trip_id'] ?? 0);
$trip = null;

if ($tripId > 0) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM trips WHERE id = ?");
        $stmt->execute([$tripId]);
        $trip = $stmt->fetch();
    } catch (Exception $e) {
        $trip = null;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Page Title & Navigation -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1">
                    <i class="fa-solid fa-anchor text-primary me-2"></i><?= $trip ? 'Edit Trip Registration' : 'Register New Fishing Departure' ?>
                </h2>
                <p class="text-muted small mb-0">Record boat identification, skipper info, crew roster, and trip departure schedule.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <!-- Form Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white py-3">
                <div class="fw-bold"><i class="fa-solid fa-ship me-2"></i>Vessel & Skipper Information</div>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="../actions/save_trip.php">
                    <input type="hidden" name="trip_id" value="<?= $trip ? $trip['id'] : 0 ?>">

                    <div class="row g-3">
                        <!-- Boat Name -->
                        <div class="col-md-6">
                            <label for="boat_name" class="form-label fw-bold small text-muted">Boat / Vessel Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-ship text-primary"></i></span>
                                <input type="text" class="form-control" id="boat_name" name="boat_name" required placeholder="e.g. Ocean Queen" value="<?= htmlspecialchars($trip['boat_name'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Registration Number -->
                        <div class="col-md-6">
                            <label for="reg_number" class="form-label fw-bold small text-muted">Fisheries Registration Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-id-card text-secondary"></i></span>
                                <input type="text" class="form-control" id="reg_number" name="reg_number" required placeholder="e.g. IMUL-A-0482-TRINCO" value="<?= htmlspecialchars($trip['reg_number'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Skipper / Captain Name -->
                        <div class="col-md-6">
                            <label for="skipper_name" class="form-label fw-bold small text-muted">Skipper / Captain Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-user-tie text-info"></i></span>
                                <input type="text" class="form-control" id="skipper_name" name="skipper_name" required placeholder="e.g. Sunil Perera" value="<?= htmlspecialchars($trip['skipper_name'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Crew Count -->
                        <div class="col-md-6">
                            <label for="crew_count" class="form-label fw-bold small text-muted">Total Crew Members <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-users text-success"></i></span>
                                <input type="number" class="form-control" id="crew_count" name="crew_count" min="1" max="25" required placeholder="5" value="<?= htmlspecialchars($trip['crew_count'] ?? 5) ?>">
                            </div>
                        </div>

                        <!-- Crew Members Roster -->
                        <div class="col-12">
                            <label for="crew_members" class="form-label fw-bold small text-muted">Crew Roster Names (Comma Separated)</label>
                            <textarea class="form-control" id="crew_members" name="crew_members" rows="2" placeholder="e.g. K. Silva, M. Fernando, P. Kumara, S. Bandara, R. Gamage"><?= htmlspecialchars($trip['crew_members'] ?? '') ?></textarea>
                        </div>

                        <!-- Departure Date -->
                        <div class="col-md-6">
                            <label for="departure_date" class="form-label fw-bold small text-muted">Departure Date <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-regular fa-calendar-days text-primary"></i></span>
                                <input type="date" class="form-control" id="departure_date" name="departure_date" required value="<?= htmlspecialchars($trip['departure_date'] ?? date('Y-m-d')) ?>">
                            </div>
                        </div>

                        <!-- Return / Arrival Date -->
                        <div class="col-md-6">
                            <label for="arrival_date" class="form-label fw-bold small text-muted">Return / Harbour Landing Date</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-flag-checkered text-success"></i></span>
                                <input type="date" class="form-control" id="arrival_date" name="arrival_date" value="<?= htmlspecialchars($trip['arrival_date'] ?? '') ?>">
                            </div>
                            <div class="form-text small">Leave blank if the boat is currently at sea.</div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Submit Buttons -->
                    <div class="d-flex align-items-center justify-content-end gap-2">
                        <a href="index.php" class="btn btn-light border px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm" <?= isset($trip['is_locked']) && $trip['is_locked'] == 1 ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-arrow-right me-1"></i> Save Trip Registration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
