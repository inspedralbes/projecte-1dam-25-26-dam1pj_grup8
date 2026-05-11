<?php

require_once __DIR__ . '/../incidencies/auth.php';
auth_require_role('PROFESSOR');

require_once __DIR__ . '/../incidencies/connexio.php';

require_once __DIR__ . '/../incidencies/incidencies_schema.php';

function longitud(string $text): int
{
	if (function_exists('mb_strlen')) {
		return (int) mb_strlen($text);
	}

	return strlen($text);
}

function departaments_disponibles(?mysqli $conn = null): array
{
	if ($conn instanceof mysqli) {
		$res = $conn->query("SHOW TABLES LIKE 'DEPARTMENT'");
		if ($res !== false) {
			$exists = ($res->num_rows > 0);
			$res->free();
			if ($exists) {
				$names = [];
				$res2 = $conn->query('SELECT DEPARTMENT_NAME FROM DEPARTMENT ORDER BY DEPARTMENT_NAME ASC');
				if ($res2 !== false) {
					while ($row = $res2->fetch_assoc()) {
						$name = trim((string)($row['DEPARTMENT_NAME'] ?? ''));
						if ($name !== '') {
							$names[] = $name;
						}
					}
					$res2->free();
				}
				if (count($names) > 0) {
					return $names;
				}
			}
		}
	}

	return ['IT', 'Administració', 'Manteniment', 'Consergeria', 'ESO', 'Batxillerat'];
}

function crear_incidencia(mysqli $conn): array
{
	$schema_result = ensure_incidencies_schema($conn);
	if (!is_array($schema_result) || ($schema_result['ok'] ?? false) !== true) {
		return [
			'type' => 'error',
			'message_html' => "No s'ha pogut inicialitzar l'esquema d'incidències: " . htmlspecialchars((string)($schema_result['error'] ?? 'Error desconegut')),
		];
	}

	$departament = trim((string)($_POST['departament'] ?? ''));
	$descripcio_curta = trim((string)($_POST['descripcio_curta'] ?? ''));
	$email = trim((string)($_POST['email'] ?? ''));
	$planta = (int) ($_POST['planta'] ?? 0);
	$aula = (int) ($_POST['aula'] ?? 0);
	$localitzacio = 'P' . $planta . '_A' . $aula;

	if ($departament === '' || $descripcio_curta === '' || $email === '') {
		return [
			'type' => 'error',
			'message_html' => 'Omple tots els camps obligatoris.',
		];
	}

	if ($planta < 1 || $planta > 3 || $aula < 1 || $aula > 10) {
		return [
			'type' => 'error',
			'message_html' => 'Localització no vàlida (planta 1-3 i aula 1-10).',
		];
	}

	if (longitud($departament) > 80) {
		return [
			'type' => 'error',
			'message_html' => 'El departament és massa llarg (màxim 80 caràcters).',
		];
	}

	$departaments_valids = departaments_disponibles($conn);
	if (!in_array($departament, $departaments_valids, true)) {
		return [
			'type' => 'error',
			'message_html' => 'Departament no vàlid.',
		];
	}

	if (longitud($descripcio_curta) > 255) {
		return [
			'type' => 'error',
			'message_html' => 'La descripció és massa llarga (màxim 255 caràcters).',
		];
	}

	if (longitud($descripcio_curta) < 20) {
		return [
			'type' => 'error',
			'message_html' => 'La descripció ha de tenir com a mínim 20 caràcters.',
		];
	}

	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		return [
			'type' => 'error',
			'message_html' => 'El correu electrònic no és vàlid.',
		];
	}

	if (longitud($email) > 255) {
		return [
			'type' => 'error',
			'message_html' => 'El correu electrònic és massa llarg (màxim 255 caràcters).',
		];
	}
	$sql = "INSERT INTO incidencies (departament, descripcio_curta, localitzacio, email) VALUES (?, ?, ?, ?)";
	$stmt = $conn->prepare($sql);
	if ($stmt === false) {
		return [
			'type' => 'error',
			'message_html' => 'Error preparant la consulta: ' . htmlspecialchars($conn->error),
		];
	}

	$stmt->bind_param('ssss', $departament, $descripcio_curta, $localitzacio, $email);
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

