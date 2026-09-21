<?php
/**
 * Action Controller: Upload Market Final Bill (Bill Vault)
 * Multi-Day Fishing Boat Catch, Sorting & Dispatch Logistics Management System
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

// Authenticate session
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/index.php');
    exit;
}

$tripId    = intval($_POST['trip_id'] ?? 0);
$billTitle = trim($_POST['bill_title'] ?? '');
$notes     = trim($_POST['notes'] ?? '');

// Validate trip ID
if ($tripId <= 0) {
    $_SESSION['flash_message'] = 'Invalid Fishing Trip ID specified.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ../views/index.php');
    exit;
}

// Validate bill title
if (empty($billTitle)) {
    $_SESSION['flash_message'] = 'Bill Title is required.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../views/trip_bills.php?trip_id={$tripId}");
    exit;
}

try {
    $pdo = getDbConnection();

    // Verify trip existence
    $tripStmt = $pdo->prepare("SELECT id, boat_name, reg_number, is_locked FROM trips WHERE id = ?");
    $tripStmt->execute([$tripId]);
    $trip = $tripStmt->fetch();

    if (!$trip) {
        $_SESSION['flash_message'] = 'Target Fishing Trip record not found.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ../views/index.php');
        exit;
    }

    // Check if file was provided
    if (!isset($_FILES['bill_file']) || $_FILES['bill_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMap = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server maximum upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the specified size limit.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was selected for upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
        ];
        $errCode = $_FILES['bill_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $_SESSION['flash_message'] = $errorMap[$errCode] ?? 'File upload error occurred.';
        $_SESSION['flash_type'] = 'error';
        header("Location: ../views/trip_bills.php?trip_id={$tripId}");
        exit;
    }

    $fileTmpPath = $_FILES['bill_file']['tmp_name'];
    $fileOriginalName = $_FILES['bill_file']['name'];
    $fileSize = $_FILES['bill_file']['size'];

    // Enforce 8MB max file size (8 * 1024 * 1024 = 8,388,608 bytes)
    $maxBytes = 8 * 1024 * 1024;
    if ($fileSize > $maxBytes) {
        $_SESSION['flash_message'] = 'File size exceeds maximum limit of 8MB.';
        $_SESSION['flash_type'] = 'error';
        header("Location: ../views/trip_bills.php?trip_id={$tripId}");
        exit;
    }

    // Validate Extension
    $rawExtension = strtolower(pathinfo($fileOriginalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($rawExtension, $allowedExtensions, true)) {
        $_SESSION['flash_message'] = 'Invalid file type. Only JPG, JPEG, PNG, WEBP, and PDF documents are allowed.';
        $_SESSION['flash_type'] = 'error';
        header("Location: ../views/trip_bills.php?trip_id={$tripId}");
        exit;
    }

    // Validate MIME type safely
    $allowedMimes = [
        'image/jpeg'      => 'JPG',
        'image/pjpeg'     => 'JPG',
        'image/png'       => 'PNG',
        'image/webp'      => 'WEBP',
        'application/pdf' => 'PDF'
    ];

    $detectedMime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $fileTmpPath);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $detectedMime = mime_content_type($fileTmpPath);
    }

    if ($detectedMime && !array_key_exists($detectedMime, $allowedMimes)) {
        $_SESSION['flash_message'] = "Security Warning: Unsupported file format detected ({$detectedMime}).";
        $_SESSION['flash_type'] = 'error';
        header("Location: ../views/trip_bills.php?trip_id={$tripId}");
        exit;
    }

    // Determine standard file type label
    $fileType = strtoupper($rawExtension);
    if ($fileType === 'JPEG') {
        $fileType = 'JPG';
    }

    // Generate unique cryptographically safe filename
    $dateStamp = date('Ymd_His');
    $randomHex = bin2hex(random_bytes(4)); // 8 hex characters
    $safeExtension = ($rawExtension === 'jpeg') ? 'jpg' : $rawExtension;
    $newFilename = "bill_trip_{$tripId}_{$dateStamp}_{$randomHex}.{$safeExtension}";

    // Ensure uploads directory exists
    $uploadDir = __DIR__ . '/../uploads/bills/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new Exception("Failed to create safe upload directory 'uploads/bills/'.");
        }
    }

    $destinationPath = $uploadDir . $newFilename;
    $relativeFilePath = "uploads/bills/" . $newFilename;

    // Move file securely
    if (!move_uploaded_file($fileTmpPath, $destinationPath)) {
        throw new Exception("Failed to save uploaded file to destination storage.");
    }

    $fileSizeKb = (int)ceil($fileSize / 1024);
    $currentUser = getCurrentUser();
    $userId = $currentUser['id'] ?? null;

    // Save metadata into database
    $insertStmt = $pdo->prepare("
        INSERT INTO trip_bills (trip_id, bill_title, file_path, file_type, file_size_kb, uploaded_by, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([
        $tripId,
        $billTitle,
        $relativeFilePath,
        $fileType,
        $fileSizeKb,
        $userId,
        $notes
    ]);

    $_SESSION['flash_message'] = "Market bill '{$billTitle}' successfully uploaded and secured in Bill Vault.";
    $_SESSION['flash_type'] = 'success';
    header("Location: ../views/trip_bills.php?trip_id={$tripId}");
    exit;

} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Upload Failed: ' . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header("Location: ../views/trip_bills.php?trip_id={$tripId}");
    exit;
}
