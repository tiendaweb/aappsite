<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (current_user() !== null) {
    header('Location: /admin.php');
    exit;
}

$error = '';
$username = '';
$isInitialSetup = !has_users();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isInitialSetup) {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            $error = 'El usuario/email debe tener entre 3 y 50 caracteres.';
        } elseif (!preg_match('/^[A-Za-z0-9._@+-]+$/', $username)) {
            $error = 'El usuario/email contiene caracteres no permitidos.';
        } elseif (str_contains($username, '@') && filter_var($username, FILTER_VALIDATE_EMAIL) === false) {
            $error = 'El email no es válido.';
        } elseif (
            strlen($password) < 10
            || !preg_match('/[A-Z]/', $password)
            || !preg_match('/[a-z]/', $password)
            || !preg_match('/[0-9]/', $password)
            || !preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            $error = 'La contraseña debe tener al menos 10 caracteres, con mayúsculas, minúsculas, número y símbolo.';
        } elseif (has_users()) {
            $error = 'La configuración inicial ya fue completada.';
            $isInitialSetup = false;
        } else {
            $user = [
                'id' => 1,
                'username' => $username,
                'name' => 'Administrador',
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ];

            if (!save_users([$user])) {
                $error = 'No se pudo crear el usuario inicial.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                header('Location: /admin.php');
                exit;
            }
        }
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = find_user_by_username($username);

        if ($user !== null && isset($user['password_hash']) && password_verify($password, (string) $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: /admin.php');
            exit;
        }

        $error = 'Usuario o contraseña inválidos.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; max-width: 480px; }
        form { display: grid; gap: 0.75rem; }
        input, button { padding: 0.6rem; font-size: 1rem; }
        .error { color: #b00020; }
    </style>
</head>
<body>
    <?php if ($isInitialSetup): ?>
        <h1>Crear administrador inicial</h1>
    <?php else: ?>
        <h1>Iniciar sesión</h1>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="/login.php">
        <label>
            Usuario o email
            <input type="text" name="username" required value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>
            Contraseña
            <input type="password" name="password" required>
        </label>
        <button type="submit"><?= $isInitialSetup ? 'Crear administrador' : 'Entrar' ?></button>
    </form>

    <?php if ($isInitialSetup): ?>
        <p>La contraseña debe tener mínimo 10 caracteres e incluir mayúsculas, minúsculas, número y símbolo.</p>
    <?php endif; ?>
</body>
</html>
