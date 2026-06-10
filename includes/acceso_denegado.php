<?php
/**
 * Página de Acceso Denegado — se muestra cuando el rol no tiene permiso.
 * Se incluye directamente desde requireRol(), no es una redirección.
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/session.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso denegado — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body style="background:#eef2f7">
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="text-center p-5">
        <i class="bi bi-shield-x" style="font-size:5rem;color:var(--danger,#dc3545);opacity:.7"></i>
        <h2 class="fw-bold mt-3" style="color:#1a3a5c">Acceso denegado</h2>
        <p class="text-muted mb-4">
            Tu rol no tiene permisos para acceder a esta sección.<br>
            Si crees que esto es un error, contacta al Administrador del sistema.
        </p>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary px-4">
            <i class="bi bi-house me-1"></i> Volver al Dashboard
        </a>
    </div>
</div>
</body>
</html>
