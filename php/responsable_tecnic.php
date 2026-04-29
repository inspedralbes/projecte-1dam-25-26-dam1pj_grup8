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

$tecnic_assignacio = INCIDENCIA_TECNIC_PER_DEFECTE;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $alert === null) {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'assignar') {
        $stmt = $conn->prepare('UPDATE incidencies SET estat = ?, tecnic_assignat = ? WHERE id = ? AND estat = ?');
        if ($stmt === false) {
            $alert = ['type' => 'danger', 'message' => 'Error preparant la consulta: ' . $conn->error];
        } else {
            $estat_assignada = INCIDENCIA_ESTAT_ASSIGNADA;
            $estat_pendent = INCIDENCIA_ESTAT_PENDENT_ASSIGNAR;
            $stmt->bind_param('ssis', $estat_assignada, $tecnic_assignacio, $id, $estat_pendent);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $alert = ['type' => 'success', 'message' => "Incidència #$id assignada a $tecnic_assignacio."];
            } else {
                $alert = ['type' => 'warning', 'message' => "No s'ha pogut assignar la incidència #$id (potser ja està assignada)."];
            }
            $stmt->close();
        }
    }

    if ($id > 0 && $action === 'desassignar') {
        $stmt = $conn->prepare('UPDATE incidencies SET estat = ?, tecnic_assignat = NULL WHERE id = ? AND estat = ?');
        if ($stmt === false) {
            $alert = ['type' => 'danger', 'message' => 'Error preparant la consulta: ' . $conn->error];
        } else {
            $estat_pendent = INCIDENCIA_ESTAT_PENDENT_ASSIGNAR;
            $estat_assignada = INCIDENCIA_ESTAT_ASSIGNADA;
            $stmt->bind_param('sis', $estat_pendent, $id, $estat_assignada);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $alert = ['type' => 'success', 'message' => "Incidència #$id marcada com a pendent d'assignar."];
            } else {
                $alert = ['type' => 'warning', 'message' => "No s'ha pogut desassignar la incidència #$id."];
            }
            $stmt->close();
        }
    }
}

function render_table_responsable(array $rows, string $mode): void
{
    // mode: 'pendent' | 'assignada' | 'historial'
    if (count($rows) === 0) {
        echo "<div class='text-muted'>No hi ha incidències per mostrar.</div>";
        return;
    }

    echo "<div class='table-responsive'>";
    echo "<table class='table table-sm table-striped align-middle'>";
    echo "<thead><tr><th scope='col'>ID</th><th scope='col'>Departament</th><th scope='col'>Descripció</th><th scope='col'>Data</th>";
    if ($mode !== 'pendent') {
        echo "<th scope='col'>Tècnic</th>";
    }
    if ($mode === 'pendent' || $mode === 'assignada') {
        echo "<th scope='col'>Accions</th>";
    }
    echo "</tr></thead><tbody>";

    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        $dep = htmlspecialchars((string)($row['departament'] ?? ''));
        $desc = htmlspecialchars((string)($row['descripcio_curta'] ?? ''));
        $data = htmlspecialchars((string)($row['data'] ?? ''));
        $tecnic = htmlspecialchars((string)($row['tecnic_assignat'] ?? ''));

        echo "<tr>";
        echo "<th scope='row'>$id</th>";
        echo "<td>$dep</td>";
        echo "<td>$desc</td>";
        echo "<td>$data</td>";

        if ($mode !== 'pendent') {
            echo "<td>" . ($tecnic !== '' ? $tecnic : "<span class='text-muted'>—</span>") . "</td>";
        }

        if ($mode === 'pendent') {
            echo "<td>";
            echo "<form method='POST' class='d-inline'>";
            echo "<input type='hidden' name='action' value='assignar'>";
            echo "<input type='hidden' name='id' value='" . (int)$id . "'>";
            echo "<button type='submit' class='btn btn-sm btn-outline-primary'>Assignar</button>";
            echo "</form>";
            echo "</td>";
        }

        if ($mode === 'assignada') {
            echo "<td>";
            echo "<form method='POST' class='d-inline'>";
            echo "<input type='hidden' name='action' value='desassignar'>";
            echo "<input type='hidden' name='id' value='" . (int)$id . "'>";
            echo "<button type='submit' class='btn btn-sm btn-outline-warning'>Desassignar</button>";
            echo "</form>";
            echo "</td>";
        }

        echo "</tr>";
    }

    echo "</tbody></table></div>";
}

