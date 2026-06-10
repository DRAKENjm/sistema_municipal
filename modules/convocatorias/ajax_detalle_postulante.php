<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

header('Content-Type: application/json');

$db = getDB();
$idPostulante = (int)($_GET['id'] ?? 0);
$idCurriculum = (int)($_GET['cv'] ?? 0);
$idConvocatoria = (int)($_GET['conv'] ?? 0);

if (!$idPostulante || !$idCurriculum) {
    echo json_encode(['error' => 'Parámetros inválidos']);
    exit;
}

// Obtener datos del postulante
$stmtPost = $db->prepare("
    SELECT p.*, cv.nombre_archivo, cv.ruta_archivo, cv.procesado, cv.fecha_carga
    FROM postulantes p
    JOIN curriculums cv ON cv.id_postulante = p.id_postulante
    WHERE p.id_postulante = ? AND cv.id_curriculum = ?
");
$stmtPost->execute([$idPostulante, $idCurriculum]);
$postulante = $stmtPost->fetch();

if (!$postulante) {
    echo json_encode(['error' => 'Postulante no encontrado']);
    exit;
}

// Obtener evaluación ML si existe
$stmtEval = $db->prepare("
    SELECT puntaje, porcentaje_coincidencia, ranking, fecha_evaluacion
    FROM evaluaciones_ml
    WHERE id_curriculum = ?
");
$stmtEval->execute([$idCurriculum]);
$evaluacion = $stmtEval->fetch();

// Preparar respuesta
$response = [
    'id_postulante' => $postulante['id_postulante'],
    'dni' => $postulante['dni'],
    'nombres' => $postulante['nombres'],
    'apellido_paterno' => $postulante['apellido_paterno'],
    'apellido_materno' => $postulante['apellido_materno'],
    'correo' => $postulante['correo'],
    'telefono' => $postulante['telefono'],
    'nombre_archivo' => $postulante['nombre_archivo'],
    'ruta_archivo' => $postulante['ruta_archivo'] ? basename($postulante['ruta_archivo']) : null,
    'procesado' => $postulante['procesado'],
    'fecha_carga' => date('d/m/Y H:i', strtotime($postulante['fecha_carga'])),
    'evaluacion' => $evaluacion ? [
        'puntaje' => number_format($evaluacion['puntaje'], 2),
        'porcentaje_coincidencia' => number_format($evaluacion['porcentaje_coincidencia'], 2),
        'ranking' => $evaluacion['ranking'],
        'fecha_evaluacion' => date('d/m/Y H:i', strtotime($evaluacion['fecha_evaluacion']))
    ] : null
];

echo json_encode($response);
