<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$eval = $db->prepare("SELECT id_convocatoria FROM evaluaciones_ml WHERE id_evaluacion = ?");
$eval->execute([$id]);
$eval = $eval->fetch();

if ($eval) {
    $db->prepare("UPDATE evaluaciones_ml SET revisado_rrhh=1 WHERE id_evaluacion=?")->execute([$id]);
    registrarBitacora('REVISAR', 'evaluaciones_ml', $id, 'Evaluación marcada como revisada');
    $_SESSION['success'] = 'Evaluación marcada como revisada.';
    header('Location: ver.php?id=' . $id);
} else {
    $_SESSION['error'] = 'Evaluación no encontrada.';
    header('Location: index.php');
}
exit;
