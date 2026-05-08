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

$tecnics_disponibles = [];
if ($schema_ok) {
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

$prioritat_filter = strtolower(trim((string)($_POST['prioritat_filter'] ?? $_GET['prioritat_filter'] ?? '')));
$tipologia_filter = strtolower(trim((string)($_POST['tipologia_filter'] ?? $_GET['tipologia_filter'] ?? '')));

//valors que pot tenir prioritat
$prioritats_valides = [
    INCIDENCIA_PRIORITAT_BAIXA,
    INCIDENCIA_PRIORITAT_MITJA,
    INCIDENCIA_PRIORITAT_ALTA,
];
//valors que pot tenir tipologia
$tipologies_valides = [
    INCIDENCIA_TIPOLOGIA_HARDWARE,
    INCIDENCIA_TIPOLOGIA_SOFTWARE,
    INCIDENCIA_TIPOLOGIA_XARXA,
    INCIDENCIA_TIPOLOGIA_COMPTES,
    INCIDENCIA_TIPOLOGIA_IMPRESSIO,
    INCIDENCIA_TIPOLOGIA_AULES,
    INCIDENCIA_TIPOLOGIA_MOBILS,
    INCIDENCIA_TIPOLOGIA_PLATAFORMES,
    INCIDENCIA_TIPOLOGIA_SEGURETAT,
];



$historial_page = max(1, (int)($_POST['historial_page'] ?? $_GET['historial_page'] ?? 1));
$historial_per_page = 10;

$sort_valids = ['id', 'departament', 'data'];
if (!in_array($sort, $sort_valids, true)) {
    $sort = 'data';
}
if (!in_array($dir, ['asc', 'desc'], true)) {
    $dir = 'desc';
}

if ($prioritat_filter !== '' && !in_array($prioritat_filter, $prioritats_valides, true)) {
    $prioritat_filter = '';
}
if ($tipologia_filter !== '' && !in_array($tipologia_filter, $tipologies_valides, true)) {
    $tipologia_filter = '';
}
// Accept YYYY-MM-DD only; otherwise ignore.
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schema_ok) {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    $estats_editables = [
        INCIDENCIA_ESTAT_PENDENT_ASSIGNAR,
        INCIDENCIA_ESTAT_ASSIGNADA,
    ];

    if ($id > 0 && $action === 'assignar') {

    $tecnic_assignacio = trim((string)($_POST['tecnic'] ?? ''));

    if ($tecnic_assignacio === '') {
        $alert = ['type' => 'warning', 'message' => "Selecciona un tècnic."];
    } else {

        $stmt = $conn->prepare("
            UPDATE incidencies 
            SET estat = ?, tecnic_assignat = ? 
            WHERE id = ?
        ");

        if ($stmt === false) {
            $alert = ['type' => 'danger', 'message' => $conn->error];
        } else {

            $estat_assignada = INCIDENCIA_ESTAT_ASSIGNADA;

            $stmt->bind_param('ssi', $estat_assignada, $tecnic_assignacio, $id);

            if ($stmt->execute()) {
                $alert = ['type' => 'success', 'message' => "Incidència #$id assignada."];
            } else {
                $alert = ['type' => 'warning', 'message' => "No s'ha pogut assignar #$id."];
            }

            $stmt->close();
        }
    }
}
    if ($id > 0 && $action === 'editar') {

    $nova_prioritat = strtolower(trim((string)($_POST['prioritat'] ?? '')));
    $nova_tipologia = strtolower(trim((string)($_POST['tipologia'] ?? '')));

    if (!in_array($nova_prioritat, $prioritats_valides, true)) {
        $alert = ['type' => 'warning', 'message' => "Prioritat no vàlida per la incidència #$id."];
    } elseif (!in_array($nova_tipologia, $tipologies_valides, true)) {
        $alert = ['type' => 'warning', 'message' => "Tipologia no vàlida per la incidència #$id."];
    } else {

        $stmt = $conn->prepare("
            UPDATE incidencies 
            SET prioritat = ?, tipologia = ? 
            WHERE id = ?
        ");

        if ($stmt === false) {
            $alert = ['type' => 'danger', 'message' => 'Error: ' . $conn->error];
        } else {

            $stmt->bind_param('ssi', $nova_prioritat, $nova_tipologia, $id);

            if ($stmt->execute()) {
                $alert = ['type' => 'success', 'message' => "Incidència #$id actualitzada."];
            } else {
                $alert = ['type' => 'warning', 'message' => "No s'ha pogut actualitzar #$id."];
            }

            $stmt->close();
        }
    }
}
    if ($id > 0 && $action === 'rebutjar') {
        $placeholders = implode(',', array_fill(0, count($estats_editables), '?'));
        $sql = "UPDATE incidencies SET estat = ?, tecnic_assignat = NULL, data_inici_tasca = NULL, data_tancament = NOW() WHERE id = ? AND estat IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $alert = ['type' => 'danger', 'message' => 'Error preparant la consulta: ' . $conn->error];
        } else {
            $estat_rebutjada = INCIDENCIA_ESTAT_REBUTJADA;
            $types = 'si' . str_repeat('s', count($estats_editables));
            $params = array_merge([$estat_rebutjada, $id], $estats_editables);
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $alert = ['type' => 'success', 'message' => "Incidència #$id rebutjada."];
            } else {
                $alert = ['type' => 'warning', 'message' => "No s'ha pogut rebutjar la incidència #$id (potser ja està tancada o rebutjada)."];
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

	// PRG: redirect to GET so the page refreshes and avoids resubmission.
	if ($action !== '' && $id > 0 && is_array($alert)) {
		$redirect_params = [
			'sort' => $sort,
			'dir' => $dir,
			'q' => $q,
			'data' => $data,
            'prioritat_filter' => $prioritat_filter,
			'historial_page' => $historial_page,
			'flash_type' => (string)($alert['type'] ?? 'info'),
			'flash_msg' => (string)($alert['message'] ?? ''),
		];
		header('Location: responsable_tecnic.php?' . http_build_query($redirect_params));
		exit;
	}
}

function render_table_responsable(array $rows, string $mode, array $filters): void
{
    // mode: 'pendent' | 'assignada' | 'historial'
    if (count($rows) === 0) {
        echo "<div class='text-muted'>No hi ha incidències per mostrar.</div>";
        return;
    }
    //asignem valor a les variables de prioritat, tipologia i estat per mostrar-les a la taula
    $prio_labels = [
        INCIDENCIA_PRIORITAT_ALTA => 'Alta',
        INCIDENCIA_PRIORITAT_MITJA => 'Mitja',
        INCIDENCIA_PRIORITAT_BAIXA => 'Baixa',
    ];
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

    $estat_labels = [
        INCIDENCIA_ESTAT_PENDENT_ASSIGNAR => "Pendent",
        INCIDENCIA_ESTAT_ASSIGNADA => "Assignada",
        INCIDENCIA_ESTAT_TANCADA => "Tancada",
        INCIDENCIA_ESTAT_REBUTJADA => "Rebutjada",
    ];

    echo "<div class='table-responsive scrollable-list'>";
    echo "<table class='table table-sm table-striped align-middle'>";
    echo "<thead><tr><th scope='col'>ID</th><th scope='col'>Departament</th><th scope='col'>Descripció</th><th scope='col'>Prioritat</th><th scope='col'>Tipologia</th>";
	if ($mode === 'historial') {
		echo "<th scope='col'>Estat</th>";
	}
	echo "<th scope='col'>Data</th>";
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
        $desc_raw = (string)($row['descripcio_curta'] ?? '');
        $desc = htmlspecialchars($desc_raw);
        $prio_raw = strtolower(trim((string)($row['prioritat'] ?? INCIDENCIA_PRIORITAT_MITJA)));
        $prio_label = htmlspecialchars($prio_labels[$prio_raw] ?? 'Mitja');
        $tipo_raw = strtolower(trim((string)($row['tipologia'] ?? '')));
        $tipo_label = htmlspecialchars($tipo_labels[$tipo_raw] ?? ($tipo_raw !== '' ? ucfirst($tipo_raw) : '—'));
        $estat_raw = (string)($row['estat'] ?? '');
        $estat_label = htmlspecialchars($estat_labels[$estat_raw] ?? $estat_raw);
        $data = htmlspecialchars((string)($row['data'] ?? ''));
        $tecnic = htmlspecialchars((string)($row['tecnic_assignat'] ?? ''));

        echo "<tr>";
        echo "<th scope='row'>$id</th>";
        echo "<td>$dep</td>";
        echo "<td>$desc</td>";
        echo "<td><span class='prio-badge prio-" . htmlspecialchars($prio_raw, ENT_QUOTES) . "'>$prio_label</span></td>";
        echo "<td>$tipo_label</td>";
		if ($mode === 'historial') {
			echo "<td>" . ($estat_label !== '' ? $estat_label : "<span class='text-muted'>—</span>") . "</td>";
		}
        echo "<td>$data</td>";

        if ($mode !== 'pendent') {
            echo "<td>" . ($tecnic !== '' ? $tecnic : "<span class='text-muted'>—</span>") . "</td>";
        }

        if ($mode === 'pendent') {
            echo "<td>";
            echo "<button type='button' class='btn btn-sm btn-outline-primary me-2' data-bs-toggle='modal' data-bs-target='#assignarModal' data-incidencia-id='" . (int)$id . "'>Assignar</button>";
            $prio_attr = htmlspecialchars($prio_raw, ENT_QUOTES);
            echo "<button type='button' class='btn btn-sm btn-outline-secondary me-2' data-bs-toggle='modal' data-bs-target='#editarModal' data-incidencia-id='" . (int)$id . "' data-incidencia-prio='" . $prio_attr . "'>Editar</button>";
            echo "<form method='POST' class='d-inline' onsubmit=\"return confirm('Segur que vols rebutjar la incidència #" . (int)$id . "?');\">";
            echo "<input type='hidden' name='sort' value='" . htmlspecialchars((string)($filters['sort'] ?? '')) . "'>";
            echo "<input type='hidden' name='dir' value='" . htmlspecialchars((string)($filters['dir'] ?? '')) . "'>";
            echo "<input type='hidden' name='q' value='" . htmlspecialchars((string)($filters['q'] ?? '')) . "'>";
            echo "<input type='hidden' name='data' value='" . htmlspecialchars((string)($filters['data'] ?? '')) . "'>";
            echo "<input type='hidden' name='prioritat_filter' value='" . htmlspecialchars((string)($filters['prioritat_filter'] ?? '')) . "'>";
            echo "<input type='hidden' name='action' value='rebutjar'>";
            echo "<input type='hidden' name='id' value='" . (int)$id . "'>";
            echo "<button type='submit' class='btn btn-sm btn-outline-danger'>Rebutjar</button>";
            echo "</form>";
            echo "</td>";
        }

        if ($mode === 'assignada') {
            echo "<td>";
			$prio_attr = htmlspecialchars($prio_raw, ENT_QUOTES);
            echo "<button type='button' class='btn btn-sm btn-outline-secondary me-2' data-bs-toggle='modal' data-bs-target='#editarModal' data-incidencia-id='" . (int)$id . "' data-incidencia-prio='" . $prio_attr . "'>Editar</button>";
            echo "<form method='POST' class='d-inline'>";
            echo "<input type='hidden' name='sort' value='" . htmlspecialchars((string)($filters['sort'] ?? '')) . "'>";
            echo "<input type='hidden' name='dir' value='" . htmlspecialchars((string)($filters['dir'] ?? '')) . "'>";
            echo "<input type='hidden' name='q' value='" . htmlspecialchars((string)($filters['q'] ?? '')) . "'>";
            echo "<input type='hidden' name='data' value='" . htmlspecialchars((string)($filters['data'] ?? '')) . "'>";
            echo "<input type='hidden' name='prioritat_filter' value='" . htmlspecialchars((string)($filters['prioritat_filter'] ?? '')) . "'>";
            echo "<input type='hidden' name='action' value='desassignar'>";
            echo "<input type='hidden' name='id' value='" . (int)$id . "'>";
            echo "<button type='submit' class='btn btn-sm btn-outline-warning'>Desassignar</button>";
            echo "</form>";
            echo "<form method='POST' class='d-inline ms-2' onsubmit=\"return confirm('Segur que vols rebutjar la incidència #" . (int)$id . "?');\">";
            echo "<input type='hidden' name='sort' value='" . htmlspecialchars((string)($filters['sort'] ?? '')) . "'>";
            echo "<input type='hidden' name='dir' value='" . htmlspecialchars((string)($filters['dir'] ?? '')) . "'>";
            echo "<input type='hidden' name='q' value='" . htmlspecialchars((string)($filters['q'] ?? '')) . "'>";
            echo "<input type='hidden' name='data' value='" . htmlspecialchars((string)($filters['data'] ?? '')) . "'>";
            echo "<input type='hidden' name='prioritat_filter' value='" . htmlspecialchars((string)($filters['prioritat_filter'] ?? '')) . "'>";
            echo "<input type='hidden' name='action' value='rebutjar'>";
            echo "<input type='hidden' name='id' value='" . (int)$id . "'>";
            echo "<button type='submit' class='btn btn-sm btn-outline-danger'>Rebutjar</button>";
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
        'prioritat_filter' => (string)($filters['prioritat_filter'] ?? ''),
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

$pending_rows = [];
$assigned_rows = [];
$history_rows = [];

if ($schema_ok) {
    $filters = [
        'sort' => $sort,
        'dir' => $dir,
        'q' => $q,
        'data' => $data,
        'prioritat_filter' => $prioritat_filter,
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

    $build_where = function (bool $is_history) use ($q, $data, $prioritat_filter, $tipologia_filter): array {
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

        if ($prioritat_filter !== '') {
            $where[] = 'prioritat = ?';
            $types .= 's';
            $params[] = $prioritat_filter;
        }
        if ($tipologia_filter !== '') {
            $where[] = 'tipologia = ?';
             $types .= 's';
            $params[] = $tipologia_filter;
        }

        return [$where, $types, $params];
    };

    $estat_pendent = INCIDENCIA_ESTAT_PENDENT_ASSIGNAR;
    $estat_assignada = INCIDENCIA_ESTAT_ASSIGNADA;
    $estat_tancada = INCIDENCIA_ESTAT_TANCADA;
	$estat_rebutjada = INCIDENCIA_ESTAT_REBUTJADA;

    // Pendents
    list($where_parts, $types, $params) = $build_where(false);
    $where_sql = 'estat = ?';
    if (count($where_parts) > 0) {
        $where_sql .= ' AND ' . implode(' AND ', $where_parts);
    }
    $order_col = $order_map_pending_assigned[$sort] ?? 'data_incidencia';
    //mostrar incidencia pendents de assignar
    $sql1 = "SELECT id, departament, descripcio_curta, prioritat,tipologia, data_incidencia FROM incidencies WHERE $where_sql ORDER BY $order_col $dir";
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
						'prioritat' => $row['prioritat'] ?? INCIDENCIA_PRIORITAT_MITJA,
                        'tipologia' => $row['tipologia'] ?? INCIDENCIA_TIPOLOGIA_HARDWARE,
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
    //mostrar incidencias assignades
    $sql2 = "SELECT id, departament, descripcio_curta,  prioritat, tipologia, data_incidencia, tecnic_assignat FROM incidencies WHERE $where_sql2 ORDER BY $order_col2 $dir";
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
						'prioritat' => $row['prioritat'] ?? INCIDENCIA_PRIORITAT_MITJA,
                        'tipologia' => $row['tipologia'] ?? INCIDENCIA_TIPOLOGIA_HARDWARE,
                        'data' => $row['data_incidencia'],
                        'tecnic_assignat' => $row['tecnic_assignat'],
                    ];
                }
            }
        }
        $stmt2->close();
    }

    // Historial (tancades + rebutjades)
    list($where_parts3, $types3, $params3) = $build_where(true);
    $where_sql3 = '(estat = ? OR estat = ?)';
    if (count($where_parts3) > 0) {
        $where_sql3 .= ' AND ' . implode(' AND ', $where_parts3);
    }
    $order_col3 = $order_map_history[$sort] ?? 'data_hist';
    $count_sql3 = "SELECT COUNT(*) AS total FROM incidencies WHERE $where_sql3";
    $total_history_rows = 0;
    $stmt3_count = $conn->prepare($count_sql3);
    if ($stmt3_count !== false) {
        $bind_types3_count = 'ss' . $types3;
        $bind_values3_count = array_merge([$estat_tancada, $estat_rebutjada], $params3);
        $stmt3_count->bind_param($bind_types3_count, ...$bind_values3_count);
        if ($stmt3_count->execute()) {
            $resCount = $stmt3_count->get_result();
            if ($resCount !== false) {
                $countRow = $resCount->fetch_assoc();
                $total_history_rows = (int)($countRow['total'] ?? 0);
            }
        }
        $stmt3_count->close();
    }

    $total_history_pages = max(1, (int)ceil($total_history_rows / $historial_per_page));
    $historial_page = min($historial_page, $total_history_pages);
    $historial_offset = ($historial_page - 1) * $historial_per_page;

    $sql3 = "SELECT id, departament, descripcio_curta, prioritat, estat, COALESCE(data_tancament, data_incidencia) AS data_hist, tecnic_assignat FROM incidencies WHERE $where_sql3 ORDER BY $order_col3 $dir LIMIT ? OFFSET ?";
    $stmt3 = $conn->prepare($sql3);
    if ($stmt3 !== false) {
        $bind_types3 = 'ss' . $types3 . 'ii';
        $bind_values3 = array_merge([$estat_tancada, $estat_rebutjada], $params3, [$historial_per_page, $historial_offset]);
        $stmt3->bind_param($bind_types3, ...$bind_values3);
        if ($stmt3->execute()) {
            $res = $stmt3->get_result();
            if ($res !== false) {
                while ($row = $res->fetch_assoc()) {
                    $history_rows[] = [
                        'id' => $row['id'],
                        'departament' => $row['departament'],
                        'descripcio_curta' => $row['descripcio_curta'],
						'prioritat' => $row['prioritat'] ?? INCIDENCIA_PRIORITAT_MITJA,
						'estat' => $row['estat'] ?? '',
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

<?php include __DIR__ . '/../incidencies/header.php'; ?>

<link rel="stylesheet" href="/css/tecnic.css">

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

            <div class="col-12 col-md-3">
                <label for="prioritat_filter" class="form-label mb-1">Prioritat</label>
                <select id="prioritat_filter" name="prioritat_filter" class="form-select">
                    <option value="" <?php echo $prioritat_filter === '' ? 'selected' : ''; ?>>Totes</option>
                    <option value="alta" <?php echo $prioritat_filter === 'alta' ? 'selected' : ''; ?>>Alta</option>
                    <option value="mitja" <?php echo $prioritat_filter === 'mitja' ? 'selected' : ''; ?>>Mitja</option>
                    <option value="baixa" <?php echo $prioritat_filter === 'baixa' ? 'selected' : ''; ?>>Baixa</option>
                </select>
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
        <?php render_table_responsable($pending_rows, 'pendent', $filters ?? ['sort' => $sort, 'dir' => $dir, 'q' => $q, 'data' => $data, 'prioritat_filter' => $prioritat_filter]); ?>
        <div class="form-text">En assignar, podràs escollir el tècnic i es marcarà com a <strong>assignada</strong>.</div>
    </div>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Assignades</h2>
        <?php render_table_responsable($assigned_rows, 'assignada', $filters ?? ['sort' => $sort, 'dir' => $dir, 'q' => $q, 'data' => $data, 'prioritat_filter' => $prioritat_filter]); ?>
    </div>

    <div class="card card-body mb-4">
        <h2 class="h5 mb-3">Historial (totes)</h2>
        <?php render_table_responsable($history_rows, 'historial', $filters ?? ['sort' => $sort, 'dir' => $dir, 'q' => $q, 'data' => $data, 'prioritat_filter' => $prioritat_filter]); ?>
        <?php render_historial_pagination($historial_page, $total_history_pages ?? 1, $filters ?? ['sort' => $sort, 'dir' => $dir, 'q' => $q, 'data' => $data, 'prioritat_filter' => $prioritat_filter]); ?>
        <?php if (isset($total_history_rows)) : ?>
            <div class="form-text mt-2">Mostrant <?php echo min($historial_per_page, max(0, $total_history_rows - (($historial_page - 1) * $historial_per_page))); ?> de <?php echo (int)$total_history_rows; ?> registres.</div>
        <?php endif; ?>
    </div>

    <a class="btn btn-outline-secondary" href="/index.php">Tornar</a>
</div>

    <!-- Modal: Editar només prioritat -->
    <div class="modal fade" id="editarModal" tabindex="-1" aria-labelledby="editarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarModalLabel">Editar incidència</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tancar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Editant la incidència <span class="fw-bold">#<span id="editarIncidenciaId">—</span></span>.</p>
                     <!-- valors modificar al editar -->
                    <form method="POST" id="editarForm">
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars((string)$sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo htmlspecialchars((string)$dir); ?>">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars((string)$q); ?>">
                        <input type="hidden" name="data" value="<?php echo htmlspecialchars((string)$data); ?>">
                        <input type="hidden" name="prioritat_filter" value="<?php echo htmlspecialchars((string)$prioritat_filter); ?>">
                        <input type="hidden" name="action" value="editar">
                        <input type="hidden" name="id" id="editarId" value="0">
                        <!--  editar prioritat -->
                        <div class="mb-3">
                            <label for="editarPrioritat" class="form-label">Prioritat</label>
                            <select class="form-select" id="editarPrioritat" name="prioritat" required>
                                <option value="baixa">Baixa</option>
                                <option value="mitja">Mitja</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                         <!-- //editar tipologia -->
                        <div class="mb-3">
                            <label for="editarTipologia" class="form-label">Tipologia</label>

                            <select class="form-select" id="editarTipologia" name="tipologia" required>
                                <option value="hardware">Hardware</option>
                                <option value="software">Software</option>
                                <option value="xarxa">Xarxa</option>
                                <option value="comptes">Comptes</option>
                                <option value="impressio">Impressió</option>
                                <option value="aules">Aules</option>
                                <option value="mobils">Mòbils</option>
                                <option value="plataformes">Plataformes</option>
                                <option value="seguretat">Seguretat</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Desar canvis</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel·lar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
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
                    <input type="hidden" name="prioritat_filter" value="<?php echo htmlspecialchars((string)$prioritat_filter); ?>">
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

<script>
    (function() {
        var modal = document.getElementById('editarModal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            if (!button) return;
            var id = button.getAttribute('data-incidencia-id') || '0';
            var prio = (button.getAttribute('data-incidencia-prio') || 'mitja').toLowerCase();

            var input = document.getElementById('editarId');
            var label = document.getElementById('editarIncidenciaId');
            var select = document.getElementById('editarPrioritat');
            if (input) input.value = id;
            if (label) label.textContent = id;
            if (select) select.value = (['baixa','mitja','alta'].includes(prio) ? prio : 'mitja');
        });
    })();
</script>

<?php include __DIR__ . '/../incidencies/footer.php'; ?>
