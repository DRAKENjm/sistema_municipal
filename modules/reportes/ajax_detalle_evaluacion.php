<?php
/**
 * Endpoint AJAX — devuelve detalles de una evaluación ML
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

requireRol(ROLES_REPORTES);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'ID no válido']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("
    SELECT e.*, 
           c.titulo AS convocatoria,
           a.nombre AS area,
           cv.nombre_archivo,
           DATE_FORMAT(cv.fecha_carga, '%d/%m/%Y') AS fecha_carga,
           DATE_FORMAT(e.fecha_evaluacion, '%d/%m/%Y %H:%i') AS fecha_evaluacion
    FROM evaluaciones_ml e
    JOIN convocatorias c ON c.id_convocatoria = e.id_convocatoria
    LEFT JOIN areas a ON a.id_area = c.id_area
    JOIN curriculums cv ON cv.id_curriculum = e.id_curriculum
    WHERE e.id_evaluacion = ?
");
$stmt->execute([$id]);
$eval = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$eval) {
    echo json_encode(['error' => 'Evaluación no encontrada']);
    exit;
}

echo json_encode($eval, JSON_UNESCAPED_UNICODE);
