<?php
/*
 * Data Logger remote configuration.
 * Render reads DB_* values from its Environment settings.
 * A local .env file is supported for development but must never be committed.
 */

date_default_timezone_set('Asia/Kolkata');

/* Load a local .env file if present; existing process environment wins. */
$envFile = __DIR__ . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
        $envLine = trim($envLine);
        if ($envLine === '' || str_starts_with($envLine, '#') || !str_contains($envLine, '=')) {
            continue;
        }
        [$envKey, $envValue] = explode('=', $envLine, 2);
        $envKey = trim($envKey);
        $envValue = trim($envValue);
        if ($envValue !== '' && strlen($envValue) >= 2) {
            $first = $envValue[0];
            $last = $envValue[strlen($envValue) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $envValue = substr($envValue, 1, -1);
            }
        }
        if ($envKey !== '' && getenv($envKey) === false) {
            putenv($envKey . '=' . $envValue);
            $_ENV[$envKey] = $envValue;
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax'
    ]);
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function dataLoggerEnv(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value === false ? $default : trim((string)$value);
}

$servername = dataLoggerEnv('DB_HOST', 'localhost');
$dbUsername = dataLoggerEnv('DB_USER', 'root');
$dbPassword = dataLoggerEnv('DB_PASSWORD', '');
$dbname = dataLoggerEnv('DB_NAME', 'espdemo2');
$dbport = (int)dataLoggerEnv('DB_PORT', '3306');
$dbSslCa = dataLoggerEnv('DB_SSL_CA', '/etc/ssl/certs/ca-certificates.crt');

try {
    if ($servername === '' || $dbUsername === '' || $dbname === '' || $dbport < 1 || $dbport > 65535) {
        throw new RuntimeException('One or more required database settings are missing or invalid.');
    }

    $conn = mysqli_init();
    if (!$conn) {
        throw new RuntimeException('Unable to initialize MySQL connection.');
    }
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 15);

    $isRemoteDatabase = !in_array(strtolower($servername), ['localhost', '127.0.0.1', '::1'], true);

    if ($isRemoteDatabase) {
        // TiDB Cloud requires TLS. Use the operating system CA bundle (or DB_SSL_CA).
        if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
            $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
        }
        $caFile = ($dbSslCa !== '' && is_readable($dbSslCa)) ? $dbSslCa : null;
        $conn->ssl_set(null, null, $caFile, null, null);
        $conn->real_connect(
            $servername,
            $dbUsername,
            $dbPassword,
            $dbname,
            $dbport,
            null,
            MYSQLI_CLIENT_SSL
        );
    } else {
        $conn->real_connect($servername, $dbUsername, $dbPassword, $dbname, $dbport);
    }

    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('Data Logger database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please check the Render Environment settings and TiDB Cloud connection permissions.');
}

/* Safe HTML output helper used by the website pages. */
if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
