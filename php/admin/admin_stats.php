<?php
/**
 *  - Verifica permisos de administrador
 *  - Obtiene filtros desde la URL ($_GET)
 *  - Consulta logs de acceso almacenados en MongoDB
 *  - Consulta incidencias almacenadas en MySQL
 *  - Genera estadísticas para dashboards
 *  - Devuelve los datos en formato JSON
 *
 * Respuesta:
 * {
 *   "total": 120,
 *   "pagesCount": 15,
 *   "usersCount": 7,
 *   ...
 * }
 */
require_once __DIR__ . '/../incidencies/auth.php';
auth_require_role('ADMIN');

require_once __DIR__ . '/../incidencies/connexio.php'; //mysql
require_once __DIR__ . '/../incidencies/incidencies_schema.php'; //esquema de incidencias
require_once __DIR__ . '/../incidencies/access_logs_schema.php'; //esquema de logs de acceso
require_once __DIR__ . '/../incidencies/mongo_connexio.php'; //mongo
require_once __DIR__ . '/../incidencies/logger.php'; //sistema de logs

header('Content-Type: application/json; charset=utf-8'); //api responde en json

$errors = []; //guarda errores
//flags para sber si funcionan
$mongoOk = true;
$mysqlOk = true;

$schemaResult = ensure_incidencies_schema($conn); //comprobacion de errores de sql( tablas y campos) sino se crea
if (is_array($schemaResult) && (($schemaResult['ok'] ?? false) !== true)) {
    $mysqlOk = false;
    $errors['mysql'] = (string)($schemaResult['error'] ?? 'Error assegurant l\'esquema de incidències');
}


/**
 * Limpia y valida una fecha
 * y solo acepta formato
 *
 * YYYY-MM-DD
 * Ejemplos :
 *  - 2026-05-12
 *  - 2025-01-01
 *
 * @param string|null $value Fecha recibida
 *
 * @return string|null
 *  - Fecha válida
 *  - null si es inválida o vacía
 */

