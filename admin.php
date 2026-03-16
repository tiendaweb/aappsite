<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/url.php';

require_auth();
$user = current_user();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin</title>
</head>
<body>
    <h1>Panel de administración</h1>
    <p>Sesión iniciada como <?= htmlspecialchars((string) ($user['name'] ?? $user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>.</p>
    <p><a href="<?= htmlspecialchars(url_for('/logout'), ENT_QUOTES, 'UTF-8') ?>">Cerrar sesión</a></p>
</body>
</html>
