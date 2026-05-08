<?php

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

$sort = (string)($_POST['sort'] ?? $_GET['sort'] ?? 'data');
$dir = strtolower((string)($_POST['dir'] ?? $_GET['dir'] ?? 'desc'));
$q = trim((string)($_POST['q'] ?? $_GET['q'] ?? ''));
$data = trim((string)($_POST['data'] ?? $_GET['data'] ?? ''));
$tecnic_param = trim((string)($_POST['tecnic'] ?? $_GET['tecnic'] ?? ''));
$historial_page = max(1, (int)($_POST['historial_page'] ?? $_GET['historial_page'] ?? 1));
$historial_per_page = 10;

$sort_valids = ['id', 'departament', 'data'];
if (!in_array($sort, $sort_valids, true)) {
    $sort = 'data';
}
if (!in_array($dir, ['asc', 'desc'], true)) {
    $dir = 'desc';
}
if ($data !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    $data = '';
}

// Flash alert support after PRG redirect.
if ($schema_ok && $alert === null) {
    $flash_type = (string)($_GET['flash_type'] ?? '');
    $flash_msg = trim((string)($_GET['flash_msg'] ?? ''));
    $valid_flash_types = ['success', 'warning', 'danger', 'info'];
    if ($flash_msg !== '' && in_array($flash_type, $valid_flash_types, true)) {
        $flash_msg_short = function_exists('mb_substr')
            ? (string) mb_substr($flash_msg, 0, 400)
            : (string) substr($flash_msg, 0, 400);
        $alert = [
            'type' => $flash_type,
            'message' => $flash_msg_short,
        ];
    }
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

// Always allow the legacy default label used by the Responsable screen.
if (!in_array(INCIDENCIA_TECNIC_PER_DEFECTE, $tecnics_disponibles, true)) {
    array_unshift($tecnics_disponibles, INCIDENCIA_TECNIC_PER_DEFECTE);
}

$tecnic_actual = $tecnic_param !== '' ? $tecnic_param : INCIDENCIA_TECNIC_PER_DEFECTE;
if (!in_array($tecnic_actual, $tecnics_disponibles, true)) {
    $tecnic_actual = $tecnics_disponibles[0] ?? INCIDENCIA_TECNIC_PER_DEFECTE;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schema_ok) {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'iniciar' && $id > 0) {
        $stmt = $conn->prepare('UPDATE incidencies SET data_inici_tasca = NOW() WHERE id = ? AND estat = ? AND tecnic_assignat = ? AND data_inici_tasca IS NULL');
        if ($stmt === false) {
            $alert = ['type' => 'danger', 'message' => 'Error preparant la consulta: ' . $conn->error];
        } else {
            $estat_assignada = INCIDENCIA_ESTAT_ASSIGNADA;
            $stmt->bind_param('iss', $id, $estat_assignada, $tecnic_actual);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $alert = ['type' => 'success', 'message' => "Tasca iniciada per la incidència #$id."];
            } else {
                $alert = ['type' => 'warning', 'message' => "No s'ha pogut iniciar la tasca de la incidència #$id (potser ja està iniciada o no és teva)."];
            }
            $stmt->close();
        }
    }

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

    // PRG: redirect to GET so the page refreshes and avoids resubmission.
    if ($action !== '' && $id > 0 && is_array($alert)) {
        $redirect_params = [
            'sort' => $sort,
            'dir' => $dir,
            'q' => $q,
            'data' => $data,
            'tecnic' => $tecnic_actual,
            'historial_page' => $historial_page,
            'flash_type' => (string)($alert['type'] ?? 'info'),
            'flash_msg' => (string)($alert['message'] ?? ''),
        ];
        header('Location: tecnic.php?' . http_build_query($redirect_params));
        exit;
    }
}

function format_simple_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return "<span class='text-muted'>—</span>";
    }

    try {
        $dt = new DateTime($value);
        return htmlspecialchars($dt->format('Y-m-d H:i'));
    } catch (Exception $e) {
        return htmlspecialchars($value);
    }
}

function format_simple_duration(?string $start, ?string $end): string
{
    if ($start === null || trim($start) === '' || $end === null || trim($end) === '') {
        return "<span class='text-muted'>—</span>";
    }

    try {
        $a = new DateTime($start);
        $b = new DateTime($end);
        $diff = $a->diff($b);
        $totalMinutes = ((int)$diff->days * 24 * 60) + ((int)$diff->h * 60) + (int)$diff->i;
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        if ($hours > 0) {
            return htmlspecialchars($hours . 'h ' . $minutes . 'm');
        }
        return htmlspecialchars($minutes . 'm');
    } catch (Exception $e) {
        return "<span class='text-muted'>—</span>";
    }
}

