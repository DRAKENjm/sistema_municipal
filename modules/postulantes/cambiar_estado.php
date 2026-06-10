<?php
/**
 * Bloquear / activar la cuenta de un postulante (solo ADMIN y JEFE GENERAL).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_USUARIOS); // Admin y Jefe General pueden gestionar postulantes

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT estado, nombres, apellido_paterno, usuario FROM postulantes WHERE id_postulante = ?");
$stmt->execute([$id]);
$post = $stmt->fetch();

if ($post) {
    $nuevoEstado = $post['estado'] ? 0 : 1;
    $db->prepare("UPDATE postulantes SET estado = ? WHERE id_postulante = ?")->execute([$nuevoEstado, $id]);

    $accion = $nuevoEstado ? 'ACTIVAR_POSTULANTE' : 'BLOQUEAR_POSTULANTE';
    $msg    = $nuevoEstado ? 'activado' : 'bloqueado';
    registrarBitacora($accion, 'postulantes', $id,
        "Postulante {$post['nombres']} {$post['apellido_paterno']} ({$post['usuario']}) $msg");

    $_SESSION['success'] = "Cuenta del postulante $msg correctamente.";
} else {
    $_SESSION['error'] = 'Postulante no encontrado.';
}

header('Location: ver.php?id=' . $id);
exit;
