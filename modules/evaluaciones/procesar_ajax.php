<?php
/**
 * Endpoint AJAX — procesa los CVs seleccionados y devuelve JSON.
 * Llamado desde index.php vía fetch().
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

// Solo POST autenticado
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

requireRol(ROLES_RRHH);

$db = getDB();

// ── IDs de CVs a procesar ─────────────────────────────────
$cvIds    = array_filter(array_map('intval', $_POST['cv_ids'] ?? []));
$convFiltro = (int)($_POST['conv'] ?? 0);

if (empty($cvIds)) {
    // Si no vienen IDs, procesar todos los pendientes de la convocatoria
    if ($convFiltro > 0) {
        $stmt = $db->prepare("SELECT id_curriculum FROM curriculums WHERE id_convocatoria = ?");
        $stmt->execute([$convFiltro]);
    } else {
        $stmt = $db->query("SELECT id_curriculum FROM curriculums WHERE procesado = 0");
    }
    $cvIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if (empty($cvIds)) {
    echo json_encode(['procesados' => 0, 'errores' => [], 'log' => [
        ['ok' => false, 'msg' => 'No se encontraron CVs para procesar.']
    ]]);
    exit;
}

// ── Cargar datos de los CVs ───────────────────────────────
$placeholders = implode(',', array_fill(0, count($cvIds), '?'));
$stmt = $db->prepare("
    SELECT cv.*, c.palabras_clave, c.perfil_requerido, c.titulo AS conv_titulo
    FROM curriculums cv
    JOIN convocatorias c ON c.id_convocatoria = cv.id_convocatoria
    WHERE cv.id_curriculum IN ($placeholders)
");
$stmt->execute($cvIds);
$curriculums = $stmt->fetchAll();

$procesados = 0;
$errores    = [];
$log        = [];

// ── Función: llamar a Python ──────────────────────────────
function llamarPython(string $rutaCV, string $perfil, string $keywords): array
{
    // Usar C:\Windows\Temp (sin espacios, accesible desde Apache)
    $argsFile = 'C:\\Windows\\Temp\\sigdoc_' . uniqid() . '.json';

    file_put_contents($argsFile, json_encode([
        'ruta_cv'  => str_replace('/', '\\', $rutaCV),
        'perfil'   => $perfil,
        'keywords' => $keywords,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $pyExe  = PYTHON_EXE;
    $script = str_replace('/', '\\', ML_SCRIPT);

    // ── Construir comando portable ────────────────────────
    // Si PYTHON_EXE es una ruta con espacios → necesita comillas + cmd /c
    // Si es "python" o "py" → no necesita cmd /c
    $pyTieneEspacios = str_contains($pyExe, ' ');

    if ($pyTieneEspacios) {
        // cmd /c ""exe con espacios" "script" --args-file "argsFile""
        $cmd = 'cmd /c ""' . $pyExe . '" "' . $script . '" --args-file "' . $argsFile . '""';
    } else {
        // python "script" --args-file "argsFile"
        $cmd = $pyExe . ' "' . $script . '" --args-file "' . $argsFile . '"';
    }

    // proc_open separa stdout/stderr → evita que warnings de pdfminer
    // contaminen el JSON que viene por stdout
    $descriptors = [
        1 => ['pipe', 'w'],   // stdout → JSON
        2 => ['pipe', 'w'],   // stderr → warnings (ignorar)
    ];
    $pipes   = [];
    $proceso = proc_open($cmd, $descriptors, $pipes);
    $stdout  = '';
    $exitCode = -1;

    if (is_resource($proceso)) {
        $stdout   = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $exitCode = proc_close($proceso);
    }

    if (file_exists($argsFile)) @unlink($argsFile);

    $raw       = trim($stdout);
    $resultado = null;

    // stdout es directamente el JSON (Python usa stdout limpio, stderr para warnings)
    if ($raw) {
        // Intentar parsear directamente
        $resultado = json_decode($raw, true);
        // Si falla (hay algún warning antes del JSON), buscar el bloque JSON
        if (!$resultado) {
            $pos = strpos($raw, '{');
            if ($pos !== false) {
                $resultado = json_decode(substr($raw, $pos), true);
            }
        }
    }

    return [
        'resultado' => $resultado,
        'exitCode'  => $exitCode,
        'raw'       => $raw ?: ($stderr ?? ''),
    ];
}

// ── Procesar cada CV ──────────────────────────────────────
foreach ($curriculums as $cv) {
    $rutaCV  = UPLOAD_CVS . $cv['ruta_archivo'];
    $nombre  = $cv['nombre_archivo'] ?? $cv['ruta_archivo'];

    if (!file_exists($rutaCV)) {
        $errores[] = "Archivo no encontrado: $nombre";
        $log[]     = ['ok' => false, 'msg' => "❌ $nombre — archivo no encontrado"];
        continue;
    }

    $res = llamarPython(
        $rutaCV,
        $cv['perfil_requerido'] ?? '',
        $cv['palabras_clave']   ?? ''
    );

    if (!$res['resultado']) {
        $errorMsg  = mb_strimwidth(preg_replace('/\s+/', ' ', strip_tags($res['raw'])), 0, 120, '…');
        $errores[] = "$nombre: $errorMsg";
        $log[]     = ['ok' => false, 'msg' => "❌ $nombre — " . ($errorMsg ?: "exit {$res['exitCode']}")];
        continue;
    }

    $r            = $res['resultado'];
    $puntaje      = round(min(100, max(0, (float)($r['puntaje']                  ?? 0))), 2);
    $coincidencia = round(min(100, max(0, (float)($r['porcentaje_coincidencia']  ?? 0))), 2);
    $texto        = isset($r['texto_extraido']) ? mb_substr($r['texto_extraido'], 0, 65000) : null;
    $detalles     = json_encode($r['detalles'] ?? [], JSON_UNESCAPED_UNICODE);
    $observaciones = $r['observaciones'] ?? null;

    // Guardar en BD
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

    // Recalcular ranking de la convocatoria
    $rankList = $db->prepare("
        SELECT id_evaluacion FROM evaluaciones_ml
        WHERE id_convocatoria = ?
        ORDER BY puntaje DESC
    ");
    $rankList->execute([$cv['id_convocatoria']]);
    foreach ($rankList->fetchAll(PDO::FETCH_COLUMN) as $pos => $evalId) {
        $db->prepare("UPDATE evaluaciones_ml SET ranking = ? WHERE id_evaluacion = ?")
           ->execute([$pos + 1, $evalId]);
    }

    $procesados++;
    $log[] = ['ok' => true, 'msg' => "✅ {$nombre} — {$puntaje} pts ({$coincidencia}% coincidencia)"];
}

// ── Bitácora ──────────────────────────────────────────────
registrarBitacora(
    'PROCESAR_ML', 'evaluaciones_ml', null,
    "AJAX — Procesados: $procesados · Errores: " . count($errores)
);

echo json_encode([
    'procesados' => $procesados,
    'errores'    => $errores,
    'log'        => $log,
], JSON_UNESCAPED_UNICODE);
exit;
