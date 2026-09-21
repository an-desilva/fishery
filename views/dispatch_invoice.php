<?php
/**
 * Redirect Alias: Legacy dispatch_invoice.php now redirects to peliyagoda_waybill.php
 */
$dispatchId = intval($_GET['dispatch_id'] ?? 0);
$tripId     = intval($_GET['trip_id'] ?? 0);

if ($dispatchId > 0) {
    header("Location: peliyagoda_waybill.php?dispatch_id={$dispatchId}");
} elseif ($tripId > 0) {
    header("Location: peliyagoda_waybill.php?trip_id={$tripId}");
} else {
    header("Location: peliyagoda_waybill.php");
}
exit;
