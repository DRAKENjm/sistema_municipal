<?php
/**
 * El postulante edita sus propios datos desde su portal.
 * RRHH / Admin solo puede ver el perfil y bloquear/activar la cuenta.
 * Esta ruta redirige al detalle.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$id = (int)($_GET['id'] ?? 0);
header('Location: ver.php?id=' . $id);
exit;
