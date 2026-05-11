<?php
require_once __DIR__ . '/connexio.php';
require_once __DIR__ . '/incidencies_schema.php';

$tab = strtolower(trim((string)($_GET['tab'] ?? 'add')));
if (!in_array($tab, ['add', 'historial'], true)) {
    $tab = 'add';
}

$tecnic_hint = trim((string)($_GET['tecnic'] ?? ''));

$schema_result = ensure_incidencies_schema($conn);
if (!is_array($schema_result) || ($schema_result['ok'] ?? false) !== true) {
    die("Error inicialitzant l'esquema d'incidències");
}

$id = (int)($_GET['id'] ?? $_GET['incident_id'] ?? 0);
if ($id <= 0) {
    require_once __DIR__ . '/header.php';
    echo "<div class='container py-4'><div class='alert alert-danger'>ID d'incidència invàlid.</div></div>";
    include __DIR__ . '/footer.php';
    exit;
}

$alert = null;

// Handle Work Log POST before any HTML output (PRG).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add_worklog') {
        $opened_at = trim((string)($_POST['opened_at'] ?? ''));
        $hours_raw = trim((string)($_POST['hours_spent'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($hours_raw === '' || !is_numeric($hours_raw)) {
            $alert = ['type' => 'danger', 'message' => "Introdueix les hores (número)."];
            $tab = 'add';
        } elseif ($description === '') {
            $alert = ['type' => 'danger', 'message' => "Introdueix la descripció del treball."];
            $tab = 'add';
        } else {
            try {
                require_once __DIR__ . '/logger.php';

                $user = logger_authenticated_user();
                if ($user === null || trim($user) === '') {
                    $user = $tecnic_hint !== '' ? $tecnic_hint : null;
                }

                $hours_value = (float)$hours_raw;
                if ($hours_value < 0) {
                    $hours_value = 0.0;
                }
                $hours_value = round($hours_value, 2);

                $opened_at_value = $opened_at !== '' ? $opened_at : date('Y-m-d H:i:s');
                if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $opened_at_value)) {
                    $opened_at_value = date('Y-m-d H:i:s');
                }

                $stmtWL = $conn->prepare('INSERT INTO worklogs (incident_id, opened_at, user, hours_spent, description) VALUES (?, ?, ?, ?, ?)');
                if ($stmtWL === false) {
                    throw new RuntimeException('Error preparant la inserció: ' . $conn->error);
                }
                $stmtWL->bind_param('issds', $id, $opened_at_value, $user, $hours_value, $description);
                if (!$stmtWL->execute()) {
                    $stmtWL->close();
                    throw new RuntimeException('Error desant el Work Log: ' . $conn->error);
                }
                $stmtWL->close();

                $redirect_params = ['id' => (string)$id, 'tab' => 'historial'];
                if ($tecnic_hint !== '') {
                    $redirect_params['tecnic'] = $tecnic_hint;
                }
                $redirect_params['flash_type'] = 'success';
                $redirect_params['flash_msg'] = 'Work Log desat correctament.';
                header('Location: detall_incidencia.php?' . http_build_query($redirect_params));
                exit;
            } catch (Throwable $e) {
                $alert = ['type' => 'danger', 'message' => "Error guardant el Work Log: " . $e->getMessage()];
                $tab = 'add';
            }
        }
    }
}

// Flash alert support (after PRG redirect).
if ($alert === null) {
    $flash_type = (string)($_GET['flash_type'] ?? '');
    $flash_msg = trim((string)($_GET['flash_msg'] ?? ''));
    $valid_flash_types = ['success', 'warning', 'danger', 'info'];
    if ($flash_msg !== '' && in_array($flash_type, $valid_flash_types, true)) {
        $flash_msg_short = function_exists('mb_substr')
            ? (string) mb_substr($flash_msg, 0, 400)
            : (string) substr($flash_msg, 0, 400);
        $alert = ['type' => $flash_type, 'message' => $flash_msg_short];
    }
}

require_once __DIR__ . '/header.php';

