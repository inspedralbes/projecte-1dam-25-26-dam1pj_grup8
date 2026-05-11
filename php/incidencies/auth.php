<?php

// Minimal authentication + session helpers for this project.

require_once __DIR__ . '/connexio.php';
require_once __DIR__ . '/usuari_schema.php';

if (!defined('AUTH_SESSION_TIMEOUT_SECONDS')) {
	define('AUTH_SESSION_TIMEOUT_SECONDS', 30 * 60);
}

function auth_session_start(): void
{
	if (PHP_SAPI === 'cli') {
		return;
	}

	if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
		@session_start();
	}

	if (!isset($_SESSION) || !is_array($_SESSION)) {
		return;
	}

	$now = time();
	$last = (int)($_SESSION['last_activity'] ?? 0);
	if ($last > 0 && ($now - $last) > AUTH_SESSION_TIMEOUT_SECONDS) {
		auth_logout();
		return;
	}

	$_SESSION['last_activity'] = $now;
}

function auth_is_logged_in(): bool
{
	return isset($_SESSION['user']) && is_array($_SESSION['user']) && (int)($_SESSION['user']['id'] ?? 0) > 0;
}

function auth_user(): ?array
{
	return auth_is_logged_in() ? $_SESSION['user'] : null;
}

function auth_user_role(): string
{
	$user = auth_user();
	return is_array($user) ? (string)($user['role'] ?? '') : '';
}

function auth_login(array $user_row): void
{
	auth_session_start();
	if (session_status() === PHP_SESSION_ACTIVE) {
		@session_regenerate_id(true);
	}

	$_SESSION['user'] = [
		'id' => (int)($user_row['USUARI_ID'] ?? 0),
		'username' => (string)($user_row['USERNAME'] ?? ''),
		'email' => (string)($user_row['EMAIL'] ?? ''),
		'first_name' => (string)($user_row['FIRST_NAME'] ?? ''),
		'last_name' => (string)($user_row['LAST_NAME'] ?? ''),
		'role' => (string)($user_row['ROLE'] ?? ''),
		'department_id' => (int)($user_row['DEPARTMENT_ID'] ?? 0),
		'is_verified' => (int)($user_row['IS_VERIFIED'] ?? 0) === 1,
	];

	// Convenience key for Mongo logger.
	$_SESSION['username'] = (string)($_SESSION['user']['username'] ?? '');
	if ($_SESSION['username'] === '') {
		$_SESSION['username'] = (string)($_SESSION['user']['email'] ?? '');
	}

	$_SESSION['last_activity'] = time();
}

function auth_logout(): void
{
	if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
		@session_start();
	}

	if (session_status() === PHP_SESSION_ACTIVE) {
		$_SESSION = [];
		if (ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
		}
		@session_destroy();
	}
}

function auth_redirect_to_login(): void
{
	$next = (string)($_SERVER['REQUEST_URI'] ?? '/');
	header('Location: /auth/login.php?next=' . urlencode($next));
	exit;
}

function auth_require_login(): void
{
	auth_session_start();
	if (!auth_is_logged_in()) {
		auth_redirect_to_login();
	}
}

function auth_require_role($roles): void
{
	auth_require_login();
	$role = auth_user_role();
	$allowed = is_array($roles) ? $roles : [$roles];
	if (!in_array($role, $allowed, true)) {
		header('Location: /index.php');
		exit;
	}
}

function auth_post_login_redirect(): string
{
	$role = auth_user_role();
	return match ($role) {
		'ADMIN' => '/admin/admin.php',
		'RESPONSABLE' => '/responsable/responsable_tecnic.php',
		'TECNIC' => '/tecnic/tecnic.php',
		'PROFESSOR' => '/professor/professor.php',
		default => '/index.php',
	};
}