function render_table_tecnic(array $rows, bool $show_actions, array $filters): void
{
    if (count($rows) === 0) {
        echo "<div class='text-muted'>No hi ha incidències per mostrar.</div>";
        return;
    }

    $tipo_labels = [
        INCIDENCIA_TIPOLOGIA_HARDWARE => "Hardware",
        INCIDENCIA_TIPOLOGIA_SOFTWARE => "Software",
        INCIDENCIA_TIPOLOGIA_XARXA => "Xarxa",
        INCIDENCIA_TIPOLOGIA_COMPTES => "Comptes",
        INCIDENCIA_TIPOLOGIA_IMPRESSIO => "Impressió",
        INCIDENCIA_TIPOLOGIA_AULES => "Aules",
        INCIDENCIA_TIPOLOGIA_MOBILS => "Mòbils",
        INCIDENCIA_TIPOLOGIA_PLATAFORMES => "Plataformes",
        INCIDENCIA_TIPOLOGIA_SEGURETAT => "Seguretat",
    ];

    echo "<div class='table-responsive scrollable-list'>";
    echo "<table class='table table-sm table-striped align-middle'>";
    echo "<thead><tr><th scope='col'>ID</th><th scope='col'>Departament</th><th scope='col'>Descripció</th><th scope='col'>Tipologia</th><th scope='col'>Data</th><th scope='col'>Inici tasca</th><th scope='col'>Tancament</th><th scope='col'>Temps</th>";
    if ($show_actions) {
        echo "<th scope='col'>Accions</th>";
    }
    echo "</tr></thead><tbody>";

    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        $dep = htmlspecialchars((string)($row['departament'] ?? ''));
        $desc = htmlspecialchars((string)($row['descripcio_curta'] ?? ''));
        $tipo_raw = strtolower(trim((string)($row['tipologia'] ?? '')));
        $tipo_label = htmlspecialchars($tipo_labels[$tipo_raw] ?? ($tipo_raw !== '' ? ucfirst($tipo_raw) : '—'));
        $data = htmlspecialchars((string)($row['data'] ?? ''));
        $inici_tasca = (string)($row['data_inici_tasca'] ?? '');
        $tancament = (string)($row['data_tancament'] ?? '');

        echo "<tr>";
        echo "<th scope='row'>$id</th>";
        echo "<td>$dep</td>";
        echo "<td>$desc</td>";
        echo "<td>$tipo_label</td>";
        echo "<td>$data</td>";
        echo "<td>" . format_simple_datetime($inici_tasca !== '' ? $inici_tasca : null) . "</td>";
        echo "<td>" . format_simple_datetime($tancament !== '' ? $tancament : null) . "</td>";
        echo "<td>" . format_simple_duration($inici_tasca !== '' ? $inici_tasca : null, $tancament !== '' ? $tancament : null) . "</td>";

        if ($show_actions) {
            echo "<td>";

            if ($inici_tasca === '') {
                echo "<form method='POST' class='d-inline me-2'>";
                echo "<input type='hidden' name='sort' value='" . htmlspecialchars((string)($filters['sort'] ?? '')) . "'>";
                echo "<input type='hidden' name='dir' value='" . htmlspecialchars((string)($filters['dir'] ?? '')) . "'>";
                echo "<input type='hidden' name='q' value='" . htmlspecialchars((string)($filters['q'] ?? '')) . "'>";
                echo "<input type='hidden' name='data' value='" . htmlspecialchars((string)($filters['data'] ?? '')) . "'>";
                echo "<input type='hidden' name='tecnic' value='" . htmlspecialchars((string)($filters['tecnic'] ?? '')) . "'>";
                echo "<input type='hidden' name='action' value='iniciar'>";
                echo "<input type='hidden' name='id' value='" . (int)$id . "'>";
                echo "<button type='submit' class='btn btn-sm btn-outline-primary'>Iniciar</button>";
                echo "</form>";
            } else {
                echo "<span class='text-muted me-2'>Iniciada</span>";
            }

            echo "<form method='POST' class='d-inline'>";
            echo "<input type='hidden' name='sort' value='" . htmlspecialchars((string)($filters['sort'] ?? '')) . "'>";
            echo "<input type='hidden' name='dir' value='" . htmlspecialchars((string)($filters['dir'] ?? '')) . "'>";
            echo "<input type='hidden' name='q' value='" . htmlspecialchars((string)($filters['q'] ?? '')) . "'>";
            echo "<input type='hidden' name='data' value='" . htmlspecialchars((string)($filters['data'] ?? '')) . "'>";
            echo "<input type='hidden' name='tecnic' value='" . htmlspecialchars((string)($filters['tecnic'] ?? '')) . "'>";
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

function render_historial_pagination(int $current_page, int $total_pages, array $filters): void
{
    if ($total_pages <= 1) {
        return;
    }

    $base_params = [
        'sort' => (string)($filters['sort'] ?? 'data'),
        'dir' => (string)($filters['dir'] ?? 'desc'),
        'q' => (string)($filters['q'] ?? ''),
        'data' => (string)($filters['data'] ?? ''),
        'tecnic' => (string)($filters['tecnic'] ?? ''),
    ];

    echo "<nav aria-label='Paginació historial' class='mt-3'>";
    echo "<ul class='pagination pagination-sm mb-0 flex-wrap'>";

    $prev_disabled = $current_page <= 1 ? ' disabled' : '';
    $prev_query = htmlspecialchars(http_build_query($base_params + ['historial_page' => max(1, $current_page - 1)]));
    echo "<li class='page-item{$prev_disabled}'><a class='page-link' href='?{$prev_query}'>Anterior</a></li>";

    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    if ($start_page > 1) {
        $first_query = htmlspecialchars(http_build_query($base_params + ['historial_page' => 1]));
        echo "<li class='page-item'><a class='page-link' href='?{$first_query}'>1</a></li>";
        if ($start_page > 2) {
            echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
        }
    }

    for ($page = $start_page; $page <= $end_page; $page++) {
        $active = $page === $current_page ? ' active' : '';
        $query = htmlspecialchars(http_build_query($base_params + ['historial_page' => $page]));
        echo "<li class='page-item{$active}'><a class='page-link' href='?{$query}'>{$page}</a></li>";
    }

    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
        }
        $last_query = htmlspecialchars(http_build_query($base_params + ['historial_page' => $total_pages]));
        echo "<li class='page-item'><a class='page-link' href='?{$last_query}'>{$total_pages}</a></li>";
    }

    $next_disabled = $current_page >= $total_pages ? ' disabled' : '';
    $next_query = htmlspecialchars(http_build_query($base_params + ['historial_page' => min($total_pages, $current_page + 1)]));
    echo "<li class='page-item{$next_disabled}'><a class='page-link' href='?{$next_query}'>Següent</a></li>";

    echo "</ul></nav>";
}

