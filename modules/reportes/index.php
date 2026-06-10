<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../libs/SimpleExcel.php';
requireRol(ROLES_REPORTES);

$db    = getDB();
$usr   = usuarioActual();
$idRol = (int)$usr['id_rol'];
$pageTitle = 'Reportes y Estadísticas';

// ── Filtros de fecha ──────────────────────────────────────
$fechaD = $_GET['fecha_d'] ?? date('Y-m-01');          // primer día del mes actual
$fechaH = $_GET['fecha_h'] ?? date('Y-m-d');           // hoy
$idArea = (int)($_GET['id_area'] ?? 0);

// Resp. de Área ve solo su área
if ($idRol === ROL_RESP_AREA) {
    $idArea = (int)($usr['id_area'] ?? 0);
}

// ── Exportar EXCEL de ranking ML ─────────────────────────
if (isset($_GET['exportar_ranking']) && in_array($idRol, [ROL_ADMIN, ROL_RRHH, ROL_JEFE_GENERAL])) {
    $idConv = (int)($_GET['exportar_ranking']);
    $stmt = $db->prepare("
        SELECT e.ranking, CONCAT(p.apellido_paterno,' ',p.apellido_materno,', ',p.nombres) AS postulante,
               p.dni, p.correo, p.telefono,
               e.puntaje, e.porcentaje_coincidencia, e.observaciones,
               e.revisado_rrhh, e.fecha_evaluacion, c.titulo AS convocatoria
        FROM evaluaciones_ml e
        JOIN postulantes p ON p.id_postulante = e.id_postulante
        JOIN convocatorias c ON c.id_convocatoria = e.id_convocatoria
        WHERE e.id_convocatoria = ?
        ORDER BY e.ranking ASC
    ");
    $stmt->execute([$idConv]);
    $filas = $stmt->fetchAll();

    $excel = new SimpleExcel('ranking_convocatoria_' . $idConv . '_' . date('Ymd') . '.xls');
    $excel->setHeaders([
        'Ranking', 'Postulante', 'DNI', 'Correo', 'Teléfono', 
        'Puntaje', '% Coincidencia', 'Observaciones', 
        'Revisado RRHH', 'Fecha Evaluación', 'Convocatoria'
    ]);
    
    foreach ($filas as $f) {
        $excel->addRow([
            $f['ranking'],
            $f['postulante'],
            $f['dni'],
            $f['correo'],
            $f['telefono'] ?? '',
            $f['puntaje'],
            $f['porcentaje_coincidencia'],
            $f['observaciones'] ?? '',
            $f['revisado_rrhh'] ? 'Sí' : 'No',
            $f['fecha_evaluacion'],
            $f['convocatoria']
        ]);
    }
    
    $excel->download();
}

// ── Exportar EXCEL de documentos ─────────────────────────
if (isset($_GET['exportar_docs'])) {
    $whereExp  = "WHERE DATE(d.fecha_registro) BETWEEN ? AND ?";
    $paramsExp = [$fechaD, $fechaH];
    if ($idArea > 0) { 
        $whereExp .= " AND (d.id_area_origen=? OR d.id_area_destino=?)"; 
        $paramsExp[] = $idArea; 
        $paramsExp[] = $idArea; 
    }

    $stmtExp = $db->prepare("
        SELECT d.numero_documento, t.nombre AS tipo, d.asunto, d.estado,
               ao.nombre AS area_origen, ad.nombre AS area_destino,
               d.fecha_documento, d.fecha_registro,
               CONCAT(u.nombres,' ',u.apellido_paterno) AS registrado_por
        FROM documentos d
        JOIN tipos_documento t ON t.id_tipo_documento = d.id_tipo_documento
        LEFT JOIN areas ao ON ao.id_area = d.id_area_origen
        LEFT JOIN areas ad ON ad.id_area = d.id_area_destino
        LEFT JOIN usuarios u ON u.id_usuario = d.id_usuario
        $whereExp ORDER BY d.fecha_registro DESC
    ");
    $stmtExp->execute($paramsExp);
    $filas = $stmtExp->fetchAll();

    $excel = new SimpleExcel('documentos_' . date('Ymd') . '.xls');
    $excel->setHeaders([
        'N° Documento', 'Tipo', 'Asunto', 'Estado', 
        'Área Origen', 'Área Destino', 
        'Fecha Documento', 'Fecha Registro', 'Registrado por'
    ]);
    
    foreach ($filas as $f) {
        $excel->addRow([
            $f['numero_documento'],
            $f['tipo'],
            $f['asunto'],
            $f['estado'],
            $f['area_origen'] ?? '',
            $f['area_destino'] ?? '',
            $f['fecha_documento'],
            $f['fecha_registro'],
            $f['registrado_por'] ?? ''
        ]);
    }
    
    $excel->download();
}

// ── Construir condición de área y fechas ──────────────────
$whereDoc    = "DATE(d.fecha_registro) BETWEEN '$fechaD' AND '$fechaH'";
$whereDocArea = $idArea > 0 ? " AND (d.id_area_origen=$idArea OR d.id_area_destino=$idArea)" : '';

// ── ESTADÍSTICAS DOCUMENTALES ─────────────────────────────
$totalDocs = $db->query("SELECT COUNT(*) FROM documentos d WHERE $whereDoc $whereDocArea")->fetchColumn();
$totalExps = $db->query("SELECT COUNT(*) FROM expedientes WHERE DATE(fecha_creacion) BETWEEN '$fechaD' AND '$fechaH'" .
                         ($idArea > 0 ? " AND id_area=$idArea" : ""))->fetchColumn();

$docsPorEstado = $db->query("
    SELECT estado, COUNT(*) AS total
    FROM documentos d WHERE $whereDoc $whereDocArea
    GROUP BY estado ORDER BY total DESC
")->fetchAll();

$docsPorTipo = $db->query("
    SELECT t.nombre, t.abreviatura, COUNT(*) AS total
    FROM documentos d
    JOIN tipos_documento t ON t.id_tipo_documento = d.id_tipo_documento
    WHERE $whereDoc $whereDocArea
    GROUP BY t.id_tipo_documento ORDER BY total DESC LIMIT 8
")->fetchAll();

$docsPorArea = $db->query("
    SELECT a.nombre AS area, COUNT(*) AS total
    FROM documentos d
    JOIN areas a ON a.id_area = d.id_area_origen
    WHERE $whereDoc
    GROUP BY a.id_area ORDER BY total DESC LIMIT 8
")->fetchAll();

// ── ESTADÍSTICAS ML (Admin, RRHH y Jefe General) ─────────
$statsML = [];
$convocatorias = [];
if (in_array($idRol, [ROL_ADMIN, ROL_RRHH, ROL_JEFE_GENERAL])) {
    $statsML['postulantes']  = $db->query("SELECT COUNT(*) FROM postulantes")->fetchColumn();
    $statsML['convocatorias']= $db->query("SELECT COUNT(*) FROM convocatorias")->fetchColumn();
    $statsML['evaluaciones'] = $db->query("SELECT COUNT(*) FROM evaluaciones_ml")->fetchColumn();
    $statsML['pendientes']   = $db->query("SELECT COUNT(*) FROM curriculums WHERE procesado=0")->fetchColumn();
    $statsML['sin_revisar']  = $db->query("SELECT COUNT(*) FROM evaluaciones_ml WHERE revisado_rrhh=0")->fetchColumn();

    $convocatorias = $db->query("
        SELECT c.id_convocatoria, c.titulo, c.estado, c.fecha_fin,
               a.nombre AS area_nombre,
               COUNT(DISTINCT cv.id_curriculum) AS total_cvs,
               COUNT(DISTINCT e.id_evaluacion)  AS total_evaluados,
               ROUND(AVG(e.puntaje),1)           AS puntaje_promedio,
               MAX(e.puntaje)                    AS puntaje_maximo
        FROM convocatorias c
        LEFT JOIN areas a        ON a.id_area         = c.id_area
        LEFT JOIN curriculums cv ON cv.id_convocatoria = c.id_convocatoria
        LEFT JOIN evaluaciones_ml e ON e.id_convocatoria = c.id_convocatoria
        GROUP BY c.id_convocatoria
        ORDER BY c.fecha_registro DESC
    ")->fetchAll();
}

// ── Áreas para filtro ─────────────────────────────────────
$areas = $db->query("SELECT id_area, nombre FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="page-title mb-0">
        <i class="bi bi-bar-chart-line me-2"></i>Reportes y Estadísticas
    </h4>
    <div class="d-flex gap-2">
        <a href="?<?= http_build_query(array_merge($_GET, ['exportar_docs' => 1])) ?>"
           class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Exportar a Excel
        </a>
        <button class="btn btn-outline-secondary btn-sm" onclick="imprimirSeccion('reporte-completo')">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
    </div>
</div>

<!-- ── FILTROS ── -->
<div class="form-section mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-3">
            <label class="form-label small fw-semibold">Desde</label>
            <input type="date" name="fecha_d" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($fechaD) ?>">
        </div>
        <div class="col-sm-3">
            <label class="form-label small fw-semibold">Hasta</label>
            <input type="date" name="fecha_h" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($fechaH) ?>">
        </div>
        <?php if ($idRol !== ROL_RESP_AREA): ?>
        <div class="col-sm-4">
            <label class="form-label small fw-semibold">Área</label>
            <select name="id_area" class="form-select form-select-sm">
                <option value="">Todas las áreas</option>
                <?php foreach ($areas as $a): ?>
                <option value="<?= $a['id_area'] ?>" <?= $idArea === (int)$a['id_area'] ? 'selected':'' ?>>
                    <?= htmlspecialchars($a['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-sm-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                <i class="bi bi-funnel"></i> Filtrar
            </button>
            <a href="?" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x"></i>
            </a>
        </div>
    </form>
</div>

<div id="reporte-completo">

<!-- ══ SECCIÓN DOCUMENTAL ══ -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card blue">
            <i class="bi bi-file-earmark-text stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($totalDocs) ?></div>
                <div class="stat-label">Documentos en el período</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card orange">
            <i class="bi bi-folder2-open stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($totalExps) ?></div>
                <div class="stat-label">Expedientes en el período</div>
            </div>
        </div>
    </div>
    <?php if (!empty($statsML)): ?>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card green">
            <i class="bi bi-people stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($statsML['postulantes']) ?></div>
                <div class="stat-label">Postulantes registrados</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card purple">
            <i class="bi bi-robot stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($statsML['evaluaciones']) ?></div>
                <div class="stat-label">CVs evaluados con ML</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <!-- Documentos por estado -->
    <div class="col-md-4">
        <div class="table-card p-3 h-100">
            <h6 class="fw-semibold text-primary-custom mb-3">
                <i class="bi bi-pie-chart me-1"></i> Documentos por estado
            </h6>
            <?php if (empty($docsPorEstado)): ?>
            <p class="text-muted small">Sin datos en el período.</p>
            <?php else: ?>
            <?php foreach ($docsPorEstado as $row):
                $pct = $totalDocs > 0 ? round(($row['total'] / $totalDocs) * 100, 1) : 0;
            ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between mb-1">
                    <span class="badge-estado badge-<?= strtolower($row['estado']) ?>"><?= $row['estado'] ?></span>
                    <span class="fw-semibold small"><?= $row['total'] ?> (<?= $pct ?>%)</span>
                </div>
                <div class="ranking-bar">
                    <div class="ranking-bar-fill bg-primary" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Documentos por tipo -->
    <div class="col-md-4">
        <div class="table-card p-3 h-100">
            <h6 class="fw-semibold text-primary-custom mb-3">
                <i class="bi bi-bar-chart me-1"></i> Por tipo de documento
            </h6>
            <?php if (empty($docsPorTipo)): ?>
            <p class="text-muted small">Sin datos en el período.</p>
            <?php else: ?>
            <?php $maxTipo = max(array_column($docsPorTipo, 'total') ?: [1]); ?>
            <?php foreach ($docsPorTipo as $row):
                $pct = round(($row['total'] / $maxTipo) * 100);
            ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between mb-1">
                    <span class="small">
                        <span class="badge bg-secondary me-1"><?= htmlspecialchars($row['abreviatura'] ?? '') ?></span>
                        <?= htmlspecialchars($row['nombre']) ?>
                    </span>
                    <span class="fw-semibold small"><?= $row['total'] ?></span>
                </div>
                <div class="ranking-bar">
                    <div class="ranking-bar-fill bg-info" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Documentos por área origen -->
    <div class="col-md-4">
        <div class="table-card p-3 h-100">
            <h6 class="fw-semibold text-primary-custom mb-3">
                <i class="bi bi-diagram-3 me-1"></i> Documentos emitidos por área
            </h6>
            <?php if (empty($docsPorArea)): ?>
            <p class="text-muted small">Sin datos en el período.</p>
            <?php else: ?>
            <?php $maxArea = max(array_column($docsPorArea, 'total') ?: [1]); ?>
            <?php foreach ($docsPorArea as $row):
                $pct = round(($row['total'] / $maxArea) * 100);
            ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between mb-1">
                    <span class="small"><?= htmlspecialchars(mb_strimwidth($row['area'], 0, 30, '…')) ?></span>
                    <span class="fw-semibold small"><?= $row['total'] ?></span>
                </div>
                <div class="ranking-bar">
                    <div class="ranking-bar-fill bg-warning" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══ SECCIÓN ML (solo Admin y RRHH) ══ -->
<?php if (!empty($convocatorias)): ?>
<h5 class="fw-bold mb-3" style="color:var(--primary)">
    <i class="bi bi-robot me-2"></i>Reclutamiento — Ranking por convocatoria
</h5>

<?php foreach ($convocatorias as $conv): ?>
<?php
    // Obtener ranking completo de esta convocatoria
    $ranking = $db->prepare("
        SELECT e.ranking, e.puntaje, e.porcentaje_coincidencia, e.revisado_rrhh, e.id_evaluacion,
               CONCAT(p.apellido_paterno,' ',p.apellido_materno,', ',p.nombres) AS postulante,
               p.dni, p.correo
        FROM evaluaciones_ml e
        JOIN postulantes p ON p.id_postulante = e.id_postulante
        WHERE e.id_convocatoria = ?
        ORDER BY e.ranking ASC
        LIMIT 10
    ");
    $ranking->execute([$conv['id_convocatoria']]);
    $ranking = $ranking->fetchAll();
    if (empty($ranking)) continue;
?>
<div class="table-card mb-4">
    <div class="table-card-header flex-wrap gap-2">
        <div>
            <h5 class="mb-0">
                <?= htmlspecialchars($conv['titulo']) ?>
                <span class="badge-estado badge-<?= strtolower($conv['estado']) ?> ms-1"><?= $conv['estado'] ?></span>
            </h5>
            <div class="text-muted small mt-1">
                <?= htmlspecialchars($conv['area_nombre'] ?? '—') ?>
                <?php if ($conv['fecha_fin']): ?>
                · Cierre: <?= date('d/m/Y', strtotime($conv['fecha_fin'])) ?>
                <?php endif; ?>
                · <?= $conv['total_cvs'] ?> CVs · Promedio: <strong><?= $conv['puntaje_promedio'] ?? '—' ?></strong>
                · Máx: <strong class="text-success"><?= $conv['puntaje_maximo'] ?? '—' ?></strong>
            </div>
        </div>
        <a href="?<?= http_build_query(array_merge($_GET, ['exportar_ranking' => $conv['id_convocatoria']])) ?>"
           class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Exportar ranking
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead>
                <tr>
                    <th style="width:50px">Rank</th>
                    <th>Postulante</th>
                    <th>DNI</th>
                    <th>Coincidencia</th>
                    <th>Puntaje</th>
                    <th>Revisado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ranking as $i => $r): ?>
                <tr class="<?= $i === 0 ? 'table-success' : ($i === 1 ? 'table-light' : '') ?>">
                    <td class="text-center fw-bold">
                        <?php if ($i === 0): ?>
                        <i class="bi bi-trophy-fill text-warning fs-5"></i>
                        <?php elseif ($i === 1): ?>
                        <i class="bi bi-trophy text-secondary fs-5"></i>
                        <?php elseif ($i === 2): ?>
                        <i class="bi bi-trophy text-danger fs-5"></i>
                        <?php else: ?>
                        <span class="text-muted"><?= $r['ranking'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($r['postulante']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($r['correo'] ?? '') ?></div>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars($r['dni']) ?></td>
                    <td style="min-width:130px">
                        <?php 
                        $coincidenciaValor = isset($r['porcentaje_coincidencia']) ? floatval($r['porcentaje_coincidencia']) : 0;
                        ?>
                        <div class="d-flex align-items-center gap-2">
                            <div class="ranking-bar flex-fill">
                                <div class="ranking-bar-fill <?= $coincidenciaValor >= 70 ? 'score-high' : ($coincidenciaValor >= 40 ? 'score-medium' : 'score-low') ?>"
                                     style="width:<?= $coincidenciaValor ?>%"></div>
                            </div>
                            <small class="fw-semibold"><?= number_format($coincidenciaValor, 1) ?>%</small>
                        </div>
                    </td>
                    <td>
                        <span class="fw-bold fs-5 <?= $r['puntaje'] >= 70 ? 'text-success' : ($r['puntaje'] >= 40 ? 'text-warning' : 'text-danger') ?>">
                            <?= $r['puntaje'] ?>
                        </span>
                        <span class="text-muted small">/100</span>
                    </td>
                    <td>
                        <?= $r['revisado_rrhh']
                            ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Revisado</span>'
                            : '<span class="badge bg-warning text-dark">Pendiente</span>' ?>
                    </td>
                    <td>
                        <button class="btn btn-action bg-info text-white" 
                                title="Ver detalle ML"
                                data-bs-toggle="modal"
                                data-bs-target="#modalDetalleEval"
                                data-id="<?= $r['id_evaluacion'] ?>"
                                data-postulante="<?= htmlspecialchars($r['postulante']) ?>"
                                data-dni="<?= htmlspecialchars($r['dni']) ?>"
                                data-correo="<?= htmlspecialchars($r['correo']) ?>"
                                data-puntaje="<?= $r['puntaje'] ?>"
                                data-coincidencia="<?= $r['porcentaje_coincidencia'] ?>"
                                data-ranking="<?= $r['ranking'] ?>"
                                data-revisado="<?= $r['revisado_rrhh'] ?>">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div><!-- /reporte-completo -->

<!-- ══════════════════════════════════════════════════════════
     MODAL: DETALLE DE EVALUACIÓN ML
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalleEval" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:#fff">
                <h5 class="modal-title">
                    <i class="bi bi-robot me-2"></i>Detalle de Evaluación ML
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleEvalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Cerrar
                </button>
                <div class="btn-group">
                    <a id="btnDescargarPDF" href="#" class="btn btn-danger" target="_blank" title="Ficha técnica ML (RRHH)">
                        <i class="bi bi-file-pdf me-1"></i>PDF Técnico
                    </a>
                    <a id="btnDescargarConstancia" href="#" class="btn btn-outline-danger" target="_blank" title="Constancia para postulante">
                        <i class="bi bi-file-earmark-text me-1"></i>Constancia Oficial
                    </a>
                </div>
                <button id="btnVerificarCV" class="btn btn-success" style="display:none;"
                        data-bs-toggle="modal" data-bs-target="#modalVerificar">
                    <i class="bi bi-check-circle me-1"></i>Verificar CV
                </button>
                <a id="btnVerCompleto" href="#" class="btn btn-primary">
                    <i class="bi bi-eye me-1"></i>Ver Página Completa
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: VERIFICAR CV
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerificar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/modules/evaluaciones/verificar_cv.php" id="formVerificar">
                <div class="modal-header" style="background:#198754;color:#fff">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle me-2"></i>Verificar Evaluación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_evaluacion" id="verificar_id">
                    <input type="hidden" name="redirect_to" value="reportes">
                    
                    <div class="text-center mb-3 pb-3 border-bottom">
                        <div class="fw-bold fs-5" id="verificar_postulante"></div>
                        <div class="mt-2">
                            <span class="badge bg-primary fs-6 px-3 py-2">
                                Puntaje ML: <span id="verificar_puntaje"></span>/100
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold required">
                            <i class="bi bi-clipboard-check me-1"></i> Resultado de la verificación
                        </label>
                        <select name="resultado" class="form-select" required>
                            <option value="">— Seleccionar —</option>
                            <option value="ACEPTADO">✓ Aceptado para el puesto</option>
                            <option value="EN_ESPERA">⏳ En espera / Lista de reserva</option>
                            <option value="RECHAZADO">✗ Rechazado</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold required">
                            <i class="bi bi-chat-text me-1"></i> Comentario / Motivo
                        </label>
                        <textarea name="comentario" class="form-control" rows="4" 
                                  placeholder="Describe el motivo de la decisión, observaciones sobre el CV, resultados de entrevista, etc."
                                  required></textarea>
                        <div class="form-text">
                            Este comentario será visible para el postulante en su consulta de resultados.
                        </div>
                    </div>

                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Nota:</strong> Una vez verificado, el postulante podrá consultar el resultado 
                        y descargar el PDF de su evaluación.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> Guardar verificación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Cargar detalle de evaluación en modal
document.getElementById('modalDetalleEval').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const idEval = button.getAttribute('data-id');
    const postulante = button.getAttribute('data-postulante');
    const dni = button.getAttribute('data-dni');
    const correo = button.getAttribute('data-correo');
    const puntaje = parseFloat(button.getAttribute('data-puntaje'));
    const coincidencia = parseFloat(button.getAttribute('data-coincidencia'));
    const ranking = button.getAttribute('data-ranking');
    const revisado = button.getAttribute('data-revisado') === '1';
    
    // Actualizar enlaces de botones
    document.getElementById('btnDescargarPDF').href = '<?= BASE_URL ?>/modules/evaluaciones/pdf_ficha.php?id=' + idEval;
    document.getElementById('btnDescargarConstancia').href = '<?= BASE_URL ?>/modules/evaluaciones/pdf_constancia_postulante.php?id=' + idEval;
    document.getElementById('btnVerCompleto').href = '<?= BASE_URL ?>/modules/evaluaciones/ver.php?id=' + idEval;
    
    // Mostrar/ocultar botón de verificar según estado
    const btnVerificar = document.getElementById('btnVerificarCV');
    if (!revisado) {
        btnVerificar.style.display = 'inline-block';
        btnVerificar.setAttribute('data-id', idEval);
        btnVerificar.setAttribute('data-postulante', postulante);
        btnVerificar.setAttribute('data-puntaje', puntaje);
    } else {
        btnVerificar.style.display = 'none';
    }
    
    // Determinar color según puntaje
    const scoreColor = puntaje >= 70 ? '#198754' : (puntaje >= 50 ? '#f0a500' : '#dc3545');
    const scoreClass = puntaje >= 70 ? 'score-high' : (puntaje >= 50 ? 'score-medium' : 'score-low');
    const coincidenciaClass = coincidencia >= 70 ? 'score-high' : (coincidencia >= 50 ? 'score-medium' : 'score-low');
    
    // Cargar datos con AJAX
    fetch('<?= BASE_URL ?>/modules/reportes/ajax_detalle_evaluacion.php?id=' + idEval)
        .then(response => response.json())
        .then(data => {
            const body = document.getElementById('detalleEvalBody');
            
            if (data.error) {
                body.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>${data.error}
                    </div>
                `;
                return;
            }
            
            body.innerHTML = `
                <!-- Header con info principal -->
                <div class="text-center pb-4 mb-4 border-bottom">
                    <div class="mb-3">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold"
                             style="width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 2rem;">
                            ${postulante.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase()}
                        </div>
                    </div>
                    <h4 class="fw-bold mb-2">${postulante}</h4>
                    <div class="text-muted mb-3">
                        <i class="bi bi-person-badge me-1"></i><strong>DNI:</strong> ${dni}
                        ${correo ? '<br><i class="bi bi-envelope me-1"></i>' + correo : ''}
                    </div>
                    <div class="d-flex justify-content-center gap-4 flex-wrap">
                        <div class="text-center">
                            <div class="display-4 fw-bold" style="color: ${scoreColor};">${puntaje}</div>
                            <small class="text-muted">/ 100 puntos</small>
                        </div>
                        <div class="vr d-none d-md-block"></div>
                        <div class="text-center">
                            <div class="display-4 fw-bold text-primary">#${ranking}</div>
                            <small class="text-muted">Ranking</small>
                        </div>
                        <div class="vr d-none d-md-block"></div>
                        <div class="text-center">
                            <div class="display-4 fw-bold text-info">${coincidencia}%</div>
                            <small class="text-muted">Coincidencia</small>
                        </div>
                    </div>
                </div>

                <!-- Estado de revisión -->
                <div class="alert ${revisado ? 'alert-success' : 'alert-warning'} mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi ${revisado ? 'bi-check-circle-fill' : 'bi-hourglass-split'} fs-5"></i>
                        <div>
                            <strong>${revisado ? 'CV Revisado por RRHH' : 'Pendiente de Revisión'}</strong>
                            ${!revisado ? '<br><small>Este CV aún no ha sido verificado por el área de Recursos Humanos.</small>' : ''}
                        </div>
                    </div>
                </div>

                <!-- Información detallada -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="bi bi-file-earmark-person me-2"></i>Convocatoria
                                </h6>
                                <p class="mb-1 fw-semibold">${data.convocatoria || '—'}</p>
                                <small class="text-muted">
                                    <i class="bi bi-building me-1"></i>${data.area || '—'}
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="bi bi-bar-chart-fill me-2"></i>Análisis ML
                                </h6>
                                <dl class="mb-0 small">
                                    <dt class="text-muted">Modelo utilizado</dt>
                                    <dd class="mb-2">${data.modelo_version || '—'}</dd>
                                    
                                    <dt class="text-muted">Fecha de evaluación</dt>
                                    <dd class="mb-2">${data.fecha_evaluacion || '—'}</dd>
                                    
                                    <dt class="text-muted">Estado de revisión</dt>
                                    <dd class="mb-0">
                                        ${revisado 
                                            ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Revisado por RRHH</span>'
                                            : '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pendiente de revisión</span>'}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="bi bi-file-earmark-text me-2"></i>Curriculum Vitae
                                </h6>
                                <dl class="mb-0 small">
                                    <dt class="text-muted">Archivo</dt>
                                    <dd class="mb-2">
                                        <i class="bi bi-file-pdf text-danger me-1"></i>
                                        ${data.nombre_archivo || 'CV.pdf'}
                                    </dd>
                                    
                                    <dt class="text-muted">Fecha de carga</dt>
                                    <dd class="mb-2">${data.fecha_carga || '—'}</dd>
                                    
                                    <dt class="text-muted">Estado</dt>
                                    <dd class="mb-0">
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>Procesado
                                        </span>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Barra de coincidencia visual -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-diagram-3 me-2"></i>Coincidencia con el Perfil Requerido
                        </h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small">Nivel de ajuste al perfil</span>
                            <span class="fw-bold fs-5">${coincidencia}%</span>
                        </div>
                        <div class="ranking-bar" style="height: 20px; border-radius: 10px;">
                            <div class="ranking-bar-fill ${coincidenciaClass}" 
                                 style="width: ${coincidencia}%; height: 100%; border-radius: 10px; transition: width 1s ease;"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small text-muted">
                            <span>0%</span>
                            <span>50%</span>
                            <span>100%</span>
                        </div>
                    </div>
                </div>

                <!-- Observaciones si existen -->
                ${data.observaciones ? `
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-chat-text me-2"></i>Observaciones del Sistema
                        </h6>
                        <p class="mb-0 small text-muted">${data.observaciones}</p>
                    </div>
                </div>
                ` : ''}
            `;
        })
        .catch(error => {
            document.getElementById('detalleEvalBody').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>Error al cargar los detalles: ${error.message}
                </div>
            `;
        });
});

// Modal de verificación: rellenar datos cuando se abre desde el botón del modal de detalle
document.getElementById('modalVerificar').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    if (!button) return;

    const id = button.getAttribute('data-id');
    const postulante = button.getAttribute('data-postulante');
    const puntaje = button.getAttribute('data-puntaje');
    
    document.getElementById('verificar_id').value = id;
    document.getElementById('verificar_postulante').textContent = postulante;
    document.getElementById('verificar_puntaje').textContent = puntaje;
    
    // Reset form
    document.getElementById('formVerificar').reset();
    document.getElementById('verificar_id').value = id;
});

function imprimirSeccion(id) {
    const contenido = document.getElementById(id).innerHTML;
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html>
        <head>
            <title>Reporte - SIGDOC-ML</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="<?= BASE_URL ?>/assets/css/portal.css" rel="stylesheet">
        </head>
        <body onload="window.print();window.close()">
            <div class="container py-4">${contenido}</div>
        </body>
        </html>
    `);
    ventana.document.close();
}
</script>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
