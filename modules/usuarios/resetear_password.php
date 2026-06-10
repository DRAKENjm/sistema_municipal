<?php
/**
 * Resetea la contraseña de un usuario a "Reset2026@".
 * Solo disponible para el Administrador.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_USUARIOS);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

// No se puede resetear la propia cuenta
$actual = usuarioActual();
if ($id === (int)$actual['id_usuario']) {
    $_SESSION['error'] = 'No puede resetear su propia contraseña desde aquí. Use su perfil.';
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT usuario FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$id]);
$usr = $stmt->fetch();

if (!$usr) {
    $_SESSION['error'] = 'Usuario no encontrado.';
    header('Location: index.php');
    exit;
}

$nuevaPass = 'Reset2026@';
$hash = password_hash($nuevaPass, PASSWORD_BCRYPT);

$db->prepare("UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?")
   ->execute([$hash, $id]);

registrarBitacora(
    'RESET_PASSWORD',
    'usuarios',
    $id,
    "Contraseña reseteada para usuario: {$usr['usuario']} por " . ($actual['usuario'] ?? 'Admin')
);

$_SESSION['success'] = "Contraseña del usuario <strong>{$usr['usuario']}</strong> reseteada a: <code>Reset2026@</code> — Informe al usuario para que la cambie.";
header('Location: index.php');
exit;
