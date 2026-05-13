<?php

require_once __DIR__ . '/../incidencies/auth.php';
auth_require_role('ADMIN');

// Ocultem el botó 'Crear Usuari' del header perquè la creació es faci
// des del modal o des del llistat d'usuaris
$showCrearUsuariButton = false;
$showUsuarisButton = true;

include __DIR__ . '/../incidencies/header.php';
require_once __DIR__ . '/../incidencies/connexio.php';
require_once __DIR__ . '/../incidencies/usuari_schema.php';
require_once __DIR__ . '/../incidencies/tecnic_schema.php';

$alert = null;
$schema_result = ensure_usuari_schema($conn); //comporbar que el esquema existeix sino es crea
$schema_ok = (is_array($schema_result) && ($schema_result['ok'] ?? false) === true);
if (!is_array($schema_result) || ($schema_result['ok'] ?? false) !== true) {
    $alert = [
        'type' => 'danger',
        'message' => "No s'ha pogut inicialitzar l'esquema d'usuaris: " . (string)($schema_result['error'] ?? 'Error desconegut'),
    ];
}



$allowed_roles = ['TECNIC', 'ADMIN', 'RESPONSABLE', 'PROFESSOR'];

if (!function_exists('format_datetime_local')) {
    function format_datetime_local($value): string
    {
        if ($value === null) {
            return '';
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return '';
        }
        return date('Y-m-d\TH:i', $ts);
    }
}
/**
 * Carrega els tècnics disponibles
 * per omplir el selector de filtres.
 */
$tecnics_disponibles = [];
if ($schema_ok) {
    $tecnic_schema_result = ensure_tecnic_schema($conn);
    if (is_array($tecnic_schema_result) && ($tecnic_schema_result['ok'] ?? false) === true) {
        $tecnics_query = $conn->query("SELECT FIRST_NAME, LAST_NAME FROM TECNIC ORDER BY FIRST_NAME, LAST_NAME");
        if ($tecnics_query !== false) {
            while ($row = $tecnics_query->fetch_assoc()) {
                $label = trim((string)($row['FIRST_NAME'] ?? '') . ' ' . (string)($row['LAST_NAME'] ?? ''));
                if ($label !== '') {
                    $tecnics_disponibles[] = $label;
                }
            }
            $tecnics_query->free();
        }
    }
}

$usuaris_rows = [];
$selected_usuari = null;
$open_usuari_detail_modal = false;
$has_created_at_col = $schema_ok ? columna_existeix($conn, 'USUARI', 'CREATED_AT') : false;
// Detectem columnes opcionals per al formulari d'edició
$has_username_col = $schema_ok ? columna_existeix($conn, 'USUARI', 'USERNAME') : false;
$has_department_col = $schema_ok ? columna_existeix($conn, 'USUARI', 'DEPARTMENT_ID') : false;
$has_is_verified_col = $schema_ok ? columna_existeix($conn, 'USUARI', 'IS_VERIFIED') : false;

// Metadades de columnes (per renderitzar/validar l'edició completa)
$usuari_columns = [];
if ($schema_ok) {
    $res_cols = $conn->query('SHOW COLUMNS FROM USUARI');
    if ($res_cols !== false) {
        while ($c = $res_cols->fetch_assoc()) {
            if (!is_array($c) || !isset($c['Field'])) {
                continue;
            }
            $field = (string)$c['Field'];
            $usuari_columns[$field] = $c;
        }
        $res_cols->free();
    }
}

