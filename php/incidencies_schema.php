<?php

// Helpers to keep the "incidencies" table schema compatible across environments.
// This project can be initialized either via db_init/create.sql (fresh DB) or via
// runtime creation (see crear_incidencia.php). Therefore we ensure the schema
// on demand.

if (!function_exists('taula_existeix')) {
	function taula_existeix(mysqli $conn, string $taula): bool
	{
		$taula_escapada = $conn->real_escape_string($taula);
		$result = $conn->query("SHOW TABLES LIKE '$taula_escapada'");
		if ($result === false) {
			return false;
		}

		$existeix = ($result->num_rows > 0);
		$result->free();
		return $existeix;
	}
}

if (!function_exists('columna_existeix')) {
	function columna_existeix(mysqli $conn, string $taula, string $columna): bool
	{
		$taula_escapada = $conn->real_escape_string($taula);
		$columna_escapada = $conn->real_escape_string($columna);
		$result = $conn->query("SHOW COLUMNS FROM `$taula_escapada` LIKE '$columna_escapada'");
		if ($result === false) {
			return false;
		}

		$existeix = ($result->num_rows > 0);
		$result->free();
		return $existeix;
	}
}

if (!defined('INCIDENCIA_ESTAT_PENDENT_ASSIGNAR')) {
	define('INCIDENCIA_ESTAT_PENDENT_ASSIGNAR', 'pendent_assignar');
}
if (!defined('INCIDENCIA_ESTAT_ASSIGNADA')) {
	define('INCIDENCIA_ESTAT_ASSIGNADA', 'assignada');
}
if (!defined('INCIDENCIA_ESTAT_TANCADA')) {
	define('INCIDENCIA_ESTAT_TANCADA', 'tancada');
}

if (!defined('INCIDENCIA_TECNIC_PER_DEFECTE')) {
	// This project currently has role-based screens without per-user login.
	// We still store who it's assigned to, so we use a single default label.
	define('INCIDENCIA_TECNIC_PER_DEFECTE', 'Tècnic');
}

function ensure_incidencies_schema(mysqli $conn): array
{
	if (!taula_existeix($conn, 'incidencies')) {
		$create_sql = "CREATE TABLE IF NOT EXISTS incidencies (
			id INT AUTO_INCREMENT PRIMARY KEY,
			departament VARCHAR(80) NOT NULL,
			descripcio_curta VARCHAR(255) NOT NULL,
			data_incidencia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			estat VARCHAR(30) NOT NULL DEFAULT '" . INCIDENCIA_ESTAT_PENDENT_ASSIGNAR . "',
			tecnic_assignat VARCHAR(80) NULL,
			data_tancament TIMESTAMP NULL DEFAULT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

		if ($conn->query($create_sql) === false) {
			return [
				'ok' => false,
				'error' => "No s'ha pogut crear la taula incidencies: " . $conn->error,
			];
		}
	}

	// Ensure required base columns exist.
	$required_columns = [
		'departament' => "ALTER TABLE incidencies ADD COLUMN departament VARCHAR(80) NOT NULL",
		'descripcio_curta' => "ALTER TABLE incidencies ADD COLUMN descripcio_curta VARCHAR(255) NOT NULL",
		'data_incidencia' => "ALTER TABLE incidencies ADD COLUMN data_incidencia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
		'estat' => "ALTER TABLE incidencies ADD COLUMN estat VARCHAR(30) NOT NULL DEFAULT '" . INCIDENCIA_ESTAT_PENDENT_ASSIGNAR . "'",
		'tecnic_assignat' => "ALTER TABLE incidencies ADD COLUMN tecnic_assignat VARCHAR(80) NULL",
		'data_tancament' => "ALTER TABLE incidencies ADD COLUMN data_tancament TIMESTAMP NULL DEFAULT NULL",
	];

	foreach ($required_columns as $column => $alter_sql) {
		if (!columna_existeix($conn, 'incidencies', $column)) {
			if ($conn->query($alter_sql) === false) {
				return [
					'ok' => false,
					'error' => "No s'ha pogut afegir la columna $column: " . $conn->error,
				];
			}
		}
	}

	// Normalize existing data to new states.
	$normalized_ok = $conn->query("UPDATE incidencies SET estat = '" . INCIDENCIA_ESTAT_PENDENT_ASSIGNAR . "' WHERE estat IS NULL OR estat = ''");
	if ($normalized_ok === false) {
		return [
			'ok' => false,
			'error' => "No s'ha pogut normalitzar la columna estat: " . $conn->error,
		];
	}

	$conn->query("UPDATE incidencies SET tecnic_assignat = NULL WHERE tecnic_assignat = ''");

	return [
		'ok' => true,
	];
}
