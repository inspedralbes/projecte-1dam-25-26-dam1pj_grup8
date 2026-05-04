<?php

// Show the "Crear Usuari" button in the header for this page.
$showCrearUsuariButton = true;
$showUsuarisButton = true;

include 'header.php';
require_once 'connexio.php';
require_once 'access_logs_schema.php';
require_once 'usuari_schema.php';
require_once 'tecnic_schema.php';

ensure_access_logs_schema($conn);

$alert = null;
$schema_result = ensure_usuari_schema($conn);
$schema_ok = (is_array($schema_result) && ($schema_result['ok'] ?? false) === true);
if (!is_array($schema_result) || ($schema_result['ok'] ?? false) !== true) {
    $alert = [
        'type' => 'danger',
        'message' => "No s'ha pogut inicialitzar l'esquema d'usuaris: " . (string)($schema_result['error'] ?? 'Error desconegut'),
    ];
}

// (button is now enabled above before header include)

$allowed_roles = ['TECNIC', 'ADMIN', 'RESPONSABLE', 'PROFESSOR'];

$usuaris_rows = [];
$selected_usuari = null;
$open_usuari_detail_modal = false;
$has_created_at_col = $schema_ok ? columna_existeix($conn, 'USUARI', 'CREATED_AT') : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $alert === null) {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'view_usuari') {
        $usuari_id = (int)($_POST['usuari_id'] ?? 0);
        if ($usuari_id > 0) {
            $stmt = $conn->prepare('SELECT * FROM USUARI WHERE USUARI_ID = ?');
            if ($stmt !== false) {
                $stmt->bind_param('i', $usuari_id);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($res !== false) {
                        $selected_usuari = $res->fetch_assoc();
                        $res->free();
                        $open_usuari_detail_modal = is_array($selected_usuari);
                    }
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'update_usuari') {
        $usuari_id = (int)($_POST['usuari_id'] ?? 0);
        $first_name_u = trim((string)($_POST['first_name'] ?? ''));
        $last_name_u = trim((string)($_POST['last_name'] ?? ''));
        $email_u = trim((string)($_POST['email'] ?? ''));
        $phone_u = trim((string)($_POST['phone'] ?? ''));
        $role_u = strtoupper(trim((string)($_POST['role'] ?? '')));

        $errors_u = [];
        if ($usuari_id <= 0) {
            $errors_u[] = 'Usuari invàlid.';
        }
        if ($first_name_u === '') {
            $errors_u[] = 'El nom és obligatori.';
        }
        if ($last_name_u === '') {
            $errors_u[] = 'Els cognoms són obligatoris.';
        }
        if ($email_u === '') {
            $errors_u[] = "L'email és obligatori.";
        } elseif (filter_var($email_u, FILTER_VALIDATE_EMAIL) === false) {
            $errors_u[] = "L'email no és vàlid.";
        }
        if (!in_array($role_u, $allowed_roles, true)) {
            $errors_u[] = 'Rol invàlid.';
        }

        if (count($errors_u) > 0) {
            $alert = ['type' => 'warning', 'message' => implode(' ', $errors_u)];
        } else {
            $has_phone_col_u = columna_existeix($conn, 'USUARI', 'PHONE_NUMBER');
            $phone_value_u = ($phone_u !== '') ? $phone_u : '000000000';

            if ($has_phone_col_u) {
                $stmt = $conn->prepare('UPDATE USUARI SET FIRST_NAME = ?, LAST_NAME = ?, EMAIL = ?, PHONE_NUMBER = ?, ROLE = ? WHERE USUARI_ID = ?');
                if ($stmt !== false) {
                    $stmt->bind_param('sssssi', $first_name_u, $last_name_u, $email_u, $phone_value_u, $role_u, $usuari_id);
                    $ok = $stmt->execute();
                    $stmt->close();
                } else {
                    $ok = false;
                }
            } else {
                $stmt = $conn->prepare('UPDATE USUARI SET FIRST_NAME = ?, LAST_NAME = ?, EMAIL = ?, ROLE = ? WHERE USUARI_ID = ?');
                if ($stmt !== false) {
                    $stmt->bind_param('ssssi', $first_name_u, $last_name_u, $email_u, $role_u, $usuari_id);
                    $ok = $stmt->execute();
                    $stmt->close();
                } else {
                    $ok = false;
                }
            }

            if (!$ok) {
                $alert = ['type' => 'danger', 'message' => "No s'ha pogut actualitzar l'usuari: " . $conn->error];
            } else {
                // Keep TECNIC table in sync when role becomes TECNIC/RESPONSABLE.
                if ($role_u === 'TECNIC' || $role_u === 'RESPONSABLE') {
                    $tecnic_schema_result = ensure_tecnic_schema($conn);
                    if (is_array($tecnic_schema_result) && ($tecnic_schema_result['ok'] ?? false) === true) {
                        $rol_employee = ($role_u === 'RESPONSABLE') ? 'ENCARGADO' : 'TECNICO';

                        $select_tecnic = $conn->prepare('SELECT TECNIC_ID FROM TECNIC WHERE EMAIL = ? LIMIT 1');
                        if ($select_tecnic !== false) {
                            $select_tecnic->bind_param('s', $email_u);
                            if ($select_tecnic->execute()) {
                                $res = $select_tecnic->get_result();
                                $exists = ($res !== false && $res->num_rows > 0);
                                if ($res !== false) {
                                    $res->free();
                                }

                                if ($exists) {
                                    $upd = $conn->prepare('UPDATE TECNIC SET FIRST_NAME = ?, LAST_NAME = ?, PHONE_NUMBER = ?, ROL_EMPLOYEE = ? WHERE EMAIL = ?');
                                    if ($upd !== false) {
                                        $upd->bind_param('sssss', $first_name_u, $last_name_u, $phone_value_u, $rol_employee, $email_u);
                                        $upd->execute();
                                        $upd->close();
                                    }
                                } else {
                                    $ins = $conn->prepare('INSERT INTO TECNIC (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROL_EMPLOYEE) VALUES (?, ?, ?, ?, ?, ?)');
                                    if ($ins !== false) {
                                        $pwd = 'demo';
                                        $ins->bind_param('ssssss', $first_name_u, $last_name_u, $email_u, $pwd, $phone_value_u, $rol_employee);
                                        $ins->execute();
                                        $ins->close();
                                    }
                                }
                            }
                            $select_tecnic->close();
                        }
                    }
                }

                $alert = ['type' => 'success', 'message' => 'Usuari actualitzat correctament.'];

                // Reload selected user for detail modal.
                $stmt = $conn->prepare('SELECT * FROM USUARI WHERE USUARI_ID = ?');
                if ($stmt !== false) {
                    $stmt->bind_param('i', $usuari_id);
                    if ($stmt->execute()) {
                        $res = $stmt->get_result();
                        if ($res !== false) {
                            $selected_usuari = $res->fetch_assoc();
                            $res->free();
                            $open_usuari_detail_modal = is_array($selected_usuari);
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }

    if ($action === 'create_usuari') {
        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $role = strtoupper(trim((string)($_POST['role'] ?? '')));

        $errors = [];
        if ($first_name === '') {
            $errors[] = 'El nom és obligatori.';
        }
        if ($last_name === '') {
            $errors[] = 'Els cognoms són obligatoris.';
        }
        if ($email === '') {
            $errors[] = "L'email és obligatori.";
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = "L'email no és vàlid.";
        }
        if (!in_array($role, $allowed_roles, true)) {
            $errors[] = 'Has de seleccionar un rol.';
        }

        if (count($errors) > 0) {
            $alert = ['type' => 'warning', 'message' => implode(' ', $errors)];
        } else {
            $has_password_col = function_exists('columna_existeix') ? columna_existeix($conn, 'USUARI', 'PASSWORD') : false;
            $has_phone_col = function_exists('columna_existeix') ? columna_existeix($conn, 'USUARI', 'PHONE_NUMBER') : false;
            $has_department_col = function_exists('columna_existeix') ? columna_existeix($conn, 'USUARI', 'DEPARTMENT_ID') : false;

            if ($has_password_col && $has_department_col) {
                $stmt = $conn->prepare('INSERT INTO USUARI (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, DEPARTMENT_ID, ROLE) VALUES (?, ?, ?, ?, ?, ?, ?)');
            } elseif ($has_password_col) {
                $stmt = $conn->prepare('INSERT INTO USUARI (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROLE) VALUES (?, ?, ?, ?, ?, ?)');
            } else {
                // Newer simplified schema
                $stmt = $conn->prepare('INSERT INTO USUARI (FIRST_NAME, LAST_NAME, EMAIL, PHONE_NUMBER, ROLE) VALUES (?, ?, ?, ?, ?)');
            }
            if ($stmt === false) {
                $alert = ['type' => 'danger', 'message' => 'Error preparant la consulta: ' . $conn->error];
            } else {
                $phone_value = ($phone !== '') ? $phone : ($has_phone_col ? '000000000' : '');

                if ($has_password_col && $has_department_col) {
                    $password_value = 'demo';
                    $dept_id = function_exists('ensure_default_department_id') ? ensure_default_department_id($conn) : null;
                    $dept_id = is_int($dept_id) ? $dept_id : 1;
                    $stmt->bind_param('sssssis', $first_name, $last_name, $email, $password_value, $phone_value, $dept_id, $role);
                } elseif ($has_password_col) {
                    $password_value = 'demo';
                    $stmt->bind_param('ssssss', $first_name, $last_name, $email, $password_value, $phone_value, $role);
                } else {
                    $stmt->bind_param('sssss', $first_name, $last_name, $email, $phone_value, $role);
                }
                $ok = $stmt->execute();
                $stmt->close();

                if (!$ok) {
                    // Most common: duplicate email.
                    $alert = ['type' => 'danger', 'message' => "No s'ha pogut crear l'usuari: " . $conn->error];
                } else {
                    // If it's a technician/responsible, also ensure it exists in TECNIC (used by dropdowns).
                    if ($role === 'TECNIC' || $role === 'RESPONSABLE') {
                        $tecnic_schema_result = ensure_tecnic_schema($conn);
                        if (is_array($tecnic_schema_result) && ($tecnic_schema_result['ok'] ?? false) === true) {
                            $rol_employee = ($role === 'RESPONSABLE') ? 'ENCARGADO' : 'TECNICO';

                            $select_tecnic = $conn->prepare('SELECT TECNIC_ID FROM TECNIC WHERE EMAIL = ? LIMIT 1');
                            if ($select_tecnic !== false) {
                                $select_tecnic->bind_param('s', $email);
                                if ($select_tecnic->execute()) {
                                    $res = $select_tecnic->get_result();
                                    $exists = ($res !== false && $res->num_rows > 0);
                                    if ($res !== false) {
                                        $res->free();
                                    }

                                    if (!$exists) {
                                        $insert_tecnic = $conn->prepare("INSERT INTO TECNIC (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROL_EMPLOYEE) VALUES (?, ?, ?, ?, ?, ?)");
                                        if ($insert_tecnic !== false) {
                                            $password = 'demo';
                                            $phone_tecnic = ($phone !== '') ? $phone : '000000000';
                                            $insert_tecnic->bind_param('ssssss', $first_name, $last_name, $email, $password, $phone_tecnic, $rol_employee);
                                            $insert_tecnic->execute();
                                            $insert_tecnic->close();
                                        }
                                    }
                                }
                                $select_tecnic->close();
                            }
                        }
                    }

                    $alert = ['type' => 'success', 'message' => "Usuari creat correctament ($first_name $last_name)."];
                }
            }
        }
    }
}

// Always load user list after handling POST so the modal shows fresh data.
if ($schema_ok) {
    $usuaris_rows = [];
    $select_sql = $has_created_at_col
        ? 'SELECT USUARI_ID, FIRST_NAME, LAST_NAME, EMAIL, ROLE, CREATED_AT FROM USUARI ORDER BY USUARI_ID DESC'
        : 'SELECT USUARI_ID, FIRST_NAME, LAST_NAME, EMAIL, ROLE FROM USUARI ORDER BY USUARI_ID DESC';
    $res = $conn->query($select_sql);
    if ($res !== false) {
        while ($row = $res->fetch_assoc()) {
            $usuaris_rows[] = $row;
        }
        $res->free();
    }
}
?>

<link rel="stylesheet" href="css/admin.css">

<div class="container py-5">

    <?php if (is_array($alert)) : ?>
        <div class="alert alert-<?php echo htmlspecialchars((string)($alert['type'] ?? 'info')); ?>" role="alert">
            <?php echo htmlspecialchars((string)($alert['message'] ?? '')); ?>
        </div>
    <?php endif; ?>

    <h1 class="mb-4 text-center fw-bold">
        Panell d'Estadístiques d'Accessos
    </h1>

    <!-- FILTROS -->
    <div class="card shadow-sm p-4 mb-4">
        <h4 class="mb-3">Filtres</h4>

        <div class="row g-3">

            <div class="col-md-4">
                <label class="form-label">Data inici</label>
                <input type="date" id="fecha_inicio" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label">Data fi</label>
                <input type="date" id="fecha_fin" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label">Usuari</label>
                <input type="text" id="usuario" class="form-control"
                    placeholder="Filtrar per usuari">
            </div>

            <div class="col-md-8">
                <label class="form-label">Pàgina visitada</label>
                <input type="text" id="pagina" class="form-control"
                    placeholder="Ex: incidencies.php">
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-dark w-100" onclick="cargarStats()">
                    Aplicar filtres
                </button>
            </div>

        </div>
    </div>


    <!-- RESUM -->
    <div class="row g-4 mb-4">

        <div class="col-md-4">
            <div class="stat-card">
                <h5>Accessos totals</h5>
                <h2 id="totalAccess">0</h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <h5>Pàgines visitades</h5>
                <h2 id="totalPages">0</h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <h5>Usuaris actius</h5>
                <h2 id="activeUsers">0</h2>
            </div>
        </div>

    </div>


    <!-- GRAFICOS -->
    <div class="row g-4 mb-5">

        <div class="col-md-6">
            <div class="chart-box">
                <h4>Tendència d'ús</h4>
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="col-md-6">
            <div class="chart-box">
                <h4>Pàgines més visitades</h4>
                <canvas id="pagesChart"></canvas>
            </div>
        </div>

    </div>


    <!-- TABLAS -->
    <div class="row g-4">

        <div class="col-md-6">
            <div class="table-box">
                <h4>Top usuaris actius</h4>

                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Usuari</th>
                            <th>Accessos</th>
                        </tr>
                    </thead>
                    <tbody id="usersTable"></tbody>
                </table>

            </div>
        </div>


        <div class="col-md-6">
            <div class="table-box">
                <h4>Pàgines més visitades</h4>

                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Pàgina</th>
                            <th>Visites</th>
                        </tr>
                    </thead>

                    <tbody id="pagesTable"></tbody>

                </table>

            </div>
        </div>

    </div>

</div>

<!-- Modal: Usuaris (llistat) -->
<div class="modal fade" id="usuarisModal" tabindex="-1" aria-labelledby="usuarisLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="usuarisLabel">Usuaris</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tancar"></button>
            </div>

            <div class="modal-body">
                <?php if (!$schema_ok) : ?>
                    <div class="alert alert-danger mb-0" role="alert">
                        No s'ha pogut carregar la llista d'usuaris.
                    </div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Cognoms</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <?php if ($has_created_at_col) : ?>
                                        <th>Creat</th>
                                    <?php endif; ?>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($usuaris_rows) === 0) : ?>
                                    <tr>
                                        <td colspan="<?php echo $has_created_at_col ? '7' : '6'; ?>" class="text-muted">No hi ha usuaris.</td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($usuaris_rows as $u) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string)($u['USUARI_ID'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars((string)($u['FIRST_NAME'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars((string)($u['LAST_NAME'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars((string)($u['EMAIL'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars((string)($u['ROLE'] ?? '')); ?></td>
                                            <?php if ($has_created_at_col) : ?>
                                                <td><?php echo htmlspecialchars((string)($u['CREATED_AT'] ?? '')); ?></td>
                                            <?php endif; ?>
                                            <td class="text-end">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="view_usuari">
                                                    <input type="hidden" name="usuari_id" value="<?php echo htmlspecialchars((string)($u['USUARI_ID'] ?? '')); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Més informació</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tancar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Usuari (detall + edició) -->
<div class="modal fade" id="usuariDetailModal" tabindex="-1" aria-labelledby="usuariDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" novalidate>
                <input type="hidden" name="action" value="update_usuari">
                <input type="hidden" name="usuari_id" value="<?php echo htmlspecialchars(is_array($selected_usuari) ? (string)($selected_usuari['USUARI_ID'] ?? '') : ''); ?>">

                <div class="modal-header">
                    <h5 class="modal-title" id="usuariDetailLabel">Detall d'usuari</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tancar"></button>
                </div>

                <div class="modal-body">
                    <?php if (!is_array($selected_usuari)) : ?>
                        <p class="text-muted mb-0">Selecciona un usuari des del llistat.</p>
                    <?php else : ?>
                        <h6 class="mb-2">Tots els camps</h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-bordered mb-0">
                                <tbody>
                                    <?php foreach ($selected_usuari as $key => $val) : ?>
                                        <tr>
                                            <th style="width: 35%;"><?php echo htmlspecialchars((string)$key); ?></th>
                                            <td><?php echo htmlspecialchars($val === null ? '' : (string)$val); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <h6 class="mb-2">Editar</h6>
                        <p class="text-muted">Pots editar alguns camps bàsics.</p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="edit_first_name">Nom</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required value="<?php echo htmlspecialchars((string)($selected_usuari['FIRST_NAME'] ?? '')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="edit_last_name">Cognoms</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required value="<?php echo htmlspecialchars((string)($selected_usuari['LAST_NAME'] ?? '')); ?>">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label" for="edit_email">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required value="<?php echo htmlspecialchars((string)($selected_usuari['EMAIL'] ?? '')); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="edit_phone">Telèfon</label>
                                <input type="text" class="form-control" id="edit_phone" name="phone" value="<?php echo htmlspecialchars((string)($selected_usuari['PHONE_NUMBER'] ?? '')); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="edit_role">Rol</label>
                                <select class="form-select" id="edit_role" name="role" required>
                                    <?php foreach ($allowed_roles as $r) : ?>
                                        <option value="<?php echo htmlspecialchars((string)$r); ?>" <?php echo ((string)($selected_usuari['ROLE'] ?? '') === (string)$r) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string)$r); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tancar</button>
                    <?php if (is_array($selected_usuari)) : ?>
                        <button type="submit" class="btn btn-success">Guardar canvis</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($open_usuari_detail_modal) : ?>
    <script>
        window.addEventListener('load', function () {
            var el = document.getElementById('usuariDetailModal');
            if (!el || typeof bootstrap === 'undefined') return;
            var modal = new bootstrap.Modal(el);
            modal.show();
        });
    </script>
<?php endif; ?>

<!-- Modal: Crear Usuari -->
<div class="modal fade" id="crearUsuariModal" tabindex="-1" aria-labelledby="crearUsuariLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="crearUsuariForm" novalidate>
                <input type="hidden" name="action" value="create_usuari">

                <div class="modal-header">
                    <h5 class="modal-title" id="crearUsuariLabel">Crear Usuari</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tancar"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted mb-3">Els camps marcats amb <span class="fw-bold">*</span> són obligatoris.</p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="first_name">Nom <span class="fw-bold">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                            <div class="invalid-feedback">El nom és obligatori.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="last_name">Cognoms <span class="fw-bold">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                            <div class="invalid-feedback">Els cognoms són obligatoris.</div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label" for="email">Email <span class="fw-bold">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback">Introdueix un email vàlid.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="phone">Telèfon <span class="text-muted">(opcional)</span></label>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="600123123">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div id="rolStep" class="d-none">
                        <h6 class="mb-2">Rol <span class="fw-bold">*</span></h6>
                        <p class="text-muted mb-3">Selecciona el rol abans de crear l'usuari.</p>

                        <div class="row g-2">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" id="role_tecnic" value="TECNIC" required>
                                    <label class="form-check-label" for="role_tecnic">Tècnic</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" id="role_admin" value="ADMIN" required>
                                    <label class="form-check-label" for="role_admin">Admin</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" id="role_responsable" value="RESPONSABLE" required>
                                    <label class="form-check-label" for="role_responsable">Responsable</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" id="role_professor" value="PROFESSOR" required>
                                    <label class="form-check-label" for="role_professor">Professor</label>
                                </div>
                            </div>
                        </div>
                        <div class="invalid-feedback d-block" id="roleInvalid" style="display:none;">Has de seleccionar un rol.</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel·lar</button>
                    <button type="button" class="btn btn-primary" id="continuarCrearUsuari">Continuar</button>
                    <button type="submit" class="btn btn-success d-none" id="submitCrearUsuari">Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('crearUsuariForm');
    if (!form) return;

    var continuarBtn = document.getElementById('continuarCrearUsuari');
    var submitBtn = document.getElementById('submitCrearUsuari');
    var rolStep = document.getElementById('rolStep');
    var roleInvalid = document.getElementById('roleInvalid');

    function validateBaseFields() {
        var baseInputs = [
            document.getElementById('first_name'),
            document.getElementById('last_name'),
            document.getElementById('email')
        ];

        var ok = true;
        baseInputs.forEach(function (input) {
            if (!input) return;
            if (!input.checkValidity()) {
                input.classList.add('is-invalid');
                ok = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        return ok;
    }

    continuarBtn.addEventListener('click', function () {
        roleInvalid.style.display = 'none';
        if (!validateBaseFields()) {
            return;
        }
        rolStep.classList.remove('d-none');
        submitBtn.classList.remove('d-none');
        continuarBtn.classList.add('d-none');
    });

    form.addEventListener('submit', function (e) {
        roleInvalid.style.display = 'none';
        if (!validateBaseFields()) {
            e.preventDefault();
            continuarBtn.classList.remove('d-none');
            submitBtn.classList.add('d-none');
            rolStep.classList.add('d-none');
            return;
        }

        var roleChecked = form.querySelector('input[name="role"]:checked');
        if (!roleChecked) {
            e.preventDefault();
            roleInvalid.style.display = 'block';
        }
    });
});
</script>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/admin.js"></script>

<?php include 'footer.php'; ?>