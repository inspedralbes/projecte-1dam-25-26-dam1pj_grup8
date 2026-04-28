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

function crear_incidencia(mysqli $conn): array
{
	$departament = trim((string)($_POST['departament'] ?? ''));
	$descripcio_curta = trim((string)($_POST['descripcio_curta'] ?? ''));

	if ($departament === '' || $descripcio_curta === '') {
		return [
			'type' => 'error',
			'message_html' => 'Omple tots els camps obligatoris.',
		];
	}

	if (longitud($departament) > 80) {
		return [
			'type' => 'error',
			'message_html' => 'El departament és massa llarg (màxim 80 caràcters).',
		];
	}

	if (longitud($descripcio_curta) > 255) {
		return [
			'type' => 'error',
			'message_html' => 'La descripció és massa llarga (màxim 255 caràcters).',
		];
	}

	if (!taula_existeix($conn, 'incidencies')) {
		$create_sql = "CREATE TABLE IF NOT EXISTS incidencies (
			id INT AUTO_INCREMENT PRIMARY KEY,
			departament VARCHAR(80) NOT NULL,
			descripcio_curta VARCHAR(255) NOT NULL,
			data_incidencia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

		if ($conn->query($create_sql) === false) {
			return [
				'type' => 'error',
				'message_html' => "No s'ha pogut crear la taula <strong>incidencies</strong>: " . htmlspecialchars($conn->error),
			];
		}
	}

	if (!columna_existeix($conn, 'incidencies', 'departament') || !columna_existeix($conn, 'incidencies', 'descripcio_curta')) {
		return [
			'type' => 'error',
			'message_html' => "La taula <strong>incidencies</strong> existeix però no té l'esquema esperat (falten columnes). Reinicialitza la BD (esborrant <code>db_data/</code>) o actualitza la taula via Adminer perquè tingui: <code>departament</code>, <code>descripcio_curta</code> i <code>data_incidencia</code>.",
		];
	}

	$sql = "INSERT INTO incidencies (departament, descripcio_curta) VALUES (?, ?)";
	$stmt = $conn->prepare($sql);
	if ($stmt === false) {
		return [
			'type' => 'error',
			'message_html' => 'Error preparant la consulta: ' . htmlspecialchars($conn->error),
		];
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

		$message_html = 'Incidència creada amb èxit. ID: <strong>' . htmlspecialchars((string) $nou_id) . '</strong>';
		if ($data_guardada !== '') {
			$message_html .= ' - Data: <strong>' . htmlspecialchars($data_guardada) . '</strong>';
		}

		$stmt->close();

		return [
			'type' => 'success',
			'message_html' => $message_html,
		];
	} else {
		$stmt->close();

		return [
			'type' => 'error',
			'message_html' => 'Error al crear la incidència: ' . htmlspecialchars($stmt->error),
		];
	}
}

?>

<?php include 'header.php'; ?>

<div class="container py-4" style="max-width: 760px;">
	<h1 class="h3 mb-3">Registrar nova incidència</h1>
	<p class="text-muted mb-4">Introdueix el departament i una descripció curta. La data s'assigna automàticament.</p>

	<?php
	$resultat_creacio = null;
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$resultat_creacio = crear_incidencia($conn);
	}

	$formulari_departament = (is_array($resultat_creacio) && ($resultat_creacio['type'] ?? '') === 'success')
		? ''
		: (string) ($_POST['departament'] ?? '');
	$formulari_descripcio = (is_array($resultat_creacio) && ($resultat_creacio['type'] ?? '') === 'success')
		? ''
		: (string) ($_POST['descripcio_curta'] ?? '');

	if (is_array($resultat_creacio) && ($resultat_creacio['type'] ?? '') === 'error') {
		echo "<div class='alert alert-danger' role='alert'>" . ($resultat_creacio['message_html'] ?? '') . "</div>";
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
				value="<?php echo htmlspecialchars($formulari_departament); ?>"
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
			><?php echo htmlspecialchars($formulari_descripcio); ?></textarea>
		</div>

		<div class="d-flex gap-2">
			<button type="submit" class="btn btn-primary">Crear incidència</button>
			<a class="btn btn-outline-secondary" href="professor.php">Tornar</a>
		</div>
	</form>

	<?php if (is_array($resultat_creacio) && ($resultat_creacio['type'] ?? '') === 'success') : ?>
		<div class="toast-container-custom">
			<div
				id="toastIncidenciaCreada"
				class="toast js-toast-notification text-bg-success border-0"
				role="alert"
				aria-live="assertive"
				aria-atomic="true"
				data-bs-autohide="true"
				data-bs-delay="3500"
			>
				<div class="d-flex">
					<div class="toast-body">
						<?php echo $resultat_creacio['message_html'] ?? ''; ?>
					</div>
					<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tancar"></button>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<?php include 'footer.php'; ?>

