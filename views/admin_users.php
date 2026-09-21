<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

// Master Admin Access Guard
requireAdmin();

$pdo = getDbConnection();
$stmt = $pdo->query("SELECT * FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1">
            <i class="fa-solid fa-users-gear text-purple me-2"></i>User Management & RBAC Control Panel
        </h2>
        <p class="text-muted small mb-0">Master Admin control module to register new Staff/Admin accounts, reset passwords, and toggle active status.</p>
    </div>
    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fa-solid fa-user-plus me-1"></i> Add New User Account
    </button>
</div>

<!-- Users List Card -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-dark text-white py-3 d-flex align-items-center justify-content-between">
        <div class="fw-bold"><i class="fa-solid fa-user-shield me-2"></i>System User Accounts</div>
        <span class="badge bg-secondary font-monospace"><?= count($users) ?> Total Accounts</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Username</th>
                        <th>Email Address</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="ps-3 font-monospace fw-bold">#<?= $u['id'] ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= getRoleBadge($u['role']) ?></td>
                            <td>
                                <?php if ($u['is_active'] == 1): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fa-solid fa-ban me-1"></i>Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= formatDate($u['created_at']) ?></td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm" role="group">
                                    <!-- Toggle Active/Disabled Form -->
                                    <form method="POST" action="../actions/admin_user_action.php" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-outline-<?= $u['is_active'] == 1 ? 'warning' : 'success' ?>" title="<?= $u['is_active'] == 1 ? 'Disable Account' : 'Activate Account' ?>">
                                            <i class="fa-solid <?= $u['is_active'] == 1 ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                        </button>
                                    </form>

                                    <!-- Reset Password Modal Trigger -->
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetPassModal<?= $u['id'] ?>" title="Reset Password">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                </div>

                                <!-- Reset Password Modal -->
                                <div class="modal fade text-start" id="resetPassModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-dark text-white">
                                                <h5 class="modal-title h6"><i class="fa-solid fa-key me-2"></i>Reset Password for '<?= htmlspecialchars($u['username']) ?>'</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST" action="../actions/admin_user_action.php">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label for="new_password<?= $u['id'] ?>" class="form-label fw-bold small text-muted">New Password <span class="text-danger">*</span></label>
                                                        <input type="password" class="form-control" id="new_password<?= $u['id'] ?>" name="new_password" required placeholder="••••••••">
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        <i class="fa-solid fa-floppy-disk me-1"></i> Update Password
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add New User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title h6" id="addUserModalLabel"><i class="fa-solid fa-user-plus me-2"></i>Create New Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="../actions/admin_user_action.php">
                <input type="hidden" name="action" value="create_user">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="username" class="form-label fw-bold small text-muted">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" required placeholder="e.g. jsmith">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-bold small text-muted">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="e.g. staff@sealogix.com">
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label fw-bold small text-muted">Account Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="Staff">Staff (Harbour Data Entry & Catch Packing)</option>
                            <option value="Admin">Admin (Full Control, User Management & Expense Summaries)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label fw-bold small text-muted">Initial Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-user-check me-1"></i> Register Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
