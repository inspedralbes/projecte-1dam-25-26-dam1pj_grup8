<?php

// Logger d'accessos a MongoDB.
// S'executa a cada petició HTTP (inclòs des de header.php) i desa un document
// a la col·lecció `access_logs`.

require_once __DIR__ . '/mongo_connexio.php';

use MongoDB\BSON\UTCDateTime;

function logger_request_path(): string
{
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = (string)(parse_url($requestUri, PHP_URL_PATH) ?: '');
    return $path !== '' ? $path : $requestUri;
}

function logger_mysql_connect_safely(): ?mysqli
{
    // Don't ever hard-fail the request for logging.
    try {
        if (function_exists('load_dotenv_if_present')) {
            load_dotenv_if_present();
        }

        $isDocker = @file_exists('/.dockerenv');

        $servername = getenv('MYSQL_HOST') ?: ($isDocker ? 'db' : 'localhost');
        $username = getenv('MYSQL_USER') ?: ($isDocker ? 'usuari' : 'a25asipozdor_usuari_inc');
        $password = getenv('MYSQL_PASSWORD') ?: ($isDocker ? 'paraula_de_pas' : 'P@ssw0rd');
        $dbname = getenv('MYSQL_DATABASE') ?: ($isDocker ? 'persones' : 'a25asipozdor_incidencies');
        $port = (int)(getenv('MYSQL_PORT') ?: 3306);

        $servername = trim((string)$servername);
        $username = trim((string)$username);
        $password = (string)$password;
        $dbname = trim((string)$dbname);

        if ($servername === '' || $username === '' || $dbname === '') {
            return null;
        }

        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli($servername, $username, $password, $dbname, $port);
        if ($conn->connect_errno) {
            return null;
        }

        @$conn->set_charset('utf8mb4');
        return $conn;
    } catch (Throwable $e) {
        return null;
    }
}

function logger_ensure_access_logs_table(mysqli $conn): void
{
    // Minimal schema, compatible with existing access_logs_schema.php.
    $sql = "CREATE TABLE IF NOT EXISTS access_logs(
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100),
        page VARCHAR(150),
        access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @$conn->query($sql);
}

function log_request_to_mysql(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
    }

    $conn = logger_mysql_connect_safely();
    if (!$conn instanceof mysqli) {
        return false;
    }

    try {
        logger_ensure_access_logs_table($conn);

        $username = logger_authenticated_user();
        $page = logger_request_path();

        $stmt = $conn->prepare('INSERT INTO access_logs(username, page) VALUES (?, ?)');
        if ($stmt === false) {
            return false;
        }

        $u = $username !== null ? $username : null;
        $p = $page;

        // bind_param requires variables by reference
        $stmt->bind_param('ss', $u, $p);
        $ok = @$stmt->execute();
        $stmt->close();

        return $ok === true;
    } catch (Throwable $e) {
        return false;
    } finally {
        @$conn->close();
    }
}

function log_request_to_file(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
    }

    try {
        $baseDir = (string)(getenv('ACCESS_LOG_DIR') ?: '');
        $dir = $baseDir !== '' ? rtrim($baseDir, '/ ') : (__DIR__ . '/../storage');
        $file = $dir . '/access_logs.jsonl';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $payload = [
            'ts' => gmdate('c'),
            'url' => logger_request_url(),
            'path' => logger_request_path(),
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'user' => logger_authenticated_user(),
            'ip' => logger_client_ip(),
            'ua' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ];

        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        return @file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false;
    } catch (Throwable $e) {
        return false;
    }
}

function logger_client_ip(): ?string
{
    $xff = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($xff !== '') {
        $parts = array_map('trim', explode(',', $xff));
        if (count($parts) > 0 && $parts[0] !== '') {
            return $parts[0];
        }
    }

    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return $ip !== '' ? $ip : null;
}

function logger_request_url(): string
{
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');

    if ($host === '') {
        return $requestUri;
    }

    $https = (string)($_SERVER['HTTPS'] ?? '');
    $scheme = (!empty($https) && strtolower($https) !== 'off') ? 'https' : 'http';

    return $scheme . '://' . $host . $requestUri;
}

function logger_authenticated_user(): ?string
{
    // This project currently has role-based screens without a login system.
    // We try to read common session keys, else return null.
    if (PHP_SAPI === 'cli') {
        return null;
    }

    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }

    if (!isset($_SESSION) || !is_array($_SESSION)) {
        return null;
    }

    $candidates = [
        'username',
        'user',
        'email',
        'EMAIL',
    ];

    foreach ($candidates as $key) {
        if (isset($_SESSION[$key]) && is_string($_SESSION[$key])) {
            $val = trim($_SESSION[$key]);
            if ($val !== '') {
                return $val;
            }
        }
    }

    return null;
}

function log_request_to_mongodb(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
    }

    try {
        $db = mongo_db();
        $collection = $db->selectCollection('access_logs');

        $nowMs = (int) round(microtime(true) * 1000);

        $collection->insertOne([
            'url' => logger_request_url(),
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'user' => logger_authenticated_user(),
            'timestamp' => new UTCDateTime($nowMs),
            'browser' => [
                'userAgent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'acceptLanguage' => (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''),
            ],
            'ip' => logger_client_ip(),
        ]);
        return true;
    } catch (Throwable $e) {
        // Logging must never break the app.
        error_log('MongoDB logger error: ' . $e->getMessage());
        return false;
    }
}

// Try MongoDB first; fall back to MySQL or a local file so production deployments
// uploaded via FTP (FileZilla) still have working logs.
$mongoOk = log_request_to_mongodb();
if (!$mongoOk) {
    $mysqlOk = log_request_to_mysql();
    if (!$mysqlOk) {
        log_request_to_file();
    }
}
