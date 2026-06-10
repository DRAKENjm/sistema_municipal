<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

$post = $_SESSION['postulante'] ?? null;
if (!$post) {
    $_SESSION['error'] = 'Debes iniciar sesión.';
    header('Location: ' . BASE_URL . '/portal/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mis_resultados.php');
    exit;
}

$db = getDB();
$idCurriculum = (int)($_POST['id_curriculum'] ?? 0);

// Validar que el CV pertenece al postulante
$cv = $db->prepare("
    SELECT cv.*, c.titulo AS conv_titulo
    FROM curriculums cv
    JOIN convocatorias c ON c.id_convocatoria = cv.id_convocatoria
    WHERE cv.id_curriculum = ? AND cv.id_postulante = ?
");
$cv->execute([$idCurriculum, $post['id_postulante']]);
$cv = $cv->fetch();

if (!$cv) {
    $_SESSION['error'] = 'CV no encontrado.';
    header('Location: mis_resultados.php');
    exit;
}

// Validar que aún no fue procesado
if ($cv['procesado']) {
    $_SESSION['error'] = 'No puedes actualizar un CV que ya fue evaluado.';
    header('Location: mis_resultados.php');
    exit;
}

// Validar que no lo ha actualizado antes
if ($cv['actualizado']) {
    $_SESSION['error'] = 'Ya actualizaste este CV anteriormente. Solo se permite una actualización.';
    header('Location: mis_resultados.php');
    exit;
}

// Validar archivo subido
if (empty($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = 'No se subió ningún archivo o hubo un error.';
    header('Location: mis_resultados.php');
    exit;
}

$archivo = $_FILES['cv'];
$ext     = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ALLOWED_CV_EXT)) {
    $_SESSION['error'] = 'Formato no permitido. Solo: ' . implode(', ', ALLOWED_CV_EXT);
    header('Location: mis_resultados.php');
    exit;
}

if ($archivo['size'] > MAX_FILE_SIZE) {
    $_SESSION['error'] = 'El archivo excede el tamaño máximo de ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB.';
    header('Location: mis_resultados.php');
    exit;
}

// Eliminar archivo anterior
$rutaAnterior = UPLOAD_CVS . $cv['ruta_archivo'];
if (file_exists($rutaAnterior)) {
    @unlink($rutaAnterior);
}

// Generar nuevo nombre de archivo
$nombreLimpio = preg_replace('/[^a-z0-9_\-\.]/i', '_', pathinfo($archivo['name'], PATHINFO_FILENAME));
$nombreFinal  = 'cv_' . $post['id_postulante'] . '_' . $cv['id_convocatoria'] . '_actualizado_' . time() . '.' . $ext;
$rutaDestino  = UPLOAD_CVS . $nombreFinal;

// Mover archivo
if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
    $_SESSION['error'] = 'Error al guardar el archivo.';
    header('Location: mis_resultados.php');
    exit;
}

// Actualizar registro en BD
$stmt = $db->prepare("
    UPDATE curriculums 
    SET ruta_archivo = ?,
        nombre_archivo = ?,
        actualizado = 1,
        fecha_actualizacion = NOW(),
        texto_extraido = NULL
    WHERE id_curriculum = ?
");
$stmt->execute([$nombreFinal, $archivo['name'], $idCurriculum]);

$_SESSION['success'] = 'Tu CV fue actualizado exitosamente. Será evaluado próximamente.';
header('Location: mis_resultados.php');
exit;
