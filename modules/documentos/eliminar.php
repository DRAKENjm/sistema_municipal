<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_DOCUMENTOS);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$doc = $db->prepare("SELECT * FROM documentos WHERE id_documento = ?");
$doc->execute([$id]);
$doc = $doc->fetch();

if ($doc) {
    // Eliminar archivo físico si existe
    if ($doc['ruta_archivo'] && file_exists(UPLOAD_DOCS . $doc['ruta_archivo'])) {
        unlink(UPLOAD_DOCS . $doc['ruta_archivo']);
    }
    // Desvincular de expedientes
    $db->prepare("DELETE FROM expediente_documento WHERE id_documento = ?")->execute([$id]);
    // Eliminar documento
    $db->prepare("DELETE FROM documentos WHERE id_documento = ?")->execute([$id]);
    registrarBitacora('ELIMINAR', 'documentos', $id, 'Documento eliminado: ' . $doc['asunto']);
    $_SESSION['success'] = 'Documento eliminado correctamente.';
} else {
    $_SESSION['error'] = 'Documento no encontrado.';
}

header('Location: index.php');
exit;
