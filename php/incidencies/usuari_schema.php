<?php

// Helpers to keep the "USUARI" table schema compatible across environments.

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

if (!function_exists('index_existeix')) {
	function index_existeix(mysqli $conn, string $taula, string $index): bool
	{
		$taula_escapada = $conn->real_escape_string($taula);
		$index_escapat = $conn->real_escape_string($index);
		$result = $conn->query("SHOW INDEX FROM `$taula_escapada` WHERE Key_name = '$index_escapat'");
		if ($result === false) {
			return false;
		}

		$existeix = ($result->num_rows > 0);
		$result->free();
		return $existeix;
	}
}

if (!function_exists('ensure_default_department_id')) {
	function ensure_default_department_id(mysqli $conn): ?int
	{
		// Some environments already have a DEPARTMENT table used by legacy USUARI schemas.
		if (!taula_existeix($conn, 'DEPARTMENT')) {
			$create_sql = "CREATE TABLE IF NOT EXISTS DEPARTMENT (
				DEPARTMENT_ID INT NOT NULL AUTO_INCREMENT,
				DEPARTMENT_NAME VARCHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL,
				PRIMARY KEY (DEPARTMENT_ID)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
			if ($conn->query($create_sql) === false) {
				return null;
			}
		}

		// Ensure at least one department exists.
		$res = $conn->query('SELECT DEPARTMENT_ID FROM DEPARTMENT ORDER BY DEPARTMENT_ID ASC LIMIT 1');
		if ($res !== false) {
			$row = $res->fetch_assoc();
			$res->free();
			if (is_array($row) && isset($row['DEPARTMENT_ID'])) {
				return (int)$row['DEPARTMENT_ID'];
			}
		}

		$stmt = $conn->prepare('INSERT INTO DEPARTMENT (DEPARTMENT_NAME) VALUES (?)');
		if ($stmt === false) {
			return null;
		}
		$name = 'General';
		$stmt->bind_param('s', $name);
		$ok = $stmt->execute();
		$new_id = $ok ? (int)$conn->insert_id : null;
		$stmt->close();
		return $new_id;
	}
}

function ensure_usuari_schema(mysqli $conn): array
{
	// Handle possible legacy lowercase table name.
	if (!taula_existeix($conn, 'USUARI') && taula_existeix($conn, 'usuari')) {
		if ($conn->query('RENAME TABLE usuari TO USUARI') === false) {
			return [
				'ok' => false,
				'error' => "No s'ha pogut renombrar la taula usuari a USUARI: " . $conn->error,
			];
		}
	}

	if (!taula_existeix($conn, 'USUARI')) {
		$create_sql = "CREATE TABLE IF NOT EXISTS USUARI (
			USUARI_ID INT NOT NULL AUTO_INCREMENT,
			FIRST_NAME VARCHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL,
			LAST_NAME VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL,
			EMAIL VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL,
			PHONE_NUMBER VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL,
			ROLE ENUM('TECNIC','ADMIN','RESPONSABLE','PROFESSOR') COLLATE utf8mb4_unicode_ci NOT NULL,
			CREATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (USUARI_ID),
			UNIQUE KEY uniq_usuari_email (EMAIL)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

		if ($conn->query($create_sql) === false) {
			return [
				'ok' => false,
				'error' => "No s'ha pogut crear la taula USUARI: " . $conn->error,
			];
		}
	}

	$required_columns = [
		'FIRST_NAME' => "ALTER TABLE USUARI ADD COLUMN FIRST_NAME VARCHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL",
		'LAST_NAME' => "ALTER TABLE USUARI ADD COLUMN LAST_NAME VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL",
		'EMAIL' => "ALTER TABLE USUARI ADD COLUMN EMAIL VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL",
		'PHONE_NUMBER' => "ALTER TABLE USUARI ADD COLUMN PHONE_NUMBER VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL",
		'ROLE' => "ALTER TABLE USUARI ADD COLUMN ROLE ENUM('TECNIC','ADMIN','RESPONSABLE','PROFESSOR') COLLATE utf8mb4_unicode_ci NOT NULL",
		'CREATED_AT' => "ALTER TABLE USUARI ADD COLUMN CREATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	];

	foreach ($required_columns as $column => $alter_sql) {
		if (!columna_existeix($conn, 'USUARI', $column)) {
			if ($conn->query($alter_sql) === false) {
				return [
					'ok' => false,
					'error' => "No s'ha pogut afegir la columna $column a USUARI: " . $conn->error,
				];
			}
		}
	}

	// Ensure unique email key exists.
	if (!index_existeix($conn, 'USUARI', 'uniq_usuari_email')) {
		if ($conn->query('ALTER TABLE USUARI ADD UNIQUE KEY uniq_usuari_email (EMAIL)') === false) {
			return [
				'ok' => false,
				'error' => "No s'ha pogut afegir l'índex únic d'EMAIL: " . $conn->error,
			];
		}
	}

	// Legacy compatibility: some schemas require DEPARTMENT_ID (FK to DEPARTMENT).
	if (columna_existeix($conn, 'USUARI', 'DEPARTMENT_ID')) {
		$dept_id = ensure_default_department_id($conn);
		if (!is_int($dept_id) || $dept_id <= 0) {
			return [
				'ok' => false,
				'error' => "No s'ha pogut assegurar un departament per defecte.",
			];
		}
	}

	return [
		'ok' => true,
	];
}
