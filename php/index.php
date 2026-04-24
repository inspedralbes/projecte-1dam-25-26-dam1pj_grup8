<?php include 'header.php'; ?>

<?php $bgImage = "img/header.webp"; ?>

<header class="hero-header" style="background-image: url('<?php echo $bgImage; ?>');">
    <div class="hero-overlay"></div>

    <div class="container hero-content text-center">

        <h1 class="display-4 fw-bold">Incidències Pedralbes</h1>

        <!-- Botón entrar -->
        <a href="#roles" id="enterBtn" class="btn btn-custom btn-lg">
            Entrar
        </a>

        <!-- Roles (inicialmente ocultos) -->
        <div id="roles" class="d-none mt-4">
            <div class="d-flex flex-column flex-md-row justify-content-center gap-3">

                <a href="professor.php" class="btn btn-custom btn-lg px-4">
                    Professor
                </a>

                <a href="tecnic.php" class="btn btn-custom btn-lg px-4">
                    Tècnic
                </a>

                <a href="admin.php" class="btn btn-custom btn-lg px-4">
                    Admin
                </a>

            </div>
        </div>

    </div>
</header>

<?php include 'footer.php'; ?>