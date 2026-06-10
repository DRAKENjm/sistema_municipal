<?php
/**
 * Cabecera HTML + Sidebar dinámico por rol — Municipalidad de Yauli
 *
 * Roles y sus accesos:
 *   ROL_ADMIN        → Control técnico total (desarrollador externo). Acceso a TODO incluida bitácora.
 *   ROL_JEFE_GENERAL → Director de la municipalidad. Acceso operativo COMPLETO (usuarios, áreas, documentos,
 *                       convocatorias, reportes) excepto bitácora técnica.
 *   ROL_MESA_PARTES  → Documentos (CRUD), Expedientes (CRUD).
 *   ROL_RESP_AREA    → Documentos (su área), Expedientes (su área), Reportes propios.
 *   ROL_RRHH         → Convocatorias, Postulantes, Evaluaciones ML, Reportes.
 */
if (!isset($pageTitle)) $pageTitle = APP_NAME;
$usr      = usuarioActual();
$idRol    = (int)($usr['id_rol'] ?? 0);
$initials = strtoupper(
    substr($usr['nombres']          ?? 'U', 0, 1) .
    substr($usr['apellido_paterno'] ?? '',  0, 1)
);
$self = $_SERVER['PHP_SELF'] ?? '';

// Helper: marca nav-link como active si la ruta coincide
function navActive(string $path): string {
    global $self;
    return str_contains($self, $path) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- ══════════════ SIDEBAR ══════════════ -->
<nav id="sidebar">
    <a href="<?= BASE_URL ?>/index.php" class="sidebar-brand">
        <i class="bi bi-building brand-icon"></i>
        <span><?= APP_NAME ?></span>
    </a>

    <!-- ── Dashboard (todos) ── -->
    <p class="nav-label">Principal</p>
    <a href="<?= BASE_URL ?>/index.php"
       class="nav-link <?= basename($self) === 'index.php' && !str_contains($self, '/modules/') ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <?php
    // ════════════════════════════════════════════════════
    // GESTIÓN DOCUMENTAL
    // Visible para: ADMIN (lectura), MESA DE PARTES, RESP. ÁREA, JEFE GENERAL
    // ════════════════════════════════════════════════════
    if (in_array($idRol, [ROL_ADMIN, ROL_MESA_PARTES, ROL_RESP_AREA, ROL_JEFE_GENERAL])):
    ?>
    <p class="nav-label">Gestión Documental</p>

    <a href="<?= BASE_URL ?>/modules/documentos/index.php"
       class="nav-link <?= navActive('/documentos/') ?>">
        <i class="bi bi-file-earmark-text"></i> Documentos
    </a>

    <a href="<?= BASE_URL ?>/modules/expedientes/index.php"
       class="nav-link <?= navActive('/expedientes/') ?>">
        <i class="bi bi-folder2-open"></i> Expedientes
    </a>

    <?php endif; ?>

    <?php
    // ════════════════════════════════════════════════════
    // RECLUTAMIENTO / ML
    // Visible para: ADMIN (lectura), RRHH, JEFE GENERAL
    // ════════════════════════════════════════════════════
    if (in_array($idRol, [ROL_ADMIN, ROL_RRHH, ROL_JEFE_GENERAL])):
    ?>
    <p class="nav-label">Reclutamiento</p>

    <a href="<?= BASE_URL ?>/modules/convocatorias/index.php"
       class="nav-link <?= navActive('/convocatorias/') ?>">
        <i class="bi bi-megaphone"></i> Convocatorias
    </a>

    <a href="<?= BASE_URL ?>/modules/postulantes/index.php"
       class="nav-link <?= navActive('/postulantes/') ?>">
        <i class="bi bi-people"></i> Postulantes
    </a>

    <a href="<?= BASE_URL ?>/modules/evaluaciones/index.php"
       class="nav-link <?= navActive('/evaluaciones/') ?>">
        <i class="bi bi-robot"></i> Evaluaciones ML
    </a>

    <a href="<?= BASE_URL ?>/portal/login.php" target="_blank" class="nav-link">
        <i class="bi bi-box-arrow-up-right"></i> Portal postulantes
    </a>

    <?php endif; ?>

    <?php
    // ════════════════════════════════════════════════════
    // REPORTES
    // Visible para: ADMIN, RRHH, RESP. ÁREA, JEFE GENERAL
    // ════════════════════════════════════════════════════
    if (in_array($idRol, [ROL_ADMIN, ROL_RRHH, ROL_RESP_AREA, ROL_JEFE_GENERAL])):
    ?>
    <p class="nav-label">Reportes</p>

    <a href="<?= BASE_URL ?>/modules/reportes/index.php"
       class="nav-link <?= navActive('/reportes/') ?>">
        <i class="bi bi-bar-chart-line"></i> Reportes
    </a>

    <?php endif; ?>

    <?php
    // ════════════════════════════════════════════════════
    // ADMINISTRACIÓN
    // Visible para: ADMIN (todo) y JEFE GENERAL (sin bitácora)
    // ════════════════════════════════════════════════════
    if (in_array($idRol, [ROL_ADMIN, ROL_JEFE_GENERAL])):
    ?>
    <p class="nav-label">Administración</p>

    <a href="<?= BASE_URL ?>/modules/usuarios/index.php"
       class="nav-link <?= navActive('/usuarios/') ?>">
        <i class="bi bi-person-gear"></i> Usuarios
    </a>

    <a href="<?= BASE_URL ?>/modules/areas/index.php"
       class="nav-link <?= navActive('/areas/') ?>">
        <i class="bi bi-diagram-3"></i> Áreas
    </a>

    <a href="<?= BASE_URL ?>/modules/tipos_documento/index.php"
       class="nav-link <?= navActive('/tipos_documento/') ?>">
        <i class="bi bi-card-list"></i> Tipos de documento
    </a>

    <?php if ($idRol === ROL_ADMIN): ?>
    <a href="<?= BASE_URL ?>/modules/bitacora/index.php"
       class="nav-link <?= navActive('/bitacora/') ?>">
        <i class="bi bi-journal-text"></i> Bitácora
    </a>
    <?php endif; ?>

    <?php endif; ?>

</nav>

<!-- ══════════════ TOPBAR ══════════════ -->
<header id="topbar">
    <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>

    <div class="user-info ms-auto">
        <div class="user-avatar"><?= $initials ?></div>
        <div>
            <div class="fw-semibold" style="line-height:1.2">
                <?= htmlspecialchars($usr['nombres'] . ' ' . $usr['apellido_paterno']) ?>
            </div>
            <div class="text-muted" style="font-size:.72rem">
                <?= htmlspecialchars($usr['rol_nombre'] ?? '') ?>
                <?php if (!empty($usr['area_nombre'])): ?>
                · <?= htmlspecialchars($usr['area_nombre']) ?>
                <?php endif; ?>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/logout.php"
           class="btn btn-sm btn-outline-danger ms-2" title="Cerrar sesión">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</header>

<!-- ══════════════ CONTENIDO PRINCIPAL ══════════════ -->
<main id="main-content">
