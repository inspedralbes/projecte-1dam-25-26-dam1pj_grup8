<?php
/**
 * Utilidad de listado de incidencias (reutilizable).
 *
 * Contiene `mostrar_incidencies($conn)` para renderizar una tabla HTML con
 * incidencias desde MySQL.
 */

function mostrar_incidencies(mysqli $conn): void
{
    require_once __DIR__ . '/incidencies_schema.php';
    $schema_result = ensure_incidencies_schema($conn);
    if (!is_array($schema_result) || ($schema_result['ok'] ?? false) !== true) {
        echo "<div class='alert alert-warning' role='alert'>No s'ha pogut assegurar la taula incidencies: " . htmlspecialchars((string)($schema_result['error'] ?? 'Error desconegut')) . "</div>";
        return;
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
