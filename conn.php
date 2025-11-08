<?php
// show errors during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// enable mysqli exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
  Loading strategy (priority):
  1) conn.local.php (if present) — create this locally with your local DB creds (NOT committed)
  2) environment variables: DB_HOST, DB_USER, DB_PASS, DB_NAME
  3) auto-detect localhost -> use the provided local XAMPP creds
  4) fallback to existing production Hostinger credentials
*/

$localConfig = __DIR__ . '/conn.local.php';
if (file_exists($localConfig)) {
    // conn.local.php should define $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME
    include $localConfig;
} else {
    // env vars (CI / container)
    $envHost = getenv('DB_HOST');
    if ($envHost !== false && $envHost !== '') {
        $DB_HOST = $envHost;
        $DB_USER = getenv('DB_USER') ?: '';
        $DB_PASS = getenv('DB_PASS') ?: '';
        $DB_NAME = getenv('DB_NAME') ?: '';
    } else {
        // detect if accessed via localhost (web) or running on CLI dev environment
        $isLocalHost = (PHP_SAPI === 'cli')
            || (isset($_SERVER['HTTP_HOST']) && (stripos($_SERVER['HTTP_HOST'], 'localhost') !== false || $_SERVER['HTTP_HOST'] === '127.0.0.1'))
            || (isset($_SERVER['SERVER_NAME']) && (stripos($_SERVER['SERVER_NAME'], 'localhost') !== false || $_SERVER['SERVER_NAME'] === '127.0.0.1'));

        if ($isLocalHost) {
            // Local XAMPP credentials (as requested)
            $DB_HOST = '127.0.0.1';
            $DB_USER = 'root';
            $DB_PASS = '';
            $DB_NAME = 'capstone';
        } else {
            // PRODUCTION (Hostinger) fallback
            $DB_HOST = 'localhost';
            $DB_USER = 'u389936701_user';
            $DB_PASS = 'CapstoneDefended1';
            $DB_NAME = 'u389936701_capstone';
        }
    }
}

// determine if we're running on a local dev machine
$isLocalHost = (PHP_SAPI === 'cli')
    || (isset($_SERVER['HTTP_HOST']) && (stripos($_SERVER['HTTP_HOST'], 'localhost') !== false || $_SERVER['HTTP_HOST'] === '127.0.0.1'))
    || (isset($_SERVER['SERVER_NAME']) && (stripos($_SERVER['SERVER_NAME'], 'localhost') !== false || $_SERVER['SERVER_NAME'] === '127.0.0.1'));

// attempt connection. suppress the mysqli warning with @ and handle exceptions so we can retry on localhost
$triedLocalFallback = false;
try {
    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME); // @ suppresses PHP warning
    if ($conn->connect_errno) {
        throw new Exception('Connect error: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    // If we're on localhost, try the local XAMPP fallback credentials once
    if ($isLocalHost && !$triedLocalFallback) {
        $triedLocalFallback = true;
        $DB_HOST = '127.0.0.1';
        $DB_USER = 'root';
        $DB_PASS = '';
        $DB_NAME = 'capstone';
        try {
            $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
            if ($conn->connect_errno) {
                throw new Exception('Connect error: ' . $conn->connect_error);
            }
            $conn->set_charset('utf8mb4');
        } catch (Exception $e2) {
            if (php_sapi_name() !== 'cli') {
                header('Content-Type: application/json; charset=utf-8', true, 500);
                echo json_encode(['success' => false, 'message' => 'DB connection failed (primary and local fallback): ' . $e2->getMessage()]);
                exit;
            }
            throw $e2;
        }
    } else {
        if (php_sapi_name() !== 'cli') {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $e->getMessage()]);
            exit;
        }
        throw $e;
    }
}
?>