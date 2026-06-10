<?php
/**
 * Gestión de sesión, autenticación y helpers de permisos — SIGDOC-ML
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

// ── Autenticación ─────────────────────────────────────────

/**
 * Exige que el usuario esté autenticado en el panel admin.
 * Si no, redirige al login.
 */
function requireLogin(): void {
    if (empty($_SESSION['usuario'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Exige que el usuario tenga uno de los roles indicados.
 * Muestra página de "Acceso denegado" en lugar de redirigir silenciosamente.
 */
function requireRol(array $roles): void {
    requireLogin();
    if (!in_array((int)$_SESSION['usuario']['id_rol'], $roles, true)) {
        http_response_code(403);
        include BASE_PATH . '/includes/acceso_denegado.php';
        exit;
    }
}

// ── Helpers de sesión ─────────────────────────────────────

/** Devuelve el array del usuario autenticado o null. */
function usuarioActual(): ?array {
    return $_SESSION['usuario'] ?? null;
}

/** Devuelve true si el usuario tiene alguno de los roles dados. */
function tieneRol(array $roles): bool {
    $usr = usuarioActual();
    return $usr !== null && in_array((int)$usr['id_rol'], $roles, true);
}

/** Devuelve true si es Administrador. */
function esAdmin(): bool {
    return tieneRol([ROL_ADMIN]);
}

/** Devuelve true si es Mesa de Partes. */
function esMesaPartes(): bool {
    return tieneRol([ROL_MESA_PARTES]);
}

/** Devuelve true si es Responsable de Área. */
function esRespArea(): bool {
    return tieneRol([ROL_RESP_AREA]);
}

/** Devuelve true si es RRHH. */
function esRRHH(): bool {
    return tieneRol([ROL_RRHH]);
}

/** Devuelve true si puede gestionar documentos. */
function puedeGestionarDocumentos(): bool {
    return tieneRol(ROLES_DOCUMENTOS);
}

/** Devuelve true si puede gestionar convocatorias / ML. */
function puedeGestionarRRHH(): bool {
    return tieneRol(ROLES_RRHH);
}

/** Devuelve true si puede ver reportes. */
function puedeVerReportes(): bool {
    return tieneRol(ROLES_REPORTES);
}

// ── Bitácora ──────────────────────────────────────────────

/**
 * Registra una acción en la bitácora del sistema.
 */
function registrarBitacora(string $accion, string $tabla, ?int $idRegistro, string $descripcion): void {
    try {
        $db  = getDB();
        $usr = usuarioActual();
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $db->prepare("
            INSERT INTO bitacora
                (id_usuario, accion, tabla_afectada, id_registro, descripcion, ip_origen)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $usr['id_usuario'] ?? null,
            $accion,
            $tabla,
            $idRegistro,
            $descripcion,
            $ip,
        ]);
    } catch (Throwable) {
        // No interrumpir el flujo normal por fallo en bitácora
    }
}
