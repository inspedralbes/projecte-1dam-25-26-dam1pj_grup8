<?php

require_once 'connexio.php';
require_once 'incidencies_schema.php';

$schema_result = ensure_incidencies_schema($conn);
$alert = null;

if (!is_array($schema_result) || ($schema_result['ok'] ?? false) !== true) {
    $alert = [
        'type' => 'danger',
        'message' => "No s'ha pogut inicialitzar l'esquema d'incidències: " . (string)($schema_result['error'] ?? 'Error desconegut'),
    ];
}

$tecnic_actual = INCIDENCIA_TECNIC_PER_DEFECTE;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $alert === null) {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'tancar' && $id > 0) {
        $stmt = $conn->prepare('UPDATE incidencies SET estat = ?, data_tancament = NOW() WHERE id = ? AND estat = ? AND tecnic_assignat = ?');
        if ($stmt === false) {
            $alert = ['type' => 'danger', 'message' => 'Error preparant la consulta: ' . $conn->error];
        } else {
            $estat_tancada = INCIDENCIA_ESTAT_TANCADA;
            $estat_assignada = INCIDENCIA_ESTAT_ASSIGNADA;
            $stmt->bind_param('siss', $estat_tancada, $id, $estat_assignada, $tecnic_actual);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $alert = ['type' => 'success', 'message' => "Incidència #$id tancada."];
            } else {
                $alert = ['type' => 'warning', 'message' => "No s'ha pogut tancar la incidència #$id (potser ja no està assignada o no és teva)."];
            }
            $stmt->close();
        }
    }
}

function render_table_tecnic(array $rows, bool $show_close_action): void
{
    if (count($rows) === 0) {
        echo "<div class='text-muted'>No hi ha incidències per mostrar.</div>";
        return;
    }

    echo "<div class='table-responsive'>";
    echo "<table class='table table-sm table-striped align-middle'>";
    echo "<thead><tr><th scope='col'>ID</th><th scope='col'>Departament</th><th scope='col'>Descripció</th><th scope='col'>Data</th>";
    if ($show_close_action) {
        echo "<th scope='col'>Accions</th>";
    }
    echo "</tr></thead><tbody>";

    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        $dep = htmlspecialchars((string)($row['departament'] ?? ''));
        $desc = htmlspecialchars((string)($row['descripcio_curta'] ?? ''));
        $data = htmlspecialchars((string)($row['data'] ?? ''));

        echo "<tr>";
        echo "<th scope='row'>$id</th>";
        echo "<td>$dep</td>";
        echo "<td>$desc</td>";
        echo "<td>$data</td>";

        if ($show_close_action) {
            echo "<td>";
            echo "<form method='POST' class='d-inline'>";
            echo "<input type='hidden' name='action' value='tancar'>";
            echo "<input type='hidden' name='id' value='" . (int)$id . "'>";
            echo "<button type='submit' class='btn btn-sm btn-outline-success'>Tancar</button>";
            echo "</form>";
            echo "</td>";
        }

        echo "</tr>";
    }

    echo "</tbody></table></div>";
}

$assigned_rows = [];
$history_rows = [];

if ($alert === null) {
    $stmt1 = $conn->prepare('SELECT id, departament, descripcio_curta, data_incidencia FROM incidencies WHERE estat = ? AND tecnic_assignat = ? ORDER BY data_incidencia DESC');
    if ($stmt1 !== false) {
        $estat_assignada = INCIDENCIA_ESTAT_ASSIGNADA;
        $stmt1->bind_param('ss', $estat_assignada, $tecnic_actual);
        if ($stmt1->execute()) {
            $res = $stmt1->get_result();
            if ($res !== false) {
                while ($row = $res->fetch_assoc()) {
                    $assigned_rows[] = [
                        'id' => $row['id'],
                        'departament' => $row['departament'],
                        'descripcio_curta' => $row['descripcio_curta'],
                        'data' => $row['data_incidencia'],
                    ];
                }
            }
        }
        $stmt1->close();
    }

    $stmt2 = $conn->prepare('SELECT id, departament, descripcio_curta, COALESCE(data_tancament, data_incidencia) AS data_hist FROM incidencies WHERE estat = ? AND tecnic_assignat = ? ORDER BY data_hist DESC');
    if ($stmt2 !== false) {
        $estat_tancada = INCIDENCIA_ESTAT_TANCADA;
        $stmt2->bind_param('ss', $estat_tancada, $tecnic_actual);
        if ($stmt2->execute()) {
            $res = $stmt2->get_result();
            if ($res !== false) {
                while ($row = $res->fetch_assoc()) {
                    $history_rows[] = [
                        'id' => $row['id'],
                        'departament' => $row['departament'],
                        'descripcio_curta' => $row['descripcio_curta'],
                        'data' => $row['data_hist'],
                    ];
                }
            }
        }
        $stmt2->close();
    }
}

?>

<?php include 'header.php'; ?>

<link rel="stylesheet" href="css/tecnic.css">

<div class="container py-4">
    <h1 class="h3 mb-2">Tècnic</h1>
    <p class="text-muted mb-4">Només es mostren les incidències assignades al tècnic i el seu historial.</p>

    <?php if (is_array($alert)) : ?>
        <div class="alert alert-<?php echo htmlspecialchars((string)$alert['type']); ?>" role="alert">
            <?php echo htmlspecialchars((string)$alert['message']); ?>
        </div>
    <?php endif; ?>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Incidències assignades</h2>
        <?php render_table_tecnic($assigned_rows, true); ?>
    </div>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Historial</h2>
        <?php render_table_tecnic($history_rows, false); ?>
    </div>

    <a class="btn btn-outline-secondary" href="index.php">Tornar</a>
</div>

<?php include 'footer.php'; ?>