$pending_rows = [];
$assigned_rows = [];
$history_rows = [];

if ($alert === null) {
    $stmt1 = $conn->prepare('SELECT id, departament, descripcio_curta, data_incidencia FROM incidencies WHERE estat = ? ORDER BY data_incidencia DESC');
    if ($stmt1 !== false) {
        $estat_pendent = INCIDENCIA_ESTAT_PENDENT_ASSIGNAR;
        $stmt1->bind_param('s', $estat_pendent);
        if ($stmt1->execute()) {
            $res = $stmt1->get_result();
            if ($res !== false) {
                while ($row = $res->fetch_assoc()) {
                    $pending_rows[] = [
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

    $stmt2 = $conn->prepare('SELECT id, departament, descripcio_curta, data_incidencia, tecnic_assignat FROM incidencies WHERE estat = ? ORDER BY data_incidencia DESC');
    if ($stmt2 !== false) {
        $estat_assignada = INCIDENCIA_ESTAT_ASSIGNADA;
        $stmt2->bind_param('s', $estat_assignada);
        if ($stmt2->execute()) {
            $res = $stmt2->get_result();
            if ($res !== false) {
                while ($row = $res->fetch_assoc()) {
                    $assigned_rows[] = [
                        'id' => $row['id'],
                        'departament' => $row['departament'],
                        'descripcio_curta' => $row['descripcio_curta'],
                        'data' => $row['data_incidencia'],
                        'tecnic_assignat' => $row['tecnic_assignat'],
                    ];
                }
            }
        }
        $stmt2->close();
    }

    $stmt3 = $conn->prepare('SELECT id, departament, descripcio_curta, COALESCE(data_tancament, data_incidencia) AS data_hist, tecnic_assignat FROM incidencies WHERE estat = ? ORDER BY data_hist DESC');
    if ($stmt3 !== false) {
        $estat_tancada = INCIDENCIA_ESTAT_TANCADA;
        $stmt3->bind_param('s', $estat_tancada);
        if ($stmt3->execute()) {
            $res = $stmt3->get_result();
            if ($res !== false) {
                while ($row = $res->fetch_assoc()) {
                    $history_rows[] = [
                        'id' => $row['id'],
                        'departament' => $row['departament'],
                        'descripcio_curta' => $row['descripcio_curta'],
                        'data' => $row['data_hist'],
                        'tecnic_assignat' => $row['tecnic_assignat'],
                    ];
                }
            }
        }
        $stmt3->close();
    }
}

?>

<?php include 'header.php'; ?>

<link rel="stylesheet" href="css/tecnic.css">

<div class="container py-4">
    <h1 class="h3 mb-2">Responsable Tècnic</h1>
    <p class="text-muted mb-4">Mostra totes les incidències pendents d'assignar, les assignades i l'historial global.</p>

    <?php if (is_array($alert)) : ?>
        <div class="alert alert-<?php echo htmlspecialchars((string)$alert['type']); ?>" role="alert">
            <?php echo htmlspecialchars((string)$alert['message']); ?>
        </div>
    <?php endif; ?>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Pendents d'assignar</h2>
        <?php render_table_responsable($pending_rows, 'pendent'); ?>
        <div class="form-text">En assignar, es marcarà automàticament com a <strong>assignada</strong> al tècnic: <?php echo htmlspecialchars($tecnic_assignacio); ?>.</div>
    </div>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Assignades</h2>
        <?php render_table_responsable($assigned_rows, 'assignada'); ?>
    </div>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Historial (totes)</h2>
        <?php render_table_responsable($history_rows, 'historial'); ?>
    </div>

    <a class="btn btn-outline-secondary" href="index.php">Tornar</a>
</div>

<?php include 'footer.php'; ?>