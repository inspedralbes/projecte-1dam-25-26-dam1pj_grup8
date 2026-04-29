<?php
include 'header.php';
require_once 'connexio.php';
require_once 'access_logs_schema.php';

ensure_access_logs_schema($conn);
?>

<link rel="stylesheet" href="css/admin.css">

<div class="container py-5">

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


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/admin.js"></script>

<?php include 'footer.php'; ?>