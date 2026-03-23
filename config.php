<?php
// DB credentials (use env vars when available)
define("DB_HOST", getenv("DB_HOST") ?: "");
define("DB_PORT", (int) (getenv("DB_PORT") ?: 3306));
define("DB_USER", getenv("DB_USER") ?: "contactuser");
define("DB_PASS", getenv("DB_PASS") ?: "password");
define("DB_NAME", getenv("DB_NAME") ?: "contactdb");

if (!extension_loaded("mysqli") || !function_exists("mysqli_connect")) {
    die("MySQLi extension is not enabled in PHP. Install/enable php-mysql and restart web server.");
}

mysqli_report(MYSQLI_REPORT_OFF);

$dbHosts = array();
if (DB_HOST !== "") {
    $dbHosts[] = DB_HOST;
}
$dbHosts = array_merge($dbHosts, array("mysql", "mariadb", "db", "127.0.0.1", "host.docker.internal"));
$dbHosts = array_values(array_unique($dbHosts));

$con = false;
$lastError = "";
foreach ($dbHosts as $host) {
    $con = @mysqli_connect($host, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($con) {
        break;
    }
    $lastError = mysqli_connect_error();
}

if (!$con) {
    exit("Database connection failed. Tried hosts: " . implode(", ", $dbHosts) . " on port " . DB_PORT . ". Error: " . $lastError);
}

mysqli_set_charset($con, "utf8");
