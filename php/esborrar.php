<?php

//Sempre volem tenir una connexió a la base de dades, així que la creem al principi del fitxer
require_once 'connexio.php';
// Un cop inclòs el fitxer connexio.php, ja podeu utilitzar la variable $conn per a fer les consultes a la base de dades.

?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Llistat</title>
</head>

<body>
    <h1>Esborrar</h1>
    <!-- Tots els esborrats han de tenir conformació
 Per tant, primer hem de mostrar LA CASA, i aleshores tornar preguntar
 si realment la vol esborrar
 El primer cop rebem l'id de la casa via GET, i el segon cop via POST
 Això és una (de les moltes formes) de fer la doble confirmació
-->

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Si el formulari s'ha enviat (mètode POST), procedim a esborrar la casa 
        $id = $_POST['id'];
        // Comprovar si l'ID és un número vàlid
        if (is_numeric($id)) {
            // Preparar la consulta SQL per esborrar la casa
            $sql = "DELETE FROM cases WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);

            // Executar la consulta i comprovar si s'ha esborrat correctament
            if ($stmt->execute()) {
                echo "<p class='info'>Casa esborrada amb èxit!</p>";
            } else {
                echo "<p class='error'>Error al esborrar la casa: " . htmlspecialchars($stmt->error) . "</p>";
            }

            // Tancar la declaració
            $stmt->close();
        } else {
            echo "<p class='error'>ID no vàlid.</p>";
        }
    } elseif (isset($_GET['id'])) {
        // Comprovar si s'ha rebut  l'ID de la casa via GET (a la URL esborrar.php?id=XXX)
        $id = $_GET['id'];
        // Comprovar si l'ID és un número vàlid
        if (is_numeric($id)) {
            // Preparar la consulta SQL per obtenir la casa a esborrar
            $sql = "SELECT id, name FROM cases WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();

            // Comprovar si s'ha trobat la casa
            if ($result->num_rows > 0) {
                // Mostrar la casa a esborrar
                $row = $result->fetch_assoc();

                // Mostrar el formulari, que s'enviarà per POST, per confirmar l'esborrat
                echo "<form method='POST' action='esborrar.php'>";
                echo "<fieldset><legend>Casa a esborrar:</legend>" . htmlspecialchars($row["name"]) . "";

                echo "<br>";
                echo "<input type='hidden' name='id' value='" . htmlspecialchars($row["id"]) . "'>";
                echo "<input type='submit' value='Sí, esborrar'>";
                echo "</fieldset>";
                echo "</form>";
            } else {
                echo "<p class='error'>No s'ha trobat la casa amb ID: " . htmlspecialchars($id) . "</p>";
            }
        } else {
            echo "<p class='error'>ID no vàlid.</p>";
        }
    } else {
        echo "<p class='error'>No s'ha especificat cap ID.</p>";
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