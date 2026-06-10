<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
requireLogin();

$db    = getDB();
$usr   = usuarioActual();
$idRol = (int)$usr['id_rol'];
$pageTitle = 'Dashboard';

// ── Carga de datos según el rol ──────────────────────────────────

// Estadísticas de documentos (Admin, Mesa de Partes, Resp. Área, Jefe General)
$statsDoc = [];
if (in_array($idRol, [ROL_ADMIN, ROL_MESA_PARTES, ROL_RESP_AREA, ROL_JEFE_GENERAL])) {

    // Resp. Área solo ve los de su área
    if ($idRol === ROL_RESP_AREA && !empty($usr['id_area'])) {
        $areaId = (int)$usr['id_area'];
        $statsDoc['total']     = $db->prepare("SELECT COUNT(*) FROM documentos WHERE id_area_origen=? OR id_area_destino=?")->execute([$areaId,$areaId]) ? null : 0;
        $stmtTot = $db->prepare("SELECT COUNT(*) FROM documentos WHERE id_area_origen=? OR id_area_destino=?");
        $stmtTot->execute([$areaId, $areaId]);
        $statsDoc['total'] = (int)$stmtTot->fetchColumn();

        $stmtEst = $db->prepare("SELECT estado, COUNT(*) FROM documentos WHERE id_area_origen=? OR id_area_destino=? GROUP BY estado");
        $stmtEst->execute([$areaId, $areaId]);
        $statsDoc['por_estado'] = $stmtEst->fetchAll(PDO::FETCH_KEY_PAIR);

        $stmtExp = $db->prepare("SELECT COUNT(*) FROM expedientes WHERE id_area=?");
        $stmtExp->execute([$areaId]);
        $statsDoc['expedientes'] = (int)$stmtExp->fetchColumn();
    } else {
        $statsDoc['total']      = (int)$db->query("SELECT COUNT(*) FROM documentos")->fetchColumn();
        $statsDoc['por_estado'] = $db->query("SELECT estado, COUNT(*) FROM documentos GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
        $statsDoc['expedientes']= (int)$db->query("SELECT COUNT(*) FROM expedientes")->fetchColumn();
    }
}

// Estadísticas de reclutamiento (Admin, RRHH, Jefe General)
$statsML = [];
if (in_array($idRol, [ROL_ADMIN, ROL_RRHH, ROL_JEFE_GENERAL])) {
    $statsML['convocatorias_activas'] = (int)$db->query("SELECT COUNT(*) FROM convocatorias WHERE estado='ACTIVA'")->fetchColumn();
    $statsML['postulantes']           = (int)$db->query("SELECT COUNT(*) FROM postulantes")->fetchColumn();
    $statsML['pendientes_ml']         = (int)$db->query("SELECT COUNT(*) FROM curriculums WHERE procesado=0")->fetchColumn();
    $statsML['evaluaciones']          = (int)$db->query("SELECT COUNT(*) FROM evaluaciones_ml")->fetchColumn();
}

// Últimos documentos (para quien los gestiona)
$ultimosDocs = [];
if (in_array($idRol, [ROL_ADMIN, ROL_MESA_PARTES, ROL_RESP_AREA, ROL_JEFE_GENERAL])) {
    if ($idRol === ROL_RESP_AREA && !empty($usr['id_area'])) {
        $areaId = (int)$usr['id_area'];
        $stmtD = $db->prepare("
            SELECT d.id_documento, d.asunto, d.estado, d.fecha_registro,
                   t.nombre AS tipo, ao.nombre AS area_origen
            FROM documentos d
            JOIN tipos_documento t ON t.id_tipo_documento = d.id_tipo_documento
            LEFT JOIN areas ao ON ao.id_area = d.id_area_origen
            WHERE d.id_area_origen=? OR d.id_area_destino=?
            ORDER BY d.fecha_registro DESC LIMIT 6
        ");
        $stmtD->execute([$areaId, $areaId]);
    } else {
        $stmtD = $db->query("
            SELECT d.id_documento, d.asunto, d.estado, d.fecha_registro,
                   t.nombre AS tipo, ao.nombre AS area_origen
            FROM documentos d
            JOIN tipos_documento t ON t.id_tipo_documento = d.id_tipo_documento
            LEFT JOIN areas ao ON ao.id_area = d.id_area_origen
            ORDER BY d.fecha_registro DESC LIMIT 6
        ");
    }
    $ultimosDocs = $stmtD->fetchAll();
}

// Top evaluaciones ML (para RRHH, Admin y Jefe General)
$ultimasEvals = [];
if (in_array($idRol, [ROL_ADMIN, ROL_RRHH, ROL_JEFE_GENERAL])) {
    $ultimasEvals = $db->query("
        SELECT e.puntaje, e.porcentaje_coincidencia, e.ranking,
               CONCAT(p.nombres,' ',p.apellido_paterno) AS postulante,
               c.titulo AS convocatoria
        FROM evaluaciones_ml e
        JOIN postulantes p ON p.id_postulante = e.id_postulante
        JOIN convocatorias c ON c.id_convocatoria = e.id_convocatoria
        ORDER BY e.puntaje DESC LIMIT 5
    ")->fetchAll();
}

// Convocatorias activas (para RRHH, Admin y Jefe General)
$convActivas = [];
if (in_array($idRol, [ROL_ADMIN, ROL_RRHH, ROL_JEFE_GENERAL])) {
    $convActivas = $db->query("
        SELECT c.id_convocatoria, c.titulo, c.fecha_fin, c.estado,
               a.nombre AS area_nombre,
               (SELECT COUNT(*) FROM curriculums cv WHERE cv.id_convocatoria=c.id_convocatoria) AS total_cvs,
               (SELECT COUNT(*) FROM curriculums cv WHERE cv.id_convocatoria=c.id_convocatoria AND cv.procesado=0) AS pendientes
        FROM convocatorias c
        LEFT JOIN areas a ON a.id_area = c.id_area
        WHERE c.estado = 'ACTIVA'
        ORDER BY 
            (SELECT COUNT(*) FROM curriculums cv WHERE cv.id_convocatoria=c.id_convocatoria AND cv.procesado=0) DESC,
            c.fecha_fin ASC
        LIMIT 5
    ")->fetchAll();
}

include __DIR__ . '/includes/layout_head.php';
?>

<?php include __DIR__ . '/includes/alerts.php'; ?>

<!-- ══════════ BIENVENIDA ══════════ -->
<div class="d-flex align-items-center gap-3 mb-4 p-3 bg-white rounded-3 shadow-sm">
    <div style="width:48px;height:48px;border-radius:50%;background:var(--primary);color:#fff;
                display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:700;flex-shrink:0">
        <?= strtoupper(substr($usr['nombres'] ?? 'U', 0, 1) . substr($usr['apellido_paterno'] ?? '', 0, 1)) ?>
    </div>
    <div>
        <div class="fw-bold" style="color:var(--primary)">
            Bienvenido, <?= htmlspecialchars($usr['nombres'] . ' ' . $usr['apellido_paterno']) ?>
        </div>
        <div class="text-muted small">
            <span class="badge bg-primary me-1"><?= htmlspecialchars($usr['rol_nombre'] ?? '') ?></span>
            <?php if (!empty($usr['area_nombre'])): ?>
            <span class="badge bg-light text-dark"><?= htmlspecialchars($usr['area_nombre']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══════════ STATS — UNA SOLA FILA UNIFICADA ══════════
     Admin ve las 8 cards juntas (docs + ML).
     Mesa/Resp.Área ven solo las 4 de documentos.
     RRHH ve solo las 4 de reclutamiento.
══════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">

    <?php if (!empty($statsDoc)): ?>
    <div class="col-6 col-xl-3">
        <div class="stat-card blue">
            <i class="bi bi-file-earmark-text stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($statsDoc['total'] ?? 0) ?></div>
                <div class="stat-label"><?= $idRol === ROL_RESP_AREA ? 'Docs. de mi área' : 'Documentos' ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card orange">
            <i class="bi bi-folder2-open stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($statsDoc['expedientes'] ?? 0) ?></div>
                <div class="stat-label">Expedientes</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card green">
            <i class="bi bi-check2-circle stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($statsDoc['por_estado']['ARCHIVADO'] ?? 0) ?></div>
                <div class="stat-label">Archivados</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card purple">
            <i class="bi bi-hourglass-split stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format(($statsDoc['por_estado']['EN_TRAMITE'] ?? 0) + ($statsDoc['por_estado']['DERIVADO'] ?? 0)) ?></div>
                <div class="stat-label">En trámite</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($statsML)): ?>
    <div class="col-6 col-xl-3">
        <div class="stat-card green">
            <i class="bi bi-megaphone stat-icon"></i>
            <div>
                <div class="stat-value"><?= $statsML['convocatorias_activas'] ?></div>
                <div class="stat-label">Convocatorias activas</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card blue">
            <i class="bi bi-people stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($statsML['postulantes']) ?></div>
                <div class="stat-label">Postulantes</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card purple">
            <i class="bi bi-robot stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($statsML['evaluaciones']) ?></div>
                <div class="stat-label">CVs evaluados</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card orange">
            <i class="bi bi-hourglass-split stat-icon"></i>
            <div>
                <div class="stat-value"><?= $statsML['pendientes_ml'] ?></div>
                <div class="stat-label">CVs pendientes ML</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /row stats -->

<!-- Estado de documentos + últimos docs -->
<?php if (!empty($statsDoc)): ?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="table-card p-3 h-100">
            <h6 class="fw-semibold text-primary-custom mb-3">
                <i class="bi bi-pie-chart me-1"></i> Estado de documentos
            </h6>
            <?php
            $estadoLabels = [
                'REGISTRADO' => ['registrado', 'Registrado'],
                'EN_TRAMITE' => ['en_tramite', 'En trámite'],
                'DERIVADO'   => ['derivado',   'Derivado'],
                'ARCHIVADO'  => ['archivado',  'Archivado'],
                'ANULADO'    => ['anulado',    'Anulado'],
            ];
            $totalDocs = max(1, $statsDoc['total'] ?? 1);
            foreach ($estadoLabels as $key => [$cls, $label]):
                $cnt = $statsDoc['por_estado'][$key] ?? 0;
                $pct = round(($cnt / $totalDocs) * 100);
            ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between mb-1">
                    <span class="badge-estado badge-<?= $cls ?>"><?= $label ?></span>
                    <span class="fw-semibold small"><?= $cnt ?></span>
                </div>
                <div class="ranking-bar">
                    <div class="ranking-bar-fill bg-primary" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="mt-3 d-grid">
                <a href="<?= BASE_URL ?>/modules/documentos/crear.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo documento
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="table-card">
            <div class="table-card-header">
                <h5><i class="bi bi-clock-history me-1"></i>
                    <?= $idRol === ROL_RESP_AREA ? 'Documentos recientes de mi área' : 'Últimos documentos' ?>
                </h5>
                <a href="<?= BASE_URL ?>/modules/documentos/index.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr><th>Tipo</th><th>Asunto</th><th>Origen</th><th>Estado</th><th>Fecha</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimosDocs)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Sin documentos aún.</td></tr>
                        <?php else: ?>
                        <?php foreach ($ultimosDocs as $doc): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark small"><?= htmlspecialchars($doc['tipo']) ?></span></td>
                            <td>
                                <a href="<?= BASE_URL ?>/modules/documentos/ver.php?id=<?= $doc['id_documento'] ?>"
                                   class="text-decoration-none text-dark small">
                                    <?= htmlspecialchars(mb_strimwidth($doc['asunto'], 0, 45, '…')) ?>
                                </a>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($doc['area_origen'] ?? '—') ?></td>
                            <td><span class="badge-estado badge-<?= strtolower($doc['estado']) ?>"><?= $doc['estado'] ?></span></td>
                            <td class="small text-muted"><?= date('d/m/y', strtotime($doc['fecha_registro'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Alerta CVs pendientes de procesar ── -->
<?php if (!empty($statsML) && $statsML['pendientes_ml'] > 0): ?>
<?php
// Contar cuántas convocatorias tienen CVs pendientes
$stmtConvPend = $db->query("
    SELECT COUNT(DISTINCT id_convocatoria) 
    FROM curriculums 
    WHERE procesado=0
");
$convocatoriasPendientes = (int)$stmtConvPend->fetchColumn();
?>
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4 shadow-sm">
    <i class="bi bi-bell-fill fs-4"></i>
    <div class="flex-grow-1">
        <strong>CVs Pendientes de Procesar:</strong> 
        Hay <strong><?= $statsML['pendientes_ml'] ?></strong> curriculum(s) sin procesar 
        en <strong><?= $convocatoriasPendientes ?></strong> convocatoria(s).
    </div>
    <a href="<?= BASE_URL ?>/modules/convocatorias/index.php" 
       class="btn btn-warning btn-sm fw-semibold">
        <i class="bi bi-megaphone me-1"></i> Ver Convocatorias
    </a>
</div>
<?php endif; ?>

<!-- ── Paneles ML: Convocatorias + Top candidatos ── -->
<?php if (!empty($statsML)): ?>
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="table-card">
            <div class="table-card-header">
                <h5><i class="bi bi-megaphone me-1"></i> Convocatorias activas</h5>
                <a href="<?= BASE_URL ?>/modules/convocatorias/index.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <?php if (empty($convActivas)): ?>
            <p class="text-muted text-center py-3 small">Sin convocatorias activas.</p>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($convActivas as $c): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2 
                    <?= $c['pendientes'] > 0 ? 'border-start border-warning border-3' : '' ?>">
                    <div class="d-flex align-items-center gap-2 flex-grow-1">
                        <?php if ($c['pendientes'] > 0): ?>
                        <i class="bi bi-bell-fill text-warning fs-5" title="<?= $c['pendientes'] ?> CV(s) pendientes"></i>
                        <?php endif; ?>
                        <div>
                            <a href="<?= BASE_URL ?>/modules/convocatorias/ver.php?id=<?= $c['id_convocatoria'] ?>"
                               class="text-decoration-none fw-semibold small">
                                <?= htmlspecialchars(mb_strimwidth($c['titulo'], 0, 35, '…')) ?>
                            </a>
                            <div class="text-muted" style="font-size:.72rem">
                                <?= htmlspecialchars($c['area_nombre'] ?? '—') ?>
                                <?php if ($c['fecha_fin']): ?>· Cierre: <?= date('d/m/Y', strtotime($c['fecha_fin'])) ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-end ms-2 d-flex gap-1 align-items-center">
                        <span class="badge bg-secondary"><?= $c['total_cvs'] ?> CVs</span>
                        <?php if ($c['pendientes'] > 0): ?>
                        <span class="badge bg-warning text-dark">
                            <i class="bi bi-hourglass-split"></i> <?= $c['pendientes'] ?> pendiente<?= $c['pendientes'] > 1 ? 's' : '' ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="table-card">
            <div class="table-card-header">
                <h5><i class="bi bi-trophy me-1"></i> Top candidatos ML</h5>
                <a href="<?= BASE_URL ?>/modules/evaluaciones/index.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr><th>#</th><th>Postulante</th><th>Convocatoria</th><th>Coincidencia</th><th>Puntaje</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimasEvals)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Sin evaluaciones aún.</td></tr>
                        <?php else: ?>
                        <?php foreach ($ultimasEvals as $i => $ev): ?>
                        <tr>
                            <td class="fw-bold text-muted"><?= $i + 1 ?></td>
                            <td class="fw-semibold small"><?= htmlspecialchars($ev['postulante']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars(mb_strimwidth($ev['convocatoria'], 0, 28, '…')) ?></td>
                            <td style="min-width:110px">
                                <div class="ranking-bar mb-1">
                                    <div class="ranking-bar-fill <?= $ev['porcentaje_coincidencia'] >= 70 ? 'score-high' : ($ev['porcentaje_coincidencia'] >= 40 ? 'score-medium' : 'score-low') ?>"
                                         style="width:<?= $ev['porcentaje_coincidencia'] ?>%"></div>
                                </div>
                                <small class="fw-semibold"><?= $ev['porcentaje_coincidencia'] ?>%</small>
                            </td>
                            <td class="fw-bold <?= $ev['puntaje'] >= 70 ? 'text-success' : ($ev['puntaje'] >= 40 ? 'text-warning' : 'text-danger') ?>">
                                <?= $ev['puntaje'] ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
