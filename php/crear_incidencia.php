<?php

require_once 'connexio.php';

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

function longitud(string $text): int
{
	if (function_exists('mb_strlen')) {
		return (int) mb_strlen($text);
	}

	return strlen($text);
}

function crear_incidencia(mysqli $conn): void
{
	$departament = trim((string)($_POST['departament'] ?? ''));
	$descripcio_curta = trim((string)($_POST['descripcio_curta'] ?? ''));

	if ($departament === '' || $descripcio_curta === '') {
		echo "<div class='alert alert-danger' role='alert'>Omple tots els camps obligatoris.</div>";
		return;
	}

	if (longitud($departament) > 80) {
		echo "<div class='alert alert-danger' role='alert'>El departament és massa llarg (màxim 80 caràcters).</div>";
		return;
	}

	if (longitud($descripcio_curta) > 255) {
		echo "<div class='alert alert-danger' role='alert'>La descripció és massa llarga (màxim 255 caràcters).</div>";
		return;
	}

	if (!taula_existeix($conn, 'incidencies')) {
		$create_sql = "CREATE TABLE IF NOT EXISTS incidencies (
			id INT AUTO_INCREMENT PRIMARY KEY,
			departament VARCHAR(80) NOT NULL,
			descripcio_curta VARCHAR(255) NOT NULL,
			data_incidencia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

		if ($conn->query($create_sql) === false) {
			echo "<div class='alert alert-danger' role='alert'>";
			echo "No s'ha pogut crear la taula <strong>incidencies</strong>: " . htmlspecialchars($conn->error);
			echo "</div>";
			return;
		}
	}

	if (!columna_existeix($conn, 'incidencies', 'departament') || !columna_existeix($conn, 'incidencies', 'descripcio_curta')) {
		echo "<div class='alert alert-danger' role='alert'>";
		echo "La taula <strong>incidencies</strong> existeix però no té l'esquema esperat (falten columnes). ";
		echo "Reinicialitza la BD (esborrant <code>db_data/</code>) o actualitza la taula via Adminer perquè tingui: ";
		echo "<code>departament</code>, <code>descripcio_curta</code> i <code>data_incidencia</code>.";
		echo "</div>";
		return;
	}

	$sql = "INSERT INTO incidencies (departament, descripcio_curta) VALUES (?, ?)";
	$stmt = $conn->prepare($sql);
	if ($stmt === false) {
		echo "<div class='alert alert-danger' role='alert'>Error preparant la consulta: " . htmlspecialchars($conn->error) . "</div>";
		return;
	}

	$stmt->bind_param('ss', $departament, $descripcio_curta);
	if ($stmt->execute()) {
		$nou_id = (int) $conn->insert_id;
		$data_guardada = '';

		$stmt2 = $conn->prepare('SELECT data_incidencia FROM incidencies WHERE id = ?');
		if ($stmt2 !== false) {
			$stmt2->bind_param('i', $nou_id);
			if ($stmt2->execute()) {
				$res = $stmt2->get_result();
				if ($res !== false && $row = $res->fetch_assoc()) {
					$data_guardada = (string) ($row['data_incidencia'] ?? '');
				}
			}
			$stmt2->close();
		}

		echo "<div class='alert alert-success' role='alert'>Incidència creada amb èxit. ID: <strong>" . htmlspecialchars((string)$nou_id) . "</strong>";
		if ($data_guardada !== '') {
			echo " — Data: <strong>" . htmlspecialchars($data_guardada) . "</strong>";
		}
		echo "</div>";
	} else {
		echo "<div class='alert alert-danger' role='alert'>Error al crear la incidència: " . htmlspecialchars($stmt->error) . "</div>";
	}

	$stmt->close();
}

?>

<?php include 'header.php'; ?>

<div class="container py-4" style="max-width: 760px;">
	<h1 class="h3 mb-3">Registrar nova incidència</h1>
	<p class="text-muted mb-4">Introdueix el departament i una descripció curta. La data s'assigna automàticament.</p>

	<?php
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		crear_incidencia($conn);
	}
	?>

	<form method="POST" action="crear_incidencia.php" class="card card-body">
		<div class="mb-3">
			<label for="departament" class="form-label">Departament</label>
			<input
				type="text"
				class="form-control"
				id="departament"
				name="departament"
				required
				maxlength="80"
				value="<?php echo htmlspecialchars((string)($_POST['departament'] ?? '')); ?>"
			>
		</div>

		<div class="mb-3">
			<label class="form-label">Data de la incidència</label>
			<div class="form-control" aria-readonly="true" readonly>
				<?php echo htmlspecialchars(date('Y-m-d H:i')); ?>
			</div>
			<div class="form-text">No cal especificar-la: el sistema la guarda automàticament.</div>
		</div>

		<div class="mb-3">
			<label for="descripcio_curta" class="form-label">Descripció curta</label>
			<textarea
				class="form-control"
				id="descripcio_curta"
				name="descripcio_curta"
				rows="3"
				maxlength="255"
				required
			><?php echo htmlspecialchars((string)($_POST['descripcio_curta'] ?? '')); ?></textarea>
		</div>

		<div class="d-flex gap-2">
			<button type="submit" class="btn btn-primary">Crear incidència</button>
			<a class="btn btn-outline-secondary" href="professor.php">Tornar</a>
		</div>
	</form>
</div>

<?php include 'footer.php'; ?>

