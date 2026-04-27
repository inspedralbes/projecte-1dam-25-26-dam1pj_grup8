<?php

//Sempre volem tenir una connexió a la base de dades, així que la creem al principi del fitxer
require_once 'connexio.php';
// Un cop inclòs el fitxer connexio.php, ja podeu utilitzar la variable $conn per a fer les consultes a la base de dades.

/**
 * Funció que llegeix els paràmetres del formulari i crea una nova casa a la base de dades.
 * @param mixed $conn
 * @return void
 */
function crear_casa($conn)
{
    // Obtenir el nom de la casa del formulari
    $nom = $_POST['nom'];

    // Comprovar si el nom no està buit
    // Si l'html està ben escrit això no podria passar en els usuaris normals
    // Igualment SEMPRE s'ha de comprovar tot al backend ja que no tots els usuaris
    // són "bones persones" i des de les web tools es pot canviar tot el front per exemple.
    if (empty($nom)) {
        echo "<p class='error'>El nom de la casa no pot estar buit.</p>";
        return;
    }

    // Preparar la consulta SQL per inserir una nova casa
    $sql = "INSERT INTO cases (name) VALUES (?)";
    $stmt = $conn->prepare($sql);  //La variable $conn la tenim per haver inclòs el fitxer connexio.php
    $stmt->bind_param("s", $nom);

    // Executar la consulta i comprovar si s'ha inserit correctament
    if ($stmt->execute()) {
        echo "<p class='info'>Casa creada amb èxit!</p>";
    } else {
        echo "<p class='error'>Error al crear la casa: " . htmlspecialchars($stmt->error) . "</p>";
    }

    // Tancar la declaració i la connexió
    $stmt->close();

}


?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear</title>
</head>

<body>
    <h1>Crear una casa</h1>
    <?php

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Si el formulari s'ha enviatc (mètode POST), cridem a la funció per crear la casa
        crear_casa($conn);
    } else {
        //Mostrem el formulari per crear una nova casa
        //Tanquem el php per poder escriure el codi HTML de forma més còmoda.
        ?>
        <form method="POST" action="crear.php">
            <fieldset>
                <legend>CASA</legend>
                <label for="nom">Nom de la casa:</label>
                <input type="text" id="nom" name="nom">
                <input type="submit" value="Crear">
            </fieldset>
        </form>


        <?php
        //Tanquem l'else
    }
    ?>
    <div id="menu">
        <hr>
        <p><a href="index.php">Portada</a> </p>
        <p><a href="llistar.php">Llistar</a></p>
        <p><a href="crear.php">Crear</a></p>
    </div>
</body>

</html>