function clean_date(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    //validar formato 
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

//valores de filtros
$inicio = clean_date($_GET['inicio'] ?? null);
$fin = clean_date($_GET['fin'] ?? null);
$usuario = trim((string)($_GET['usuario'] ?? ''));
$pagina = trim((string)($_GET['pagina'] ?? ''));
//contruir filtros
$where = [];
$incidencies_where = [];

//valores por defecto : Así la API siempre devuelve una estructura JSON válida
$total = 0;
$pagesCount = 0;
$usersCount = 0;
$pages = [];
$users = [];
$trend = [];


/**
 * Genera $match válido para monogo.
 *
 * MongoDB no acepta arrays vacíos [] como documento.
 * Necesita un objeto vacío {}.
 *
 * @param array $match Filtros monogdb
 *
 * @return array|object
 */
function mongo_match_stage(array $match){
    return count($match) > 0 ? $match : (object)[];
}

//CONSTRUCCIÓN DE FILTROS SQL Y MONGO A PARTIR DE LOS PARAMETROS RECIBIDOS EN LA URL

if ($inicio !== null) {//filtro fecha inicio
    $i = $conn->real_escape_string($inicio);
    $where[] = "DATE(access_time) >= '$i'";
    $incidencies_where[] = "DATE(data_incidencia) >= '$i'";
}

if ($fin !== null) { //filtrpo fecha fin
    $f = $conn->real_escape_string($fin);
    $where[] = "DATE(access_time) <= '$f'";
    $incidencies_where[] = "DATE(data_incidencia) <= '$f'";
}

if ($usuario !== '') { //filtro tecnico asignado
    $u = $conn->real_escape_string($usuario);
    // Filtrar incidencias por técnico asignado
    $incidencies_where[] = "tecnic_assignat = '$u'";
}

if ($pagina !== '') { //filtor pagian viistada 
    $p = $conn->real_escape_string($pagina);
    $where[] = "page LIKE '%$p%'";
}
//where para sql y mongo se construye a partir de los filtros recibidos 
$filter = count($where) > 0 ? (' WHERE ' . implode(' AND ', $where)) : '';
$incidenciesFilter = count($incidencies_where) > 0 ? (' WHERE ' . implode(' AND ', $incidencies_where)) : '';

// ----------ESTADÍSTICAS DE ACCESOS (MongoDB)-----------------
try {
    $mongoMatch = []; // Array que tendra filtros MongoDB

    // Construcción del filtro de fechas
    if ($inicio !== null || $fin !== null) {
        if ($inicio !== null) {
            $start = new DateTimeImmutable($inicio . ' 00:00:00');
        } else {
            $start = new DateTimeImmutable('1970-01-01 00:00:00');
        }

        if ($fin !== null) {
            $end = new DateTimeImmutable($fin . ' 23:59:59');
        } else {
            $end = new DateTimeImmutable('2100-01-01 23:59:59');
        }

        $mongoMatch['timestamp'] = [  // Construcción filtro timestamp MongoDB
            '$gte' => new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000),
            '$lte' => new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000),
        ];
    }
    /*
        * Este bloque añade un filtro para buscar únicamente
        * los accesos realizados por un usuario concreto. 
         * Ejemplo URL:
        * ?usuario=alex
        *
        * MongoDB recibirá:
        * {
        *   "user": "alex"
        * }
        *
        * Esto significa:
        *  - solo devolver documentos donde
        *  - el campo "user" sea exactamente "alex"
        *
        * Ejemplo documento Mongo:
        * {
        *   "user": "alex",
        *   "url": "/dashboard",
        *   "timestamp": ...
        * }
        * */
    if ($usuario !== '') {
        $mongoMatch['user'] = $usuario;
    }

    if ($pagina !== '') {
        $mongoMatch['url'] = new MongoDB\BSON\Regex(preg_quote($pagina, '/'), 'i');
    }

    $mongoDb = mongo_db();
    $logs = $mongoDb->selectCollection('access_logs');

    $total = (int) $logs->countDocuments($mongoMatch); //total accesos

    $pagesCountAgg = $logs->aggregate([
        ['$match' => mongo_match_stage($mongoMatch)],
        ['$group' => ['_id' => '$url']],
        ['$count' => 'total'],
    ]);
    $pagesCountDoc = $pagesCountAgg->toArray();
    $pagesCountFirst = $pagesCountDoc[0] ?? null;
    if (is_object($pagesCountFirst)) {
        $pagesCount = (int)($pagesCountFirst->total ?? 0);
    } elseif (is_array($pagesCountFirst)) {
        $pagesCount = (int)($pagesCountFirst['total'] ?? 0);
    } else {
        $pagesCount = 0;
    }
    //TOTAL USUARIOS ÚNICOS
    $usersCountAgg = $logs->aggregate([
        ['$match' => mongo_match_stage(array_merge($mongoMatch, ['user' => ['$ne' => null]]))],
        ['$group' => ['_id' => '$user']],
        ['$count' => 'total'],
    ]);
    $usersCountDoc = $usersCountAgg->toArray();
    $usersCountFirst = $usersCountDoc[0] ?? null;
    if (is_object($usersCountFirst)) {
        $usersCount = (int)($usersCountFirst->total ?? 0);
    } elseif (is_array($usersCountFirst)) {
        $usersCount = (int)($usersCountFirst['total'] ?? 0);
    } else {
        $usersCount = 0;
    }
    //TOP 5 PÁGINAS MÁS VISITADAS
    $pagesAgg = $logs->aggregate([
        ['$match' => mongo_match_stage($mongoMatch)],
        ['$group' => ['_id' => '$url', 'total' => ['$sum' => 1]]],
        ['$sort' => ['total' => -1]],
        ['$limit' => 5],
        ['$project' => ['_id' => 0, 'page' => '$_id', 'total' => 1]],
    ]);
    foreach ($pagesAgg as $doc) { //conv  result a array php
        $pages[] = ['page' => (string)($doc->page ?? ''), 'total' => (int)($doc->total ?? 0)];
    }
    
    //TOP 5 USUARIOS MÁS ACTIVOS
    $usersAgg = $logs->aggregate([
        ['$match' => mongo_match_stage(array_merge($mongoMatch, ['user' => ['$ne' => null]]))],
        ['$group' => ['_id' => '$user', 'total' => ['$sum' => 1]]],
        ['$sort' => ['total' => -1]],
        ['$limit' => 5],
        ['$project' => ['_id' => 0, 'username' => '$_id', 'total' => 1]],
    ]);
    foreach ($usersAgg as $doc) {
        $users[] = ['username' => (string)($doc->username ?? ''), 'total' => (int)($doc->total ?? 0)];
    }
    //TENDENCIA ACCESOS POR DÍA
    $trendAgg = $logs->aggregate([
        ['$match' => mongo_match_stage($mongoMatch)],
        ['$group' => [
            '_id' => [
                '$dateToString' => [
                    'format' => '%Y-%m-%d',
                    'date' => '$timestamp',
                    'timezone' => 'UTC',
                ],
            ],
            'total' => ['$sum' => 1],
        ]],
        ['$sort' => ['_id' => 1]],
        ['$project' => ['_id' => 0, 'dia' => '$_id', 'total' => 1]],
    ]);
    foreach ($trendAgg as $doc) {
        $trend[] = ['dia' => (string)($doc->dia ?? ''), 'total' => (int)($doc->total ?? 0)];
    }
} catch (Throwable $e) {
    $mongoOk = false;  // Si Mongo falla -> guardar error
    $errors['mongo'] = $e->getMessage();
}
// ----------ESTADÍSTICAS DE INCIDENCIAS-----------------
/**
 * Obtiene num de incidencias
 * agrupadas por estado.
 *
 * Ejemplo:
 *  - assignada
 *  - tancada
 *  - pendent
 *  - rebutjada
 */
