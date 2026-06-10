<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

unset($_SESSION['postulante']);
header('Location: ' . BASE_URL . '/portal/login.php');
exit;
