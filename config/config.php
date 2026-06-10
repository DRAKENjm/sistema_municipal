<?php
/**
 * Constantes globales del sistema — SIGDOC-ML
 */

define('APP_NAME',    'Municipalidad de Yauli');
define('APP_VERSION', '1.0.0');

// ── BASE_URL autodetectada ─────────────────────────────────
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_folder   = trim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$_folder   = preg_replace('#/(modules|portal|config|includes)(/.+)?$#', '', '/' . $_folder);
$_folder   = rtrim($_folder, '/');
define('BASE_URL',  $_protocol . '://' . $_host . $_folder);
define('BASE_PATH', dirname(__DIR__));

// ── Directorios de subida ──────────────────────────────────
define('UPLOAD_DOCS', BASE_PATH . '/uploads/documentos/');
define('UPLOAD_CVS',  BASE_PATH . '/uploads/curriculums/');
define('ML_SCRIPT',   BASE_PATH . '/ml/analizar_cv.py');

// ── Extensiones y límite de archivo ───────────────────────
define('ALLOWED_DOC_EXT', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg']);
define('ALLOWED_CV_EXT',  ['pdf', 'doc', 'docx']);
define('MAX_FILE_SIZE',   10 * 1024 * 1024);   // 10 MB

// ══════════════════════════════════════════════════════════
// PYTHON_EXE — Detección automática y portable
// ══════════════════════════════════════════════════════════
// Estrategia:
//   1. Leer la ruta cacheada en /config/python_path.txt (se genera una sola vez).
//   2. Si no existe o el exe ya no está, buscar con `where python` / `where py`.
//   3. Guardar el resultado para los siguientes requests.
// Así funciona en cualquier equipo sin tocar este archivo.
// ══════════════════════════════════════════════════════════

define('PYTHON_CACHE_FILE', BASE_PATH . '/config/python_path.txt');

function detectarPythonExe(): string
{
    $cacheFile = PYTHON_CACHE_FILE;

    // 1. Leer caché si existe y el ejecutable sigue ahí
    if (file_exists($cacheFile)) {
        $cached = trim(file_get_contents($cacheFile));
        if ($cached !== '' && (file_exists($cached) || in_array($cached, ['python', 'py']))) {
            return $cached;
        }
    }

    // 2. Buscar en el sistema
    $encontrado = null;

    // Candidatos en orden de preferencia
    $candidatos = ['python', 'py', 'python3'];

    foreach ($candidatos as $cmd) {
        // `where` en Windows, `which` en Linux/Mac
        $whereCmd  = PHP_OS_FAMILY === 'Windows' ? "where $cmd 2>NUL" : "which $cmd 2>/dev/null";
        $resultado = trim((string) shell_exec($whereCmd));

        if ($resultado === '') continue;

        // `where` puede devolver varias rutas; tomar la primera línea válida
        $lineas = array_filter(array_map('trim', explode("\n", $resultado)));
        foreach ($lineas as $linea) {
            // Ignorar el stub de la Windows Store
            if (str_contains($linea, 'WindowsApps')) continue;
            // Verificar que el archivo existe (rutas absolutas)
            if (str_contains($linea, DIRECTORY_SEPARATOR) && !file_exists($linea)) continue;

            $encontrado = $linea;
            break 2;
        }
    }

    // 3. Fallback absoluto
    if (!$encontrado) {
        $encontrado = 'python';
    }

    // 4. Guardar en caché
    @file_put_contents($cacheFile, $encontrado);

    return $encontrado;
}

define('PYTHON_EXE', detectarPythonExe());

// ── Versión del modelo ML ──────────────────────────────────
define('ML_MODEL_VERSION', 'v1.0');

// ══════════════════════════════════════════════════════════
// ROLES DEL SISTEMA
// ══════════════════════════════════════════════════════════
//
//  1 — ADMINISTRADOR   Control técnico total (externo/desarrollador).
//                      Acceso completo incluida la bitácora.
//                      Gestiona usuarios, áreas, catálogos, bitácora.
//
//  2 — MESA DE PARTES  Recepción documental: registra, clasifica, crea expedientes 
//                      y deriva documentos.
//
//  3 — RESP. ÁREA      Atiende documentos de su área, cambia estados, gestiona 
//                      expedientes de su área.
//
//  4 — RRHH            Recursos Humanos: convocatorias, evaluación ML, selección 
//                      de personal.
//
//  5 — JEFE GENERAL    Director de la municipalidad. Acceso operativo COMPLETO:
//                      puede crear, editar y gestionar usuarios, áreas, documentos,
//                      expedientes, convocatorias, evaluaciones ML y reportes.
//                      ÚNICA RESTRICCIÓN: No accede a bitácora (es técnica).
//
// ══════════════════════════════════════════════════════════

define('ROL_ADMIN',        1);
define('ROL_MESA_PARTES',  2);
define('ROL_RESP_AREA',    3);
define('ROL_RRHH',         4);
define('ROL_JEFE_GENERAL', 5);

// ── Grupos de permisos ────────────────────────────────────
define('ROLES_DOCUMENTOS',  [ROL_ADMIN, ROL_MESA_PARTES, ROL_RESP_AREA, ROL_JEFE_GENERAL]);
define('ROLES_EXPEDIENTES', [ROL_ADMIN, ROL_MESA_PARTES, ROL_RESP_AREA, ROL_JEFE_GENERAL]);
define('ROLES_RRHH',        [ROL_ADMIN, ROL_RRHH, ROL_JEFE_GENERAL]);
define('ROLES_REPORTES',    [ROL_ADMIN, ROL_RRHH, ROL_RESP_AREA, ROL_JEFE_GENERAL]);
define('ROLES_USUARIOS',    [ROL_ADMIN, ROL_JEFE_GENERAL]);  // Admin + Jefe pueden gestionar usuarios
define('ROLES_AREAS',       [ROL_ADMIN, ROL_JEFE_GENERAL]);  // Admin + Jefe pueden gestionar áreas
define('ROLES_TIPOS_DOC',   [ROL_ADMIN, ROL_JEFE_GENERAL]);  // Admin + Jefe pueden gestionar tipos
define('ROLES_BITACORA',    [ROL_ADMIN]);                      // Solo Admin ve bitácora

// ── Crear directorios de upload si no existen ─────────────
foreach ([UPLOAD_DOCS, UPLOAD_CVS] as $_dir) {
    if (!is_dir($_dir)) {
        mkdir($_dir, 0755, true);
    }
}
