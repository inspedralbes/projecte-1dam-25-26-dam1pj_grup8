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

function crear_incidencia(mysqli $conn): void
{
	$departament = trim((string)($_POST['departament'] ?? ''));
	$descripcio_curta = trim((string)($_POST['descripcio_curta'] ?? ''));

	if ($departament === '' || $descripcio_curta === '') {
		echo "<div class='alert alert-danger' role='alert'>Omple tots els camps obligatoris.</div>";
		return;
	}

	if (mb_strlen($departament) > 80) {
		echo "<div class='alert alert-danger' role='alert'>El departament és massa llarg (màxim 80 caràcters).</div>";
		return;
	}

	if (mb_strlen($descripcio_curta) > 255) {
		echo "<div class='alert alert-danger' role='alert'>La descripció és massa llarga (màxim 255 caràcters).</div>";
		return;
	}

	if (!taula_existeix($conn, 'incidencies')) {
		echo "<div class='alert alert-danger' role='alert'>";
		echo "No existeix la taula <strong>incidencies</strong> a la base de dades. ";
		echo "Si ja tenies la BD creada (carpeta <code>db_data/</code>), l'script de <code>db_init/</code> no s'executa de nou. ";
		echo "Crea la taula manualment (via Adminer) o reinicialitza la BD esborrant <code>db_data/</code> i tornant a aixecar els contenidors.";
		echo "</div>";
		return;
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
		echo "<div class='alert alert-success' role='alert'>Incidència creada amb èxit.</div>";
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