$assigned_rows = [];
$history_rows = [];
$tecnic_info = null;

if ($schema_ok) {
    $name_parts = preg_split('/\s+/', trim($tecnic_actual), 2);
    $first_name = trim((string)($name_parts[0] ?? ''));
    $last_name = trim((string)($name_parts[1] ?? ''));
    if ($first_name !== '' && $last_name !== '') {
        $stmt_tech = $conn->prepare('SELECT FIRST_NAME, LAST_NAME, EMAIL, PHONE_NUMBER, ROL_EMPLOYEE FROM TECNIC WHERE FIRST_NAME = ? AND LAST_NAME = ? LIMIT 1');
        if ($stmt_tech !== false) {
            $stmt_tech->bind_param('ss', $first_name, $last_name);
            if ($stmt_tech->execute()) {
                $resTech = $stmt_tech->get_result();
                if ($resTech !== false) {
                    $tecnic_info = $resTech->fetch_assoc() ?: null;
                    $resTech->free();
                }
            }
            $stmt_tech->close();
        }
    }

    $filters = [
        'sort' => $sort,
        'dir' => $dir,
        'q' => $q,
        'data' => $data,
        'tecnic' => $tecnic_actual,
    ];

    $order_map_assigned = [
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

    $estat_assignada = INCIDENCIA_ESTAT_ASSIGNADA;
    $estat_tancada = INCIDENCIA_ESTAT_TANCADA;

    // Assignades (per tècnic)
    list($where_parts, $types, $params) = $build_where(false);
    $where_sql = 'estat = ? AND tecnic_assignat = ?';
    if (count($where_parts) > 0) {
        $where_sql .= ' AND ' . implode(' AND ', $where_parts);
    }
    $order_col = $order_map_assigned[$sort] ?? 'data_incidencia';
    $sql1 = "SELECT id, departament, descripcio_curta, tipologia, data_incidencia, data_inici_tasca, data_tancament FROM incidencies WHERE $where_sql ORDER BY $order_col $dir";
    $stmt1 = $conn->prepare($sql1);
    if ($stmt1 !== false) {
        $bind_types = 'ss' . $types;
        $bind_values = array_merge([$estat_assignada, $tecnic_actual], $params);
        $stmt1->bind_param($bind_types, ...$bind_values);
        if ($stmt1->execute()) {
            $res = $stmt1->get_result();
            if ($res !== false) {
                while ($row = $res->fetch_assoc()) {
                    $assigned_rows[] = [
                        'id' => $row['id'],
                        'departament' => $row['departament'],
                        'descripcio_curta' => $row['descripcio_curta'],
                        'tipologia' => $row['tipologia'] ?? '',
                        'data' => $row['data_incidencia'],
                        'data_inici_tasca' => $row['data_inici_tasca'] ?? null,
                        'data_tancament' => $row['data_tancament'] ?? null,
                    ];
                }
            }
        }
        $stmt1->close();
    }

    // Historial (per tècnic)
    list($where_parts2, $types2, $params2) = $build_where(true);
    $where_sql2 = 'estat = ? AND tecnic_assignat = ?';
    if (count($where_parts2) > 0) {
        $where_sql2 .= ' AND ' . implode(' AND ', $where_parts2);
    }
    $order_col2 = $order_map_history[$sort] ?? 'data_hist';
    $count_sql2 = "SELECT COUNT(*) AS total FROM incidencies WHERE $where_sql2";
    $total_history_rows = 0;
    $stmt2_count = $conn->prepare($count_sql2);
    if ($stmt2_count !== false) {
        $bind_types2_count = 'ss' . $types2;
        $bind_values2_count = array_merge([$estat_tancada, $tecnic_actual], $params2);
        $stmt2_count->bind_param($bind_types2_count, ...$bind_values2_count);
        if ($stmt2_count->execute()) {
            $res_count = $stmt2_count->get_result();
            if ($res_count !== false) {
                $count_row = $res_count->fetch_assoc();
                $total_history_rows = (int)($count_row['total'] ?? 0);
            }
        }
        $stmt2_count->close();
    }

    $total_history_pages = max(1, (int)ceil($total_history_rows / $historial_per_page));
    $historial_page = min($historial_page, $total_history_pages);
    $historial_offset = ($historial_page - 1) * $historial_per_page;

    $sql2 = "SELECT id, departament, descripcio_curta, tipologia, COALESCE(data_tancament, data_incidencia) AS data_hist, data_inici_tasca, data_tancament FROM incidencies WHERE $where_sql2 ORDER BY $order_col2 $dir LIMIT ? OFFSET ?";
    $stmt2 = $conn->prepare($sql2);
    if ($stmt2 !== false) {
        $bind_types2 = 'ss' . $types2 . 'ii';
        $bind_values2 = array_merge([$estat_tancada, $tecnic_actual], $params2, [$historial_per_page, $historial_offset]);
        $stmt2->bind_param($bind_types2, ...$bind_values2);
        if ($stmt2->execute()) {
            $res = $stmt2->get_result();
            if ($res !== false) {
                while ($row = $res->fetch_assoc()) {
                    $history_rows[] = [
                        'id' => $row['id'],
                        'departament' => $row['departament'],
                        'descripcio_curta' => $row['descripcio_curta'],
                        'tipologia' => $row['tipologia'] ?? '',
                        'data' => $row['data_hist'],
                        'data_inici_tasca' => $row['data_inici_tasca'] ?? null,
                        'data_tancament' => $row['data_tancament'] ?? null,
                    ];
                }
            }
        }
        $stmt2->close();
    }
}

?>

<?php include __DIR__ . '/../incidencies/header.php'; ?>

<link rel="stylesheet" href="/css/tecnic.css">

<div class="container py-4">
    <h1 class="h3 mb-2">Tècnic</h1>
    <p class="text-muted mb-4">Només es mostren les incidències assignades al tècnic seleccionat i el seu historial.</p>

    <div class="card card-body mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label for="tecnic" class="form-label mb-1">Tècnic</label>
                <select id="tecnic" name="tecnic" class="form-select">
                    <?php foreach ($tecnics_disponibles as $t) : ?>
                        <option value="<?php echo htmlspecialchars((string)$t); ?>" <?php echo $t === $tecnic_actual ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$t); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Canviar tècnic</button>
            </div>

            <div class="col-12 col-md-3">
                <label for="sort" class="form-label mb-1">Ordenar per</label>
                <select id="sort" name="sort" class="form-select">
                    <option value="data" <?php echo $sort === 'data' ? 'selected' : ''; ?>>Data</option>
                    <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>ID</option>
                    <option value="departament" <?php echo $sort === 'departament' ? 'selected' : ''; ?>>Departament</option>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="dir" class="form-label mb-1">Direcció</label>
                <select id="dir" name="dir" class="form-select">
                    <option value="desc" <?php echo $dir === 'desc' ? 'selected' : ''; ?>>Desc</option>
                    <option value="asc" <?php echo $dir === 'asc' ? 'selected' : ''; ?>>Asc</option>
                </select>
            </div>

            <div class="col-12 col-md-6">
                <label for="q" class="form-label mb-1">Cerca (descripció o departament)</label>
                <input id="q" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>" placeholder="Ex: ordinador, informàtica...">
            </div>

            <div class="col-12 col-md-3">
                <label for="data" class="form-label mb-1">Data (YYYY-MM-DD)</label>
                <input id="data" name="data" type="date" class="form-control" value="<?php echo htmlspecialchars($data); ?>">
            </div>

            <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-outline-primary w-100">Aplicar</button>
            </div>

            <div class="col-12">
                <a class="btn btn-outline-secondary" href="tecnic.php">Netejar</a>
            </div>
        </form>
        <div class="form-text">Aquests filtres s'apliquen a assignades i historial del tècnic seleccionat.</div>
    </div>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Dades del tècnic</h2>
        <div class="scrollable-panel">
        <?php if (is_array($tecnic_info)) : ?>
            <div><strong>Nom:</strong> <?php echo htmlspecialchars((string)($tecnic_info['FIRST_NAME'] ?? '')); ?> <?php echo htmlspecialchars((string)($tecnic_info['LAST_NAME'] ?? '')); ?></div>
            <div><strong>Email:</strong> <?php echo htmlspecialchars((string)($tecnic_info['EMAIL'] ?? '')); ?></div>
            <div><strong>Telèfon:</strong> <?php echo htmlspecialchars((string)($tecnic_info['PHONE_NUMBER'] ?? '')); ?></div>
            <div><strong>Rol:</strong> <?php echo htmlspecialchars((string)($tecnic_info['ROL_EMPLOYEE'] ?? '')); ?></div>
        <?php else : ?>
            <div class="text-muted">No hi ha dades del tècnic seleccionat en la taula TECNIC.</div>
        <?php endif; ?>
        </div>
    </div>

    <?php if (is_array($alert)) : ?>
        <div class="alert alert-<?php echo htmlspecialchars((string)$alert['type']); ?>" role="alert">
            <?php echo htmlspecialchars((string)$alert['message']); ?>
        </div>
    <?php endif; ?>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Incidències assignades</h2>
        <?php render_table_tecnic($assigned_rows, true, $filters ?? ['sort' => $sort, 'dir' => $dir, 'q' => $q, 'data' => $data, 'tecnic' => $tecnic_actual]); ?>
    </div>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Historial</h2>
        <?php render_table_tecnic($history_rows, false, $filters ?? ['sort' => $sort, 'dir' => $dir, 'q' => $q, 'data' => $data, 'tecnic' => $tecnic_actual]); ?>
        <?php render_historial_pagination($historial_page, $total_history_pages ?? 1, $filters ?? ['sort' => $sort, 'dir' => $dir, 'q' => $q, 'data' => $data, 'tecnic' => $tecnic_actual]); ?>
        <?php if (isset($total_history_rows)) : ?>
            <div class="form-text mt-2">Mostrant <?php echo min($historial_per_page, max(0, $total_history_rows - (($historial_page - 1) * $historial_per_page))); ?> de <?php echo (int)$total_history_rows; ?> registres.</div>
        <?php endif; ?>
    </div>

    <a class="btn btn-outline-secondary" href="/index.php">Tornar</a>
</div>
</div>

<?php include __DIR__ . '/../incidencies/footer.php'; ?>
