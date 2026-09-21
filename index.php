<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: views/index.php');
} else {
    header('Location: views/login.php');
}
exit;
