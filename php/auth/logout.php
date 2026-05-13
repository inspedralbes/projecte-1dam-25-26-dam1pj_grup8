<?php
/**
 * Script de cierre de sesión (logout).
 *
 * Este archivo se encarga de:
 * - Iniciar la sesión si no está activa
 * - Cerrar la sesión del usuario
 * - Redirigir al login
 */
require_once __DIR__ . '/../incidencies/auth.php';

auth_session_start();
auth_logout();

header('Location: /auth/login.php');
exit;
