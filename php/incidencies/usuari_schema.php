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
				CREATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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

if (!function_exists('ensure_departments_seeded')) {
	function ensure_departments_seeded(mysqli $conn): void
	{
		$default_id = ensure_default_department_id($conn);
		if (!is_int($default_id) || $default_id <= 0) {
			return;
		}

		$departments = ['ESO', 'Batxillerat', 'FP', 'Administració'];
		$select_stmt = $conn->prepare('SELECT DEPARTMENT_ID FROM DEPARTMENT WHERE LOWER(DEPARTMENT_NAME) = LOWER(?) LIMIT 1');
		$insert_stmt = $conn->prepare('INSERT INTO DEPARTMENT (DEPARTMENT_NAME) VALUES (?)');
		if ($select_stmt === false || $insert_stmt === false) {
			if ($select_stmt !== false) {
				$select_stmt->close();
			}
			if ($insert_stmt !== false) {
				$insert_stmt->close();
			}
			return;
		}

		foreach ($departments as $dept) {
			$select_stmt->bind_param('s', $dept);
			if ($select_stmt->execute()) {
				$res = $select_stmt->get_result();
				$exists = ($res !== false && $res->num_rows > 0);
				if ($res !== false) {
					$res->free();
				}
				if (!$exists) {
					$insert_stmt->bind_param('s', $dept);
					$insert_stmt->execute();
				}
			}
		}

		$select_stmt->close();
		$insert_stmt->close();
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
			USERNAME VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
			FIRST_NAME VARCHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL,
			LAST_NAME VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL,
			EMAIL VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL,
			PASSWORD_HASH VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			PHONE_NUMBER VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL,
			DEPARTMENT_ID INT NULL,
			ROLE ENUM('TECNIC','ADMIN','RESPONSABLE','PROFESSOR') COLLATE utf8mb4_unicode_ci NOT NULL,
			IS_VERIFIED TINYINT(1) NOT NULL DEFAULT 0,
			VERIFICATION_TOKEN VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
			TOKEN_EXPIRES_AT DATETIME NULL,
			CREATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			UPDATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (USUARI_ID),
			UNIQUE KEY uniq_usuari_email (EMAIL),
			UNIQUE KEY uniq_usuari_username (USERNAME)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

		if ($conn->query($create_sql) === false) {
			return [
				'ok' => false,
				'error' => "No s'ha pogut crear la taula USUARI: " . $conn->error,
			];
		}
	}

	$required_columns = [
		'USERNAME' => "ALTER TABLE USUARI ADD COLUMN USERNAME VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL",
		'FIRST_NAME' => "ALTER TABLE USUARI ADD COLUMN FIRST_NAME VARCHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL",
		'LAST_NAME' => "ALTER TABLE USUARI ADD COLUMN LAST_NAME VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL",
		'EMAIL' => "ALTER TABLE USUARI ADD COLUMN EMAIL VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL",
		'PASSWORD_HASH' => "ALTER TABLE USUARI ADD COLUMN PASSWORD_HASH VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL",
		'PHONE_NUMBER' => "ALTER TABLE USUARI ADD COLUMN PHONE_NUMBER VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL",
		'DEPARTMENT_ID' => "ALTER TABLE USUARI ADD COLUMN DEPARTMENT_ID INT NULL",
		'ROLE' => "ALTER TABLE USUARI ADD COLUMN ROLE ENUM('TECNIC','ADMIN','RESPONSABLE','PROFESSOR') COLLATE utf8mb4_unicode_ci NOT NULL",
		'IS_VERIFIED' => "ALTER TABLE USUARI ADD COLUMN IS_VERIFIED TINYINT(1) NOT NULL DEFAULT 0",
		'VERIFICATION_TOKEN' => "ALTER TABLE USUARI ADD COLUMN VERIFICATION_TOKEN VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL",
		'TOKEN_EXPIRES_AT' => "ALTER TABLE USUARI ADD COLUMN TOKEN_EXPIRES_AT DATETIME NULL",
		'CREATED_AT' => "ALTER TABLE USUARI ADD COLUMN CREATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
		'UPDATED_AT' => "ALTER TABLE USUARI ADD COLUMN UPDATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
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

	// Ensure unique username key exists (if column exists).
	if (columna_existeix($conn, 'USUARI', 'USERNAME') && !index_existeix($conn, 'USUARI', 'uniq_usuari_username')) {
		if ($conn->query('ALTER TABLE USUARI ADD UNIQUE KEY uniq_usuari_username (USERNAME)') === false) {
			return [
				'ok' => false,
				'error' => "No s'ha pogut afegir l'índex únic de USERNAME: " . $conn->error,
			];
		}
	}

	// Ensure DEPARTMENT exists and has realistic options.
	$dept_id = ensure_default_department_id($conn);
	if (!is_int($dept_id) || $dept_id <= 0) {
		return [
			'ok' => false,
			'error' => "No s'ha pogut assegurar un departament per defecte.",
		];
	}
	ensure_departments_seeded($conn);

	// Backfill existing rows if new auth columns were added.
	$has_username = columna_existeix($conn, 'USUARI', 'USERNAME');
	$has_pass_hash = columna_existeix($conn, 'USUARI', 'PASSWORD_HASH');
	if ($has_username || $has_pass_hash) {
		$res = $conn->query("SELECT USUARI_ID, EMAIL, USERNAME, PASSWORD_HASH FROM USUARI ORDER BY USUARI_ID ASC");
		if ($res !== false) {
			while ($row = $res->fetch_assoc()) {
				$id = (int)($row['USUARI_ID'] ?? 0);
				$email = strtolower(trim((string)($row['EMAIL'] ?? '')));
				$username = trim((string)($row['USERNAME'] ?? ''));
				$pass = trim((string)($row['PASSWORD_HASH'] ?? ''));
				if ($id <= 0) {
					continue;
				}

				$updates = [];
				$types = '';
				$params = [];

				if ($has_username && $username === '') {
					$base = $email !== '' ? preg_replace('/[^a-zA-Z]/', '', explode('@', $email)[0]) : 'user';
					$base = $base !== '' ? strtolower($base) : 'user';
					$candidate = substr($base, 0, 18);
					if ($candidate === '') {
						$candidate = 'user';
					}
					$candidate .= (string)$id;
					$updates[] = 'USERNAME = ?';
					$types .= 's';
					$params[] = $candidate;
				}

				if ($has_pass_hash && $pass === '') {
					$random = bin2hex(random_bytes(16));
					$hash = password_hash($random, PASSWORD_DEFAULT);
					$updates[] = 'PASSWORD_HASH = ?';
					$types .= 's';
					$params[] = $hash;
				}

				if (columna_existeix($conn, 'USUARI', 'DEPARTMENT_ID')) {
					$updates[] = 'DEPARTMENT_ID = COALESCE(DEPARTMENT_ID, ?)';
					$types .= 'i';
					$params[] = $dept_id;
				}

				if (count($updates) > 0) {
					$sql = 'UPDATE USUARI SET ' . implode(', ', $updates) . ' WHERE USUARI_ID = ?';
					$types .= 'i';
					$params[] = $id;
					$stmt = $conn->prepare($sql);
					if ($stmt !== false) {
						$stmt->bind_param($types, ...$params);
						$stmt->execute();
						$stmt->close();
					}
				}
			}
			$res->free();
		}
	}

	return [
		'ok' => true,
	];
}
