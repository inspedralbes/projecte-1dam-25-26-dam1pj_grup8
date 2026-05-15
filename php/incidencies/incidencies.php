<?php
/**
 * Página de ejemplo / legacy.
 *
 * Contiene fragmentos demostrativos (variables de entorno, menú simple, etc.).
 * No es parte del flujo principal del Gestor de Incidencias.
 */
include 'header.php';
?>

<?php $bgImage = "/img/header.jpg"; ?>

<header class="hero-header" style="background-image: url('<?php echo $bgImage; ?>');">
    <div class="hero-overlay"></div>

    <div class="container hero-content text-center">
        <h1 class="display-4 fw-bold">Bienvenido a mi sitio</h1>
        <p class="lead">Esto es un header con imagen de fondo usando Bootstrap + PHP</p>
        <a href="#contenido" class="btn btn-primary btn-lg">Entrar</a>
    </div>
</header>

<div id="contenido" class="container mt-5">

    <h1>Pàgina inicial</h1>
    <p>Aquesta pàgina inclou codi php</p>

    <?php
    echo "<h2>Hola, món!</h2>";
    echo "<p>Hora actual: " . date("H:i:s") . "</p>";
    ?>

    <h2>Variables</h2>
    <p>Les variables s'han d'utilitzar per a definir la cadena de connexió independentment del codi</p>

    <?php
    $v1 = getenv('VAR1') ?: 'Ups, variable no definida';
    $v2 = getenv('VAR2') ?: 'Ups, variable no definida';

    echo "<p>El valor de la variable d'entorn VAR1 és: <strong>$v1</strong></p>";
    echo "<p>El valor de la variable d'entorn VAR2 és: <strong>$v2</strong></p>";
    ?>

    <div id="menu" class="mt-4">
        <hr>
        <p><a href="/index.php">Portada</a></p>
        <p><a href="llistar.php">Llistar</a></p>
        <p><a href="crear.php">Crear</a></p>
    </div>

    <p class="mt-3">Fi de la pàgina</p>

</div>

<?php include 'footer.php'; ?>