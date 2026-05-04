<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Institut Pedralbes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/professor.css">
    <link rel="stylesheet" href="css/tecnic.css">
</head>

<body class="<?php echo isset($bodyClass) ? $bodyClass : ''; ?>">

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">Institut Pedralbes</a>
        <?php if ((isset($showCrearUsuariButton) && $showCrearUsuariButton === true) || (isset($showUsuarisButton) && $showUsuarisButton === true)) : ?>
            <div class="d-flex gap-2 ms-auto">
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
            </div>
        <?php endif; ?>
    </div>
</nav>
<script src="js/hero.js"></script>