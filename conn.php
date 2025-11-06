<?php
// show errors during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// enable mysqli exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
  Use Hostinger production credentials by default.
  If you want to run locally with XAMPP, uncomment the local block below
  and comment the production block, or set env vars accordingly.
*/

// --- PRODUCTION (Hostinger) ---
$DB_HOST = 'localhost';
$DB_USER = 'u389936701_user';
$DB_PASS = 'CapstoneDefended1';
$DB_NAME = 'u389936701_capstone';

// --- LOCAL DEV (uncomment to use local DB) ---
//$DB_HOST = '127.0.0.1';
//$DB_USER = 'root';
//$DB_PASS = '';
//$DB_NAME = 'capstone';

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    // fail early with JSON if included by an AJAX endpoint
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json; charset=utf-8', true, 500);
        echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $e->getMessage()]);
        exit;
    }
    throw $e;
}
?>