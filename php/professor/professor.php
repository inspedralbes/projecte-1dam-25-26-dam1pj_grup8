<?php
/**
 * Home del rol PROFESSOR.
 *
 * Acciones disponibles:
 * - Consultar incidencias.
 * - Registrar una nueva incidencia.
 */
require_once __DIR__ . '/../incidencies/auth.php';
auth_require_role('PROFESSOR');
include __DIR__ . '/../incidencies/header.php';
?>

<link rel="stylesheet" href="/css/professor.css">

<div class="professor-hero">

    <div class="professor-box">

        <h1 class="professor-title">Professor</h1>

        <div class="d-grid gap-3">

            <a href="/incidencies/llistar.php" class="btn btn-prof">
                Comprovar incidència
            </a>

            <a href="nova_incidencia.php" class="btn btn-prof">
                Nova incidència
            </a>

        </div>

    </div>

</div>

<script src="/js/professor.js"></script>

<?php include __DIR__ . '/../incidencies/footer.php'; ?>
