<?php

// Tots els fitxers PHP que utilitzin la connexió a la base de dades han de
// incloure aquest fitxer al principi del codi PHP.
// Un cop inclòs, podreu utilitzar la variable $conn per a fer les consultes a la base de dades.
// require_once  'connexio.php';


// Configuració de la connexió a la base de dades.
// - Dins Docker Compose: el host és el servei `db` i la BBDD és `persones`.
// - Fora de Docker: manté els valors actuals.

// Evitar warning en entorns amb `open_basedir` restringit
$isDocker = @file_exists('/.dockerenv');

// Optional .env loader for shared-hosting deployments.
// Docker Compose already loads .env automatically; in Apache/Hestia you may not have env vars.
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

load_dotenv_if_present();

// Permetre configurar via variables d'entorn (recomanat en Docker)
$servername = getenv('MYSQL_HOST') ?: ($isDocker ? 'db' : 'localhost');
$username = getenv('MYSQL_USER') ?: ($isDocker ? 'usuari' : 'a25asipozdor_usuari_inc');
$password = getenv('MYSQL_PASSWORD') ?: ($isDocker ? 'paraula_de_pas' : 'P@ssw0rd');
$dbname = getenv('MYSQL_DATABASE') ?: ($isDocker ? 'persones' : 'a25asipozdor_incidencies');
$port = (int) (getenv('MYSQL_PORT') ?: 3306);

// Quan ja tingueu un codi una mica depurat, i vulgueu fer la gestió dels errors
// vosaltres mateixos heu de desactivar el comportament predeterminat de mysqli 
// que es molt agressiu i aborta el php en el moment de l'error, i per tant, 
//  no arriba a l'if de comprovació.
// Amb la següent línia, el codi en cas d'error de mysql ja no aboratarà i ho podreu
// gestionar vosaltres mateixos.
// mysqli_report(MYSQLI_REPORT_OFF);

// Evitar que mysqli llanci excepcions i aborti el procés; gestionarem l'error manualment
mysqli_report(MYSQLI_REPORT_OFF);

// Eliminem espais accidentals en les credencials per evitar noms amb prefixos d'espai
$servername = trim($servername);
$username = trim($username);
$password = trim($password);
$dbname = trim($dbname);

// Crear la connexió
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Comprovar la connexió
if ($conn->connect_error) {
    echo "<p>Error de connexió: " . htmlspecialchars($conn->connect_error) . "</p>";
    die("Error de connexió: " . $conn->connect_error);
}

// A partir d'aquí, ja podeu fer les consultes a la base de dades a partir de la variable $conn

// L'estàndar de codificació de PHP PSR-12 indica que els fitxers que només contenen codi PHP
// NO han de tenir tancat el tag PHP de tancament "interrogant-major que".
// https://www.php-fig.org/psr/psr-12/#22-files
// Això es fa per evitar que s'afegeixin espais en blanc al final del fitxer que podrien provocar
// l'enviament de capçaleres HTTP abans d'hora.