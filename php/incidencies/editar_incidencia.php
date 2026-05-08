<?php
require_once 'connexio.php';
require_once 'incidencies_list.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    include 'header.php';
    echo "<div class='container py-4'><div class='alert alert-danger'>ID d'incidència no vàlid.</div></div>";
    include 'footer.php';
    exit;
}
//leer tipologia de la incidencia
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departament = trim((string)($_POST['departament'] ?? ''));
    $descripcio_curta = trim((string)($_POST['descripcio_curta'] ?? ''));
    $tipologia = strtolower(trim((string)($_POST['tipologia'] ?? '')));
//validaciones
    $errors = [];
    if ($departament === '' || $descripcio_curta === '') {
        $errors[] = 'Omple tots els camps obligatoris.';
    }
    if (mb_strlen($departament) > 80) {
        $errors[] = 'El departament és massa llarg (màxim 80 caràcters).';
    }
    if (mb_strlen($descripcio_curta) > 255) {
        $errors[] = 'La descripció és massa llarga (màxim 255 caràcters).';
    }

    $tipologies_valides = [
    INCIDENCIA_TIPOLOGIA_HARDWARE,
    INCIDENCIA_TIPOLOGIA_SOFTWARE,
    INCIDENCIA_TIPOLOGIA_XARXA,
    INCIDENCIA_TIPOLOGIA_COMPTES,
    INCIDENCIA_TIPOLOGIA_IMPRESSIO,
    INCIDENCIA_TIPOLOGIA_AULES,
    INCIDENCIA_TIPOLOGIA_MOBILS,
    INCIDENCIA_TIPOLOGIA_PLATAFORMES,
    INCIDENCIA_TIPOLOGIA_SEGURETAT,
    ];

    if (!in_array($tipologia, $tipologies_valides, true)) {
    $errors[] = 'La tipologia no és vàlida.';   
    }
    //actualizan la query de update
    if (empty($errors)) {
        $stmt = $conn->prepare('UPDATE incidencies SET departament = ?, descripcio_curta = ?, tipologia = ? WHERE id = ?');
        if ($stmt === false) {
            $errors[] = 'Error preparant la consulta: ' . htmlspecialchars($conn->error);
        } else { //cambiamos el bind_param
            $stmt->bind_param('ssi', $departament, $descripcio_curta,$tipologia, $id);
            if ($stmt->execute()) {
                header('Location: editar_incidencia.php?' . http_build_query(['id' => $id, 'saved' => 1]));
                exit;
            } else {
                $errors[] = 'Error actualitzant la incidència: ' . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        }
    }
}

$success = (isset($_GET['saved']) && (string)$_GET['saved'] === '1');

$row = null;
//cargar desde BD
$stmt = $conn->prepare('SELECT id, departament, descripcio_curta, tipologia, data_incidencia FROM incidencies WHERE id = ?');
if ($stmt !== false) {
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res !== false) {
            $row = $res->fetch_assoc();
            $res->free();
        }
    }
    $stmt->close();
}

if ($row === null) {
    include 'header.php';
    echo "<div class='container py-4'><div class='alert alert-danger'>No s'ha trobat la incidència.</div></div>";
    include 'footer.php';
    exit;
}

include 'header.php';
?>

<div class="container py-4" style="max-width:760px;">
    <h1 class="h3 mb-3">Editar incidència #<?php echo htmlspecialchars((string)$row['id']); ?></h1>

    <?php
    if (!empty($errors)) {
        echo '<div class="alert alert-danger"><ul>'; foreach ($errors as $e) { echo '<li>' . htmlspecialchars($e) . '</li>'; } echo '</ul></div>';
    }
    if (!empty($success)) {
        echo "<div class='alert alert-success'>Incidència actualitzada correctament.</div>";
    }
    ?>

    <form method="POST" action="editar_incidencia.php?id=<?php echo (int)$row['id']; ?>" class="card card-body">
        <div class="mb-3">
            <label for="departament" class="form-label">Departament</label>
            <input type="text" id="departament" name="departament" class="form-control" required maxlength="80" value="<?php echo htmlspecialchars((string)($_POST['departament'] ?? $row['departament'])); ?>">
        </div>

        <div class="mb-3">
            <label for="descripcio_curta" class="form-label">Descripció curta</label>
            <textarea id="descripcio_curta" name="descripcio_curta" class="form-control" rows="3" maxlength="255" required><?php echo htmlspecialchars((string)($_POST['descripcio_curta'] ?? $row['descripcio_curta'])); ?></textarea>
        </div>
        
        //añadir select de tipologia al form
        <div class="mb-3">
            <label for="tipologia" class="form-label">Tipologia</label>

            <select id="tipologia" name="tipologia" class="form-select" required>
                <option value="hardware" <?php echo (($_POST['tipologia'] ?? $row['tipologia']) === 'hardware') ? 'selected' : ''; ?>>Hardware</option>

                <option value="software" <?php echo (($_POST['tipologia'] ?? $row['tipologia']) === 'software') ? 'selected' : ''; ?>>Software</option>

                <option value="xarxa" <?php echo (($_POST['tipologia'] ?? $row['tipologia']) === 'xarxa') ? 'selected' : ''; ?>>Xarxa</option>

                <option value="comptes" <?php echo (($_POST['tipologia'] ?? $row['tipologia']) === 'comptes') ? 'selected' : ''; ?>>Comptes</option>

                <option value="impressio" <?php echo (($_POST['tipologia'] ?? $row['tipologia']) === 'impressio') ? 'selected' : ''; ?>>Impressió</option>

                <option value="aules" <?php echo (($_POST['tipologia'] ?? $row['tipologia']) === 'aules') ? 'selected' : ''; ?>>Aules</option>

                <option value="mobils" <?php echo (($_POST['tipologia'] ?? $row['tipologia']) === 'mobils') ? 'selected' : ''; ?>>Mòbils</option>

                <option value="plataformes" <?php echo (($_POST['tipologia'] ?? $row['tipologia']) === 'plataformes') ? 'selected' : ''; ?>>Plataformes</option>

                <option value="seguretat" <?php echo (($_POST['tipologia'] ?? $row['tipologia']) === 'seguretat') ? 'selected' : ''; ?>>Seguretat</option>
            </select>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Desar canvis</button>
            <a class="btn btn-outline-secondary" href="todas_las_incidencias.php">Tornar al llistat</a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
