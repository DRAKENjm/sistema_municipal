<?php
require __DIR__ . '/../config/config.php';

$argsFile = 'C:\\Windows\\Temp\\sigdoc_v2_test.json';
file_put_contents($argsFile, json_encode([
    'ruta_cv'  => 'C:\\xampp\\htdocs\\SIGDOC-ML\\uploads\\curriculums\\CV_1_1_1781024925.pdf',
    'perfil'   => 'medico pediatra especialista en salud infantil urgencias',
    'keywords' => 'pediatria, medicina, salud, pacientes, urgencias, hospital',
], JSON_UNESCAPED_UNICODE));

$p   = PYTHON_EXE;
$s   = str_replace('/', '\\', ML_SCRIPT);
$cmd = str_contains($p, ' ')
    ? 'cmd /c ""' . $p . '" "' . $s . '" --args-file "' . $argsFile . '""'
    : $p . ' "' . $s . '" --args-file "' . $argsFile . '"';

$pipes = [];
$proc  = proc_open($cmd, [1=>['pipe','w'],2=>['pipe','w']], $pipes);
$out = $err = '';
if (is_resource($proc)) {
    $out  = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err  = stream_get_contents($pipes[2]); fclose($pipes[2]);
    proc_close($proc);
}
@unlink($argsFile);

preg_match('/\{.+\}/s', $out, $m);
$r = isset($m[0]) ? json_decode($m[0], true) : null;

if (!$r) {
    $r = json_decode(trim($out), true);
    if (!$r) {
        $pos = strpos($out, '{');
        $r   = $pos !== false ? json_decode(substr($out, $pos), true) : null;
    }
}

if ($r) {
    echo "PUNTAJE     : " . $r['puntaje']       . "\n";
    echo "CATEGORIA   : " . $r['categoria']     . "\n";
    echo "NIVEL       : " . $r['nivel']         . "\n";
    echo "OBSERVACION : " . $r['observaciones'] . "\n\n";
    $d = $r['detalles'];
    echo "-- DETALLES POR MÓDULO --\n";
    printf("  Perfil TF-IDF    : %6.2f%%\n", $d['perfil_pct']);
    printf("  Habilidades      : %6.2f%%  (%s)\n", $d['habilidades_pct'], implode(', ', $d['habilidades_encontradas'] ?: ['ninguna']));
    printf("  Experiencia      : %6.2f%%  (%d años)\n", $d['experiencia_pct'], $d['anios_experiencia']);
    printf("  Educación        : %6.2f%%  (%s)\n", $d['educacion_pct'], $d['nivel_educativo']);
    printf("  Completitud CV   : %6.2f%%\n", $d['completitud_pct']);
    echo "  Idiomas          : " . implode(', ', $d['idiomas'] ?: ['no detectados']) . "\n";
} else {
    echo "ERROR\nstdout: $out\nstderr: $err\n";
}
