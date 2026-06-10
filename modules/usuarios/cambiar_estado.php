<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_USUARIOS);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

// No permitir desactivar al propio usuario
$actual = usuarioActual();
if ($id === (int)$actual['id_usuario']) {
    $_SESSION['error'] = 'No puede desactivar su propia cuenta.';
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT estado, usuario FROM usuarios WHERE id_usuario=?");
$stmt->execute([$id]);
$usr = $stmt->fetch();

if ($usr) {
    $nuevoEstado = $usr['estado'] ? 0 : 1;
    $db->prepare("UPDATE usuarios SET estado=? WHERE id_usuario=?")->execute([$nuevoEstado, $id]);
    $accion = $nuevoEstado ? 'ACTIVAR' : 'DESACTIVAR';
    registrarBitacora($accion, 'usuarios', $id, "Usuario {$usr['usuario']} " . ($nuevoEstado ? 'activado' : 'desactivado'));
    $_SESSION['success'] = 'Estado del usuario actualizado.';
} else {
    $_SESSION['error'] = 'Usuario no encontrado.';
}

header('Location: index.php');
exit;
