<?php

function ensure_access_logs_schema($conn){

$sql="
CREATE TABLE IF NOT EXISTS access_logs(
id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(100),
page VARCHAR(150),
access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

$conn->query($sql);


/*
DATOS DEMO SOLO SI TABLA VACIA
*/
$c=$conn->query("SELECT COUNT(*) c FROM access_logs")->fetch_assoc()['c'];

if($c==0){

$demo=[
['admin','admin.php'],
['professor','crear_incidencia.php'],
['tecnic','todas_las_incidencias.php'],
['admin','incidencies.php'],
['professor','crear_incidencia.php'],
['admin','admin.php']
];

foreach($demo as $d){
$u=$d[0];
$p=$d[1];
$conn->query("
INSERT INTO access_logs(username,page)
VALUES('$u','$p')
");
}

}

}