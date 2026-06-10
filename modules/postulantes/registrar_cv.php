<?php
/**
 * Los CVs los sube el propio postulante desde el portal.
 * Esta ruta redirige al portal.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

header('Location: ' . BASE_URL . '/portal/convocatorias.php');
exit;
