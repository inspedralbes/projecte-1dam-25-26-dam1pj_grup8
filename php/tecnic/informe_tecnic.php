<?php
/**
 * Informes del rol TECNIC.
 *
 * Genera informes agregados (incidencias + worklogs) para análisis de trabajo.
 * Se apoya en MySQL y en el esquema definido en `incidencies_schema.php`.
 */

require_once __DIR__ . '/../incidencies/auth.php';
auth_require_role('TECNIC');

require_once __DIR__ . '/../incidencies/connexio.php';
require_once __DIR__ . '/../incidencies/incidencies_schema.php';
require_once __DIR__ . '/../incidencies/tecnic_schema.php';

$schema_result = ensure_incidencies_schema($conn);
$alert = null;
$schema_ok = true;

if (!is_array($schema_result) || ($schema_result['ok'] ?? false) !== true) {
    $alert = [
        'type' => 'danger',
        'message' => "No s'ha pogut inicialitzar l'esquema d'incidències: " . (string)($schema_result['error'] ?? 'Error desconegut'),
    ];
    $schema_ok = false;
}

if ($schema_ok) {
    $tecnic_schema_result = ensure_tecnic_schema($conn);
    if (!is_array($tecnic_schema_result) || ($tecnic_schema_result['ok'] ?? false) !== true) {
        $alert = [
            'type' => 'danger',
            'message' => "No s'ha pogut inicialitzar l'esquema de tècnics: " . (string)($tecnic_schema_result['error'] ?? 'Error desconegut'),
        ];
        $schema_ok = false;
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function valid_date_yyyy_mm_dd(string $value): bool
{
    return $value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
}

$tecnics_disponibles = [];
if ($schema_ok) {
    $tecnics_query = $conn->query("SELECT FIRST_NAME, LAST_NAME FROM TECNIC ORDER BY FIRST_NAME, LAST_NAME");
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

// Keep legacy label for older seeded data.
if (!in_array(INCIDENCIA_TECNIC_PER_DEFECTE, $tecnics_disponibles, true)) {
    array_unshift($tecnics_disponibles, INCIDENCIA_TECNIC_PER_DEFECTE);
}

$tecnic = trim((string)($_GET['tecnic'] ?? ''));
if ($tecnic === '') {
    $tecnic = INCIDENCIA_TECNIC_PER_DEFECTE;
}
if (!in_array($tecnic, $tecnics_disponibles, true)) {
    $tecnic = $tecnics_disponibles[0] ?? INCIDENCIA_TECNIC_PER_DEFECTE;
}

$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to = trim((string)($_GET['date_to'] ?? ''));
$estat = trim((string)($_GET['estat'] ?? ''));

if ($date_from !== '' && !valid_date_yyyy_mm_dd($date_from)) {
    $date_from = '';
}
if ($date_to !== '' && !valid_date_yyyy_mm_dd($date_to)) {
    $date_to = '';
}

$estat_valids = [
    '',
    INCIDENCIA_ESTAT_PENDENT_ASSIGNAR,
    INCIDENCIA_ESTAT_ASSIGNADA,
    INCIDENCIA_ESTAT_TANCADA,
    INCIDENCIA_ESTAT_REBUTJADA,
];
if (!in_array($estat, $estat_valids, true)) {
    $estat = '';
}

$sort = (string)($_GET['sort'] ?? 'data');
$dir = strtolower((string)($_GET['dir'] ?? 'desc'));
$sort_valids = ['data', 'hours', 'actions', 'id', 'departament', 'estat'];
if (!in_array($sort, $sort_valids, true)) {
    $sort = 'data';
}
if (!in_array($dir, ['asc', 'desc'], true)) {
    $dir = 'desc';
}

$global_rows = [];
$detail_rows = [];
$detail_totals = ['incidents' => 0, 'actions' => 0, 'hours' => 0.0];

if ($schema_ok) {
    // Global per-technician report (3-table join via TECNIC + incidencies + worklogs).
    $where = [];
    $types = '';
    $params = [];

    if ($date_from !== '') {
        $where[] = 'DATE(i.data_incidencia) >= ?';
        $types .= 's';
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $where[] = 'DATE(i.data_incidencia) <= ?';
        $types .= 's';
        $params[] = $date_to;
    }
    if ($estat !== '') {
        $where[] = 'i.estat = ?';
        $types .= 's';
        $params[] = $estat;
    }

    $where_sql = count($where) > 0 ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql_global = "
        SELECT tecnic, COUNT(*) AS incidents, SUM(total_hours) AS total_hours, AVG(total_hours) AS avg_hours_per_incident
        FROM (
            SELECT
                CONCAT(t.FIRST_NAME, ' ', t.LAST_NAME) AS tecnic,
                i.id AS incident_id,
                COALESCE(SUM(w.hours_spent), 0) AS total_hours
            FROM TECNIC t
            INNER JOIN incidencies i
                ON i.tecnic_assignat = CONCAT(t.FIRST_NAME, ' ', t.LAST_NAME)
            LEFT JOIN worklogs w
                ON w.incident_id = i.id
            $where_sql
            GROUP BY tecnic, incident_id
        ) AS per_incident
        GROUP BY tecnic
        ORDER BY total_hours DESC, incidents DESC, tecnic ASC
    ";

    $stmt_global = $conn->prepare($sql_global);
    if ($stmt_global !== false) {
        if ($types !== '') {
            $stmt_global->bind_param($types, ...$params);
        }
        if ($stmt_global->execute()) {
            $res = $stmt_global->get_result();
            if ($res !== false) {
                while ($row = $res->fetch_assoc()) {
                    $global_rows[] = $row;
                }
                $res->free();
            }
        }
        $stmt_global->close();
    }

    // Detail report for selected technician (incidencies + worklogs join).
    $where2 = ['i.tecnic_assignat = ?'];
    $types2 = 's';
    $params2 = [$tecnic];

    if ($date_from !== '') {
        $where2[] = 'DATE(i.data_incidencia) >= ?';
        $types2 .= 's';
        $params2[] = $date_from;
    }
    if ($date_to !== '') {
        $where2[] = 'DATE(i.data_incidencia) <= ?';
        $types2 .= 's';
        $params2[] = $date_to;
    }
    if ($estat !== '') {
        $where2[] = 'i.estat = ?';
        $types2 .= 's';
        $params2[] = $estat;
    }

    $order_map = [
        'data' => 'i.data_incidencia',
        'hours' => 'total_hours',
        'actions' => 'total_actions',
        'id' => 'i.id',
        'departament' => 'i.departament',
        'estat' => 'i.estat',
    ];
    $order_col = $order_map[$sort] ?? 'i.data_incidencia';

    $sql_detail = "
        SELECT
            i.id,
            i.departament,
            i.estat,
            i.prioritat,
            i.tipologia,
            i.data_incidencia,
            COALESCE(SUM(w.hours_spent), 0) AS total_hours,
            COUNT(w.id) AS total_actions
        FROM incidencies i
        LEFT JOIN worklogs w
            ON w.incident_id = i.id
        WHERE " . implode(' AND ', $where2) . "
        GROUP BY i.id, i.departament, i.estat, i.prioritat, i.tipologia, i.data_incidencia
        ORDER BY $order_col $dir
        LIMIT 300
    ";

    $stmt_detail = $conn->prepare($sql_detail);
    if ($stmt_detail !== false) {
        $stmt_detail->bind_param($types2, ...$params2);
        if ($stmt_detail->execute()) {
            $res = $stmt_detail->get_result();
            if ($res !== false) {
                while ($row = $res->fetch_assoc()) {
                    $detail_rows[] = $row;
                    $detail_totals['incidents']++;
                    $detail_totals['actions'] += (int)($row['total_actions'] ?? 0);
                    $detail_totals['hours'] += (float)($row['total_hours'] ?? 0);
                }
                $res->free();
            }
        }
        $stmt_detail->close();
    }
}

include __DIR__ . '/../incidencies/header.php';
?>

<link rel="stylesheet" href="/css/tecnic.css">

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Informe Tècnic</h1>
            <div class="text-muted">Resum de temps (work logs) i incidències per tècnic.</div>
        </div>
        <a class="btn btn-outline-secondary" href="/tecnic/tecnic.php?<?php echo h(http_build_query(['tecnic' => $tecnic])); ?>">Tornar</a>
    </div>

    <?php if (is_array($alert)) : ?>
        <div class="alert alert-<?php echo h((string)($alert['type'] ?? 'info')); ?>" role="alert">
            <?php echo h((string)($alert['message'] ?? '')); ?>
        </div>
    <?php endif; ?>

    <div class="card card-body mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label for="tecnic" class="form-label mb-1">Tècnic</label>
                <select id="tecnic" name="tecnic" class="form-select">
                    <?php foreach ($tecnics_disponibles as $t) : ?>
                        <option value="<?php echo h((string)$t); ?>" <?php echo $t === $tecnic ? 'selected' : ''; ?>>
                            <?php echo h((string)$t); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label for="date_from" class="form-label mb-1">Data inici</label>
                <input id="date_from" name="date_from" type="date" class="form-control" value="<?php echo h($date_from); ?>">
            </div>

            <div class="col-6 col-md-2">
                <label for="date_to" class="form-label mb-1">Data fi</label>
                <input id="date_to" name="date_to" type="date" class="form-control" value="<?php echo h($date_to); ?>">
            </div>

            <div class="col-12 col-md-2">
                <label for="estat" class="form-label mb-1">Estat</label>
                <select id="estat" name="estat" class="form-select">
                    <option value="" <?php echo $estat === '' ? 'selected' : ''; ?>>Tots</option>
                    <option value="<?php echo h(INCIDENCIA_ESTAT_PENDENT_ASSIGNAR); ?>" <?php echo $estat === INCIDENCIA_ESTAT_PENDENT_ASSIGNAR ? 'selected' : ''; ?>>Pendent</option>
                    <option value="<?php echo h(INCIDENCIA_ESTAT_ASSIGNADA); ?>" <?php echo $estat === INCIDENCIA_ESTAT_ASSIGNADA ? 'selected' : ''; ?>>Assignada</option>
                    <option value="<?php echo h(INCIDENCIA_ESTAT_TANCADA); ?>" <?php echo $estat === INCIDENCIA_ESTAT_TANCADA ? 'selected' : ''; ?>>Tancada</option>
                    <option value="<?php echo h(INCIDENCIA_ESTAT_REBUTJADA); ?>" <?php echo $estat === INCIDENCIA_ESTAT_REBUTJADA ? 'selected' : ''; ?>>Rebutjada</option>
                </select>
            </div>

            <div class="col-6 col-md-1">
                <label for="sort" class="form-label mb-1">Ordenar</label>
                <select id="sort" name="sort" class="form-select">
                    <option value="data" <?php echo $sort === 'data' ? 'selected' : ''; ?>>Data</option>
                    <option value="hours" <?php echo $sort === 'hours' ? 'selected' : ''; ?>>Hores</option>
                    <option value="actions" <?php echo $sort === 'actions' ? 'selected' : ''; ?>>Accions</option>
                    <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>ID</option>
                </select>
            </div>

            <div class="col-6 col-md-1">
                <label for="dir" class="form-label mb-1">Dir</label>
                <select id="dir" name="dir" class="form-select">
                    <option value="desc" <?php echo $dir === 'desc' ? 'selected' : ''; ?>>Desc</option>
                    <option value="asc" <?php echo $dir === 'asc' ? 'selected' : ''; ?>>Asc</option>
                </select>
            </div>

            <div class="col-12 col-md-12 d-flex gap-2">
                <button type="submit" class="btn btn-dark">Aplicar</button>
                <a class="btn btn-outline-secondary" href="informe_tecnic.php">Netejar</a>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <h5>Incidències (selecció)</h5>
                <h2><?php echo (int)$detail_totals['incidents']; ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <h5>Accions totals</h5>
                <h2><?php echo (int)$detail_totals['actions']; ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <h5>Hores totals</h5>
                <h2><?php echo h(number_format((float)$detail_totals['hours'], 2)); ?></h2>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <div class="table-box">
                <h4 class="mb-3">Resum per tècnic (global)</h4>

                <?php if (!$schema_ok) : ?>
                    <div class="text-muted">No es pot carregar l'informe perquè falta l'esquema.</div>
                <?php elseif (count($global_rows) === 0) : ?>
                    <div class="text-muted">No hi ha dades per mostrar amb aquests filtres.</div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tècnic</th>
                                    <th class="text-end">Incidències</th>
                                    <th class="text-end">Hores</th>
                                    <th class="text-end">Mitjana/Inc.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($global_rows as $row) : ?>
                                    <tr>
                                        <td><?php echo h((string)($row['tecnic'] ?? '')); ?></td>
                                        <td class="text-end"><?php echo (int)($row['incidents'] ?? 0); ?></td>
                                        <td class="text-end"><?php echo h(number_format((float)($row['total_hours'] ?? 0), 2)); ?></td>
                                        <td class="text-end"><?php echo h(number_format((float)($row['avg_hours_per_incident'] ?? 0), 2)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="table-box">
                <h4 class="mb-3">Detall del tècnic seleccionat</h4>

                <?php if (!$schema_ok) : ?>
                    <div class="text-muted">No es pot carregar l'informe perquè falta l'esquema.</div>
                <?php elseif (count($detail_rows) === 0) : ?>
                    <div class="text-muted">No hi ha incidències per mostrar amb aquests filtres.</div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Departament</th>
                                    <th>Estat</th>
                                    <th>Prioritat</th>
                                    <th>Tipologia</th>
                                    <th>Data</th>
                                    <th class="text-end">Accions</th>
                                    <th class="text-end">Hores</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detail_rows as $row) : ?>
                                    <?php
                                    $id = (int)($row['id'] ?? 0);
                                    $qs = http_build_query(['id' => (string)$id, 'tecnic' => $tecnic, 'tab' => 'history']);
                                    ?>
                                    <tr>
                                        <td><?php echo $id; ?></td>
                                        <td><?php echo h((string)($row['departament'] ?? '')); ?></td>
                                        <td><?php echo h((string)($row['estat'] ?? '')); ?></td>
                                        <td><?php echo h((string)($row['prioritat'] ?? '')); ?></td>
                                        <td><?php echo h((string)($row['tipologia'] ?? '')); ?></td>
                                        <td><?php echo h((string)($row['data_incidencia'] ?? '')); ?></td>
                                        <td class="text-end"><?php echo (int)($row['total_actions'] ?? 0); ?></td>
                                        <td class="text-end"><?php echo h(number_format((float)($row['total_hours'] ?? 0), 2)); ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-info" href="/incidencies/detall_incidencia.php?<?php echo h($qs); ?>">Veure</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-muted mt-2">Mostra fins a 300 files per evitar llistats massa llargs.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../incidencies/footer.php'; ?>
