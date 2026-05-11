<?php

require_once __DIR__ . '/../incidencies/auth.php';

auth_session_start();
auth_logout();

header('Location: /auth/login.php');
exit;
