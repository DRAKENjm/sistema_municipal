<?php
/**
 * PDF — Reporte de documentos con filtros
 * URL: pdf_documentos.php?fecha_d=Y-m-d&fecha_h=Y-m-d&id_area=N
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once BASE_PATH . '/libs/SigdocPDF.php';
requireRol(ROLES_REPORTES);

$db    = getDB();
$usr   = usuarioActual();
$idRol = (int)$usr['id_rol'];

// ── Filtros ───────────────────────────────────────────────
$fechaD = $_GET['fecha_d'] ?? date('Y-m-01');
$fechaH = $_GET['fecha_h'] ?? date('Y-m-d');
$idArea = (int)($_GET['id_area'] ?? 0);

// Resp. de Área solo ve su área
if ($idRol === ROL_RESP_AREA) {
    $idArea = (int)($usr['id_area'] ?? 0);
}

// ── Consulta ──────────────────────────────────────────────
$where  = ["DATE(d.fecha_registro) BETWEEN ? AND ?"];
$params = [$fechaD, $fechaH];

if ($idArea > 0) {
    $where[]  = '(d.id_area_origen = ? OR d.id_area_destino = ?)';
    $params[] = $idArea;
    $params[] = $idArea;
}
$whereStr = implode(' AND ', $where);

$documentos = $db->prepare("
    SELECT d.numero_documento, d.asunto, d.estado, d.fecha_documento, d.fecha_registro,
           t.nombre AS tipo, t.abreviatura,
           ao.nombre AS area_origen,
           ad.nombre AS area_destino,
           CONCAT(u.nombres,' ',u.apellido_paterno) AS registrado_por
    FROM documentos d
    JOIN tipos_documento t ON t.id_tipo_documento = d.id_tipo_documento
    LEFT JOIN areas ao ON ao.id_area = d.id_area_origen
    LEFT JOIN areas ad ON ad.id_area = d.id_area_destino
    LEFT JOIN usuarios u ON u.id_usuario = d.id_usuario
    WHERE $whereStr
    ORDER BY d.fecha_registro DESC
");
$documentos->execute($params);
$documentos = $documentos->fetchAll();

// ── Estadísticas ──────────────────────────────────────────
$total = count($documentos);
$porEstado = [];
foreach ($documentos as $doc) {
    $porEstado[$doc['estado']] = ($porEstado[$doc['estado']] ?? 0) + 1;
}

// Nombre del área filtrada
$areaNombre = '';
if ($idArea > 0) {
    $aStmt = $db->prepare("SELECT nombre FROM areas WHERE id_area = ?");
    $aStmt->execute([$idArea]);
    $areaNombre = $aStmt->fetchColumn() ?: '';
}

// Helper para convertir a UTF-8 (ajusta según tu método en SigdocPDF)
$t = fn($str) => $str; // O usa: iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str) si es necesario

// ── Crear PDF ─────────────────────────────────────────────
$pdf = new SigdocPDF('L', 'mm', 'A4');
$pdf->subtitulo     = 'Reporte de Documentos Administrativos';
$pdf->nombreArchivo = 'documentos_' . date('Ymd');
$pdf->SetMargins(10, 25, 10);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

$W = $pdf->GetPageWidth() - 20;

// ── ENCABEZADO ────────────────────────────────────────────
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->SetTextColor(...SigdocPDF::C_AZUL);
$pdf->SetX(10);
$pdf->Cell($W, 7, 'Reporte de Documentos', 0, 1, 'L');

$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
$pdf->SetX(10);
$pdf->Cell(60, 5, 'Periodo: ' . date('d/m/Y', strtotime($fechaD)) . ' - ' . date('d/m/Y', strtotime($fechaH)), 0, 0, 'L');
if ($areaNombre) {
    $pdf->Cell(80, 5, 'Area: ' . $t($areaNombre), 0, 0, 'L');
}
$pdf->Cell(50, 5, 'Total: ' . $total . ' documentos', 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

// ── RESUMEN POR ESTADO ────────────────────────────────────
$pdf->seccionTitulo('Resumen por Estado');

$estados = ['REGISTRADO', 'EN_TRAMITE', 'DERIVADO', 'ARCHIVADO', 'ANULADO'];
$coloresEstado = [
    'REGISTRADO' => [209, 231, 241],
    'EN_TRAMITE' => [255, 243, 205],
    'DERIVADO'   => [209, 236, 241],
    'ARCHIVADO'  => [209, 231, 218],
    'ANULADO'    => [248, 215, 218],
];

$bw = ($W) / count($estados);
$pdf->SetX(10);
$yBase = $pdf->GetY();

foreach ($estados as $est) {
    $cnt = $porEstado[$est] ?? 0;
    $pct = $total > 0 ? round(($cnt / $total) * 100) : 0;
    $rgb = $coloresEstado[$est];

    $x = $pdf->GetX();
    $pdf->SetFillColor(...$rgb);
    $pdf->Rect($x, $yBase, $bw - 1, 18, 'F');

    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetTextColor(...SigdocPDF::C_AZUL);
    $pdf->SetXY($x, $yBase + 1);
    $pdf->Cell($bw - 1, 8, (string)$cnt, 0, 0, 'C');

    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
    $pdf->SetXY($x, $yBase + 9);
    $pdf->Cell($bw - 1, 4, str_replace('_', ' ', $est), 0, 0, 'C');
    $pdf->SetXY($x, $yBase + 13);
    $pdf->Cell($bw - 1, 4, $pct . '%', 0, 0, 'C');

    $pdf->SetXY($x + $bw, $yBase);
}
$pdf->SetTextColor(0, 0, 0);
$pdf->SetY($yBase + 21);

if (empty($documentos)) {
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->SetTextColor(...SigdocPDF::C_GRIS_TX);
    $pdf->SetX(10);
    $pdf->Cell($W, 10, 'No hay documentos en el periodo seleccionado.', 0, 1, 'C');
    $pdf->Output('I', 'documentos_' . date('Ymd') . '.pdf');
    exit;
}

// ── TABLA DE DOCUMENTOS ───────────────────────────────────
$pdf->seccionTitulo('Listado de Documentos');

$anchos = [10, 18, 18, 72, 40, 35, 24, 22];
$cabeceras = ['#', 'N. Doc.', 'Tipo', 'Asunto', 'Area Origen', 'Area Destino', 'Estado', 'Fecha'];

$pdf->filaTabla(array_map($t, $cabeceras), $anchos, true);

$estadoFillColors = [
    'REGISTRADO' => [255, 255, 255],
    'EN_TRAMITE' => [255, 251, 230],
    'DERIVADO'   => [230, 244, 255],
    'ARCHIVADO'  => [232, 245, 233],
    'ANULADO'    => [255, 235, 235],
];

foreach ($documentos as $n => $doc) {
    $fillRGB = $estadoFillColors[$doc['estado']] ?? [255, 255, 255];
    $pdf->filaTabla([
        (string)($n + 1),
        $t($doc['numero_documento'] ?? '—'),
        $t($doc['abreviatura'] ?? ''),
        $t(mb_strimwidth($doc['asunto'], 0, 48, '...')),
        $t(mb_strimwidth($doc['area_origen'] ?? '—', 0, 28, '...')),
        $t(mb_strimwidth($doc['area_destino'] ?? '—', 0, 24, '...')),
        $t(str_replace('_', ' ', $doc['estado'])),
        $doc['fecha_registro'] ? date('d/m/Y', strtotime($doc['fecha_registro'])) : '—',
    ], $anchos, false, $fillRGB);
}

$pdf->Output('I', 'documentos_' . date('Ymd') . '.pdf');
exit;
