<?php
/**
 * Esquema de logs de acceso en MySQL (fallback cuando MongoDB no está disponible).
 *
 * Tabla: `access_logs` (username, page, access_time).
 * Nota: el seeding de datos demo está desactivado por defecto.
 */

function ensure_access_logs_schema($conn, bool $seedDemo = false): void
{

$sql="
CREATE TABLE IF NOT EXISTS access_logs(
id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(100),
page VARCHAR(150),
access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

$conn->query($sql);


// Dades demo només si s'activa explícitament.
// Això evita “logs falsos” en producció.
$seedDemo = $seedDemo || (trim((string)(getenv('SEED_DEMO_ACCESS_LOGS') ?: '')) === '1');
if (!$seedDemo) {
	return;
}

$cRes = $conn->query("SELECT COUNT(*) c FROM access_logs");
if ($cRes === false) {
	return;
}

$row = $cRes->fetch_assoc();
$cRes->free();
$c = (int)($row['c'] ?? 0);

if ($c !== 0) {
	return;
}

$demo = [
	['admin', 'admin.php'],
	['professor', 'crear_incidencia.php'],
	['tecnic', 'todas_las_incidencias.php'],
	['admin', 'incidencies.php'],
	['professor', 'crear_incidencia.php'],
	['admin', 'admin.php'],
];

$stmt = $conn->prepare('INSERT INTO access_logs(username, page) VALUES(?, ?)');
if ($stmt === false) {
	return;
}

foreach ($demo as $d) {
	$u = (string)($d[0] ?? '');
	$p = (string)($d[1] ?? '');
	$stmt->bind_param('ss', $u, $p);
	@$stmt->execute();
}

$stmt->close();
}