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
if (!defined('INCIDENCIA_ESTAT_REBUTJADA')) {
	define('INCIDENCIA_ESTAT_REBUTJADA', 'rebutjada');
}

if (!defined('INCIDENCIA_PRIORITAT_BAIXA')) {
	define('INCIDENCIA_PRIORITAT_BAIXA', 'baixa');
}
if (!defined('INCIDENCIA_PRIORITAT_MITJA')) {
	define('INCIDENCIA_PRIORITAT_MITJA', 'mitja');
}
if (!defined('INCIDENCIA_PRIORITAT_ALTA')) {
	define('INCIDENCIA_PRIORITAT_ALTA', 'alta');
}
//añadir tablas de tipologia 
if (!defined('INCIDENCIA_TIPOLOGIA_HARDWARE')) {
	define('INCIDENCIA_TIPOLOGIA_HARDWARE', 'hardware');
}
if (!defined('INCIDENCIA_TIPOLOGIA_SOFTWARE')) {
	define('INCIDENCIA_TIPOLOGIA_SOFTWARE', 'software');
}
if (!defined('INCIDENCIA_TIPOLOGIA_XARXA')) {
	define('INCIDENCIA_TIPOLOGIA_XARXA', 'xarxa');
}
if (!defined('INCIDENCIA_TIPOLOGIA_COMPTES')) {
	define('INCIDENCIA_TIPOLOGIA_COMPTES', 'comptes');
}
if (!defined('INCIDENCIA_TIPOLOGIA_IMPRESSIO')) {
	define('INCIDENCIA_TIPOLOGIA_IMPRESSIO', 'impressio');
}
if (!defined('INCIDENCIA_TIPOLOGIA_AULES')) {
	define('INCIDENCIA_TIPOLOGIA_AULES', 'aules');
}
if (!defined('INCIDENCIA_TIPOLOGIA_MOBILS')) {
	define('INCIDENCIA_TIPOLOGIA_MOBILS', 'mobils');
}
if (!defined('INCIDENCIA_TIPOLOGIA_PLATAFORMES')) {
	define('INCIDENCIA_TIPOLOGIA_PLATAFORMES', 'plataformes');
}
if (!defined('INCIDENCIA_TIPOLOGIA_SEGURETAT')) {
	define('INCIDENCIA_TIPOLOGIA_SEGURETAT', 'seguretat');
}
if (!defined('INCIDENCIA_TECNIC_PER_DEFECTE')) {
	// This project currently has role-based screens without per-user login.
	// We still store who it's assigned to, so we use a single default label.
	define('INCIDENCIA_TECNIC_PER_DEFECTE', 'Tècnic');
}

