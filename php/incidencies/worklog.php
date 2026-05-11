<?php
// worklog.php (legacy) - redirect to the incident detail tabbed Work Log UI.

$incident_id = (int)($_GET['incident_id'] ?? $_POST['incident_id'] ?? 0);
if ($incident_id <= 0) {
    header('Location: /incidencies/llistar.php');
    exit;
}

$tecnic = trim((string)($_GET['tecnic'] ?? $_POST['tecnic'] ?? ''));

$params = ['id' => (string)$incident_id, 'tab' => 'add'];
if ($tecnic !== '') {
    $params['tecnic'] = $tecnic;
}

header('Location: /incidencies/detall_incidencia.php?' . http_build_query($params));
exit;
