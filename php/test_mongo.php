<?php
/**
 * Script de diagnóstico de MongoDB (pensado para CLI).
 *
 * Comprueba:
 * - Que la extensión PHP `mongodb` (ext-mongodb) está cargada.
 * - Que existe `MONGODB_URI`.
 * - Conexión + ping.
 * - (Opcional) escritura/lectura en una colección de test.
 */

require_once __DIR__ . '/incidencies/mongo_connexio.php';

echo "MongoDB PHP extension loaded: " . (extension_loaded('mongodb') ? "yes" : "no") . "\n";

echo "MONGODB_URI set: " . (getenv('MONGODB_URI') ? "yes" : "no") . "\n";

echo "Connecting...\n";

try {
    $db = mongo_db();

    // Ping (no requereix permisos especials habitualment)
    $ping = $db->command(['ping' => 1])->toArray();

    echo "Connected OK. Ping result:\n";
    echo json_encode($ping[0] ?? $ping, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

    // Optional small write/read test (requires write permissions)
    $collection = $db->selectCollection('connection_test');
    $insert = $collection->insertOne([
        'createdAt' => new MongoDB\BSON\UTCDateTime(),
        'message' => 'hello from PHP',
    ]);

    $doc = $collection->findOne(['_id' => $insert->getInsertedId()]);

    echo "\nWrite/read OK. Inserted document:\n";
    echo json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