function ensure_incidencies_schema(mysqli $conn): array
{
	$localitzacions = [];
	for ($planta = 1; $planta <= 3; $planta++) {
		for ($aula = 1; $aula <= 10; $aula++) {
			$localitzacions[] = 'P' . $planta . '_A' . $aula;
		}
	}
	$localitzacions_sql = "'" . implode("','", $localitzacions) . "'";

	if (!taula_existeix($conn, 'incidencies')) {
		$create_sql = "CREATE TABLE IF NOT EXISTS incidencies (
			id INT AUTO_INCREMENT PRIMARY KEY,
			departament VARCHAR(80) NOT NULL,
			descripcio_curta VARCHAR(255) NOT NULL,
			localitzacio ENUM($localitzacions_sql) NULL,
			prioritat VARCHAR(10) NOT NULL DEFAULT '" . INCIDENCIA_PRIORITAT_MITJA . "',
			tipologia VARCHAR(30) NOT NULL DEFAULT '" . INCIDENCIA_TIPOLOGIA_HARDWARE . "',
			data_incidencia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			estat VARCHAR(30) NOT NULL DEFAULT '" . INCIDENCIA_ESTAT_PENDENT_ASSIGNAR . "',
			tecnic_assignat VARCHAR(80) NULL,
			data_inici_tasca TIMESTAMP NULL DEFAULT NULL,
			data_tancament TIMESTAMP NULL DEFAULT NULL,
			email VARCHAR(255) NOT NULL
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
		'localitzacio' => "ALTER TABLE incidencies ADD COLUMN localitzacio ENUM($localitzacions_sql) NULL AFTER descripcio_curta",
		'prioritat' => "ALTER TABLE incidencies ADD COLUMN prioritat VARCHAR(10) NOT NULL DEFAULT '" . INCIDENCIA_PRIORITAT_MITJA . "'",
		'tipologia' => "ALTER TABLE incidencies ADD COLUMN tipologia VARCHAR(30) NOT NULL DEFAULT '" . INCIDENCIA_TIPOLOGIA_HARDWARE . "'",
		'data_incidencia' => "ALTER TABLE incidencies ADD COLUMN data_incidencia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
		'estat' => "ALTER TABLE incidencies ADD COLUMN estat VARCHAR(30) NOT NULL DEFAULT '" . INCIDENCIA_ESTAT_PENDENT_ASSIGNAR . "'",
		'tecnic_assignat' => "ALTER TABLE incidencies ADD COLUMN tecnic_assignat VARCHAR(80) NULL",
		'data_inici_tasca' => "ALTER TABLE incidencies ADD COLUMN data_inici_tasca TIMESTAMP NULL DEFAULT NULL",
		'data_tancament' => "ALTER TABLE incidencies ADD COLUMN data_tancament TIMESTAMP NULL DEFAULT NULL",
		'email' => "ALTER TABLE incidencies ADD COLUMN email VARCHAR(255) NOT NULL",
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

	// Work Logs (MySQL) - linked to incidencies.
	if (!taula_existeix($conn, 'worklogs')) {
		$create_worklogs_sql = "CREATE TABLE IF NOT EXISTS worklogs (
			id INT AUTO_INCREMENT PRIMARY KEY,
			incident_id INT NOT NULL,
			opened_at DATETIME NOT NULL,
			user VARCHAR(255) NULL,
			hours_spent DECIMAL(6,2) NOT NULL DEFAULT 0,
			description TEXT NOT NULL,
			created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_worklogs_incident (incident_id),
			INDEX idx_worklogs_created (created_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

		if ($conn->query($create_worklogs_sql) === false) {
			return [
				'ok' => false,
				'error' => "No s'ha pogut crear la taula worklogs: " . $conn->error,
			];
		}
	}

	$worklogs_required_columns = [
		'incident_id' => "ALTER TABLE worklogs ADD COLUMN incident_id INT NOT NULL",
		'opened_at' => "ALTER TABLE worklogs ADD COLUMN opened_at DATETIME NOT NULL",
		'user' => "ALTER TABLE worklogs ADD COLUMN user VARCHAR(255) NULL",
		'hours_spent' => "ALTER TABLE worklogs ADD COLUMN hours_spent DECIMAL(6,2) NOT NULL DEFAULT 0",
		'description' => "ALTER TABLE worklogs ADD COLUMN description TEXT NOT NULL",
		'created_at' => "ALTER TABLE worklogs ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	];

	foreach ($worklogs_required_columns as $column => $alter_sql) {
		if (!columna_existeix($conn, 'worklogs', $column)) {
			if ($conn->query($alter_sql) === false) {
				return [
					'ok' => false,
					'error' => "No s'ha pogut afegir la columna $column a worklogs: " . $conn->error,
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

	$valid_prioritats = [
		INCIDENCIA_PRIORITAT_BAIXA,
		INCIDENCIA_PRIORITAT_MITJA,
		INCIDENCIA_PRIORITAT_ALTA,
	];
	$valid_list_sql = "'" . implode("','", array_map([$conn, 'real_escape_string'], $valid_prioritats)) . "'";
	$normalized_prio_ok = $conn->query("UPDATE incidencies SET prioritat = '" . INCIDENCIA_PRIORITAT_MITJA . "' WHERE prioritat IS NULL OR prioritat = '' OR prioritat NOT IN ($valid_list_sql)");
	if ($normalized_prio_ok === false) {
		return [
			'ok' => false,
			'error' => "No s'ha pogut normalitzar la columna prioritat: " . $conn->error,
		];
	}

	$conn->query("UPDATE incidencies SET tecnic_assignat = NULL WHERE tecnic_assignat = ''");

	return [
		'ok' => true,
	];
}
