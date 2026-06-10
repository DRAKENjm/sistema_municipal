<?php
/**
 * PDF — Constancia de Resultado para Postulante
 * Documento formal sin información técnica de ML
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
    die('Se requiere el parámetro id.');
}

$stmt = $db->prepare("
    SELECT e.*,
           CONCAT(p.apellido_paterno,' ',p.apellido_materno,', ',p.nombres) AS postulante,
           p.dni, p.correo, p.telefono,
           c.titulo AS conv_titulo, c.id_convocatoria,
           a.nombre AS area_nombre,
           cv.nombre_archivo, cv.fecha_carga,
           u.nombres AS verificador_nombre,
           u.apellido_paterno AS verificador_apellido
    FROM evaluaciones_ml e
    JOIN postulantes  p  ON p.id_postulante  = e.id_postulante
    JOIN convocatorias c ON c.id_convocatoria = e.id_convocatoria
    LEFT JOIN areas a ON a.id_area = c.id_area
    JOIN curriculums  cv ON cv.id_curriculum  = e.id_curriculum
    LEFT JOIN usuarios u ON u.id_usuario = e.verificado_por
    WHERE e.id_evaluacion = ?
");
$stmt->execute([$id]);
$ev = $stmt->fetch();

if (!$ev) {
    die('Evaluación no encontrada.');
}

// Si es consulta pública, verificar que esté verificado
if ($esConsultaPublica && !$ev['verificado']) {
    die('Esta evaluación aún no ha sido verificada por Recursos Humanos.');
}

$t = fn(string $s) => iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);

// ── Crear PDF ─────────────────────────────────────────────
$pdf = new SigdocPDF('P', 'mm', 'A4');
$pdf->subtitulo     = 'Constancia de Evaluacion de Postulacion';
$pdf->nombreArchivo = 'constancia_' . $id;
$pdf->SetMargins(20, 30, 20);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

$W = $pdf->GetPageWidth() - 40;   // ancho útil

// ══════════════════════════════════════════════════════════
// ENCABEZADO INSTITUCIONAL
// ══════════════════════════════════════════════════════════

$pdf->SetFont('Helvetica', 'B', 14);
$pdf->SetTextColor(...SigdocPDF::C_AZUL);
$pdf->Cell($W, 8, 'CONSTANCIA DE EVALUACION DE POSTULACION', 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell($W, 5, $t('Municipalidad Provincial — Área de Recursos Humanos'), 0, 1, 'C');
$pdf->Ln(8);

// ══════════════════════════════════════════════════════════
// DATOS DEL POSTULANTE
// ══════════════════════════════════════════════════════════

$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($W, 6, 'DATOS DEL POSTULANTE', 0, 1, 'L');
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(20, $pdf->GetY(), 20 + $W, $pdf->GetY());
$pdf->Ln(4);

$pdf->SetFont('Helvetica', '', 10);

// Tabla de datos
$col1_w = 40;
$col2_w = $W - $col1_w;

$datos = [
    ['Apellidos y Nombres:', $ev['postulante']],
    ['DNI:', $ev['dni']],
    ['Correo Electrónico:', $ev['correo'] ?? '—'],
    ['Teléfono:', $ev['telefono'] ?? '—'],
];

foreach ($datos as [$label, $value]) {
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell($col1_w, 5, $t($label), 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($col2_w, 5, $t($value), 0, 1, 'L');
}

$pdf->Ln(6);

// ══════════════════════════════════════════════════════════
// DATOS DE LA CONVOCATORIA
// ══════════════════════════════════════════════════════════

$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($W, 6, 'CONVOCATORIA', 0, 1, 'L');
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(20, $pdf->GetY(), 20 + $W, $pdf->GetY());
$pdf->Ln(4);

$pdf->SetFont('Helvetica', '', 10);

$convDatos = [
    ['Puesto:', $ev['conv_titulo']],
    ['Área:', $ev['area_nombre'] ?? '—'],
    ['Fecha de Postulación:', date('d/m/Y', strtotime($ev['fecha_carga']))],
];

foreach ($convDatos as [$label, $value]) {
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell($col1_w, 5, $t($label), 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->MultiCell($col2_w, 5, $t($value), 0, 'L');
}

$pdf->Ln(6);

// ══════════════════════════════════════════════════════════
// RESULTADO DE LA EVALUACIÓN
// ══════════════════════════════════════════════════════════

$resultado = $ev['resultado_verificacion'] ?? 'EN_PROCESO';
$resultadoTexto = str_replace('_', ' ', $resultado);

// Colores según resultado
$colores = [
    'ACEPTADO'   => [40, 167, 69],   // Verde
    'RECHAZADO'  => [220, 53, 69],   // Rojo
    'EN_ESPERA'  => [108, 117, 125], // Gris
    'EN_PROCESO' => [13, 110, 253],  // Azul
];
$colorResultado = $colores[$resultado] ?? [108, 117, 125];

$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($W, 6, 'RESULTADO DE LA EVALUACION', 0, 1, 'L');
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(20, $pdf->GetY(), 20 + $W, $pdf->GetY());
$pdf->Ln(4);

// Caja de resultado destacada
$pdf->SetFillColor(...$colorResultado);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Helvetica', 'B', 14);
$pdf->Cell($W, 12, $t($resultadoTexto), 0, 1, 'C', true);

$pdf->Ln(3);

// Texto según resultado
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Helvetica', '', 10);

$textos = [
    'ACEPTADO' => 'Felicitaciones, ha sido seleccionado/a para el puesto. El area de Recursos Humanos se pondra en contacto con usted a la brevedad para coordinar los siguientes pasos del proceso de contratacion.',
    'RECHAZADO' => 'Lamentamos informarle que en esta oportunidad su postulacion no ha sido seleccionada. Agradecemos su interes y le invitamos a participar en futuras convocatorias.',
    'EN_ESPERA' => 'Su postulacion se encuentra en nuestra lista de reserva. De surgir una vacante o requerirse candidatos adicionales, nos pondremos en contacto con usted.',
    'EN_PROCESO' => 'Su postulacion se encuentra en proceso de evaluacion. Le notificaremos cuando tengamos un resultado definitivo.',
];

$texto = $textos[$resultado] ?? '';
if ($texto) {
    $pdf->SetFillColor(248, 249, 250);
    $pdf->Rect(20, $pdf->GetY(), $W, 20, 'F');
    $pdf->SetXY(23, $pdf->GetY() + 3);
    $pdf->MultiCell($W - 6, 5, $t($texto), 0, 'J');
    $pdf->Ln(8);
}

// ══════════════════════════════════════════════════════════
// COMENTARIO DE RRHH (si existe)
// ══════════════════════════════════════════════════════════

if ($ev['verificado'] && $ev['comentario_verificacion']) {
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($W, 6, 'OBSERVACIONES DE RECURSOS HUMANOS', 0, 1, 'L');
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line(20, $pdf->GetY(), 20 + $W, $pdf->GetY());
    $pdf->Ln(3);
    
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetFillColor(248, 249, 250);
    $pdf->Rect(20, $pdf->GetY(), $W, 0);  // Border will be calculated
    $pdf->SetXY(23, $pdf->GetY() + 2);
    
    $startY = $pdf->GetY();
    $pdf->MultiCell($W - 6, 5, $t($ev['comentario_verificacion']), 0, 'J');
    $endY = $pdf->GetY();
    $height = $endY - $startY + 2;
    
    $pdf->Rect(20, $startY, $W, $height, 'D');
    $pdf->Ln(6);
}

// ══════════════════════════════════════════════════════════
// INFORMACIÓN DE VERIFICACIÓN
// ══════════════════════════════════════════════════════════

if ($ev['verificado']) {
    $pdf->Ln(10);
    
    $verificador = trim(($ev['verificador_nombre'] ?? '') . ' ' . ($ev['verificador_apellido'] ?? ''));
    if (empty($verificador)) {
        $verificador = 'Recursos Humanos';
    }
    
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell($W, 4, 'Verificado por: ' . $t($verificador), 0, 1, 'L');
    $pdf->Cell($W, 4, 'Fecha de verificacion: ' . date('d/m/Y H:i', strtotime($ev['fecha_verificacion'])), 0, 1, 'L');
}

// ══════════════════════════════════════════════════════════
// PIE DE PÁGINA CON INFORMACIÓN DE CONTACTO
// ══════════════════════════════════════════════════════════

$pdf->Ln(15);

$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(20, $pdf->GetY(), 20 + $W, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('Helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell($W, 4, 
    "Este documento es una constancia oficial del resultado de su proceso de postulacion. " .
    "Para consultas o aclaraciones, puede comunicarse con el Area de Recursos Humanos de la Municipalidad.\n\n" .
    "Documento generado el: " . date('d/m/Y H:i') . " — Codigo de verificacion: CV-" . str_pad($id, 6, '0', STR_PAD_LEFT),
    0, 'C'
);

$pdf->Output('I', 'constancia_postulacion_' . $id . '_' . date('Ymd') . '.pdf');
exit;
