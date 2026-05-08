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

function mongodb_uri(): string
{
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

    // Backward-compat
    $legacyDb = trim((string)(getenv('MONGO_DB') ?: ''));
    if ($legacyDb !== '') {
        return $legacyDb;
    }

    throw new RuntimeException('Falta el nom de base de dades a MONGODB_URI (afegeix /<db> al final)');
}

function mongo_client(): Client
{
    static $client = null;

    if ($client instanceof Client) {
        return $client;
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
