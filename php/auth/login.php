<?php

require_once __DIR__ . '/../incidencies/connexio.php';
require_once __DIR__ . '/../incidencies/usuari_schema.php';
require_once __DIR__ . '/../incidencies/auth.php';

auth_session_start();

$schema_result = ensure_usuari_schema($conn);
$schema_ok = (is_array($schema_result) && ($schema_result['ok'] ?? false) === true);

$alert = null;

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$next = trim((string)($_GET['next'] ?? $_POST['next'] ?? ''));
if ($next === '' || !str_starts_with($next, '/')) {
    $next = '';
}

$identifier = trim((string)($_POST['identifier'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    if (!$schema_ok) {
        $errors[] = "Database schema isn't ready.";
    }

    if ($identifier === '') {
        $errors[] = 'Username or Email is required.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (count($errors) > 0) {
        $alert = ['type' => 'danger', 'message' => implode(' ', $errors)];
    } else {
        $stmt = $conn->prepare('SELECT * FROM USUARI WHERE LOWER(USERNAME) = LOWER(?) OR LOWER(EMAIL) = LOWER(?) LIMIT 1');
        if ($stmt === false) {
            $alert = ['type' => 'danger', 'message' => 'Database error: ' . $conn->error];
        } else {
            $ident_lower = strtolower($identifier);
            $stmt->bind_param('ss', $ident_lower, $ident_lower);
            $user = null;
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res !== false) {
                    $user = $res->fetch_assoc() ?: null;
                    $res->free();
                }
            }
            $stmt->close();

            $generic_error = 'Invalid username or password.';

            if (!is_array($user)) {
                $alert = ['type' => 'danger', 'message' => $generic_error];
            } else {
                $hash = (string)($user['PASSWORD_HASH'] ?? '');
                if ($hash === '' || !password_verify($password, $hash)) {
                    $alert = ['type' => 'danger', 'message' => $generic_error];
                } else {
                    $is_verified = (int)($user['IS_VERIFIED'] ?? 0) === 1;
                    auth_login($user);
                    $redirect = $next !== '' ? $next : auth_post_login_redirect();
                    if (!$is_verified) {
                        $redirect .= (str_contains($redirect, '?') ? '&' : '?') . 'unverified=1';
                    }
                    header('Location: ' . $redirect);
                    exit;
                }
            }
        }
    }
}

include __DIR__ . '/../incidencies/header.php';
?>

<div class="container py-5" style="max-width: 640px;">
    <h1 class="h3 mb-3">Login</h1>

    <?php if (is_array($alert)) : ?>
        <div class="alert alert-<?php echo h((string)($alert['type'] ?? 'info')); ?>" role="alert">
            <?php echo h((string)($alert['message'] ?? '')); ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="card card-body" novalidate>
        <input type="hidden" name="next" value="<?php echo h($next); ?>">

        <div class="mb-3">
            <label class="form-label" for="identifier">Username or Email</label>
            <input class="form-control" id="identifier" name="identifier" value="<?php echo h($identifier); ?>" autocomplete="username" required>
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <div class="input-group">
                <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">Show</button>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-dark" type="submit">Login</button>
            <a class="btn btn-outline-secondary" href="/auth/register.php">Register</a>
        </div>

        <div class="mt-3">
            <a href="#" class="text-muted">Forgot password</a>
        </div>
    </form>
</div>

<script>
(function() {
    const pass = document.getElementById('password');
    const toggle = document.getElementById('togglePassword');
    if (toggle && pass) {
        toggle.addEventListener('click', function() {
            const isPwd = pass.type === 'password';
            pass.type = isPwd ? 'text' : 'password';
            toggle.textContent = isPwd ? 'Hide' : 'Show';
        });
    }
})();
</script>

<?php include __DIR__ . '/../incidencies/footer.php'; ?>
