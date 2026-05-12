<?php

require_once __DIR__ . '/../incidencies/connexio.php';
require_once __DIR__ . '/../incidencies/usuari_schema.php';
require_once __DIR__ . '/../incidencies/auth.php';

auth_session_start();

$schema_result = ensure_usuari_schema($conn);
$schema_ok = (is_array($schema_result) && ($schema_result['ok'] ?? false) === true);

$alert = null;
$success = false;

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function username_is_valid(string $u, array &$errors): bool
{
    $u = trim($u);
    if ($u === '') {
        $errors[] = 'Username is required.';
        return false;
    }
    if (preg_match('/\s/', $u)) {
        $errors[] = 'Username cannot contain spaces.';
    }
    if (preg_match('/\d/', $u)) {
        $errors[] = 'Username cannot contain numbers.';
    }
    if (strlen($u) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }
    if (strlen($u) > 20) {
        $errors[] = 'Username must be at most 20 characters.';
    }
    if (!preg_match('/^[a-zA-Z]+$/', $u)) {
        $errors[] = 'Username must contain only letters (a-z, A-Z).';
    }

    return count($errors) === 0;
}

function password_is_valid(string $p, string $p2, array &$errors): bool
{
    if ($p === '') {
        $errors[] = 'Password is required.';
        return false;
    }
    if (strlen($p) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[a-z]/', $p) || !preg_match('/[A-Z]/', $p) || !preg_match('/\d/', $p) || !preg_match('/[^a-zA-Z\d]/', $p)) {
        $errors[] = 'Password must include uppercase, lowercase, number, and special character.';
    }
    if ($p2 === '' || $p2 !== $p) {
        $errors[] = "Passwords don't match.";
    }

    return count($errors) === 0;
}

$departments = [];
if ($schema_ok && taula_existeix($conn, 'DEPARTMENT')) {
    $res = $conn->query('SELECT DEPARTMENT_ID, DEPARTMENT_NAME FROM DEPARTMENT ORDER BY DEPARTMENT_NAME ASC');
    if ($res !== false) {
        while ($row = $res->fetch_assoc()) {
            $departments[] = $row;
        }
        $res->free();
    }
}