//INCIDENCIAS AGRUPADAS POR ESTADO
$incidenciesStatus = [];
$res = $conn->query("SELECT estat, COUNT(*) total FROM incidencies $incidenciesFilter GROUP BY estat ORDER BY total DESC");
if ($res === false) {
    $mysqlOk = false;
    if (!isset($errors['mysql'])) {
        $errors['mysql'] = 'Error consultant incidències per estat: ' . $conn->error;
    }
} else {
    while ($r = $res->fetch_assoc()) {
        $incidenciesStatus[] = $r;
    }
    $res->free();
}
//INCIDENCIAS POR DEPARTAMENTO Y PRIORIDAD
$incidenciesByDept = [];
$res = $conn->query("SELECT departament, prioritat, COUNT(*) total FROM incidencies $incidenciesFilter GROUP BY departament, prioritat ORDER BY total DESC");
if ($res === false) {
    $mysqlOk = false;
    if (!isset($errors['mysql'])) {
        $errors['mysql'] = 'Error consultant incidències per departament/prioritat: ' . $conn->error;
    }
} else {
    while ($r = $res->fetch_assoc()) {
        $incidenciesByDept[] = $r;
    }
    $res->free();
}

$deptMatrix = [];
/**
 * prioritats de incidencias
 */
//MAPA DE PRIORIDADES
$priorityMap = [
    'alta' => 'Alta',
    'mitja' => 'Mitja',
    'baixa' => 'Baixa',
];
/**
 * amb una "matriu" de deps y prioritats:
 *
 * Departamento =>
 *   Alta
 *   Mitja
 *   Baixa
 *   Total
 */
foreach ($incidenciesByDept as $row) {
    $dept = trim((string)($row['departament'] ?? ''));
    $prioritat = strtolower(trim((string)($row['prioritat'] ?? '')));
    $totalDept = (int)($row['total'] ?? 0);
    
    if ($dept === '') { //si no existe dept
        $dept = 'Sense departament';
    }
    //contadors
    if (!isset($deptMatrix[$dept])) {
        $deptMatrix[$dept] = ['Alta' => 0, 'Mitja' => 0, 'Baixa' => 0, 'total' => 0];
    }
    //prioridadvalida
    $priority = $priorityMap[$prioritat] ?? 'Mitja';
    //sumar incidencia
    $deptMatrix[$dept][$priority] += $totalDept;
    $deptMatrix[$dept]['total'] += $totalDept;
}
//ORDENAR DEPARTAMENTOS POR TOTAL
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
//AGRUPAR RESTO COMO "ALTRES"
if (count($deptOrdered) > count($deptLabels)) {
    $otherAlta = 0;
    $otherMitja = 0;
    $otherBaixa = 0;

    foreach (array_slice($deptOrdered, 6, null, true) as $restValues) {
        $otherAlta += (int)($restValues['Alta'] ?? 0);
        $otherMitja += (int)($restValues['Mitja'] ?? 0);
        $otherBaixa += (int)($restValues['Baixa'] ?? 0);
    }
    
    //TOP 6 DEPARTAMENTOS
    $deptLabels[] = 'Altres';
    $deptData['Alta'][] = $otherAlta;
    $deptData['Mitja'][] = $otherMitja;
    $deptData['Baixa'][] = $otherBaixa;
}
//Respuesta final de la API
/*
Fcionalidades:
 *  - obte estadísticas de accesos desde mongo
 *  - Consulta metricas(número, estado, usua unicos, paginas mas viistadas, accesos por dia) de incidencias desde MySQL
 *  - Procesa y organiza los datos
 *  - Devuelve  info en formato JSON
 * */
//RESPUESTA FINAL API
$response = [
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
    'mongoOk' => $mongoOk,
    'mysqlOk' => $mysqlOk,
];
//añdir errores si los hay
if (!empty($errors)) {
    $response['errors'] = $errors;
}
//json final devolver a frontend
echo json_encode($response);
