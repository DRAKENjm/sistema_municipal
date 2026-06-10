<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$db = getDB();
$filtroConv = (int)($_GET['conv'] ?? 0);

// ── Obtener CVs a procesar ────────────────────────────────
if ($filtroConv > 0) {
    $stmt = $db->prepare("
        SELECT cv.*, c.palabras_clave, c.perfil_requerido, c.titulo AS conv_titulo
        FROM curriculums cv
        JOIN convocatorias c ON c.id_convocatoria = cv.id_convocatoria
        WHERE cv.id_convocatoria = ?
    ");
    $stmt->execute([$filtroConv]);
} else {
    $stmt = $db->query("
        SELECT cv.*, c.palabras_clave, c.perfil_requerido, c.titulo AS conv_titulo
        FROM curriculums cv
        JOIN convocatorias c ON c.id_convocatoria = cv.id_convocatoria
        WHERE cv.procesado = 0
    ");
}
$curriculums = $stmt->fetchAll();

$procesados = 0;
$errores    = [];

foreach ($curriculums as $cv) {
    $rutaCV = UPLOAD_CVS . $cv['ruta_archivo'];

    if (!file_exists($rutaCV)) {
        $errores[] = "Archivo no encontrado: " . ($cv['nombre_archivo'] ?? $cv['ruta_archivo']);
        continue;
    }

    // ── Estrategia: pasar argumentos por archivo JSON temporal ──
    // Evita todos los problemas de escaping de comillas en Windows.
    // PHP escribe el JSON sin BOM; Python lo lee con utf-8-sig (tolera BOM igual).
    $argsFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigdoc_' . uniqid() . '.json';
    $argsJson = json_encode([
        'ruta_cv'  => $rutaCV,
        'perfil'   => $cv['perfil_requerido'] ?? '',
        'keywords' => $cv['palabras_clave']   ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($argsFile, $argsJson);   // UTF-8 sin BOM desde PHP

    $pythonExe = PYTHON_EXE;
    $script    = ML_SCRIPT;

    // Sintaxis correcta para cmd /c con rutas que tienen espacios en Windows:
    // cmd /c ""exe" "script" args"  (doble comilla externa + comillas en cada ruta)
    $cmd = 'cmd /c ""' . $pythonExe . '" "' . $script . '" --args-file "' . $argsFile . '"" 2>&1';

    $output   = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    // Eliminar archivo temporal
    if (file_exists($argsFile)) {
        @unlink($argsFile);
    }

    $raw = implode("\n", $output);

    // ── Parsear JSON de la salida ─────────────────────────
    $resultado = null;
    // Buscar el bloque JSON (puede haber warnings de pdfminer antes)
    preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)?\}/s', $raw, $matches);
    if (!empty($matches[0])) {
        $resultado = json_decode($matches[0], true);
    }

    // Si el JSON está incompleto, intentar con un regex más permisivo
    if (!$resultado) {
        $posJson = strrpos($raw, '{');
        if ($posJson !== false) {
            $jsonStr = substr($raw, $posJson);
            $resultado = json_decode($jsonStr, true);
        }
    }

    if (!$resultado || $exitCode !== 0) {
        // Guardar el error completo para diagnóstico
        $errorMsg = mb_strimwidth(strip_tags($raw), 0, 200, '…');
        $errores[] = "CV #{$cv['id_curriculum']} ({$cv['nombre_archivo']}): $errorMsg";
        continue;
    }

    // ── Guardar resultados ────────────────────────────────
    $puntaje      = round(min(100, max(0, (float)($resultado['puntaje']                  ?? 0))), 2);
    $coincidencia = round(min(100, max(0, (float)($resultado['porcentaje_coincidencia']  ?? 0))), 2);
    $texto        = isset($resultado['texto_extraido']) ? mb_substr($resultado['texto_extraido'], 0, 65000) : null;
    $detalles     = json_encode($resultado['detalles'] ?? [], JSON_UNESCAPED_UNICODE);
    $observaciones = $resultado['observaciones'] ?? null;

    $db->prepare("UPDATE curriculums SET texto_extraido = ?, procesado = 1 WHERE id_curriculum = ?")
       ->execute([$texto, $cv['id_curriculum']]);

    $evalExiste = $db->prepare("SELECT id_evaluacion FROM evaluaciones_ml WHERE id_curriculum = ?");
    $evalExiste->execute([$cv['id_curriculum']]);
    $evalExiste = $evalExiste->fetch();

    if ($evalExiste) {
        $db->prepare("
            UPDATE evaluaciones_ml SET
                puntaje = ?, porcentaje_coincidencia = ?,
                detalles_json = ?, observaciones = ?,
                modelo_version = ?, revisado_rrhh = 0,
                fecha_evaluacion = NOW()
            WHERE id_evaluacion = ?
        ")->execute([
            $puntaje, $coincidencia, $detalles,
            $observaciones, ML_MODEL_VERSION,
            $evalExiste['id_evaluacion']
        ]);
    } else {
        $db->prepare("
            INSERT INTO evaluaciones_ml
                (id_curriculum, id_postulante, id_convocatoria,
                 puntaje, porcentaje_coincidencia,
                 detalles_json, observaciones, modelo_version)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $cv['id_curriculum'], $cv['id_postulante'], $cv['id_convocatoria'],
            $puntaje, $coincidencia,
            $detalles, $observaciones, ML_MODEL_VERSION
        ]);
    }

    // ── Recalcular ranking de la convocatoria ─────────────
    $rankList = $db->prepare("
        SELECT id_evaluacion
        FROM evaluaciones_ml
        WHERE id_convocatoria = ?
        ORDER BY puntaje DESC
    ");
    $rankList->execute([$cv['id_convocatoria']]);
    foreach ($rankList->fetchAll(PDO::FETCH_COLUMN) as $pos => $evalId) {
        $db->prepare("UPDATE evaluaciones_ml SET ranking = ? WHERE id_evaluacion = ?")
           ->execute([$pos + 1, $evalId]);
    }

    $procesados++;
}

// ── Bitácora y respuesta ──────────────────────────────────
registrarBitacora(
    'PROCESAR_ML', 'evaluaciones_ml', null,
    "Procesados: $procesados · Errores: " . count($errores)
);

if ($procesados > 0) {
    $_SESSION['success'] = "$procesados curriculum(s) evaluados con ML correctamente.";
} elseif (empty($errores)) {
    $_SESSION['info'] = "No había curriculums pendientes de evaluación.";
}

if (!empty($errores)) {
    $_SESSION['error'] = implode(' | ', array_slice($errores, 0, 3));
}

header('Location: ' . BASE_URL . '/modules/evaluaciones/index.php' .
       ($filtroConv ? "?conv=$filtroConv" : ''));
exit;