// Carreguem departaments si existeix la columna
$departments = [];
if ($has_department_col) {
    $res_dept = $conn->query("SELECT DEPARTMENT_ID, DEPARTMENT_NAME
        FROM DEPARTMENT
        WHERE DEPARTMENT_NAME IN ('ESO','Batxillerat','FP','Administració')
        ORDER BY FIELD(DEPARTMENT_NAME, 'ESO','Batxillerat','FP','Administració')");
    if ($res_dept !== false) {
        while ($drow = $res_dept->fetch_assoc()) {
            $departments[] = $drow;
        }
        $res_dept->free();
    }
}
/**
 * Gestiona les accions principals rebudes
 * des dels formularis del panell.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $alert === null) {
    $action = (string)($_POST['action'] ?? '');

    /**
     * Mostra la informació detallada
     * d'un usuari seleccionat.
     */
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
    /**
     * Actualitza les dades bàsiques
     * d'un usuari existent.
     */
    if ($action === 'update_usuari') {
        $usuari_id = (int)($_POST['usuari_id'] ?? 0);
        $fields_u = $_POST['fields'] ?? [];
        if (!is_array($fields_u)) {
            $fields_u = [];
        }

        // Camps habituals per validacions/sincronització (si existeixen)
        $first_name_u = trim((string)($fields_u['FIRST_NAME'] ?? ''));
        $last_name_u = trim((string)($fields_u['LAST_NAME'] ?? ''));
        $email_u = trim((string)($fields_u['EMAIL'] ?? ''));
        $phone_u = trim((string)($fields_u['PHONE_NUMBER'] ?? ''));
        $role_u = strtoupper(trim((string)($fields_u['ROLE'] ?? '')));
        //valida les dades del form abans de guardar-les
        $errors_u = [];
        if ($usuari_id <= 0) {
            $errors_u[] = 'Usuari invàlid.';
        }
        if (array_key_exists('EMAIL', $fields_u)) {
            if ($email_u === '') {
                $errors_u[] = "L'email és obligatori.";
            } elseif (filter_var($email_u, FILTER_VALIDATE_EMAIL) === false) {
                $errors_u[] = "L'email no és vàlid.";
            }
        }
        if (array_key_exists('ROLE', $fields_u)) {
            if (!in_array($role_u, $allowed_roles, true)) {
                $errors_u[] = 'Rol invàlid.';
            }
        }

        if (count($errors_u) > 0) {
            $alert = ['type' => 'warning', 'message' => implode(' ', $errors_u)];
        } else {
            $ok = true;

            // Guardem cada camp que vingui del formulari, però només si és una columna real de USUARI.
            foreach ($fields_u as $col => $raw_val) {
                $col = (string)$col;
                if ($col === 'USUARI_ID') {
                    continue;
                }
                if (!isset($usuari_columns[$col])) {
                    continue;
                }

                $meta = $usuari_columns[$col];
                $type = strtolower((string)($meta['Type'] ?? ''));
                $nullable = ((string)($meta['Null'] ?? 'YES')) === 'YES';

                // Normalització de valors
                $val = is_string($raw_val) ? trim($raw_val) : $raw_val;

                // Ajustos per camps especials
                if ($col === 'ROLE') {
                    $val = strtoupper(trim((string)$val));
                }

                if ($col === 'IS_VERIFIED') {
                    $val = (int)$val;
                }

                // Converteix datetime-local a format SQL (YYYY-mm-dd HH:ii:00)
                if (in_array($col, ['TOKEN_EXPIRES_AT', 'CREATED_AT', 'UPDATED_AT'], true)) {
                    $v = trim((string)$val);
                    if ($v !== '' && str_contains($v, 'T')) {
                        $val = str_replace('T', ' ', $v) . ':00';
                    } else {
                        $val = $v;
                    }
                }

                // NULL handling
                $is_empty = ($val === '' || $val === null);
                if ($is_empty && $nullable) {
                    $stmt = $conn->prepare('UPDATE USUARI SET `' . $col . '` = NULL WHERE USUARI_ID = ?');
                    if ($stmt !== false) {
                        $stmt->bind_param('i', $usuari_id);
                        $ok = $ok && $stmt->execute();
                        $stmt->close();
                    } else {
                        $ok = false;
                    }
                    continue;
                }

                // Tipus de binding
                $bind_type = 's';
                if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $type) === 1) {
                    $bind_type = 'i';
                    $val = (int)$val;
                }

                $stmt = $conn->prepare('UPDATE USUARI SET `' . $col . '` = ? WHERE USUARI_ID = ?');
                if ($stmt !== false) {
                    $stmt->bind_param($bind_type . 'i', $val, $usuari_id);
                    $ok = $ok && $stmt->execute();
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
                                        $phone_value_u = ($phone_u !== '') ? $phone_u : '000000000';
                                        $upd->bind_param('sssss', $first_name_u, $last_name_u, $phone_value_u, $rol_employee, $email_u);
                                        $upd->execute();
                                        $upd->close();
                                    }
                                } else {
                                    $ins = $conn->prepare('INSERT INTO TECNIC (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROL_EMPLOYEE) VALUES (?, ?, ?, ?, ?, ?)');
                                    if ($ins !== false) {
                                        $phone_value_u = ($phone_u !== '') ? $phone_u : '000000000';
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
    /**
     * Manté sincronitzada la taula TECNIC
     * quan l'usuari és tècnic o responsable. recupera i neteja
     */
    if ($action === 'create_usuari') {
        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $role = strtoupper(trim((string)($_POST['role'] ?? '')));
        //valida les dades del form abans de guardar-les
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
        } else { //comprueba si existe las columnas
            $has_password_col = function_exists('columna_existeix') ? columna_existeix($conn, 'USUARI', 'PASSWORD') : false;
            $has_password_hash_col = function_exists('columna_existeix') ? columna_existeix($conn, 'USUARI', 'PASSWORD_HASH') : false;
            $has_username_col = function_exists('columna_existeix') ? columna_existeix($conn, 'USUARI', 'USERNAME') : false;
            $has_phone_col = function_exists('columna_existeix') ? columna_existeix($conn, 'USUARI', 'PHONE_NUMBER') : false;
            $has_department_col = function_exists('columna_existeix') ? columna_existeix($conn, 'USUARI', 'DEPARTMENT_ID') : false;

            /**
             * Genera i prepara les dades necessàries
             * per inserir el nou usuari a la base de dades.
             */
            $generated_username = '';
            $generated_hash = '';
            if ($has_username_col) {
                $base = preg_replace('/[^a-zA-Z]/', '', (string)strtok($email, '@'));
                $base = $base !== '' ? strtolower($base) : 'user';
                $generated_username = substr($base, 0, 18) . (string)random_int(10, 99);
            }
            if ($has_password_hash_col) {
                $generated_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            }
            // Inserta el nuevo usuario en la base de datos
            if ($has_username_col && $has_password_hash_col && $has_password_col && $has_department_col) {
                $stmt = $conn->prepare('INSERT INTO USUARI (USERNAME, FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PASSWORD_HASH, PHONE_NUMBER, DEPARTMENT_ID, ROLE, IS_VERIFIED) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            } elseif ($has_username_col && $has_password_hash_col && $has_password_col) {
                $stmt = $conn->prepare('INSERT INTO USUARI (USERNAME, FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PASSWORD_HASH, PHONE_NUMBER, ROLE, IS_VERIFIED) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            } elseif ($has_username_col && $has_password_hash_col && $has_department_col) {
                $stmt = $conn->prepare('INSERT INTO USUARI (USERNAME, FIRST_NAME, LAST_NAME, EMAIL, PASSWORD_HASH, PHONE_NUMBER, DEPARTMENT_ID, ROLE, IS_VERIFIED) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            } elseif ($has_username_col && $has_password_hash_col) {
                $stmt = $conn->prepare('INSERT INTO USUARI (USERNAME, FIRST_NAME, LAST_NAME, EMAIL, PASSWORD_HASH, PHONE_NUMBER, ROLE, IS_VERIFIED) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            } elseif ($has_password_col && $has_department_col) {
                $stmt = $conn->prepare('INSERT INTO USUARI (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, DEPARTMENT_ID, ROLE) VALUES (?, ?, ?, ?, ?, ?, ?)');
            } elseif ($has_password_col) {
                $stmt = $conn->prepare('INSERT INTO USUARI (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROLE) VALUES (?, ?, ?, ?, ?, ?)');
            } else {
                
                $stmt = $conn->prepare('INSERT INTO USUARI (FIRST_NAME, LAST_NAME, EMAIL, PHONE_NUMBER, ROLE) VALUES (?, ?, ?, ?, ?)');
            }
            if ($stmt === false) {
                $alert = ['type' => 'danger', 'message' => 'Error preparant la consulta: ' . $conn->error];
            } else {
                $phone_value = ($phone !== '') ? $phone : ($has_phone_col ? '000000000' : '');

                if ($has_username_col && $has_password_hash_col && $has_password_col && $has_department_col) {
                    $dept_id = function_exists('ensure_default_department_id') ? ensure_default_department_id($conn) : null;
                    $dept_id = is_int($dept_id) ? $dept_id : 1;
                    $is_verified = 1;
                    $legacy_password = bin2hex(random_bytes(12));
                    $stmt->bind_param('sssssssisi', $generated_username, $first_name, $last_name, $email, $legacy_password, $generated_hash, $phone_value, $dept_id, $role, $is_verified);
                } elseif ($has_username_col && $has_password_hash_col && $has_password_col) {
                    $is_verified = 1;
                    $legacy_password = bin2hex(random_bytes(12));
                    $stmt->bind_param('ssssssssi', $generated_username, $first_name, $last_name, $email, $legacy_password, $generated_hash, $phone_value, $role, $is_verified);
                } elseif ($has_username_col && $has_password_hash_col && $has_department_col) {
                    $dept_id = function_exists('ensure_default_department_id') ? ensure_default_department_id($conn) : null;
                    $dept_id = is_int($dept_id) ? $dept_id : 1;
                    $is_verified = 1;
                    $stmt->bind_param('ssssssisi', $generated_username, $first_name, $last_name, $email, $generated_hash, $phone_value, $dept_id, $role, $is_verified);
                } elseif ($has_username_col && $has_password_hash_col) {
                    $is_verified = 1;
                    $stmt->bind_param('sssssssi', $generated_username, $first_name, $last_name, $email, $generated_hash, $phone_value, $role, $is_verified);
                } elseif ($has_password_col && $has_department_col) {
                    $password_value = bin2hex(random_bytes(8));
                    $dept_id = function_exists('ensure_default_department_id') ? ensure_default_department_id($conn) : null;
                    $dept_id = is_int($dept_id) ? $dept_id : 1;
                    $stmt->bind_param('sssssis', $first_name, $last_name, $email, $password_value, $phone_value, $dept_id, $role);
                } elseif ($has_password_col) {
                    $password_value = bin2hex(random_bytes(8));
                    $stmt->bind_param('ssssss', $first_name, $last_name, $email, $password_value, $phone_value, $role);
                } else {
                    $stmt->bind_param('sssss', $first_name, $last_name, $email, $phone_value, $role);
                }
                $ok = $stmt->execute(); //inserció
                $stmt->close();

                if (!$ok) {
                    // duplicar email error
                    $alert = ['type' => 'danger', 'message' => "No s'ha pogut crear l'usuari: " . $conn->error];
                } else {
                    /**
                     * Si el nou usuari és tecnic o responsable,
                     * també s'afegeix a la taula TECNIC
                     */
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
                                            $password = bin2hex(random_bytes(8));
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
                    //mensaje final usuario creado
                    $alert = ['type' => 'success', 'message' => "Usuari creat correctament ($first_name $last_name)."];
                }
            }
        }
    }
}

/**
 * Carrega el llistat d'usuaris
 * per mostrar-lo .
 */
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

<link rel="stylesheet" href="/css/admin.css">

<div class="container py-5">

    <?php if (is_array($alert)) : ?>
        <div class="alert alert-<?php echo htmlspecialchars((string)($alert['type'] ?? 'info')); ?>" role="alert">
            <?php echo htmlspecialchars((string)($alert['message'] ?? '')); ?>
        </div>
    <?php endif; ?>

    <h1 class="mb-4 text-center fw-bold">
        Panell d'Estadístiques
    </h1>

    <!-- FILTROS (shared) -->
    <div class="card shadow-sm p-4 mb-4">
        <h4 class="mb-1">Filtres</h4>
        <p class="text-muted mb-3">Els filtres s'apliquen a les pestanyes de Logs i Incidències.</p>

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
                <select id="usuario" class="form-control">
                    <option value="">-- Tots els tècnics --</option>
                    <?php
                    foreach ($tecnics_disponibles as $tecnic) {
                        $tecnic_escaped = htmlspecialchars($tecnic);
                        echo '<option value="' . $tecnic_escaped . '">' . $tecnic_escaped . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-8">
                <label class="form-label">Pàgina visitada <span class="text-muted">(Logs)</span></label>
                <input type="text" id="pagina" class="form-control" placeholder="Ex: incidencies.php">
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-dark w-100" onclick="cargarStats()">
                    Aplicar filtres
                </button>
            </div>

        </div>
    </div>

    <!-- TABS -->
    <ul class="nav nav-tabs mb-4" id="adminStatsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab" aria-controls="logs" aria-selected="true">
                Logs
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="incidencies-tab" data-bs-toggle="tab" data-bs-target="#incidencies" type="button" role="tab" aria-controls="incidencies" aria-selected="false">
                Incidències
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminStatsTabsContent">
        <!-- LOGS TAB -->
        <div class="tab-pane fade show active" id="logs" role="tabpanel" aria-labelledby="logs-tab" tabindex="0">
            <div class="d-flex justify-content-end mb-3">
                <a id="statsLink" class="btn btn-outline-secondary btn-sm" href="admin_stats.php?inicio=&fin=&usuario=&pagina=" target="_blank" rel="noopener">
                    Veure JSON d'estadístiques
                </a>
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

            <!-- GRAFIC (LOGS) -->
            <div class="row g-4 mb-5">
                <div class="col-12">
                    <div class="chart-box">
                        <h4>Accessos per dia</h4>
                        <canvas id="accessTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- TABLAS (LOGS) -->
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

        <!-- INCIDENCIES TAB -->
        <div class="tab-pane fade" id="incidencies" role="tabpanel" aria-labelledby="incidencies-tab" tabindex="0">
            <div class="row g-4 mb-5">
                <div class="col-lg-5">
                    <div class="chart-box chart-box-compact">
                        <h4>Incidències per estat</h4>
                        <canvas id="incidenciesStatusChart"></canvas>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="chart-box">
                        <h4>Tipus d'incidència i prioritat</h4>
                        <canvas id="incidenciesTypePriorityChart"></canvas>
                    </div>
                </div>
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

                <div class="modal-body" style="max-height: calc(100vh - 220px); overflow-y: auto;">
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
                        <p class="text-muted">Pots editar tots els camps de l'usuari. Si un camp és nullable i el deixes buit, es desarà com a NULL.</p>

                        <div class="row g-3">
                            <?php foreach ($usuari_columns as $col => $meta) : ?>
                                <?php
                                $col = (string)$col;
                                if ($col === 'USUARI_ID') {
                                    continue;
                                }
                                $val = $selected_usuari[$col] ?? null;
                                $type = strtolower((string)($meta['Type'] ?? ''));
                                $nullable = ((string)($meta['Null'] ?? 'YES')) === 'YES';
                                $input_id = 'edit_' . strtolower($col);
                                $col_class = 'col-md-6';
                                if (in_array($col, ['PASSWORD_HASH', 'VERIFICATION_TOKEN', 'PASSWORD'], true)) {
                                    $col_class = 'col-12';
                                }
                                if ($col === 'EMAIL') {
                                    $col_class = 'col-md-8';
                                }
                                if ($col === 'USERNAME') {
                                    $col_class = 'col-md-4';
                                }
                                if ($col === 'PHONE_NUMBER') {
                                    $col_class = 'col-md-4';
                                }
                                if ($col === 'ROLE') {
                                    $col_class = 'col-md-6';
                                }
                                if ($col === 'DEPARTMENT_ID') {
                                    $col_class = 'col-md-6';
                                }
                                if ($col === 'IS_VERIFIED') {
                                    $col_class = 'col-md-6 d-flex align-items-center';
                                }
                                if (in_array($col, ['TOKEN_EXPIRES_AT', 'CREATED_AT', 'UPDATED_AT'], true)) {
                                    $col_class = 'col-md-6';
                                }
                                ?>

                                <div class="<?php echo $col_class; ?>">
                                    <?php if ($col === 'IS_VERIFIED') : ?>
                                        <input type="hidden" name="fields[IS_VERIFIED]" value="0">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="<?php echo htmlspecialchars($input_id); ?>" name="fields[IS_VERIFIED]" value="1" <?php echo ((int)($val ?? 0) === 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="<?php echo htmlspecialchars($input_id); ?>">Verificat</label>
                                        </div>
                                    <?php elseif ($col === 'ROLE') : ?>
                                        <label class="form-label" for="<?php echo htmlspecialchars($input_id); ?>">Rol<?php echo $nullable ? '' : ' *'; ?></label>
                                        <select class="form-select" id="<?php echo htmlspecialchars($input_id); ?>" name="fields[ROLE]">
                                            <?php foreach ($allowed_roles as $r) : ?>
                                                <option value="<?php echo htmlspecialchars((string)$r); ?>" <?php echo ((string)($val ?? '') === (string)$r) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$r); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($col === 'DEPARTMENT_ID' && $has_department_col && count($departments) > 0) : ?>
                                        <label class="form-label" for="<?php echo htmlspecialchars($input_id); ?>">Departament<?php echo $nullable ? '' : ' *'; ?></label>
                                        <select class="form-select" id="<?php echo htmlspecialchars($input_id); ?>" name="fields[DEPARTMENT_ID]">
                                            <option value="" <?php echo ((string)($val ?? '') === '') ? 'selected' : ''; ?>>-- Cap --</option>
                                            <?php foreach ($departments as $d) : ?>
                                                <option value="<?php echo htmlspecialchars((string)($d['DEPARTMENT_ID'] ?? '')); ?>" <?php echo ((string)($val ?? '') === (string)($d['DEPARTMENT_ID'] ?? '')) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($d['DEPARTMENT_NAME'] ?? '')); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif (in_array($col, ['TOKEN_EXPIRES_AT', 'CREATED_AT', 'UPDATED_AT'], true)) : ?>
                                        <label class="form-label" for="<?php echo htmlspecialchars($input_id); ?>"><?php echo htmlspecialchars($col); ?><?php echo $nullable ? '' : ' *'; ?></label>
                                        <input type="datetime-local" class="form-control" id="<?php echo htmlspecialchars($input_id); ?>" name="fields[<?php echo htmlspecialchars($col); ?>]" value="<?php echo htmlspecialchars(format_datetime_local($val)); ?>">
                                    <?php else : ?>
                                        <?php
                                        $input_type = 'text';
                                        if ($col === 'EMAIL') {
                                            $input_type = 'email';
                                        } elseif (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $type) === 1) {
                                            $input_type = 'number';
                                        }
                                        ?>
                                        <label class="form-label" for="<?php echo htmlspecialchars($input_id); ?>"><?php echo htmlspecialchars($col); ?><?php echo $nullable ? '' : ' *'; ?></label>
                                        <input type="<?php echo htmlspecialchars($input_type); ?>" class="form-control" id="<?php echo htmlspecialchars($input_id); ?>" name="fields[<?php echo htmlspecialchars($col); ?>]" value="<?php echo htmlspecialchars($val === null ? '' : (string)$val); ?>" autocomplete="off">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
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
<script src="/js/admin.js"></script>

<?php include __DIR__ . '/../incidencies/footer.php'; ?>