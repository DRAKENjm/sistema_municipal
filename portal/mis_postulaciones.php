<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

$pageTitle = 'Mis postulaciones';
$post = $_SESSION['postulante'] ?? null;
if (!$post) { header('Location: ' . BASE_URL . '/portal/login.php'); exit; }

$db = getDB();

$postulaciones = $db->prepare("
    SELECT cv.*, c.titulo AS conv_titulo, c.estado AS conv_estado,
           c.fecha_fin, c.salario_referencial, a.nombre AS area_nombre,
           e.puntaje, e.porcentaje_coincidencia, e.ranking, e.revisado_rrhh,
           e.fecha_evaluacion
    FROM curriculums cv
    JOIN convocatorias c ON c.id_convocatoria = cv.id_convocatoria
    LEFT JOIN areas a ON a.id_area = c.id_area
    LEFT JOIN evaluaciones_ml e ON e.id_curriculum = cv.id_curriculum
    WHERE cv.id_postulante = ?
    ORDER BY cv.fecha_carga DESC
");
$postulaciones->execute([$post['id_postulante']]);
$postulaciones = $postulaciones->fetchAll();

include __DIR__ . '/includes/portal_head.php';
?>

<div class="portal-card mb-4">
    <div class="portal-card-header">
        <h4 class="portal-card-title">
            <i class="bi bi-briefcase"></i>
            Mis postulaciones
        </h4>
        <p class="text-muted mb-0">Historial completo de tus aplicaciones a convocatorias</p>
    </div>
</div>

<?php if (empty($postulaciones)): ?>
<div class="empty-state">
    <div class="empty-state-icon">
        <i class="bi bi-briefcase"></i>
    </div>
    <h5>Aún no tienes postulaciones</h5>
    <p>Comienza tu búsqueda laboral postulando a nuestras convocatorias disponibles.</p>
    <a href="convocatorias.php" class="btn btn-portal-primary">
        <i class="bi bi-megaphone me-2"></i>Explorar convocatorias
    </a>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($postulaciones as $p): ?>
    <div class="convocatoria-card">
        <div class="convocatoria-header">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <?php if ($p['conv_estado'] === 'ACTIVA'): ?>
                    <span class="badge-portal success">
                        <i class="bi bi-circle-fill pulse" style="font-size:0.5rem"></i>
                        Activa
                    </span>
                    <?php elseif ($p['conv_estado'] === 'CERRADA'): ?>
                    <span class="badge-portal secondary">Cerrada</span>
                    <?php else: ?>
                    <span class="badge-portal danger">Anulada</span>
                    <?php endif; ?>
                    
                    <?php if ($p['area_nombre']): ?>
                    <span class="badge-portal secondary">
                        <i class="bi bi-building"></i><?= htmlspecialchars($p['area_nombre']) ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if (!$p['procesado']): ?>
                    <span class="badge-portal warning">
                        <i class="bi bi-hourglass-split"></i>Pendiente de evaluación
                    </span>
                    <?php elseif ($p['procesado'] && $p['revisado_rrhh']): ?>
                    <span class="badge-portal info">
                        <i class="bi bi-person-check"></i>Revisado
                    </span>
                    <?php else: ?>
                    <span class="badge-portal info">
                        <i class="bi bi-robot"></i>Evaluado
                    </span>
                    <?php endif; ?>
                </div>
                <?php if ($p['salario_referencial']): ?>
                <div class="text-success fw-bold fs-5">
                    S/ <?= number_format($p['salario_referencial'], 2) ?>
                </div>
                <?php endif; ?>
            </div>
            
            <h5 class="convocatoria-title"><?= htmlspecialchars($p['conv_titulo']) ?></h5>
            
            <div class="convocatoria-meta">
                <span>
                    <i class="bi bi-upload"></i>
                    Postulado: <?= date('d/m/Y', strtotime($p['fecha_carga'])) ?>
                </span>
                <?php if ($p['fecha_fin']): ?>
                <span>
                    <i class="bi bi-calendar-x"></i>
                    Cierre: <?= date('d/m/Y', strtotime($p['fecha_fin'])) ?>
                </span>
                <?php endif; ?>
                <span>
                    <i class="bi bi-file-earmark-pdf"></i>
                    <?= htmlspecialchars($p['nombre_archivo'] ?? 'CV.pdf') ?>
                </span>
            </div>
        </div>

        <div class="convocatoria-body">
            <?php if ($p['puntaje'] !== null): ?>
            <!-- Resultado ML disponible -->
            <div class="portal-card" style="background:linear-gradient(135deg, #f8faff 0%, #eef2fb 100%);border:2px solid rgba(102,126,234,0.2)">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div class="flex-fill">
                        <h6 class="fw-semibold mb-2" style="color:var(--portal-primary)">
                            <i class="bi bi-robot me-2"></i>Resultado de evaluación ML
                        </h6>
                        <div class="row g-3">
                            <div class="col-auto">
                                <div class="text-center">
                                    <div class="display-5 fw-bold mb-1 <?= $p['puntaje'] >= 70 ? 'text-success' : ($p['puntaje'] >= 40 ? 'text-warning' : 'text-danger') ?>">
                                        <?= $p['puntaje'] ?>
                                    </div>
                                    <small class="text-muted">Puntaje /100</small>
                                </div>
                            </div>
                            <?php if ($p['ranking']): ?>
                            <div class="col-auto border-start ps-3">
                                <div class="text-center">
                                    <div class="display-5 fw-bold mb-1" style="color:var(--portal-primary)">
                                        #<?= $p['ranking'] ?>
                                    </div>
                                    <small class="text-muted">Posición</small>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="col flex-fill">
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-muted fw-semibold">Coincidencia con perfil</span>
                                        <span class="fw-bold"><?= $p['porcentaje_coincidencia'] ?>%</span>
                                    </div>
                                    <div class="progress-portal">
                                        <div class="progress-portal-fill <?= $p['porcentaje_coincidencia'] >= 70 ? 'success' : ($p['porcentaje_coincidencia'] >= 40 ? 'warning' : 'danger') ?>"
                                             style="width:<?= $p['porcentaje_coincidencia'] ?>%"></div>
                                    </div>
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    Evaluado: <?= date('d/m/Y H:i', strtotime($p['fecha_evaluacion'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($p['revisado_rrhh']): ?>
                <div class="alert-portal alert-portal-success mt-3 mb-0">
                    <i class="bi bi-person-check-fill alert-icon"></i>
                    <div>
                        <strong>Revisado por RRHH</strong> · Tu postulación ha sido revisada por el área de Recursos Humanos.
                        <div class="mt-1 small">
                            <a href="mis_resultados.php" class="text-decoration-underline fw-semibold">
                                Ver resultado completo <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="alert-portal alert-portal-warning">
                <i class="bi bi-hourglass-split alert-icon"></i>
                <div>
                    <strong>Evaluación pendiente</strong><br>
                    <small>Tu CV está en cola de evaluación. El sistema de Machine Learning procesará tu currículum pronto.</small>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="convocatoria-footer">
            <div>
                <?php if ($p['conv_estado'] === 'ACTIVA'): ?>
                <a href="postular.php?id=<?= $p['id_convocatoria'] ?>"
                   class="btn btn-portal-secondary btn-sm">
                    <i class="bi bi-arrow-repeat me-1"></i>Actualizar CV
                </a>
                <?php endif; ?>
            </div>
            <a href="mis_resultados.php" class="btn btn-portal-primary btn-sm">
                <i class="bi bi-clipboard-check me-1"></i>Ver resultado detallado
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/portal_foot.php'; ?>
