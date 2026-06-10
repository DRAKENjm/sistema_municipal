<?php
/**
 * PDF — Ficha individual de evaluación ML
 * URL: pdf_ficha.php?id=<id_evaluacion>
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once BASE_PATH . '/libs/SigdocPDF.php';

// Permitir acceso público para consultas de postulantes
$esConsultaPublica = isset($_GET['public']) && $_GET['public'] === '1';

if (!$esConsultaPublica) {
    requireRol(ROLES_RRHH);
}

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    die('Se requiere el parametro id.');
}

$stmt = $db->prepare("
    SELECT e.*,
           CONCAT(p.apellido_paterno,' ',p.apellido_materno,', ',p.nombres) AS postulante,
           p.dni, p.correo, p.telefono, p.fecha_nacimiento,
           c.titulo AS conv_titulo, c.id_convocatoria,
           c.palabras_clave,
           cv.nombre_archivo
    FROM evaluaciones_ml e
    JOIN postulantes  p  ON p.id_postulante  = e.id_postulante
    JOIN convocatorias c ON c.id_convocatoria = e.id_convocatoria
    JOIN curriculums  cv ON cv.id_curriculum  = e.id_curriculum
    WHERE e.id_evaluacion = ?
");
$stmt->execute([$id]);
$ev = $stmt->fetch();

if (!$ev) {
    die('Evaluacion no encontrada.');
}

// Si es consulta pública, verificar que esté verificado
if ($esConsultaPublica && !$ev['verificado']) {
    die('Esta evaluacion aun no ha sido verificada.');
}

$detalles = json_decode($ev['detalles_json'] ?? '{}', true) ?: [];
$t = fn(string $s) => iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);

// ── Crear PDF ─────────────────────────────────────────────
$pdf = new SigdocPDF('P', 'mm', 'A4');
$pdf->subtitulo     = 'Ficha de Evaluacion ML — Seleccion de Personal';
$pdf->nombreArchivo = 'ficha_eval_' . $id;
$pdf->SetMargins(15, 25, 15);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

$W = $pdf->GetPageWidth() - 30;   // ancho útil

// Sello VERIFICADO si aplica
if ($ev['verificado']) {
    $resultado = $ev['resultado_verificacion'] ?? 'VERIFICADO';
    $colores = [
        'ACEPTADO'   => SigdocPDF::C_VERDE,
        'RECHAZADO'  => SigdocPDF::C_ROJO,
        'EN_ESPERA'  => SigdocPDF::C_GRIS_TX,
    ];
    $color = $colores[$resultado] ?? SigdocPDF::C_AZUL;
    
    // Sello en la esquina superior derecha
    $cx = $pdf->GetPageWidth() - 65;
    $cy = 30;
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetTextColor(...$color);
    $pdf->SetDrawColor(...$color);
    $pdf->SetLineWidth(0.8);
    $pdf->Rect($cx - 2, $cy - 2, 54, 10, 'D');
    $pdf->SetXY($cx - 2, $cy + 0.5);
    $pdf->Cell(54, 6, $t('VERIFICADO: ' . str_replace('_', ' ', $resultado)), 0, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);
}

// ── DATOS DEL CANDIDATO ───────────────────────────────────
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->SetTextColor(...SigdocPDF::C_AZUL);
$pdf->SetX(15);
$pdf->Cell($W, 7, $t($ev['postulante']), 0, 1, 'L');

$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
$pdf->SetX(15);
$pdf->Cell(40, 5, 'DNI: ' . $ev['dni'], 0, 0, 'L');
if ($ev['correo']) {
    $pdf->Cell(70, 5, 'Correo: ' . $t($ev['correo']), 0, 0, 'L');
}
if ($ev['telefono']) {
    $pdf->Cell(40, 5, 'Tel: ' . $ev['telefono'], 0, 0, 'L');
}
$pdf->Ln(5);

$pdf->SetFont('Helvetica', 'I', 8.5);
$pdf->SetX(15);
$pdf->Cell($W, 5, 'Convocatoria: ' . $t($ev['conv_titulo']), 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

// ── RESULTADO GENERAL ─────────────────────────────────────
$pdf->seccionTitulo('Resultado General');

$pdf->SetFillColor(...SigdocPDF::C_GRIS_BG);
$pdf->SetX(15);
$pdf->Rect(15, $pdf->GetY(), $W, 22, 'F');

$y0 = $pdf->GetY() + 2;

// Puntaje grande
$pdf->SetFont('Helvetica', 'B', 26);
$pdf->SetTextColor(...SigdocPDF::C_AZUL);
$pdf->SetXY(18, $y0);
$pdf->Cell(30, 12, number_format((float)$ev['puntaje'], 1), 0, 0, 'C');

$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
$pdf->SetXY(18, $y0 + 12);
$pdf->Cell(30, 5, '/100 pts', 0, 0, 'C');

// Separador
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(50, $y0, 50, $y0 + 18);

// Categoría y nivel
$cat   = $ev['categoria'] ?? 'N/D';
$nivel = $ev['nivel']     ?? 'N/D';
$coloresTexto = [
    'EXCELENTE'      => SigdocPDF::C_VERDE,
    'APROBADO'       => SigdocPDF::C_AZUL,
    'A CONSIDERAR'   => SigdocPDF::C_AMBAR,
    'NO RECOMENDADO' => SigdocPDF::C_ROJO,
];
$rgb = $coloresTexto[$cat] ?? SigdocPDF::C_GRIS_TX;

$pdf->SetFont('Helvetica', 'B', 14);
$pdf->SetTextColor(...$rgb);
$pdf->SetXY(53, $y0 + 2);
$pdf->Cell(55, 8, $t($cat), 0, 0, 'L');

$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
$pdf->SetXY(53, $y0 + 11);
$pdf->Cell(55, 5, 'Nivel: ' . $t($nivel), 0, 0, 'L');

// Ranking
$pdf->SetFont('Helvetica', 'B', 18);
$pdf->SetTextColor(...SigdocPDF::C_AZUL);
$pdf->SetXY(115, $y0);
$pdf->Cell(25, 10, '#' . ($ev['ranking'] ?? '?'), 0, 0, 'C');

$pdf->SetFont('Helvetica', '', 7.5);
$pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
$pdf->SetXY(115, $y0 + 10);
$pdf->Cell(25, 5, 'Posicion', 0, 0, 'C');

// Fecha
$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
$pdf->SetXY(143, $y0 + 2);
$pdf->Cell(40, 5, 'Evaluado: ' . date('d/m/Y H:i', strtotime($ev['fecha_evaluacion'])), 0, 1, 'L');
$pdf->SetXY(143, $y0 + 8);
$pdf->Cell(40, 5, 'Modelo: ' . ($ev['modelo_version'] ?? 'v1'), 0, 0, 'L');

$pdf->SetTextColor(0, 0, 0);
$pdf->SetY($y0 + 24);

// ── ANÁLISIS POR MÓDULO ───────────────────────────────────
$pdf->seccionTitulo('Analisis por Modulo (Ponderado)');

$modulos = [
    ['Similitud con el perfil requerido (30%)',
     (float)($detalles['perfil_pct']       ?? 0),
     ''],
    ['Habilidades tecnicas (25%)',
     (float)($detalles['habilidades_pct']  ?? 0),
     count($detalles['habilidades_encontradas'] ?? []) . '/' . ($detalles['habilidades_total'] ?? 0) . ' encontradas'],
    ['Experiencia laboral (20%)',
     (float)($detalles['experiencia_pct']  ?? 0),
     ($detalles['anios_experiencia'] ?? 0) . ' anios detectados'],
    ['Nivel educativo (15%)',
     (float)($detalles['educacion_pct']    ?? 0),
     $t($detalles['nivel_educativo'] ?? 'no detectado')],
    ['Completitud del CV (10%)',
     (float)($detalles['completitud_pct']  ?? 0),
     ''],
];

$pdf->Ln(1);
foreach ($modulos as [$lbl, $pct, $det]) {
    $pdf->moduloScore($lbl, $pct, $det);
}

// ── DETALLES ──────────────────────────────────────────────
$pdf->seccionTitulo('Detalles del Analisis');
$pdf->SetFont('Helvetica', '', 8.5);

// Habilidades encontradas
$habs = $detalles['habilidades_encontradas'] ?? [];
if (!empty($habs)) {
    $pdf->SetX(15);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell(45, 5, 'Habilidades detectadas:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->Cell(0, 5, $t(implode('  ·  ', $habs)), 0, 1, 'L');
}

// Idiomas
$idiomas = $detalles['idiomas'] ?? [];
$pdf->SetX(15);
$pdf->SetFont('Helvetica', 'B', 8.5);
$pdf->Cell(45, 5, 'Idiomas detectados:', 0, 0, 'L');
$pdf->SetFont('Helvetica', '', 8.5);
$pdf->Cell(0, 5, $t(empty($idiomas) ? 'Ninguno detectado' : implode(', ', $idiomas)), 0, 1, 'L');

// Secciones del CV
$secciones = $detalles['secciones'] ?? [];
if (!empty($secciones)) {
    $pdf->SetX(15);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell(0, 5, 'Secciones del CV:', 0, 1, 'L');

    $pdf->SetX(15);
    $labels = [
        'experiencia'     => 'Experiencia laboral',
        'educacion'       => 'Formacion academica',
        'habilidades'     => 'Habilidades/Skills',
        'datos_personales'=> 'Datos personales',
        'logros'          => 'Logros/Certificados',
        'referencias'     => 'Referencias',
        'idiomas'         => 'Idiomas',
    ];
    $col = 0;
    foreach ($secciones as $key => $presente) {
        if ($col === 0) $pdf->SetX(18);
        $icon = $presente ? '[OK]' : '[ X ]';
        $color = $presente ? SigdocPDF::C_VERDE : SigdocPDF::C_ROJO;
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(...$color);
        $pdf->Cell(12, 5, $icon, 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(42, 5, $t($labels[$key] ?? $key), 0, 0, 'L');
        $col++;
        if ($col === 3) { $pdf->Ln(); $col = 0; }
    }
    if ($col > 0) $pdf->Ln();
}

// ── OBSERVACIONES ─────────────────────────────────────────
if ($ev['observaciones']) {
    $pdf->seccionTitulo('Observaciones del Sistema');
    $pdf->cajaTexto($t($ev['observaciones']), SigdocPDF::C_GRIS_BG);
}

// ── VERIFICACIÓN RRHH ─────────────────────────────────────
if ($ev['verificado']) {
    $pdf->seccionTitulo('Verificacion de Recursos Humanos');
    
    $resultado = $ev['resultado_verificacion'] ?? 'EN_ESPERA';
    $resultadoTexto = str_replace('_', ' ', $resultado);
    $colores = [
        'ACEPTADO'   => SigdocPDF::C_VERDE,
        'RECHAZADO'  => SigdocPDF::C_ROJO,
        'EN_ESPERA'  => SigdocPDF::C_AMBAR,
    ];
    $colorResultado = $colores[$resultado] ?? SigdocPDF::C_GRIS_TX;
    
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(...$colorResultado);
    $pdf->SetX(15);
    $pdf->Cell($W, 6, 'Resultado: ' . $t($resultadoTexto), 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetX(15);
    $pdf->Cell(45, 5, 'Verificado por:', 0, 0, 'L');
    $pdf->Cell(65, 5, $t($ev['verificado_por'] ?? '—'), 0, 0, 'L');
    $pdf->Cell(30, 5, 'Fecha:', 0, 0, 'L');
    $pdf->Cell(0, 5, $ev['fecha_verificacion'] ? date('d/m/Y H:i', strtotime($ev['fecha_verificacion'])) : '—', 0, 1, 'L');
    
    if ($ev['comentario_verificacion']) {
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetX(15);
        $pdf->Cell(0, 5, 'Comentario:', 0, 1, 'L');
        
        $bgColor = $resultado === 'ACEPTADO' ? [232, 245, 233] : 
                   ($resultado === 'RECHAZADO' ? [255, 235, 235] : [248, 249, 250]);
        $pdf->cajaTexto($t($ev['comentario_verificacion']), $bgColor);
    }
}

// ── ADVERTENCIA FINAL ─────────────────────────────────────
$pdf->Ln(3);
$pdf->SetFont('Helvetica', 'I', 7.5);
$pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
$pdf->SetX(15);
$pdf->MultiCell($W, 4.5,
    'AVISO: Este reporte es una herramienta de apoyo para el proceso de seleccion. ' .
    'La decision final de contratacion corresponde exclusivamente al area de Recursos Humanos ' .
    'de la Municipalidad.',
    0, 'L');

$pdf->Output('I', 'ficha_eval_' . $id . '_' . date('Ymd') . '.pdf');
exit;
