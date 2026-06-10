<?php
/**
 * Los postulantes se registran ellos mismos desde el portal público.
 * Esta ruta redirige al registro público.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

header('Location: ' . BASE_URL . '/registro.php');
exit;
