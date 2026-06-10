<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

registrarBitacora('LOGOUT', 'usuarios', $_SESSION['usuario']['id_usuario'] ?? null, 'Cierre de sesión');
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
