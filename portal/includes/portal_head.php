<?php
/**
 * Layout del portal de postulantes.
 * Variables esperadas: $pageTitle
 */
if (empty($_SESSION['postulante'])) {
    header('Location: ' . BASE_URL . '/portal/login.php');
    exit;
}
$post = $_SESSION['postulante'];
if (!isset($pageTitle)) $pageTitle = 'Portal';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Portal Postulante</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/portal.css">
</head>
<body>

<nav class="portal-topbar">
    <div class="container">
        <a href="<?= BASE_URL ?>/portal/index.php" class="portal-brand">
            <i class="bi bi-building-fill brand-icon"></i>
            <div class="brand-text">
                <span class="brand-title">Municipalidad Provincial de Yau</span>
                <span class="brand-subtitle">Portal de Postulantes</span>
            </div>
        </a>
        <div class="portal-nav">
            <a href="<?= BASE_URL ?>/portal/index.php"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active':'' ?>">
                <i class="bi bi-house"></i>Inicio
            </a>
            <a href="<?= BASE_URL ?>/portal/convocatorias.php"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'convocatorias.php' ? 'active':'' ?>">
                <i class="bi bi-megaphone"></i>Convocatorias
            </a>
            <a href="<?= BASE_URL ?>/portal/mis_postulaciones.php"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'mis_postulaciones.php' ? 'active':'' ?>">
                <i class="bi bi-briefcase"></i>Mis postulaciones
            </a>
            <a href="<?= BASE_URL ?>/portal/mis_resultados.php"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'mis_resultados.php' ? 'active':'' ?>">
                <i class="bi bi-clipboard-check"></i>Resultados
            </a>
        </div>
        <div class="portal-user-menu">
            <div class="portal-user-info">
                <div class="portal-user-avatar">
                    <?= strtoupper(substr($post['nombres'], 0, 1) . substr($post['apellido_paterno'], 0, 1)) ?>
                </div>
                <span class="portal-user-name">
                    <?= htmlspecialchars($post['nombres'] . ' ' . $post['apellido_paterno']) ?>
                </span>
            </div>
            <a href="<?= BASE_URL ?>/portal/perfil.php" 
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'perfil.php' ? 'active':'' ?>"
               title="Mi perfil">
                <i class="bi bi-person-circle"></i>
            </a>
            <a href="<?= BASE_URL ?>/portal/logout.php" class="btn-logout" title="Cerrar sesión">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</nav>

<div class="portal-content">
<?php if (!empty($_SESSION['success'])): ?>
<div class="alert-portal alert-portal-success alert-auto fade-in-up">
    <i class="bi bi-check-circle-fill alert-icon"></i>
    <div>
        <?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php unset($_SESSION['success']); endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
<div class="alert-portal alert-portal-danger alert-auto fade-in-up">
    <i class="bi bi-exclamation-triangle-fill alert-icon"></i>
    <div>
        <?= htmlspecialchars($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php unset($_SESSION['error']); endif; ?>
