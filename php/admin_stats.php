<?php

require_once 'connexio.php';
require_once 'access_logs_schema.php';

ensure_access_logs_schema($conn);

$where=[];

if(!empty($_GET['inicio'])){
 $i=$conn->real_escape_string($_GET['inicio']);
 $where[]="DATE(access_time)>='$i'";
}

if(!empty($_GET['fin'])){
 $f=$conn->real_escape_string($_GET['fin']);
 $where[]="DATE(access_time)<='$f'";
}

if(!empty($_GET['usuario'])){
 $u=$conn->real_escape_string($_GET['usuario']);
 $where[]="username LIKE '%$u%'";
}

if(!empty($_GET['pagina'])){
 $p=$conn->real_escape_string($_GET['pagina']);
 $where[]="page LIKE '%$p%'";
}

$filter='';
if($where){
$filter=' WHERE '.implode(" AND ",$where);
}


$total=$conn->query("
SELECT COUNT(*) total
FROM access_logs
$filter
")->fetch_assoc()['total'];


$pagesCount=$conn->query("
SELECT COUNT(DISTINCT page) total
FROM access_logs
$filter
")->fetch_assoc()['total'];


$usersCount=$conn->query("
SELECT COUNT(DISTINCT username) total
FROM access_logs
$filter
")->fetch_assoc()['total'];


$pages=[];
$res=$conn->query("
SELECT page, COUNT(*) total
FROM access_logs
$filter
GROUP BY page
ORDER BY total DESC
LIMIT 5
");

while($r=$res->fetch_assoc()){
$pages[]=$r;
}


$users=[];
$res=$conn->query("
SELECT username,COUNT(*) total
FROM access_logs
$filter
GROUP BY username
ORDER BY total DESC
LIMIT 5
");

while($r=$res->fetch_assoc()){
$users[]=$r;
}


$trend=[];
$res=$conn->query("
SELECT DATE(access_time) dia,
COUNT(*) total
FROM access_logs
$filter
GROUP BY dia
ORDER BY dia
");

while($r=$res->fetch_assoc()){
$trend[]=$r;
}

echo json_encode([
'total'=>$total,
'pagesCount'=>$pagesCount,
'usersCount'=>$usersCount,
'pages'=>$pages,
'users'=>$users,
'trend'=>$trend
]);