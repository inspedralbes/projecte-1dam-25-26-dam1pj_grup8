<?php

// Connexió MongoDB (Atlas o qualsevol MongoDB accessible)
// Ús:
//   require_once __DIR__ . '/mongo_connexio.php';
//   $db = mongo_db();
//
// Variables d'entorn esperades (docker-compose.yaml):
//   - MONGODB_URI: mongodb+srv://.../<db> o mongodb://.../<db>
// Backward-compat:
//   - MONGO_URI + MONGO_DB

// Composer autoload (mongodb/mongodb). In development, this is created automatically
// by the Docker entrypoint. If it's missing, we avoid a fatal error so the app can
// still run (Mongo features will be unavailable).
$__mongo_autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($__mongo_autoload)) {
    require_once $__mongo_autoload;
}

use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Driver\ServerApi;

if (!function_exists('load_dotenv_if_present')) {
    function load_dotenv_if_present(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $candidates = [
            __DIR__ . '/../../.env',
            __DIR__ . '/../.env',
            __DIR__ . '/.env',
        ];

        $dotenvPath = null;
        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                $dotenvPath = $path;
                break;
            }
        }
        if ($dotenvPath === null) {
            return;
        }

        $lines = @file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if ($key === '') {
                continue;
            }

            $firstChar = substr($value, 0, 1);
            $lastChar = substr($value, -1);
            if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
                $value = substr($value, 1, -1);
            }

            // Don't override real environment variables.
            if (getenv($key) !== false) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function mongodb_uri(): string
{
    load_dotenv_if_present();

    $uri = trim((string)(getenv('MONGODB_URI') ?: ''));
    if ($uri !== '') {
        return $uri;
    }

    // Backward-compat
    $legacy = trim((string)(getenv('MONGO_URI') ?: ''));
    if ($legacy !== '') {
        return $legacy;
    }

    throw new RuntimeException('Falta la variable d\'entorn MONGODB_URI (revisa el fitxer .env i docker-compose.yaml)');
}

function mongodb_db_name_from_uri(string $uri): string
{
    // We expect the DB name as the first path segment: /<db>
    $parts = parse_url($uri);
    $path = is_array($parts) ? (string)($parts['path'] ?? '') : '';

    $dbName = trim($path, "/ ");
    if ($dbName !== '') {
        // If the path contains multiple segments, only take the first.
        $segments = explode('/', $dbName);
        $dbName = trim((string)($segments[0] ?? ''));
    }

    if ($dbName !== '') {
        return $dbName;
    }

    // Atlas URIs often omit the DB in the URI path; allow providing it separately.
    $envDb = trim((string)(getenv('MONGODB_DB') ?: getenv('MONGO_DB') ?: ''));
    if ($envDb !== '') {
        return $envDb;
    }

    throw new RuntimeException('Falta el nom de base de dades: afegeix /<db> a MONGODB_URI o defineix MONGODB_DB');
}

function mongo_client(): Client
{
    static $client = null;

    if ($client instanceof Client) {
        return $client;
    }

    if (!extension_loaded('mongodb')) {
        throw new RuntimeException(
            'Falta l\'extensió PHP "mongodb" (ext-mongodb). Activa-la/instal·la-la al servidor (php.ini / hosting panel).'
        );
    }

    if (!class_exists(Client::class)) {
        throw new RuntimeException(
            'Dependències de MongoDB no instal·lades (falta vendor/autoload.php). ' .
            'Si estàs en docker, reconstrueix/arrenca amb: docker compose up -d --build'
        );
    }

    $mongoUri = mongodb_uri();

    // Server API (recomanat per Atlas). Compatible amb SRV: mongodb+srv://...
    $serverApi = new ServerApi(ServerApi::V1);

    $client = new Client(
        $mongoUri,
        [],
        [
            'serverApi' => $serverApi,
        ]
    );

    return $client;
}

function mongo_db(): Database
{
    if (!class_exists(Database::class)) {
        throw new RuntimeException(
            'Dependències de MongoDB no instal·lades (falta vendor/autoload.php).'
        );
    }

    $dbName = mongodb_db_name_from_uri(mongodb_uri());

    return mongo_client()->selectDatabase($dbName);
}
