<?php
/**
 * View: Market Final Bill Upload & Safe Storage (Bill Vault)
 * Multi-Day Fishing Boat Catch, Sorting & Dispatch Logistics Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$currentUser = getCurrentUser();
$pdo = getDbConnection();

// Fetch all trips for selection dropdown
try {
    $tripsStmt = $pdo->query("SELECT id, boat_name, reg_number, status, departure_date, arrival_date, is_locked FROM trips ORDER BY id DESC");
    $allTrips = $tripsStmt->fetchAll();
} catch (Exception $e) {
    $allTrips = [];
}

// Selected trip handling
$selectedTripId = intval($_GET['trip_id'] ?? 0);
if ($selectedTripId <= 0 && !empty($allTrips)) {
    $selectedTripId = $allTrips[0]['id'];
}

$currentTrip = null;
$bills = [];
$totalSizeKb = 0;

if ($selectedTripId > 0) {
    try {
        // Fetch current trip
        $tripStmt = $pdo->prepare("SELECT * FROM trips WHERE id = ?");
        $tripStmt->execute([$selectedTripId]);
        $currentTrip = $tripStmt->fetch();

        if ($currentTrip) {
            // Fetch bills for this trip with uploader info
            $billsStmt = $pdo->prepare("
                SELECT tb.*, u.username as uploader_username 
                FROM trip_bills tb 
                LEFT JOIN users u ON tb.uploaded_by = u.id 
                WHERE tb.trip_id = ? 
                ORDER BY tb.bill_id DESC
            ");
            $billsStmt->execute([$selectedTripId]);
            $bills = $billsStmt->fetchAll();

            foreach ($bills as $b) {
                $totalSizeKb += intval($b['file_size_kb']);
            }
        }
    } catch (Exception $e) {
        $bills = [];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Title & Header -->
<div class="row align-items-center mb-4 gy-3">
    <div class="col-md-7">
        <h2 class="h3 fw-extrabold text-dark mb-1 d-flex align-items-center gap-2">
            <span class="bg-primary text-white rounded-3 px-2.5 py-1.5 shadow-sm fs-5">
                <i class="fa-solid fa-vault"></i>
            </span>
            Market Final Bill Vault
        </h2>
        <p class="text-muted mb-0 small">
            Mobile-friendly secure digital storage for fish market sales receipts, harbor clearance bills, & dispatch invoices.
        </p>
    </div>
    <div class="col-md-5 text-md-end">
        <div class="d-flex justify-content-md-end align-items-center gap-2">
            <?php if ($currentTrip): ?>
                <a href="catch_dispatch.php?trip_id=<?= $currentTrip['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fa-solid fa-truck-ramp-box me-1"></i> Catch Dispatch
                </a>
                <?php if (isAdmin()): ?>
                    <a href="trip_summary.php?trip_id=<?= $currentTrip['id'] ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                        <i class="fa-solid fa-calculator me-1"></i> Trip Summary
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Trip Selector Toolbar -->
<div class="card border-0 shadow-sm mb-4 bg-white">
    <div class="card-body p-3">
        <form method="GET" action="trip_bills.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-auto fw-bold text-secondary">
                <i class="fa-solid fa-filter me-1 text-primary"></i> Select Vessel Trip:
            </div>
            <div class="col-12 col-md-6 col-lg-5">
                <select name="trip_id" class="form-select font-semibold" onchange="this.form.submit()">
                    <?php if (empty($allTrips)): ?>
                        <option value="">No Fishing Trips Found</option>
                    <?php else: ?>
                        <?php foreach ($allTrips as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $t['id'] == $selectedTripId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['boat_name']) ?> (<?= htmlspecialchars($t['reg_number']) ?>) &mdash; <?= formatDate($t['departure_date']) ?> [<?= $t['status'] ?>]
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto ms-auto d-flex align-items-center gap-3">
                <div class="text-end">
                    <span class="badge bg-primary-subtle text-primary border px-3 py-2 rounded-pill fw-semibold">
                        <i class="fa-solid fa-file-invoice me-1"></i> Total Bills: <?= count($bills) ?>
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary border px-3 py-2 rounded-pill fw-semibold ms-1">
                        <i class="fa-solid fa-hard-drive me-1"></i> Storage: <?= $totalSizeKb > 1024 ? number_format($totalSizeKb / 1024, 2) . ' MB' : number_format($totalSizeKb) . ' KB' ?>
                    </span>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!$currentTrip): ?>
    <div class="alert alert-warning shadow-sm border-0 d-flex align-items-center p-4">
        <i class="fa-solid fa-circle-exclamation fa-2x me-3 text-warning"></i>
        <div>
            <h5 class="alert-heading mb-1">No Fishing Trip Selected</h5>
            <p class="mb-0">Please register a new departure or select an existing trip from the dropdown above to manage bill uploads.</p>
        </div>
    </div>
<?php else: ?>

    <div class="row g-4">
        <!-- Left Column: Upload New Bill Form Card -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="card-title h6 mb-0 fw-bold d-flex align-items-center">
                        <i class="fa-solid fa-camera me-2 text-primary"></i>Upload & Scan Market Bill
                    </h5>
                </div>
                <div class="card-body p-3.5">
                    <?php if ($currentTrip['is_locked'] == 1 && !isAdmin()): ?>
                        <div class="alert alert-dark small mb-0">
                            <i class="fa-solid fa-lock me-1 text-warning"></i> This trip is locked. Only Master Admins can attach new bills.
                        </div>
                    <?php else: ?>
                        <form action="../actions/upload_bill.php" method="POST" enctype="multipart/form-data" class="needs-validation">
                            <input type="hidden" name="trip_id" value="<?= $currentTrip['id'] ?>">

                            <div class="mb-3">
                                <label for="bill_title" class="form-label fw-semibold text-secondary small">Bill / Receipt Title <span class="text-danger">*</span></label>
                                <input type="text" id="bill_title" name="bill_title" class="form-control" placeholder="e.g. Market Sales Settlement Bill #402" required maxlength="150">
                            </div>

                            <div class="mb-3">
                                <label for="bill_file" class="form-label fw-semibold text-secondary small">
                                    Capture or Select Document <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-primary">
                                        <i class="fa-solid fa-camera-retro"></i>
                                    </span>
                                    <!-- Mobile Direct Camera Capture input -->
                                    <input type="file" id="bill_file" name="bill_file" class="form-control" accept="image/*,application/pdf" capture="environment" required>
                                </div>
                                <div class="form-text text-muted extra-small mt-1.5">
                                    <i class="fa-solid fa-mobile-screen-button me-1 text-success"></i> <strong>Mobile Ready:</strong> Tap input to capture physical receipt via camera. Allowed: JPG, PNG, WEBP, PDF (Max 8MB).
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label fw-semibold text-secondary small">Additional Notes / Reference</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Optional notes regarding market buyer, deduction details, or receipt breakdown..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                                <i class="fa-solid fa-cloud-arrow-up fs-5"></i> Secure Upload to Bill Vault
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Bill Archive Gallery Grid -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm bg-white min-vh-50">
                <div class="card-header bg-light border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h5 class="card-title h6 mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-images text-primary me-2"></i>Archived Market Receipts & Waybills
                        <span class="badge bg-secondary rounded-pill ms-2"><?= count($bills) ?> Documents</span>
                    </h5>
                    <span class="small text-muted">
                        Vessel: <strong><?= htmlspecialchars($currentTrip['boat_name']) ?></strong>
                    </span>
                </div>
                <div class="card-body p-3.5">
                    <?php if (empty($bills)): ?>
                        <div class="text-center py-5">
                            <div class="stat-icon bg-light text-muted mx-auto mb-3" style="width:70px; height:70px; font-size: 28px; line-height: 70px; border-radius: 50%;">
                                <i class="fa-solid fa-folder-open"></i>
                            </div>
                            <h6 class="fw-bold text-secondary">No Market Bills Attached Yet</h6>
                            <p class="text-muted small max-w-md mx-auto">Use the camera capture form on the left to snap a photo of market final settlement bills or upload PDF receipts for this trip.</p>
                        </div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 g-3">
                            <?php foreach ($bills as $b): ?>
                                <?php
                                    $isImage = in_array(strtoupper($b['file_type']), ['JPG', 'JPEG', 'PNG', 'WEBP']);
                                    $isPdf = (strtoupper($b['file_type']) === 'PDF');
                                    $filePath = '../' . htmlspecialchars($b['file_path']);
                                ?>
                                <div class="col">
                                    <div class="card h-100 border shadow-sm card-hover bg-white overflow-hidden">
                                        <!-- Card Thumbnail / Preview Header -->
                                        <div class="position-relative bg-dark d-flex align-items-center justify-content-center" style="height: 180px; background-color: #1a202c !important;">
                                            <?php if ($isImage): ?>
                                                <img src="<?= $filePath ?>" alt="<?= htmlspecialchars($b['bill_title']) ?>" class="w-100 h-100" style="object-fit: cover; opacity: 0.9;" data-bs-toggle="modal" data-bs-target="#viewModal<?= $b['bill_id'] ?>" role="button">
                                                <div class="position-absolute top-0 end-0 m-2">
                                                    <span class="badge bg-dark bg-opacity-75 text-white border border-secondary px-2 py-1">
                                                        <i class="fa-solid fa-image me-1 text-info"></i><?= $b['file_type'] ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center py-4 px-3" data-bs-toggle="modal" data-bs-target="#viewModal<?= $b['bill_id'] ?>" role="button">
                                                    <i class="fa-solid fa-file-pdf text-danger fa-4x mb-2 shadow-sm"></i>
                                                    <div class="text-light small fw-bold text-truncate px-2" style="max-width: 220px;">
                                                        <?= htmlspecialchars(basename($b['file_path'])) ?>
                                                    </div>
                                                </div>
                                                <div class="position-absolute top-0 end-0 m-2">
                                                    <span class="badge bg-danger text-white border border-danger-subtle px-2 py-1">
                                                        <i class="fa-solid fa-file-pdf me-1"></i>PDF Document
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Quick Click to Zoom Overlay -->
                                            <button type="button" class="btn btn-sm btn-light rounded-circle position-absolute bottom-0 end-0 m-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#viewModal<?= $b['bill_id'] ?>" title="Full Preview / Zoom">
                                                <i class="fa-solid fa-magnifying-glass-plus text-dark"></i>
                                            </button>
                                        </div>

                                        <!-- Card Content -->
                                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                                            <div>
                                                <h6 class="fw-bold text-dark mb-1.5 text-truncate" title="<?= htmlspecialchars($b['bill_title']) ?>">
                                                    <?= htmlspecialchars($b['bill_title']) ?>
                                                </h6>
                                                
                                                <div class="extra-small text-muted mb-2 d-flex flex-column gap-1">
                                                    <div>
                                                        <i class="fa-regular fa-clock me-1 text-primary"></i> <?= date('d M Y, h:i A', strtotime($b['uploaded_at'])) ?>
                                                    </div>
                                                    <div>
                                                        <i class="fa-solid fa-user me-1 text-secondary"></i> Uploaded by: <strong><?= htmlspecialchars($b['uploader_username'] ?? 'Staff') ?></strong>
                                                    </div>
                                                    <div>
                                                        <i class="fa-solid fa-hard-drive me-1 text-info"></i> File Size: <strong><?= intval($b['file_size_kb']) ?> KB</strong>
                                                    </div>
                                                </div>

                                                <?php if (!empty($b['notes'])): ?>
                                                    <div class="p-2 rounded bg-light border extra-small text-secondary mb-3 text-break">
                                                        <i class="fa-solid fa-quote-left me-1 text-muted"></i><?= htmlspecialchars($b['notes']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Card Action Footer -->
                                            <div class="pt-2 border-top d-flex align-items-center justify-content-between gap-2">
                                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill flex-grow-1" data-bs-toggle="modal" data-bs-target="#viewModal<?= $b['bill_id'] ?>">
                                                    <i class="fa-solid fa-eye me-1"></i> Preview
                                                </button>

                                                <a href="<?= $filePath ?>" download="<?= htmlspecialchars($b['bill_title'] . '.' . strtolower($b['file_type'])) ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Download Document">
                                                    <i class="fa-solid fa-download"></i>
                                                </a>

                                                <?php if (isAdmin() || $currentTrip['is_locked'] == 0): ?>
                                                    <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-2.5" data-bs-toggle="modal" data-bs-target="#deleteBillModal<?= $b['bill_id'] ?>" title="Delete Bill">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- View / Zoom Modal for Bill -->
                                <div class="modal fade" id="viewModal<?= $b['bill_id'] ?>" tabindex="-1" aria-labelledby="viewModalLabel<?= $b['bill_id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <div class="modal-header bg-dark text-white py-2.5">
                                                <h5 class="modal-title h6 fw-bold mb-0 d-flex align-items-center gap-2" id="viewModalLabel<?= $b['bill_id'] ?>">
                                                    <i class="fa-solid fa-vault text-primary"></i> <?= htmlspecialchars($b['bill_title']) ?>
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-0 text-center bg-black d-flex align-items-center justify-content-center" style="min-height: 400px;">
                                                <?php if ($isImage): ?>
                                                    <img src="<?= $filePath ?>" alt="<?= htmlspecialchars($b['bill_title']) ?>" class="img-fluid p-2" style="max-height: 75vh; object-fit: contain;">
                                                <?php elseif ($isPdf): ?>
                                                    <iframe src="<?= $filePath ?>" class="w-100 border-0" style="height: 70vh;"></iframe>
                                                <?php else: ?>
                                                    <div class="text-white p-4">
                                                        <i class="fa-solid fa-file fa-3x mb-3 text-secondary"></i>
                                                        <p class="mb-2">Document format cannot be previewed directly.</p>
                                                        <a href="<?= $filePath ?>" download class="btn btn-primary btn-sm">
                                                            <i class="fa-solid fa-download me-1"></i> Download File
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer bg-light py-2 d-flex justify-content-between align-items-center">
                                                <div class="small text-muted">
                                                    <?= $b['file_type'] ?> &bull; <?= intval($b['file_size_kb']) ?> KB &bull; <?= date('Y-m-d H:i', strtotime($b['uploaded_at'])) ?>
                                                </div>
                                                <div>
                                                    <a href="<?= $filePath ?>" target="_blank" class="btn btn-outline-dark btn-sm rounded-pill me-2">
                                                        <i class="fa-solid fa-up-right-from-square me-1"></i> Open in New Tab
                                                    </a>
                                                    <a href="<?= $filePath ?>" download="<?= htmlspecialchars($b['bill_title'] . '.' . strtolower($b['file_type'])) ?>" class="btn btn-primary btn-sm rounded-pill">
                                                        <i class="fa-solid fa-download me-1"></i> Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Bill Modal -->
                                <?php if (isAdmin() || $currentTrip['is_locked'] == 0): ?>
                                    <div class="modal fade text-start" id="deleteBillModal<?= $b['bill_id'] ?>" tabindex="-1" aria-labelledby="deleteBillLabel<?= $b['bill_id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title h6 mb-0" id="deleteBillLabel<?= $b['bill_id'] ?>">
                                                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Confirm Delete Bill
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="mb-2">Are you sure you want to permanently delete the document <strong>"<?= htmlspecialchars($b['bill_title']) ?>"</strong>?</p>
                                                    <p class="text-danger small mb-0"><i class="fa-solid fa-circle-exclamation me-1"></i>This will remove the file from safe storage storage disk.</p>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <form action="../actions/delete_bill.php" method="POST">
                                                        <input type="hidden" name="bill_id" value="<?= $b['bill_id'] ?>">
                                                        <input type="hidden" name="trip_id" value="<?= $currentTrip['id'] ?>">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="fa-solid fa-trash-can me-1"></i> Delete Bill Permanently
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
