<?php

require_once 'connexio.php';
require_once 'incidencies_schema.php';
require_once 'header.php';

// Assegurar que l'esquema existeix
$schema_result = ensure_incidencies_schema($conn);
if (!is_array($schema_result) || ($schema_result['ok'] ?? false) !== true) {
	die("Error inicialitzant l'esquema d'incidències");
}

// Variables per al formulari
$search_id = trim((string)($_POST['search_id'] ?? ''));
$search_departament = trim((string)($_POST['search_departament'] ?? ''));
$resultats = null;
$filter_applied = false;

// Si s'ha enviat el formulari, fer la búsqueda
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$filter_applied = true;
	
	if ($search_id !== '' || $search_departament !== '') {
		$sql = "SELECT id, departament, descripcio_curta, localitzacio, email, data_incidencia, estat, prioritat FROM incidencies WHERE 1=1";
		$params = [];
		$types = "";

		if ($search_id !== '') {
			$sql .= " AND id = ?";
			$params[] = (int) $search_id;
			$types .= "i";
		}

		if ($search_departament !== '') {
			$sql .= " AND departament = ?";
			$params[] = $search_departament;
			$types .= "s";
		}

		$sql .= " ORDER BY data_incidencia DESC";

		$stmt = $conn->prepare($sql);
		if ($stmt !== false && !empty($params)) {
			$stmt->bind_param($types, ...$params);
			$stmt->execute();
			$resultats = $stmt->get_result();
			$stmt->close();
		} elseif ($stmt !== false) {
			$resultats = $stmt->execute() ? $stmt->get_result() : null;
			$stmt->close();
		}
	} else {
		$resultats = $conn->query("SELECT id, departament, descripcio_curta, localitzacio, email, data_incidencia, estat, prioritat FROM incidencies ORDER BY data_incidencia DESC LIMIT 0");
	}
}

?>

<div class="container py-4" style="max-width: 1000px;">
	<h1 class="h3 mb-4">Cercar incidències</h1>

	<form method="POST" action="llistar.php" class="card card-body mb-4">
		<div class="row">
			<div class="col-md-6 mb-3">
				<label for="search_id" class="form-label">Cercar per ID:</label>
				<input type="number" id="search_id" name="search_id" class="form-control" value="<?php echo htmlspecialchars($search_id); ?>" />
			</div>

			<div class="col-md-6 mb-3">
				<label for="search_departament" class="form-label">Cercar per departament:</label>
				<select id="search_departament" name="search_departament" class="form-select">
					<option value="">-- Tots els departaments --</option>
					<?php
					$departaments = ['IT', 'Administració', 'Manteniment', 'Consergeria', 'ESO', 'Batxillerat'];
					foreach ($departaments as $dept) {
						$selected = ($search_departament === $dept) ? 'selected' : '';
						echo "<option value=\"" . htmlspecialchars($dept) . "\" $selected>" . htmlspecialchars($dept) . "</option>";
					}
					?>
				</select>
			</div>
		</div>

		<div class="d-flex gap-2">
			<button type="submit" class="btn btn-primary">Aplicar filtre</button>
			<a href="llistar.php" class="btn btn-outline-secondary">Netejar filtres</a>
		</div>
	</form>

	<?php if ($filter_applied) : ?>
		<?php if ($resultats !== null && $resultats->num_rows > 0) : ?>
			<div class="row">
				<div class="col-12">
					<h2 class="h5 mb-3">Resultats (<?php echo $resultats->num_rows; ?> incidències)</h2>
					<?php while ($row = $resultats->fetch_assoc()) : ?>
						<div class="card mb-3">
							<div class="card-header bg-light">
								<h5 class="card-title mb-0">Incidència #<?php echo htmlspecialchars((string) $row['id']); ?></h5>
							</div>
							<div class="card-body">
								<div class="row">
									<div class="col-md-6">
										<p><strong>Departament:</strong> <?php echo htmlspecialchars((string) $row['departament']); ?></p>
										<p><strong>Localització:</strong> <?php echo htmlspecialchars((string) $row['localitzacio']); ?></p>
										<p><strong>Data:</strong> <?php echo htmlspecialchars((string) $row['data_incidencia']); ?></p>
									</div>
									<div class="col-md-6">
										<p><strong>Correu:</strong> <?php echo htmlspecialchars((string) $row['email']); ?></p>
										<p><strong>Estat:</strong> <span class="badge bg-info"><?php echo htmlspecialchars((string) $row['estat']); ?></span></p>
										<p><strong>Prioritat:</strong> <span class="badge bg-warning"><?php echo htmlspecialchars((string) $row['prioritat']); ?></span></p>
									</div>
								</div>
								<p><strong>Descripció:</strong></p>
								<p class="text-muted"><?php echo htmlspecialchars((string) $row['descripcio_curta']); ?></p>
							</div>
						</div>
					<?php endwhile; ?>
				</div>
			</div>
		<?php else : ?>
			<div class="alert alert-info" role="alert">
				No hi ha incidències per mostrar
			</div>
		<?php endif; ?>
	<?php endif; ?>

</div>

<?php include __DIR__ . '/footer.php'; ?>
