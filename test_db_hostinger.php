<?php
// Simple Hostinger DB connection test.
// Edit the $pass value below with your Hostinger DB password before running.
$host = "auth-db2090.hstgr.io";   // observed Hostinger host
$user = "u389936701_user";       // DB user
$pass = "CapstoneDefended1";  // <-- replace with actual DB password
$db   = "u389936701_capstone";   // DB name
//$port = 3306;

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    echo "Connection failed: " . htmlspecialchars($mysqli->connect_error) . PHP_EOL;
    exit(1);
}
echo "CONNECTED SA HOSTINGER DB 🎉\n";
echo "MySQL server info: " . ($mysqli->server_info ?? '(unknown)') . PHP_EOL;
$mysqli->close();

?>