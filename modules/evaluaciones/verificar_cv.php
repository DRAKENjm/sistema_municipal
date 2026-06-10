<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$db  = getDB();
$usr = usuarioActual();

$id        = (int)($_POST['id_evaluacion'] ?? 0);
$resultado = trim($_POST['resultado'] ?? '');
$comentario= trim($_POST['comentario'] ?? '');

// Validaciones
if (!$id || !in_array($resultado, ['ACEPTADO', 'RECHAZADO', 'EN_ESPERA'])) {
    $_SESSION['error'] = 'Datos inválidos.';
    header('Location: index.php');
    exit;
}

if (empty($comentario)) {
    $_SESSION['error'] = 'El comentario es obligatorio.';
    header('Location: index.php');
    exit;
}

// Verificar que la evaluación existe
$eval = $db->prepare("SELECT id_convocatoria, verificado FROM evaluaciones_ml WHERE id_evaluacion = ?");
$eval->execute([$id]);
$eval = $eval->fetch();

if (!$eval) {
    $_SESSION['error'] = 'Evaluación no encontrada.';
    header('Location: index.php');
    exit;
}

if ($eval['verificado']) {
    $_SESSION['warning'] = 'Esta evaluación ya fue verificada anteriormente.';
    header('Location: ver.php?id=' . $id);
    exit;
}

// Actualizar evaluación
$stmt = $db->prepare("
    UPDATE evaluaciones_ml 
    SET verificado = 1,
        resultado_verificacion = ?,
        comentario_verificacion = ?,
        fecha_verificacion = NOW(),
        verificado_por = ?,
        revisado_rrhh = 1
    WHERE id_evaluacion = ?
");

$verificadoPor = $usr['id_usuario']; // ID del usuario, no el nombre
$stmt->execute([$resultado, $comentario, $verificadoPor, $id]);

// Registrar en bitácora
registrarBitacora('VERIFICAR', 'evaluaciones_ml', $id, "CV verificado: $resultado");

$_SESSION['success'] = 'Evaluación verificada correctamente. El postulante puede consultar el resultado.';

// Redireccionar según origen
$redirectTo = $_POST['redirect_to'] ?? '';
if ($redirectTo === 'reportes') {
    header('Location: ' . BASE_URL . '/modules/reportes/index.php');
} else {
    header('Location: ver.php?id=' . $id);
}
exit;
