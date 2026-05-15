<?php
/**
 * Página de inicio (landing).
 *
 * - Si el usuario NO está autenticado, muestra acceso a Login/Registro.
 * - Si el usuario está autenticado, enlaza a su panel según el rol.
 */
$bodyClass = 'home-page';
require_once __DIR__ . '/incidencies/auth.php';
auth_session_start();
$is_logged_in = auth_is_logged_in();
$dashboard_url = $is_logged_in ? auth_post_login_redirect() : '';

include __DIR__ . '/incidencies/header.php';
?>

<?php $bgImage = "img/header.png"; ?>

<header class="hero-header" style="background-image: url('<?php echo $bgImage; ?>');">
    <div class="hero-overlay"></div>

    <div class="container hero-content text-center">

        <h1 class="display-4 fw-bold">Incidències Pedralbes</h1>

        <?php if ($is_logged_in) : ?>
            <a href="<?php echo htmlspecialchars($dashboard_url, ENT_QUOTES); ?>" class="btn btn-custom btn-lg">
                Anar al panell
            </a>
        <?php else : ?>
            <a href="/auth/login.php" class="btn btn-custom btn-lg">
                Login
            </a>
            <div class="mt-3">
                <a href="/auth/register.php" class="btn btn-outline-light btn-lg">
                    Register
                </a>
            </div>
        <?php endif; ?>

    </div>
</header>

<?php include __DIR__ . '/incidencies/footer.php'; ?>