
<?php
// Common configuration for the Data Logger project.

date_default_timezone_set('Asia/Kolkata');

// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Enable MySQL error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Local XAMPP database settings
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "espdemo2";

// Connect to the database
try {
    $conn = new mysqli(
        $servername,
        $username,
        $password,
        $dbname
    );

    $conn->set_charset("utf8mb4");

} catch (Throwable $e) {
    http_response_code(500);
    exit("Database connection failed. Please check XAMPP and config.php.");
}

// Safe HTML output helper
function h($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
