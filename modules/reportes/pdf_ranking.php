<?php
/**
 * PDF — Ranking de candidatos por convocatoria
 * URL: pdf_ranking.php?conv=<id_convocatoria>
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once BASE_PATH . '/libs/SigdocPDF.php';
requireRol(ROLES_RRHH);

$db     = getDB();
$idConv = (int)($_GET['conv'] ?? 0);

if (!$idConv) {
    http_response_code(400);
    die('Se requiere el parámetro conv.');
}

// ── Datos de la convocatoria ──────────────────────────────
$conv = $db->prepare("
    SELECT c.*, a.nombre AS area_nombre
    FROM convocatorias c
    LEFT JOIN areas a ON a.id_area = c.id_area
    WHERE c.id_convocatoria = ?
");
$conv->execute([$idConv]);
$conv = $conv->fetch();

if (!$conv) {
    die('Convocatoria no encontrada.');
}

// ── Evaluaciones ordenadas por ranking ───────────────────
$stmt = $db->prepare("
    SELECT e.ranking, e.puntaje, e.porcentaje_coincidencia,
           e.categoria, e.nivel, e.revisado_rrhh,
           e.detalles_json, e.fecha_evaluacion,
           CONCAT(p.apellido_paterno,' ',p.apellido_materno,', ',p.nombres) AS postulante,
           p.dni, p.correo, p.telefono
    FROM evaluaciones_ml e
    JOIN postulantes p ON p.id_postulante = e.id_postulante
    WHERE e.id_convocatoria = ?
    ORDER BY e.ranking ASC
");
$stmt->execute([$idConv]);
$evaluaciones = $stmt->fetchAll();

if (empty($evaluaciones)) {
    die('No hay evaluaciones para esta convocatoria.');
}

// ── Estadísticas resumidas ────────────────────────────────
$total     = count($evaluaciones);
$aprobados = count(array_filter($evaluaciones, fn($e) => (float)$e['puntaje'] >= 50));
$promedio  = round(array_sum(array_column($evaluaciones, 'puntaje')) / max(1, $total), 1);
$maximo    = max(array_column($evaluaciones, 'puntaje'));

// ── Generar PDF ───────────────────────────────────────────
$pdf = new SigdocPDF('L', 'mm', 'A4');  // Landscape para tabla ancha
$pdf->subtitulo     = 'Reporte de Ranking — Seleccion de Personal';
$pdf->nombreArchivo = 'ranking_conv_' . $idConv;
$pdf->SetMargins(10, 25, 10);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

$W = $pdf->GetPageWidth();
$t = fn(string $s) => SigdocPDF::txt($s);

// ── ENCABEZADO DEL REPORTE ────────────────────────────────
$pdf->SetFont('Helvetica', 'B', 14);
$pdf->SetTextColor(...SigdocPDF::C_AZUL);
$pdf->SetX(10);
$pdf->MultiCell($W - 20, 7, $t($conv['titulo']), 0, 'L');

$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
$pdf->SetX(10);
$pdf->Cell(60, 5, 'Area: ' . $t($conv['area_nombre'] ?? 'Sin area'), 0, 0, 'L');
if ($conv['fecha_fin']) {
    $pdf->Cell(50, 5, 'Cierre: ' . date('d/m/Y', strtotime($conv['fecha_fin'])), 0, 0, 'L');
}
if ($conv['salario_referencial']) {
    $pdf->Cell(50, 5, 'Salario ref.: S/ ' . number_format($conv['salario_referencial'], 2), 0, 0, 'L');
}
$pdf->Ln(6);

// ── RESUMEN ESTADÍSTICO ───────────────────────────────────
$pdf->SetFillColor(...SigdocPDF::C_GRIS_BG);
$pdf->SetX(10);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetTextColor(...SigdocPDF::C_AZUL);

$bloques = [
    ['Total evaluados', $total],
    ['Aprobados (>=50)', $aprobados],
    ['No aprobados', $total - $aprobados],
    ['Puntaje promedio', $promedio . ' pts'],
    ['Puntaje maximo', $maximo . ' pts'],
];
$bw = ($W - 20) / count($bloques);
foreach ($bloques as [$lbl, $val]) {
    $pdf->SetX($pdf->GetX() === 10 ? 10 : $pdf->GetX());
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $pdf->SetFillColor(...SigdocPDF::C_GRIS_BG);
    $pdf->Rect($x, $y, $bw - 1, 14, 'F');
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(...SigdocPDF::C_AZUL);
    $pdf->SetXY($x, $y + 1);
    $pdf->Cell($bw - 1, 6, (string)$val, 0, 0, 'C');
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
    $pdf->SetXY($x, $y + 7);
    $pdf->Cell($bw - 1, 5, $lbl, 0, 0, 'C');
    $pdf->SetXY($x + $bw, $y);
}
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(18);

// ── TABLA DE RANKING ──────────────────────────────────────
$pdf->seccionTitulo('Ranking de Candidatos');

// Anchos columnas landscape A4 (~277mm usables)
$anchos = [12, 62, 22, 60, 20, 28, 22, 20, 20];
$cabeceras = ['Rank','Candidato','DNI','Coincidencia con perfil','Puntaje','Categoria','Nivel','F. Evaluacion','Revisado'];

$pdf->filaTabla(array_map($t, $cabeceras), $anchos, true);

foreach ($evaluaciones as $ev) {
    // Color de fila por categoría
    $cat = $ev['categoria'] ?? '';
    $fillRGB = match(true) {
        (float)$ev['puntaje'] >= 90 => [212, 237, 218],   // verde claro
        (float)$ev['puntaje'] >= 75 => [209, 231, 221],   // verde medio
        (float)$ev['puntaje'] >= 60 => [255, 243, 205],   // amarillo
        (float)$ev['puntaje'] >= 50 => [255, 236, 153],   // amarillo medio
        default                     => [248, 215, 218],   // rojo claro
    };

    $posY = $pdf->GetY();

    // Dibujar fila (sin la barra, que va encima)
    $pdf->filaTabla([
        '#' . $ev['ranking'],
        $t(mb_strimwidth($ev['postulante'], 0, 38, '...')),
        $t($ev['dni']),
        '',    // la barra se dibuja abajo
        $ev['puntaje'] . ' pts',
        $t($cat),
        $t($ev['nivel'] ?? ''),
        date('d/m/Y', strtotime($ev['fecha_evaluacion'])),
        $ev['revisado_rrhh'] ? 'Si' : 'Pendiente',
    ], $anchos, false, $fillRGB);

    // Dibujar barra de coincidencia sobre la celda vacía
    $barX = 10 + array_sum(array_slice($anchos, 0, 3));
    $pdf->barraScore((float)$ev['porcentaje_coincidencia'], $barX + 1, $posY + 1.2, 50, 3.5);
}

// ── SECCIÓN: CANDIDATOS APROBADOS ────────────────────────
$aprobadosList = array_filter($evaluaciones, fn($e) => (float)$e['puntaje'] >= 50);
if (!empty($aprobadosList)) {
    $pdf->Ln(4);
    $pdf->seccionTitulo('Candidatos Aprobados para Siguiente Etapa');

    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
    $pdf->SetX(10);
    $pdf->Cell(0, 5, 'Los siguientes candidatos superaron el umbral de aprobacion (>= 50 puntos):', 0, 1, 'L');
    $pdf->Ln(1);

    foreach ($aprobadosList as $idx => $ev) {
        $pdf->SetX(12);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(...SigdocPDF::C_AZUL);

        $medal = match($idx) {
            0 => '1er',
            1 => '2do',
            2 => '3er',
            default => ($idx + 1) . 'to'
        };

        $pdf->Cell(12, 6, $medal, 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(80, 6, $t($ev['postulante']), 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
        $pdf->Cell(25, 6, 'DNI: ' . $ev['dni'], 0, 0, 'L');
        $pdf->Cell(20, 6, $ev['puntaje'] . ' pts', 0, 0, 'C');

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->badgeCategoria($ev['categoria'] ?? 'APROBADO', $x, $y + 1);
        $pdf->Ln(7);
    }
}

// ── Output ────────────────────────────────────────────────
$pdf->Output('I', 'ranking_conv_' . $idConv . '_' . date('Ymd') . '.pdf');
exit;
