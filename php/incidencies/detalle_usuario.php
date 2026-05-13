<?php
require_once __DIR__ . '/auth.php';
auth_require_login();

require_once __DIR__ . '/connexio.php';
require_once __DIR__ . '/incidencies_schema.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: llistar.php');
    exit;
}

$schema_result = ensure_incidencies_schema($conn);
if (!is_array($schema_result) || ($schema_result['ok'] ?? false) !== true) {
    die("Error inicialitzant l'esquema d'incidències");
}

require_once __DIR__ . '/header.php';

$current_user = auth_user();
$current_user_email = trim((string)($current_user['email'] ?? ''));

// Obtenir la incidència de MySQL - verificar que pertany al usuari autenticat
$stmt = $conn->prepare('SELECT id, departament, localitzacio, email, descripcio_llarga, descripcio_curta, data_incidencia, estat, prioritat, tecnic_assignat, data_inici_tasca, data_tancament FROM incidencies WHERE id = ? AND email = ? LIMIT 1');
if ($stmt === false) {
    echo "<div class='container py-4'><div class='alert alert-danger'>Error de base de dades.</div></div>";
    include __DIR__ . '/footer.php';
    exit;
}
$stmt->bind_param('is', $id, $current_user_email);
$stmt->execute();
$result = $stmt->get_result();
$inc = $result->fetch_assoc();
$stmt->close();

if (!$inc) {
    echo "<div class='container py-4'><div class='alert alert-warning'>Incidència no trobada o no tens permisos per accedir-la.</div></div>";
    include __DIR__ . '/footer.php';
    exit;
}

// Carregar worklogs visibles per l'usuari
$worklogs = [];
try {
    $stmtList = $conn->prepare('SELECT opened_at, user, hours_spent, description, visible_to_user FROM worklogs WHERE incident_id = ? AND visible_to_user = 1 ORDER BY opened_at DESC, created_at DESC');
    if ($stmtList !== false) {
        $stmtList->bind_param('i', $id);
        if ($stmtList->execute()) {
            $res = $stmtList->get_result();
            if ($res !== false) {
                while ($row = $res->fetch_assoc()) {
                    $worklogs[] = $row;
                }
                $res->free();
            }
        }
        $stmtList->close();
    }
} catch (Throwable $e) {
    // ...existing code...
}

?>
<div class="container py-4" style="max-width:900px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Incidència #<?php echo htmlspecialchars((string)$inc['id']); ?></h1>
        <div>
            <a href="/incidencies/llistar.php" class="btn btn-sm btn-outline-secondary ms-2">Tornar</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Departament:</strong> <?php echo htmlspecialchars((string)$inc['departament']); ?></p>
                    <p><strong>Localització:</strong> <?php echo htmlspecialchars((string)$inc['localitzacio']); ?></p>
                    <p><strong>Data:</strong> <?php echo htmlspecialchars((string)$inc['data_incidencia']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Correu:</strong> <?php echo htmlspecialchars((string)$inc['email']); ?></p>
                    <p><strong>Estat:</strong> <span class="badge bg-info"><?php echo htmlspecialchars((string)$inc['estat']); ?></span></p>
                    <p><strong>Prioritat:</strong> <span class="badge bg-warning"><?php echo htmlspecialchars((string)$inc['prioritat']); ?></span></p>
                </div>
            </div>
            <hr>
            <p><strong>Descripció:</strong></p>
            <p class="text-muted"><?php echo nl2br(htmlspecialchars((string)$inc['descripcio_llarga'] ?: $inc['descripcio_curta'])); ?></p>
        </div>
    </div>

    <!-- Mostrar worklogs visibles per l'usuari -->
    <h2 class="h5 mb-3">
        <?php 
        $estat = strtolower(trim((string)$inc['estat']));
        $is_closed = ($estat === 'tancada');
        if ($is_closed) {
            echo "✓ Solució";
        } else {
            echo "Actuacions (Tècnics)";
        }
        ?>
    </h2>
    
    <?php if (count($worklogs) === 0) : ?>
        <div class="alert alert-secondary">
            <?php echo $is_closed ? "La incidència s'ha tancat però no hi ha notes visibles." : "No hi ha actuacions visibles per al moment."; ?>
        </div>
    <?php else : ?>
        <div class="list-group mb-4">
            <?php foreach ($worklogs as $w) : ?>
                <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1"><?php echo htmlspecialchars((string)($w['user'] ?? 'Tècnic')); ?></h6>
                        <small class="text-muted"><?php echo htmlspecialchars((string)($w['opened_at'] ?? '')); ?></small>
                    </div>
                    <p class="mb-1"><strong>Temps invertit:</strong> <?php echo htmlspecialchars((string)($w['hours_spent'] ?? '')); ?> hores</p>
                    <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars((string)($w['description'] ?? ''))); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/footer.php';
