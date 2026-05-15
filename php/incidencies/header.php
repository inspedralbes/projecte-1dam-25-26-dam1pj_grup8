<?php
/**
 * Header común (layout).
 *
 * - Incluye el logger de accesos.
 * - Inicia/valida la sesión.
 * - Renderiza la navegación (Login/Logout, botones admin, etc.).
 */
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/auth.php';

auth_session_start();
$auth_user = auth_user();
$auth_role = auth_user_role();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Institut Pedralbes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
    <link rel="stylesheet" href="/css/professor.css">
    <link rel="stylesheet" href="/css/tecnic.css">
</head>

<body class="<?php echo isset($bodyClass) ? $bodyClass : ''; ?>">

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/index.php">Institut Pedralbes</a>

        <div class="d-flex gap-2 ms-auto align-items-center">
            <?php if (is_array($auth_user)) : ?>
                <span class="text-white-50 small">
                    <?php echo htmlspecialchars((string)($auth_user['username'] ?? ''), ENT_QUOTES); ?>
                </span>
                <a class="btn btn-outline-light btn-sm" href="/auth/logout.php">Logout</a>
            <?php else : ?>
                <a class="btn btn-outline-light btn-sm" href="/auth/login.php">Login</a>
                <a class="btn btn-light btn-sm" href="/auth/register.php">Register</a>
            <?php endif; ?>

            <?php
            $wants_admin_buttons = (isset($showCrearUsuariButton) && $showCrearUsuariButton === true) || (isset($showUsuarisButton) && $showUsuarisButton === true);
            if ($wants_admin_buttons && $auth_role === 'ADMIN') :
            ?>
                <?php if (isset($showUsuarisButton) && $showUsuarisButton === true) : ?>
                    <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#usuarisModal">
                        Usuaris
                    </button>
                <?php endif; ?>

                <?php if (isset($showCrearUsuariButton) && $showCrearUsuariButton === true) : ?>
                    <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#crearUsuariModal">
                        Crear Usuari
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php if (is_array($auth_user) && (($auth_user['is_verified'] ?? true) === false)) : ?>
    <div class="alert alert-warning mb-0 rounded-0" role="alert">
        Pàgina en construcció.
    </div>
<?php endif; ?>
<script src="/js/hero.js"></script>