<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$conv = $db->prepare("SELECT titulo FROM convocatorias WHERE id_convocatoria = ?");
$conv->execute([$id]);
$conv = $conv->fetch();

if ($conv) {
    // Eliminar archivos de curriculums asociados
    $cvs = $db->prepare("SELECT ruta_archivo FROM curriculums WHERE id_convocatoria = ?");
    $cvs->execute([$id]);
    foreach ($cvs->fetchAll() as $cv) {
        if ($cv['ruta_archivo'] && file_exists(UPLOAD_CVS . $cv['ruta_archivo'])) {
            unlink(UPLOAD_CVS . $cv['ruta_archivo']);
        }
    }
    // Eliminar evaluaciones, curriculums y convocatoria
    $db->prepare("DELETE FROM evaluaciones_ml WHERE id_convocatoria = ?")->execute([$id]);
    $db->prepare("DELETE FROM curriculums WHERE id_convocatoria = ?")->execute([$id]);
    $db->prepare("DELETE FROM convocatorias WHERE id_convocatoria = ?")->execute([$id]);
    registrarBitacora('ELIMINAR', 'convocatorias', $id, 'Convocatoria eliminada: ' . $conv['titulo']);
    $_SESSION['success'] = 'Convocatoria eliminada.';
} else {
    $_SESSION['error'] = 'Convocatoria no encontrada.';
}

header('Location: index.php');
exit;
