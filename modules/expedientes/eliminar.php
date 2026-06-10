<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_DOCUMENTOS);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$exp = $db->prepare("SELECT numero_expediente FROM expedientes WHERE id_expediente=?");
$exp->execute([$id]);
$exp = $exp->fetch();

if ($exp) {
    $db->prepare("DELETE FROM expediente_documento WHERE id_expediente=?")->execute([$id]);
    $db->prepare("DELETE FROM expedientes WHERE id_expediente=?")->execute([$id]);
    registrarBitacora('ELIMINAR', 'expedientes', $id, 'Expediente eliminado: ' . $exp['numero_expediente']);
    $_SESSION['success'] = 'Expediente eliminado.';
} else {
    $_SESSION['error'] = 'Expediente no encontrado.';
}
header('Location: index.php');
exit;
