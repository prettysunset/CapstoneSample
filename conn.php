<?php
// show errors during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// DB config — update values if different
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'capstone';

// enable mysqli exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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