<?php

// Helpers to keep the "TECNIC" table schema compatible across environments.
// Important: MySQL init scripts in db_init/ only run on a fresh database volume.
// To support teammates who already have an older volume, we ensure the schema at runtime.

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

function ensure_tecnic_schema(mysqli $conn): array
{
	// Handle possible legacy lowercase table name.
	if (!taula_existeix($conn, 'TECNIC') && taula_existeix($conn, 'tecnic')) {
		if ($conn->query('RENAME TABLE tecnic TO TECNIC') === false) {
			return [
				'ok' => false,
				'error' => "No s'ha pogut renombrar la taula tecnic a TECNIC: " . $conn->error,
			];
		}
	}

	if (!taula_existeix($conn, 'TECNIC')) {
		$create_sql = "CREATE TABLE IF NOT EXISTS TECNIC (
			TECNIC_ID INT NOT NULL AUTO_INCREMENT,
			FIRST_NAME VARCHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL,
			LAST_NAME VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL,
			EMAIL VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL,
			PASSWORD CHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL,
			PHONE_NUMBER VARCHAR(12) COLLATE utf8mb4_unicode_ci NOT NULL,
			ROL_EMPLOYEE ENUM('ENCARGADO','TECNICO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TECNICO',
			PRIMARY KEY (TECNIC_ID)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

		if ($conn->query($create_sql) === false) {
			return [
				'ok' => false,
				'error' => "No s'ha pogut crear la taula TECNIC: " . $conn->error,
			];
		}
	}

	$required_columns = [
		'FIRST_NAME' => "ALTER TABLE TECNIC ADD COLUMN FIRST_NAME VARCHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL",
		'LAST_NAME' => "ALTER TABLE TECNIC ADD COLUMN LAST_NAME VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL",
		'EMAIL' => "ALTER TABLE TECNIC ADD COLUMN EMAIL VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL",
		'PASSWORD' => "ALTER TABLE TECNIC ADD COLUMN PASSWORD CHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL",
		'PHONE_NUMBER' => "ALTER TABLE TECNIC ADD COLUMN PHONE_NUMBER VARCHAR(12) COLLATE utf8mb4_unicode_ci NOT NULL",
		'ROL_EMPLOYEE' => "ALTER TABLE TECNIC ADD COLUMN ROL_EMPLOYEE ENUM('ENCARGADO','TECNICO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TECNICO'",
	];

	foreach ($required_columns as $column => $alter_sql) {
		if (!columna_existeix($conn, 'TECNIC', $column)) {
			if ($conn->query($alter_sql) === false) {
				return [
					'ok' => false,
					'error' => "No s'ha pogut afegir la columna $column a TECNIC: " . $conn->error,
				];
			}
		}
	}

	// Seed minimal default staff, only when needed:
	// - If the table is empty: add both Responsable (ENCARGADO) and one Tècnic.
	// - If there are technicians but no ENCARGADO: add only the Responsable.
	$total_tecnics = null;
	$res_count = $conn->query('SELECT COUNT(*) AS total FROM TECNIC');
	if ($res_count !== false) {
		$row = $res_count->fetch_assoc();
		$total_tecnics = (int)($row['total'] ?? 0);
		$res_count->free();
	}

	$needs_seed_all = ($total_tecnics === 0);
	$needs_seed_responsable = false;
	if ($total_tecnics !== null && $total_tecnics > 0) {
		$res_enc = $conn->query("SELECT 1 FROM TECNIC WHERE ROL_EMPLOYEE = 'ENCARGADO' LIMIT 1");
		if ($res_enc !== false) {
			$needs_seed_responsable = ($res_enc->num_rows === 0);
			$res_enc->free();
		}
	}

	if ($needs_seed_all || $needs_seed_responsable) {
		$insert_stmt = $conn->prepare('INSERT INTO TECNIC (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROL_EMPLOYEE) VALUES (?, ?, ?, ?, ?, ?)');
		if ($insert_stmt !== false) {
			if ($needs_seed_all || $needs_seed_responsable) {
				$first = 'Responsable';
				$last = 'Tècnic';
				$email = 'responsable@local';
				$password = 'responsable';
				$phone = '000000000';
				$rol = 'ENCARGADO';
				$insert_stmt->bind_param('ssssss', $first, $last, $email, $password, $phone, $rol);
				$insert_stmt->execute();
			}

			if ($needs_seed_all) {
				$first = 'Tècnic';
				$last = '1';
				$email = 'tecnic1@local';
				$password = 'tecnic';
				$phone = '000000001';
				$rol = 'TECNICO';
				$insert_stmt->bind_param('ssssss', $first, $last, $email, $password, $phone, $rol);
				$insert_stmt->execute();
			}

			$insert_stmt->close();
		}
	}

	return [
		'ok' => true,
	];
}
