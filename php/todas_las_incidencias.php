<?php include 'header.php'; ?>

<?php $bgImage = "img/header.webp"; ?>

<header class="hero-header" style="background-image: url('<?php echo $bgImage; ?>');">
    <div class="hero-overlay"></div>

    <div class="container hero-content text-center">

        <h1 class="display-4 fw-bold">Totes les incidències</h1>

        <p class="lead text-muted mb-4">Aquí pots veure totes les incidències registrades al sistema.</p>

        <!-- Aquí podrías agregar un botón para volver al admin o a otra sección -->
        <a href="admin.php" class="btn btn-custom btn-lg">
            Tornar a Admin
        </a>

    </div>
</header>

<?php
require_once 'connexio.php';
require_once 'incidencies_list.php';

// Mostrar llistat d'incidències
mostrar_incidencies($conn);

include 'footer.php';
?>