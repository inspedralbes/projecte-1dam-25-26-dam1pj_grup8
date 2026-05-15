<?php
/**
 * Esquema de técnicos (tabla `TECNIC`).
 *
 * - Crea/actualiza la tabla y columnas necesarias.
 * - Inserta técnicos de demo si no existen (por email) para poblar desplegables.
 */

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

	// Seed default staff (Alex, Berta, Carles, Dina) used in the UI dropdowns.
	// We insert them only if they don't exist (by EMAIL) to avoid duplicates.
	$seed = [
		[
			'first' => 'Alex',
			'last' => 'Serra',
			'email' => 'alex.serra@exemple.local',
			'password' => 'demo',
			'phone' => '600000001',
			'rol' => 'ENCARGADO',
		],
		[
			'first' => 'Berta',
			'last' => 'Roca',
			'email' => 'berta.roca@exemple.local',
			'password' => 'demo',
			'phone' => '600000002',
			'rol' => 'TECNICO',
		],
		[
			'first' => 'Carles',
			'last' => 'Pujol',
			'email' => 'carles.pujol@exemple.local',
			'password' => 'demo',
			'phone' => '600000003',
			'rol' => 'TECNICO',
		],
		[
			'first' => 'Dina',
			'last' => 'Vila',
			'email' => 'dina.vila@exemple.local',
			'password' => 'demo',
			'phone' => '600000004',
			'rol' => 'TECNICO',
		],
	];

	$select_stmt = $conn->prepare('SELECT TECNIC_ID FROM TECNIC WHERE EMAIL = ? LIMIT 1');
	$insert_stmt = $conn->prepare('INSERT INTO TECNIC (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROL_EMPLOYEE) VALUES (?, ?, ?, ?, ?, ?)');

	if ($select_stmt !== false && $insert_stmt !== false) {
		foreach ($seed as $user) {
			$email = (string)$user['email'];
			$select_stmt->bind_param('s', $email);
			if ($select_stmt->execute()) {
				$res = $select_stmt->get_result();
				$exists = ($res !== false && $res->num_rows > 0);
				if ($res !== false) {
					$res->free();
				}

				if (!$exists) {
					$first = (string)$user['first'];
					$last = (string)$user['last'];
					$password = (string)$user['password'];
					$phone = (string)$user['phone'];
					$rol = (string)$user['rol'];
					$insert_stmt->bind_param('ssssss', $first, $last, $email, $password, $phone, $rol);
					$insert_stmt->execute();
				}
			}
		}
	}

	if ($select_stmt !== false) {
		$select_stmt->close();
	}
	if ($insert_stmt !== false) {
		$insert_stmt->close();
	}

	return [
		'ok' => true,
	];
}
