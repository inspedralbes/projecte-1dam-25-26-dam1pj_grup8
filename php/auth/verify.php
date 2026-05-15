<?php

/**
 * Verificación de correo electrónico de usuario.
 *
 * Este archivo se encarga de:
 * - Validar el token de verificación recibido por URL
 * - Comprobar si el token existe y es válido
 * - Verificar si el token ha expirado
 * - Marcar el usuario como verificado en la base de datos
 */

require_once __DIR__ . '/../incidencies/connexio.php';
require_once __DIR__ . '/../incidencies/usuari_schema.php';
require_once __DIR__ . '/../incidencies/auth.php';

auth_session_start();

$schema_result = ensure_usuari_schema($conn);
$schema_ok = (is_array($schema_result) && ($schema_result['ok'] ?? false) === true);

$token = trim((string)($_GET['token'] ?? ''));
$alert = null;

/**
 * Escapa texto para salida HTML segura.
 *
 * @param string $v
 * @return string
 */
function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
/**
 * Validaciones iniciales antes de procesar el token.
 */
if (!$schema_ok) {
    $alert = ['type' => 'danger', 'message' => "Database schema isn't ready."];
} elseif ($token === '') {
    $alert = ['type' => 'warning', 'message' => 'Missing token.'];
} else {
    /**
     * Buscar usuario asociado al token de verificación
     */
    $stmt = $conn->prepare('SELECT USUARI_ID, TOKEN_EXPIRES_AT FROM USUARI WHERE VERIFICATION_TOKEN = ? LIMIT 1');
    if ($stmt === false) {
        $alert = ['type' => 'danger', 'message' => 'Database error: ' . $conn->error];
    } else {
        $stmt->bind_param('s', $token);
        $row = null;
        /**
         * Ejecución de consulta
         */
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res !== false) { //validacion token
                $row = $res->fetch_assoc() ?: null;
                $res->free();
            }
        }
        $stmt->close();

        if (!is_array($row)) {
            $alert = ['type' => 'danger', 'message' => 'Invalid token.'];
        } else {
            $expires_raw = (string)($row['TOKEN_EXPIRES_AT'] ?? '');
            $expires_ok = true;
            if ($expires_raw !== '') {
                try {
                    $expires_dt = new DateTime($expires_raw);
                    $expires_ok = $expires_dt >= new DateTime('now');
                } catch (Exception $e) {
                    $expires_ok = true;
                }
            }

            if (!$expires_ok) {
                $alert = ['type' => 'danger', 'message' => 'Token expired.'];
            } else {
                $id = (int)($row['USUARI_ID'] ?? 0);
                $stmt2 = $conn->prepare('UPDATE USUARI SET IS_VERIFIED = 1, VERIFICATION_TOKEN = NULL, TOKEN_EXPIRES_AT = NULL WHERE USUARI_ID = ?');
                if ($stmt2 !== false) {
                    $stmt2->bind_param('i', $id);
                    $stmt2->execute();
                    $stmt2->close();
                }
                $alert = ['type' => 'success', 'message' => 'Email verified. You can now login.'];
            }
        }
    }
}

include __DIR__ . '/../incidencies/header.php';
?>

<div class="container py-5" style="max-width: 640px;">
    <h1 class="h3 mb-3">Verify Email</h1>

    <?php if (is_array($alert)) : ?>
        <div class="alert alert-<?php echo h((string)($alert['type'] ?? 'info')); ?>" role="alert">
            <?php echo h((string)($alert['message'] ?? '')); ?>
        </div>
    <?php endif; ?>

    <a class="btn btn-outline-primary" href="/auth/login.php">Go to Login</a>
</div>

<?php include __DIR__ . '/../incidencies/footer.php'; ?>
