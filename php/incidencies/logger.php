<?php

// Logger d'accessos a MongoDB.
// S'executa a cada petició HTTP (inclòs des de header.php) i desa un document
// a la col·lecció `access_logs`.

require_once __DIR__ . '/mongo_connexio.php';

use MongoDB\BSON\UTCDateTime;

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

function log_request_to_mongodb(): void
{
    if (PHP_SAPI === 'cli') {
        return;
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
    } catch (Throwable $e) {
        // Logging must never break the app.
        error_log('MongoDB logger error: ' . $e->getMessage());
    }
}

log_request_to_mongodb();