// Obtenir la incidència de MySQL
$stmt = $conn->prepare('SELECT id, departament, localitzacio, email, descripcio_llarga, descripcio_curta, data_incidencia, estat, prioritat, tecnic_assignat, data_inici_tasca, data_tancament FROM incidencies WHERE id = ? LIMIT 1');
if ($stmt === false) {
    echo "<div class='container py-4'><div class='alert alert-danger'>Error de base de dades.</div></div>";
    include __DIR__ . '/footer.php';
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$inc = $result->fetch_assoc();
$stmt->close();

if (!$inc) {
    echo "<div class='container py-4'><div class='alert alert-warning'>No s'ha trobat la incidència #" . htmlspecialchars((string)$id) . "</div></div>";
    include __DIR__ . '/footer.php';
    exit;
}

// Carregar worklogs des de MySQL
$worklogs = [];
try {
    $stmtList = $conn->prepare('SELECT opened_at, user, hours_spent, description FROM worklogs WHERE incident_id = ? ORDER BY opened_at DESC, created_at DESC');
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
    $alert = $alert ?? ['type' => 'warning', 'message' => "No s'han pogut carregar els Work Logs: " . $e->getMessage()];
}

$opened_at = date('Y-m-d H:i:s');

require_once __DIR__ . '/logger.php';
$user_display = logger_authenticated_user();
if ($user_display === null || trim($user_display) === '') {
    $user_display = $tecnic_hint !== '' ? $tecnic_hint : '—';
}

$base_params = ['id' => (string)$id];
if ($tecnic_hint !== '') {
    $base_params['tecnic'] = $tecnic_hint;
}

?>
<div class="container py-4" style="max-width:900px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Incidència #<?php echo htmlspecialchars((string)$inc['id']); ?></h1>
        <div>
            <a href="/php/incidencies/llistar.php" class="btn btn-sm btn-outline-secondary ms-2">Tornar</a>
        </div>
    </div>

    <?php if (is_array($alert)) : ?>
        <div class="alert alert-<?php echo htmlspecialchars((string)($alert['type'] ?? 'info')); ?>">
            <?php echo htmlspecialchars((string)($alert['message'] ?? '')); ?>
        </div>
    <?php endif; ?>

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

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <?php $add_href = 'detall_incidencia.php?' . http_build_query($base_params + ['tab' => 'add']); ?>
            <a class="nav-link <?php echo $tab === 'add' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($add_href, ENT_QUOTES); ?>">Afegir Work Log</a>
        </li>
        <li class="nav-item">
            <?php $hist_href = 'detall_incidencia.php?' . http_build_query($base_params + ['tab' => 'historial']); ?>
            <a class="nav-link <?php echo $tab === 'historial' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($hist_href, ENT_QUOTES); ?>">Historial</a>
        </li>
    </ul>

    <?php if ($tab === 'add') : ?>
        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="add_worklog">
                    <input type="hidden" name="opened_at" value="<?php echo htmlspecialchars($opened_at, ENT_QUOTES); ?>">

                    <div class="col-md-6">
                        <label class="form-label">Data i hora</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($opened_at, ENT_QUOTES); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Usuari</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars((string)$user_display, ENT_QUOTES); ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hores</label>
                        <input type="number" name="hours_spent" class="form-control" min="0" step="0.25" required autofocus>
                        <div class="form-text">Ex: 0.5, 1, 1.25</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripció del treball</label>
                        <textarea name="description" class="form-control" rows="4" required placeholder="Què has fet?"></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'historial') : ?>
        <h2 class="h5">Work Logs</h2>
        <?php if (count($worklogs) === 0) : ?>
            <div class="alert alert-secondary">No hi ha entrades de Work Log per aquesta incidència.</div>
        <?php else : ?>
            <div class="list-group mb-4">
                <?php foreach ($worklogs as $w) : ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars((string)($w['user'] ?? '')); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars((string)($w['opened_at'] ?? '')); ?></small>
                        </div>
                        <p class="mb-1"><strong>Temps:</strong> <?php echo htmlspecialchars((string)($w['hours_spent'] ?? '')); ?></p>
                        <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars((string)($w['description'] ?? ''))); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/footer.php';
