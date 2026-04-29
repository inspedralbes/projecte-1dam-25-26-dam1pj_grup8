<?php

function mostrar_incidencies(mysqli $conn): void
{
    if (!function_exists('taula_existeix')) {
        // taula_existeix is defined in crear_incidencia.php; if not available,
        // implement a minimal local version.
        function taula_existeix(mysqli $conn, string $taula): bool
        {
            $taula_escapada = $conn->real_escape_string($taula);
            $result = $conn->query("SHOW TABLES LIKE '$taula_escapada'");
            if ($result === false) {
                return false;
            }
            $existeix = ($result->num_rows > 0);
            $result->free();
            return $existeix;
        }
    }

// columna_existeix similar a la que hay en otros archivos
if (!function_exists('columna_existeix')) {
    function columna_existeix(mysqli $conn, string $taula, string $columna): bool
    {
        $taula_escapada = $conn->real_escape_string($taula);
        $columna_escapada = $conn->real_escape_string($columna);
        $result = $conn->query("SHOW COLUMNS FROM `$taula_escapada` LIKE '$columna_escapada'");
        if ($result === false) {
            return false;
        }

        $existeix = ($result->num_rows > 0);
        $result->free();
        return $existeix;
    }
}

    if (!taula_existeix($conn, 'incidencies')) {
        $create_sql = "CREATE TABLE IF NOT EXISTS incidencies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            departament VARCHAR(80) NOT NULL,
            descripcio_curta VARCHAR(255) NOT NULL,
            data_incidencia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        if ($conn->query($create_sql) === false) {
            echo "<div class='alert alert-warning' role='alert'>No s'ha pogut assegurar la taula incidencies: " . htmlspecialchars($conn->error) . "</div>";
            return;
        }
    }

    // Afegir noves columnes si falten
    $adds = [];
    if (!columna_existeix($conn, 'incidencies', 'Incidencia_asignada')) {
        $adds[] = "ADD COLUMN Incidencia_asignada TINYINT(1) NOT NULL DEFAULT 0";
    }
    if (!columna_existeix($conn, 'incidencies', 'tecnic_assignat')) {
        $adds[] = "ADD COLUMN tecnic_assignat VARCHAR(100) DEFAULT NULL";
    }
    if (!empty($adds)) {
        $alter = 'ALTER TABLE incidencies ' . implode(', ', $adds);
        if ($conn->query($alter) === false) {
            echo "<div class='alert alert-warning' role='alert'>No s'han pogut afegir columnes a incidencies: " . htmlspecialchars($conn->error) . "</div>";
            // continue; no return — we can still try to show existing rows
        }
    }

    $sql = 'SELECT id, departament, descripcio_curta, data_incidencia FROM incidencies ORDER BY data_incidencia DESC';
    $res = $conn->query($sql);
    if ($res === false) {
        echo "<div class='alert alert-danger' role='alert'>Error recuperant incidències: " . htmlspecialchars($conn->error) . "</div>";
        return;
    }

    echo "<div class=\"mt-4 card card-body\">";
    echo "<h2 class=\"h5 mb-3\">Llistat d'incidències</h2>";

    if ($res->num_rows === 0) {
        echo "<div class='text-muted'>No hi ha incidències registrades.</div>";
        echo "</div>";
        $res->free();
        return;
    }

    echo "<div class=\"table-responsive\">";
    echo "<table class=\"table table-sm table-striped\">";
    echo "<thead><tr><th scope=\"col\">ID</th><th scope=\"col\">Departament</th><th scope=\"col\">Descripció</th><th scope=\"col\">Data</th><th scope=\"col\">Accions</th></tr></thead>";
    echo "<tbody>";
    while ($row = $res->fetch_assoc()) {
        $id = (int) $row['id'];
        $dep = htmlspecialchars((string) $row['departament']);
        $desc = htmlspecialchars((string) $row['descripcio_curta']);
        $data = htmlspecialchars((string) $row['data_incidencia']);

        echo "<tr>";
        echo "<th scope=\"row\">$id</th>";
        echo "<td>$dep</td>";
        echo "<td>$desc</td>";
        echo "<td>$data</td>";
        $edit_url = 'editar_incidencia.php?id=' . $id;
        echo "<td><a href=\"$edit_url\" class=\"btn btn-sm btn-outline-primary\">Editar</a></td>";
        echo "</tr>";
    }
    echo "</tbody></table></div></div>";

    $res->free();
}

?>
