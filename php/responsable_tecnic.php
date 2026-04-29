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

$tecnics_disponibles = [];
if ($alert === null) {
    $tecnics_query = $conn->query("SELECT FIRST_NAME, LAST_NAME FROM TECNIC ORDER BY TECNIC_ID ASC");
    if ($tecnics_query !== false) {
        while ($row = $tecnics_query->fetch_assoc()) {
            $label = trim((string)($row['FIRST_NAME'] ?? '') . ' ' . (string)($row['LAST_NAME'] ?? ''));
            if ($label !== '') {
                $tecnics_disponibles[] = $label;
            }
        }
        $tecnics_query->free();
    }
}
if (count($tecnics_disponibles) === 0) {
    $tecnics_disponibles[] = INCIDENCIA_TECNIC_PER_DEFECTE;
}

$sort = (string)($_POST['sort'] ?? $_GET['sort'] ?? 'data');
$dir = strtolower((string)($_POST['dir'] ?? $_GET['dir'] ?? 'desc'));
$q = trim((string)($_POST['q'] ?? $_GET['q'] ?? ''));
$data = trim((string)($_POST['data'] ?? $_GET['data'] ?? ''));

$sort_valids = ['id', 'departament', 'data'];
if (!in_array($sort, $sort_valids, true)) {
    $sort = 'data';
}
if (!in_array($dir, ['asc', 'desc'], true)) {
    $dir = 'desc';
}
// Accept YYYY-MM-DD only; otherwise ignore.
if ($data !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    $data = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $alert === null) {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'assignar') {
        $tecnic_assignacio = trim((string)($_POST['tecnic'] ?? ''));
        if ($tecnic_assignacio === '' || !in_array($tecnic_assignacio, $tecnics_disponibles, true)) {
            $alert = ['type' => 'warning', 'message' => "Has d'escollir un tècnic per assignar la incidència #$id."];
        } else {
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

function render_table_responsable(array $rows, string $mode, array $filters): void
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
            echo "<button type='button' class='btn btn-sm btn-outline-primary' data-bs-toggle='modal' data-bs-target='#assignarModal' data-incidencia-id='" . (int)$id . "'>Assignar</button>";
            echo "</td>";
        }

        if ($mode === 'assignada') {
            echo "<td>";
            echo "<form method='POST' class='d-inline'>";
            echo "<input type='hidden' name='sort' value='" . htmlspecialchars((string)($filters['sort'] ?? '')) . "'>";
            echo "<input type='hidden' name='dir' value='" . htmlspecialchars((string)($filters['dir'] ?? '')) . "'>";
            echo "<input type='hidden' name='q' value='" . htmlspecialchars((string)($filters['q'] ?? '')) . "'>";
            echo "<input type='hidden' name='data' value='" . htmlspecialchars((string)($filters['data'] ?? '')) . "'>";
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
    $filters = [
        'sort' => $sort,
        'dir' => $dir,
        'q' => $q,
        'data' => $data,
    ];

    $order_map_pending_assigned = [
        'id' => 'id',
        'departament' => 'departament',
        'data' => 'data_incidencia',
    ];
    $order_map_history = [
        'id' => 'id',
        'departament' => 'departament',
        'data' => 'data_hist',
    ];

    $build_where = function (bool $is_history) use ($q, $data): array {
        $where = [];
        $types = '';
        $params = [];

        if ($q !== '') {
            $where[] = '(departament LIKE ? OR descripcio_curta LIKE ?)';
            $types .= 'ss';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($data !== '') {
            if ($is_history) {
                $where[] = 'DATE(COALESCE(data_tancament, data_incidencia)) = ?';
            } else {
                $where[] = 'DATE(data_incidencia) = ?';
            }
            $types .= 's';
            $params[] = $data;
        }

        return [$where, $types, $params];
    };

    $estat_pendent = INCIDENCIA_ESTAT_PENDENT_ASSIGNAR;
    $estat_assignada = INCIDENCIA_ESTAT_ASSIGNADA;
    $estat_tancada = INCIDENCIA_ESTAT_TANCADA;

    // Pendents
    list($where_parts, $types, $params) = $build_where(false);
    $where_sql = 'estat = ?';
    if (count($where_parts) > 0) {
        $where_sql .= ' AND ' . implode(' AND ', $where_parts);
    }
    $order_col = $order_map_pending_assigned[$sort] ?? 'data_incidencia';
    $sql1 = "SELECT id, departament, descripcio_curta, data_incidencia FROM incidencies WHERE $where_sql ORDER BY $order_col $dir";
    $stmt1 = $conn->prepare($sql1);
    if ($stmt1 !== false) {
        $bind_types = 's' . $types;
        $bind_values = array_merge([$estat_pendent], $params);
        $stmt1->bind_param($bind_types, ...$bind_values);
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

    // Assignades
    list($where_parts2, $types2, $params2) = $build_where(false);
    $where_sql2 = 'estat = ?';
    if (count($where_parts2) > 0) {
        $where_sql2 .= ' AND ' . implode(' AND ', $where_parts2);
    }
    $order_col2 = $order_map_pending_assigned[$sort] ?? 'data_incidencia';
    $sql2 = "SELECT id, departament, descripcio_curta, data_incidencia, tecnic_assignat FROM incidencies WHERE $where_sql2 ORDER BY $order_col2 $dir";
    $stmt2 = $conn->prepare($sql2);
    if ($stmt2 !== false) {
        $bind_types2 = 's' . $types2;
        $bind_values2 = array_merge([$estat_assignada], $params2);
        $stmt2->bind_param($bind_types2, ...$bind_values2);
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

    // Historial
    list($where_parts3, $types3, $params3) = $build_where(true);
    $where_sql3 = 'estat = ?';
    if (count($where_parts3) > 0) {
        $where_sql3 .= ' AND ' . implode(' AND ', $where_parts3);
    }
    $order_col3 = $order_map_history[$sort] ?? 'data_hist';
    $sql3 = "SELECT id, departament, descripcio_curta, COALESCE(data_tancament, data_incidencia) AS data_hist, tecnic_assignat FROM incidencies WHERE $where_sql3 ORDER BY $order_col3 $dir";
    $stmt3 = $conn->prepare($sql3);
    if ($stmt3 !== false) {
        $bind_types3 = 's' . $types3;
        $bind_values3 = array_merge([$estat_tancada], $params3);
        $stmt3->bind_param($bind_types3, ...$bind_values3);
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

    <div class="card card-body mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label for="sort" class="form-label mb-1">Ordenar per</label>
                <select id="sort" name="sort" class="form-select">
                    <option value="data" <?php echo $sort === 'data' ? 'selected' : ''; ?>>Data</option>
                    <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>ID</option>
                    <option value="departament" <?php echo $sort === 'departament' ? 'selected' : ''; ?>>Departament</option>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label for="dir" class="form-label mb-1">Direcció</label>
                <select id="dir" name="dir" class="form-select">
                    <option value="desc" <?php echo $dir === 'desc' ? 'selected' : ''; ?>>Desc</option>
                    <option value="asc" <?php echo $dir === 'asc' ? 'selected' : ''; ?>>Asc</option>
                </select>
            </div>

            <div class="col-12 col-md-4">
                <label for="q" class="form-label mb-1">Cerca (descripció o departament)</label>
                <input id="q" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>" placeholder="Ex: ordinador, informàtica...">
            </div>

            <div class="col-12 col-md-3">
                <label for="data" class="form-label mb-1">Data (YYYY-MM-DD)</label>
                <input id="data" name="data" type="date" class="form-control" value="<?php echo htmlspecialchars($data); ?>">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-outline-primary">Aplicar</button>
                <a class="btn btn-outline-secondary ms-2" href="responsable_tecnic.php">Netejar</a>
            </div>
        </form>
        <div class="form-text">Aquests filtres s'apliquen a pendents, assignades i historial.</div>
    </div>

    <?php if (is_array($alert)) : ?>
        <div class="alert alert-<?php echo htmlspecialchars((string)$alert['type']); ?>" role="alert">
            <?php echo htmlspecialchars((string)$alert['message']); ?>
        </div>
    <?php endif; ?>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Pendents d'assignar</h2>
        <?php render_table_responsable($pending_rows, 'pendent', $filters ?? ['sort' => $sort, 'dir' => $dir, 'q' => $q, 'data' => $data]); ?>
        <div class="form-text">En assignar, podràs escollir el tècnic i es marcarà com a <strong>assignada</strong>.</div>
    </div>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Assignades</h2>
        <?php render_table_responsable($assigned_rows, 'assignada', $filters ?? ['sort' => $sort, 'dir' => $dir, 'q' => $q, 'data' => $data]); ?>
    </div>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Historial (totes)</h2>
        <?php render_table_responsable($history_rows, 'historial', $filters ?? ['sort' => $sort, 'dir' => $dir, 'q' => $q, 'data' => $data]); ?>
    </div>

    <a class="btn btn-outline-secondary" href="index.php">Tornar</a>
</div>

<!-- Modal: Assignar a tècnic -->
<div class="modal fade" id="assignarModal" tabindex="-1" aria-labelledby="assignarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignarModalLabel">Assignar incidència</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tancar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Selecciona un tècnic per assignar la incidència <span class="fw-bold">#<span id="assignarIncidenciaId">—</span></span>.</p>

                <form method="POST" id="assignarForm">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars((string)$sort); ?>">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars((string)$dir); ?>">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars((string)$q); ?>">
                    <input type="hidden" name="data" value="<?php echo htmlspecialchars((string)$data); ?>">
                    <input type="hidden" name="action" value="assignar">
                    <input type="hidden" name="id" id="assignarId" value="0">

                    <div class="d-grid gap-2">
                        <?php foreach ($tecnics_disponibles as $t) : ?>
                            <button type="submit" class="btn btn-outline-primary" name="tecnic" value="<?php echo htmlspecialchars((string)$t); ?>">
                                <?php echo htmlspecialchars((string)$t); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        var modal = document.getElementById('assignarModal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            if (!button) return;
            var id = button.getAttribute('data-incidencia-id') || '0';

            var input = document.getElementById('assignarId');
            var label = document.getElementById('assignarIncidenciaId');
            if (input) input.value = id;
            if (label) label.textContent = id;
        });
    })();
</script>

<?php include 'footer.php'; ?>