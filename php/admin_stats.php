<?php

require_once 'connexio.php';
require_once 'access_logs_schema.php';
require_once 'incidencies_schema.php';

header('Content-Type: application/json; charset=utf-8');

ensure_access_logs_schema($conn);
ensure_incidencies_schema($conn);

function clean_date(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

$inicio = clean_date($_GET['inicio'] ?? null);
$fin = clean_date($_GET['fin'] ?? null);
$usuario = trim((string)($_GET['usuario'] ?? ''));
$pagina = trim((string)($_GET['pagina'] ?? ''));

$where = [];
$incidencies_where = [];

if ($inicio !== null) {
    $i = $conn->real_escape_string($inicio);
    $where[] = "DATE(access_time) >= '$i'";
    $incidencies_where[] = "DATE(data_incidencia) >= '$i'";
}

if ($fin !== null) {
    $f = $conn->real_escape_string($fin);
    $where[] = "DATE(access_time) <= '$f'";
    $incidencies_where[] = "DATE(data_incidencia) <= '$f'";
}

if ($usuario !== '') {
    $u = $conn->real_escape_string($usuario);
    // Filtrar incidencias por técnico asignado
    $incidencies_where[] = "tecnic_assignat = '$u'";
}

if ($pagina !== '') {
    $p = $conn->real_escape_string($pagina);
    $where[] = "page LIKE '%$p%'";
}

$filter = count($where) > 0 ? (' WHERE ' . implode(' AND ', $where)) : '';
$incidenciesFilter = count($incidencies_where) > 0 ? (' WHERE ' . implode(' AND ', $incidencies_where)) : '';

$total = (int)($conn->query("SELECT COUNT(*) total FROM access_logs $filter")->fetch_assoc()['total'] ?? 0);
$pagesCount = (int)($conn->query("SELECT COUNT(DISTINCT page) total FROM access_logs $filter")->fetch_assoc()['total'] ?? 0);
$usersCount = (int)($conn->query("SELECT COUNT(DISTINCT username) total FROM access_logs $filter")->fetch_assoc()['total'] ?? 0);

$pages = [];
$res = $conn->query("SELECT page, COUNT(*) total FROM access_logs $filter GROUP BY page ORDER BY total DESC LIMIT 5");
if ($res !== false) {
    while ($r = $res->fetch_assoc()) {
        $pages[] = $r;
    }
    $res->free();
}

$users = [];
$res = $conn->query("SELECT username, COUNT(*) total FROM access_logs $filter GROUP BY username ORDER BY total DESC LIMIT 5");
if ($res !== false) {
    while ($r = $res->fetch_assoc()) {
        $users[] = $r;
    }
    $res->free();
}

$trend = [];
$res = $conn->query("SELECT DATE(access_time) dia, COUNT(*) total FROM access_logs $filter GROUP BY dia ORDER BY dia");
if ($res !== false) {
    while ($r = $res->fetch_assoc()) {
        $trend[] = $r;
    }
    $res->free();
}

$incidenciesStatus = [];
$res = $conn->query("SELECT estat, COUNT(*) total FROM incidencies $incidenciesFilter GROUP BY estat ORDER BY total DESC");
if ($res !== false) {
    while ($r = $res->fetch_assoc()) {
        $incidenciesStatus[] = $r;
    }
    $res->free();
}

$incidenciesByDept = [];
$res = $conn->query("SELECT departament, estat, COUNT(*) total FROM incidencies $incidenciesFilter GROUP BY departament, estat ORDER BY total DESC");
if ($res !== false) {
    while ($r = $res->fetch_assoc()) {
        $incidenciesByDept[] = $r;
    }
    $res->free();
}

$deptMatrix = [];
$priorityMap = [
    'pendent_assignar' => 'Alta',
    'assignada' => 'Mitja',
    'tancada' => 'Baixa',
];

foreach ($incidenciesByDept as $row) {
    $dept = trim((string)($row['departament'] ?? ''));
    $estat = (string)($row['estat'] ?? '');
    $totalDept = (int)($row['total'] ?? 0);

    if ($dept === '') {
        $dept = 'Sense departament';
    }

    if (!isset($deptMatrix[$dept])) {
        $deptMatrix[$dept] = ['Alta' => 0, 'Mitja' => 0, 'Baixa' => 0, 'total' => 0];
    }

    $priority = $priorityMap[$estat] ?? 'Mitja';
    $deptMatrix[$dept][$priority] += $totalDept;
    $deptMatrix[$dept]['total'] += $totalDept;
}

$deptOrdered = $deptMatrix;
uasort($deptOrdered, function ($left, $right) {
    return (($right['total'] ?? 0) <=> ($left['total'] ?? 0));
});

$deptLabels = array_slice(array_keys($deptOrdered), 0, 6);
$deptData = ['Alta' => [], 'Mitja' => [], 'Baixa' => []];

foreach ($deptLabels as $dept) {
    $deptData['Alta'][] = (int)($deptOrdered[$dept]['Alta'] ?? 0);
    $deptData['Mitja'][] = (int)($deptOrdered[$dept]['Mitja'] ?? 0);
    $deptData['Baixa'][] = (int)($deptOrdered[$dept]['Baixa'] ?? 0);
}

if (count($deptOrdered) > count($deptLabels)) {
    $otherAlta = 0;
    $otherMitja = 0;
    $otherBaixa = 0;

    foreach (array_slice($deptOrdered, 6, null, true) as $restValues) {
        $otherAlta += (int)($restValues['Alta'] ?? 0);
        $otherMitja += (int)($restValues['Mitja'] ?? 0);
        $otherBaixa += (int)($restValues['Baixa'] ?? 0);
    }

    $deptLabels[] = 'Altres';
    $deptData['Alta'][] = $otherAlta;
    $deptData['Mitja'][] = $otherMitja;
    $deptData['Baixa'][] = $otherBaixa;
}

echo json_encode([
    'total' => $total,
    'pagesCount' => $pagesCount,
    'usersCount' => $usersCount,
    'pages' => $pages,
    'users' => $users,
    'trend' => $trend,
    'incidencies' => [
        'status' => $incidenciesStatus,
        'deptLabels' => $deptLabels,
        'deptPriority' => $deptData,
    ],
]);
