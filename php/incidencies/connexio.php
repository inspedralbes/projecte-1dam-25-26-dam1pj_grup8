<?php

// Tots els fitxers PHP que utilitzin la connexió a la base de dades han de
// incloure aquest fitxer al principi del codi PHP.
// Un cop inclòs, podreu utilitzar la variable $conn per a fer les consultes a la base de dades.
// require_once  'connexio.php';


// Configuració de la connexió a la base de dades.
// - Dins Docker Compose: el host és el servei `db` i la BBDD és `persones`.
// - Fora de Docker: manté els valors actuals.

$isDocker = file_exists('/.dockerenv');

$servername = 'db';
$username = 'usuari' ;
$password = 'paraula_de_pas' ;
$dbname = 'persones';

// Quan ja tingueu un codi una mica depurat, i vulgueu fer la gestió dels errors
// vosaltres mateixos heu de desactivar el comportament predeterminat de mysqli 
// que es molt agressiu i aborta el php en el moment de l'error, i per tant, 
//  no arriba a l'if de comprovació.
// Amb la següent línia, el codi en cas d'error de mysql ja no aboratarà i ho podreu
// gestionar vosaltres mateixos.
// mysqli_report(MYSQLI_REPORT_OFF);

// Crear la connexió
$conn = new mysqli($servername, $username, $password, $dbname);

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