<?php include __DIR__ . '/../incidencies/header.php'; ?>

<div class="container py-4" style="max-width: 760px;">
	<h1 class="h3 mb-3">Registrar nova incidència</h1>
	<p class="text-muted mb-4">Selecciona el departament i escriu una descripció curta. La data s'assigna automàticament.</p>

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
	$formulari_email = (is_array($resultat_creacio) && ($resultat_creacio['type'] ?? '') === 'success')
		? ''
		: (string) ($_POST['email'] ?? '');
	$formulari_planta = (is_array($resultat_creacio) && ($resultat_creacio['type'] ?? '') === 'success')
		? ''
		: (string) ($_POST['planta'] ?? '');
	$formulari_aula = (is_array($resultat_creacio) && ($resultat_creacio['type'] ?? '') === 'success')
		? ''
		: (string) ($_POST['aula'] ?? '');

	if (is_array($resultat_creacio) && ($resultat_creacio['type'] ?? '') === 'error') {
		echo "<div class='alert alert-danger' role='alert'>" . ($resultat_creacio['message_html'] ?? '') . "</div>";
	}
	?>

	<form method="POST" action="crear_incidencia.php" class="card card-body">
		<div class="mb-3">
			<label for="email" class="form-label">Correu electrònic</label>
			<input
				type="email"
				class="form-control"
				id="email"
				name="email"
				maxlength="255"
				value="<?php echo htmlspecialchars($formulari_email); ?>"
				required
			/>
			<div class="form-text">Introdueix un correu electrònic vàlid.</div>
		</div>

		<div class="mb-3">
			<label for="departament" class="form-label">Departament</label>
			<select class="form-select" id="departament" name="departament" required>
				<option value="" <?php echo $formulari_departament === '' ? 'selected' : ''; ?> disabled>-- Selecciona --</option>
				<?php foreach (departaments_disponibles($conn) as $dept) : ?>
					<option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $formulari_departament === $dept ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($dept); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="mb-3">
			<label class="form-label">Data de la incidència</label>
			<div class="form-control" aria-readonly="true" readonly>
				<?php echo htmlspecialchars(date('Y-m-d H:i')); ?>
			</div>
			<div class="form-text">No cal especificar-la: el sistema la guarda automàticament.</div>
		</div>

		<div class="mb-3">
			<label class="form-label">Localització</label>
			<div class="row g-2">
				<div class="col-12 col-sm-6">
					<label for="planta" class="form-label">Planta</label>
					<select class="form-select" id="planta" name="planta" required>
						<option value="" <?php echo $formulari_planta === '' ? 'selected' : ''; ?> disabled>-- Selecciona --</option>
						<?php for ($p = 1; $p <= 3; $p++) : $val = (string) $p; ?>
							<option value="<?php echo $val; ?>" <?php echo $formulari_planta === $val ? 'selected' : ''; ?>>P<?php echo $val; ?></option>
						<?php endfor; ?>
					</select>
				</div>
				<div class="col-12 col-sm-6">
					<label for="aula" class="form-label">Aula</label>
					<select class="form-select" id="aula" name="aula" required>
						<option value="" <?php echo $formulari_aula === '' ? 'selected' : ''; ?> disabled>-- Selecciona --</option>
						<?php for ($a = 1; $a <= 10; $a++) : $val = (string) $a; ?>
							<option value="<?php echo $val; ?>" <?php echo $formulari_aula === $val ? 'selected' : ''; ?>>A<?php echo $val; ?></option>
						<?php endfor; ?>
					</select>
				</div>
			</div>
			<div class="form-text">Es guarda com P{planta}_A{aula} (ex.: P1_A1).</div>
		</div>

		<div class="mb-3">
			<label for="descripcio_curta" class="form-label">Descripció curta</label>
			<textarea
				class="form-control"
				id="descripcio_curta"
				name="descripcio_curta"
				rows="3"
				minlength="20"
				maxlength="255"
				required
			><?php echo htmlspecialchars($formulari_descripcio); ?></textarea>
			<div class="form-text">Mínim 20 caràcters.</div>
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

<?php include __DIR__ . '/../incidencies/footer.php'; ?>

