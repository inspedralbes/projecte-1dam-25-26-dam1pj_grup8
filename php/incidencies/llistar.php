<?php
/**
 * Búsqueda/listado de incidencias para usuario autenticado.
 *
 * - Muestra SIEMPRE las incidencias del usuario (filtradas por email).
 * - Permite filtrar adicionalmente por ID y/o departamento.
 */

require_once __DIR__ . '/auth.php';
auth_require_login();

require_once __DIR__ . '/connexio.php';
require_once __DIR__ . '/incidencies_schema.php';
require_once __DIR__ . '/header.php';

// Assegurar que l'esquema existeix
$schema_result = ensure_incidencies_schema($conn);
if (!is_array($schema_result) || ($schema_result['ok'] ?? false) !== true) {
    die("Error inicialitzant l'esquema d'incidències");
}

// Variables per al formulari
$search_id = trim((string)($_POST['search_id'] ?? ''));
$search_departament = trim((string)($_POST['search_departament'] ?? ''));
$resultats = null;
$resultats_usuario = null;
$filter_applied = false;

$current_user = auth_user();
$current_user_email = trim((string)($current_user['email'] ?? ''));
if ($current_user_email === '') {
    die('No s\'ha pogut determinar el correu de l\'usuari autenticat.');
}

// Sempre obtenir totes les incidències del usuari autenticat
$sql_user = "SELECT id, departament, descripcio_curta, localitzacio, email, data_incidencia, estat, prioritat FROM incidencies WHERE email = ? ORDER BY data_incidencia DESC";
$stmt_user = $conn->prepare($sql_user);
if ($stmt_user !== false) {
    $stmt_user->bind_param('s', $current_user_email);
    $stmt_user->execute();
    $resultats_usuario = $stmt_user->get_result();
    $stmt_user->close();
}

// Aplicar filtres si es necessari
if ($search_id !== '' || $search_departament !== '') {
    $filter_applied = true;
    
    $sql = "SELECT id, departament, descripcio_curta, localitzacio, email, data_incidencia, estat, prioritat FROM incidencies WHERE email = ?";
    $params = [$current_user_email];
    $types = 's';

    if ($search_id !== '') {
        $sql .= " AND id = ?";
        $params[] = (int) $search_id;
        $types .= 'i';
    }

    if ($search_departament !== '') {
        $sql .= " AND departament = ?";
        $params[] = $search_departament;
        $types .= 's';
    }

    $sql .= " ORDER BY data_incidencia DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt !== false) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $resultats = $stmt->get_result();
        $stmt->close();
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

    <?php if ($resultats_usuario !== null && $resultats_usuario->num_rows > 0) : ?>
        <div class="row">
            <div class="col-12">
                <h2 class="h5 mb-3">Les teves incidències (<?php echo $resultats_usuario->num_rows; ?> total)</h2>
                <?php while ($row = $resultats_usuario->fetch_assoc()) : ?>
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
                            <div class="mt-3">
                                <a href="detalle_usuario.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-primary">Ver detall</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php else : ?>
        <div class="alert alert-info" role="alert">
            No tens cap incidència registrada
        </div>
    <?php endif; ?>

    <?php if ($filter_applied) : ?>
        <hr class="my-5" />
        <h2 class="h5 mb-3">Resultats de la cerca</h2>
        <?php if ($resultats !== null && $resultats->num_rows > 0) : ?>
            <div class="row">
                <div class="col-12">
                    <p class="text-muted">Incidències que coincideixen amb els filtres (<?php echo $resultats->num_rows; ?>)</p>
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
                                <div class="mt-3">
                                    <a href="detalle_usuario.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-primary">Ver detall</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php else : ?>
            <div class="alert alert-warning" role="alert">
                Cap incidència no coincideix amb els filtres aplicats
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/footer.php'; ?>