$username = trim((string)($_POST['username'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$email2 = trim((string)($_POST['email_confirm'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$password2 = (string)($_POST['password_confirm'] ?? '');
$department_id = (int)($_POST['department_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    if (!$schema_ok) {
        $errors[] = "Database schema isn't ready.";
    }

    username_is_valid($username, $errors);

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Email format is not valid.';
    }

    if ($email2 === '' || strtolower($email2) !== strtolower($email)) {
        $errors[] = "Emails don't match.";
    }

    password_is_valid($password, $password2, $errors);

    if ($department_id <= 0) {
        $errors[] = 'Department is required.';
    } else {
        $stmtDept = $conn->prepare('SELECT DEPARTMENT_ID FROM DEPARTMENT WHERE DEPARTMENT_ID = ? LIMIT 1');
        if ($stmtDept !== false) {
            $stmtDept->bind_param('i', $department_id);
            if ($stmtDept->execute()) {
                $resD = $stmtDept->get_result();
                $exists = ($resD !== false && $resD->num_rows > 0);
                if ($resD !== false) {
                    $resD->free();
                }
                if (!$exists) {
                    $errors[] = 'Department is not valid.';
                }
            }
            $stmtDept->close();
        }
    }

    // DB uniqueness checks (case-insensitive)
    if (count($errors) === 0) {
        $stmtU = $conn->prepare('SELECT USUARI_ID FROM USUARI WHERE LOWER(USERNAME) = LOWER(?) LIMIT 1');
        if ($stmtU !== false) {
            $stmtU->bind_param('s', $username);
            if ($stmtU->execute()) {
                $res = $stmtU->get_result();
                if ($res !== false && $res->num_rows > 0) {
                    $errors[] = 'Username already exists.';
                }
                if ($res !== false) {
                    $res->free();
                }
            }
            $stmtU->close();
        }

        $email_lower = strtolower($email);
        $stmtE = $conn->prepare('SELECT USUARI_ID FROM USUARI WHERE LOWER(EMAIL) = LOWER(?) LIMIT 1');
        if ($stmtE !== false) {
            $stmtE->bind_param('s', $email_lower);
            if ($stmtE->execute()) {
                $res = $stmtE->get_result();
                if ($res !== false && $res->num_rows > 0) {
                    $errors[] = 'Email already exists.';
                }
                if ($res !== false) {
                    $res->free();
                }
            }
            $stmtE->close();
        }
    }

    if (count($errors) > 0) {
        $alert = ['type' => 'danger', 'message' => implode(' ', $errors)];
    } else {
        $email_lower = strtolower($email);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        $expires_at = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');

        // FIRST_NAME/LAST_NAME exist in this project; we keep a sensible default without adding extra fields.
        $first_name = $username;
        $last_name = '';
        $phone = '';
        $role = 'PROFESSOR';
        $is_verified = 0;

        $has_password_col = function_exists('columna_existeix') ? columna_existeix($conn, 'USUARI', 'PASSWORD') : false;
        if ($has_password_col) {
            $stmt = $conn->prepare('INSERT INTO USUARI (USERNAME, FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PASSWORD_HASH, PHONE_NUMBER, DEPARTMENT_ID, ROLE, IS_VERIFIED, VERIFICATION_TOKEN, TOKEN_EXPIRES_AT) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        } else {
            $stmt = $conn->prepare('INSERT INTO USUARI (USERNAME, FIRST_NAME, LAST_NAME, EMAIL, PASSWORD_HASH, PHONE_NUMBER, DEPARTMENT_ID, ROLE, IS_VERIFIED, VERIFICATION_TOKEN, TOKEN_EXPIRES_AT) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        }
        if ($stmt === false) {
            $alert = ['type' => 'danger', 'message' => 'Database error: ' . $conn->error];
        } else {
            if ($has_password_col) {
                // Legacy schemas may have USUARI.PASSWORD as CHAR(25) NOT NULL.
                // Use a short random placeholder (24 hex chars) to satisfy the constraint.
                $legacy_password = bin2hex(random_bytes(12));
                $stmt->bind_param('sssssssisiss', $username, $first_name, $last_name, $email_lower, $legacy_password, $hash, $phone, $department_id, $role, $is_verified, $token, $expires_at);
            } else {
                $stmt->bind_param('ssssssisiss', $username, $first_name, $last_name, $email_lower, $hash, $phone, $department_id, $role, $is_verified, $token, $expires_at);
            }
            $ok = $stmt->execute();
            $stmt->close();

            if (!$ok) {
                $alert = ['type' => 'danger', 'message' => 'Registration failed: ' . $conn->error];
            } else {
                $success = true;
                $alert = ['type' => 'success', 'message' => 'Verification email has been sent. Please check your inbox.'];
            }
        }
    }
}

include __DIR__ . '/../incidencies/header.php';
?>

<div class="container py-5" style="max-width: 720px;">
    <h1 class="h3 mb-3">Register</h1>
    <p class="text-muted">Create a new account.</p>

    <?php if (is_array($alert)) : ?>
        <div class="alert alert-<?php echo h((string)($alert['type'] ?? 'info')); ?>" role="alert">
            <?php echo h((string)($alert['message'] ?? '')); ?>
        </div>
    <?php endif; ?>

    <?php if (!$success) : ?>
        <form method="POST" class="card card-body" novalidate>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input class="form-control" id="username" name="username" value="<?php echo h($username); ?>" autocomplete="username" required>
                <div class="form-text" id="usernameHelp">Only letters, 3–20 chars. No spaces or numbers.</div>
                <div class="small" id="usernameFeedback"></div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" value="<?php echo h($email); ?>" autocomplete="email" required>
            </div>

            <div class="mb-3">
                <label class="form-label" for="email_confirm">Confirm Email</label>
                <input class="form-control" id="email_confirm" name="email_confirm" type="email" value="<?php echo h($email2); ?>" autocomplete="email" required>
                <div class="small" id="emailFeedback"></div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="department_id">Department</label>
                <select class="form-select" id="department_id" name="department_id" required>
                    <option value="">Select a department…</option>
                    <?php foreach ($departments as $dept) : ?>
                        <?php
                        $id = (int)($dept['DEPARTMENT_ID'] ?? 0);
                        $name = (string)($dept['DEPARTMENT_NAME'] ?? '');
                        $selected = ($id === $department_id) ? 'selected' : '';
                        ?>
                        <option value="<?php echo $id; ?>" <?php echo $selected; ?>><?php echo h($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">Show</button>
                </div>
                <div class="form-text">Min 8 chars, includes uppercase, lowercase, number and special character.</div>
                <div class="small" id="passwordStrength"></div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="password_confirm">Confirm Password</label>
                <input class="form-control" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
                <div class="small" id="passwordMatch"></div>
            </div>

            <button class="btn btn-dark" type="submit">Create Account</button>

            <div class="mt-3 text-muted">
                Already have an account? <a href="/auth/login.php">Login</a>
            </div>
        </form>
    <?php else : ?>
        <div class="card card-body">
            <p class="mb-2">Account created successfully.</p>
            <a class="btn btn-outline-primary" href="/auth/login.php">Go to Login</a>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const username = document.getElementById('username');
    const usernameFeedback = document.getElementById('usernameFeedback');

    const email = document.getElementById('email');
    const email2 = document.getElementById('email_confirm');
    const emailFeedback = document.getElementById('emailFeedback');

    const pass = document.getElementById('password');
    const pass2 = document.getElementById('password_confirm');
    const strength = document.getElementById('passwordStrength');
    const match = document.getElementById('passwordMatch');

    const toggle = document.getElementById('togglePassword');

    function setText(el, text, ok) {
        if (!el) return;
        el.textContent = text;
        el.className = ok ? 'small text-success' : 'small text-danger';
    }

    function validateUsername() {
        const v = (username.value || '').trim();
        if (!v) return setText(usernameFeedback, '', true);
        if (/\s/.test(v)) return setText(usernameFeedback, 'No spaces allowed.', false);
        if (/\d/.test(v)) return setText(usernameFeedback, 'No numbers allowed.', false);
        if (v.length < 3) return setText(usernameFeedback, 'Minimum length is 3.', false);
        if (v.length > 20) return setText(usernameFeedback, 'Maximum length is 20.', false);
        if (!/^[a-zA-Z]+$/.test(v)) return setText(usernameFeedback, 'Only letters (a-z, A-Z).', false);
        return setText(usernameFeedback, 'Looks good.', true);
    }

    function validateEmails() {
        const a = (email.value || '').trim();
        const b = (email2.value || '').trim();
        if (!a && !b) return setText(emailFeedback, '', true);
        if (!a || !b) return setText(emailFeedback, 'Both email fields are required.', false);
        if (a.toLowerCase() !== b.toLowerCase()) return setText(emailFeedback, "Emails don't match.", false);
        return setText(emailFeedback, 'Emails match.', true);
    }

    function passwordScore(p) {
        let score = 0;
        if (p.length >= 8) score++;
        if (/[a-z]/.test(p)) score++;
        if (/[A-Z]/.test(p)) score++;
        if (/\d/.test(p)) score++;
        if (/[^a-zA-Z\d]/.test(p)) score++;
        return score;
    }

    function validatePassword() {
        const p = pass.value || '';
        if (!p) return setText(strength, '', true);
        const s = passwordScore(p);
        if (s <= 2) return setText(strength, 'Weak password.', false);
        if (s === 3) return setText(strength, 'Medium password.', true);
        return setText(strength, 'Strong password.', true);
    }

    function validatePasswordMatch() {
        const a = pass.value || '';
        const b = pass2.value || '';
        if (!a && !b) return setText(match, '', true);
        if (!b) return setText(match, '', true);
        if (a !== b) return setText(match, "Passwords don't match.", false);
        return setText(match, 'Passwords match.', true);
    }

    if (username) {
        username.addEventListener('input', validateUsername);
        validateUsername();
    }
    if (email && email2) {
        email.addEventListener('input', validateEmails);
        email2.addEventListener('input', validateEmails);
        validateEmails();
    }
    if (pass && pass2) {
        pass.addEventListener('input', function() { validatePassword(); validatePasswordMatch(); });
        pass2.addEventListener('input', validatePasswordMatch);
        validatePassword();
        validatePasswordMatch();
    }

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
