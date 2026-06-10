<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

$pageTitle = 'Mis Resultados';
$post = $_SESSION['postulante'] ?? null;
if (!$post) { header('Location: ' . BASE_URL . '/portal/login.php'); exit; }

$db = getDB();

// Obtener todas las postulaciones con sus resultados
$postulaciones = $db->prepare("
    SELECT 
        cv.id_curriculum,
        cv.nombre_archivo,
        cv.fecha_carga,
        cv.procesado,
        cv.actualizado,
        cv.fecha_actualizacion,
        cv.estado_revision,
        c.id_convocatoria,
        c.titulo AS conv_titulo,
        c.estado AS conv_estado,
        c.fecha_fin,
        a.nombre AS area_nombre,
        e.id_evaluacion,
        e.puntaje,
        e.porcentaje_coincidencia,
        e.ranking,
        e.revisado_rrhh,
        e.verificado,
        e.resultado_verificacion,
        e.comentario_verificacion,
        e.fecha_evaluacion,
        e.fecha_verificacion,
        e.modelo_version,
        e.detalles_json
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

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1" style="color:var(--primary)">
            <i class="bi bi-clipboard-check me-2"></i>Mis Resultados
        </h4>
        <p class="text-muted mb-0">Estado de tus postulaciones y resultados de evaluación</p>
    </div>
</div>

<?php if (empty($postulaciones)): ?>
<div class="bg-white rounded-4 p-5 text-center text-muted shadow-sm">
    <i class="bi bi-inbox display-5 d-block mb-3 opacity-25"></i>
    <h5>No tienes postulaciones aún</h5>
    <p>Postula a una convocatoria para ver tus resultados aquí</p>
    <div class="mt-3">
        <a href="convocatorias.php" class="btn btn-primary">
            <i class="bi bi-megaphone me-1"></i> Ver convocatorias
        </a>
    </div>
</div>
<?php else: ?>

<!-- Estadísticas rápidas -->
<div class="row g-3 mb-4">
    <?php
    $total = count($postulaciones);
    $pendientes = count(array_filter($postulaciones, fn($p) => !$p['procesado']));
    $enRevision = count(array_filter($postulaciones, fn($p) => $p['procesado'] && !$p['verificado']));
    $verificados = count(array_filter($postulaciones, fn($p) => $p['verificado']));
    $aceptados = count(array_filter($postulaciones, fn($p) => $p['resultado_verificacion'] === 'ACEPTADO'));
    ?>
    <div class="col-6 col-md-3">
        <div class="stat-card blue">
            <i class="bi bi-file-earmark-text stat-icon"></i>
            <div>
                <div class="stat-value"><?= $total ?></div>
                <div class="stat-label">Total Postulaciones</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card orange">
            <i class="bi bi-hourglass-split stat-icon"></i>
            <div>
                <div class="stat-value"><?= $pendientes ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card purple">
            <i class="bi bi-eye stat-icon"></i>
            <div>
                <div class="stat-value"><?= $enRevision ?></div>
                <div class="stat-label">En Revisión</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card green">
            <i class="bi bi-check-circle stat-icon"></i>
            <div>
                <div class="stat-value"><?= $aceptados ?></div>
                <div class="stat-label">Aceptados</div>
            </div>
        </div>
    </div>
</div>

<!-- Listado de postulaciones -->
<div class="d-flex flex-column gap-4">
    <?php foreach ($postulaciones as $p):
        $detalles = json_decode($p['detalles_json'] ?? '{}', true) ?: [];
        
        // Determinar estado y color
        if (!$p['procesado']) {
            $estado = 'PENDIENTE';
            $estadoColor = 'warning';
            $estadoIcon = 'hourglass-split';
            $estadoTexto = 'Tu CV está en cola para ser evaluado';
        } elseif (!$p['verificado']) {
            $estado = 'EN REVISIÓN';
            $estadoColor = 'info';
            $estadoIcon = 'eye';
            $estadoTexto = 'Tu CV fue evaluado y está siendo revisado por RRHH';
        } else {
            $resultado = $p['resultado_verificacion'];
            if ($resultado === 'ACEPTADO') {
                $estado = 'ACEPTADO';
                $estadoColor = 'success';
                $estadoIcon = 'check-circle-fill';
                $estadoTexto = '¡Felicidades! Has sido seleccionado';
            } elseif ($resultado === 'RECHAZADO') {
                $estado = 'NO SELECCIONADO';
                $estadoColor = 'danger';
                $estadoIcon = 'x-circle';
                $estadoTexto = 'No fuiste seleccionado en esta ocasión';
            } else {
                $estado = 'EN ESPERA';
                $estadoColor = 'secondary';
                $estadoIcon = 'clock';
                $estadoTexto = 'Estás en lista de espera';
            }
        }
    ?>
    <div class="bg-white rounded-4 shadow-sm overflow-hidden">
        <!-- Header con estado -->
        <div class="p-4 border-bottom" style="background:linear-gradient(135deg, #f8faff 0%, #eef2fb 100%)">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="flex-fill">
                    <h5 class="fw-bold mb-2" style="color:var(--primary)">
                        <i class="bi bi-megaphone me-2"></i>
                        <?= htmlspecialchars($p['conv_titulo']) ?>
                    </h5>
                    <div class="d-flex gap-3 flex-wrap small text-muted">
                        <span>
                            <i class="bi bi-building me-1"></i>
                            <?= htmlspecialchars($p['area_nombre'] ?? 'Área no especificada') ?>
                        </span>
                        <span>
                            <i class="bi bi-calendar me-1"></i>
                            Postulado: <?= date('d/m/Y', strtotime($p['fecha_carga'])) ?>
                        </span>
                        <?php if ($p['actualizado']): ?>
                        <span class="text-primary">
                            <i class="bi bi-arrow-repeat me-1"></i>
                            Actualizado: <?= date('d/m/Y', strtotime($p['fecha_actualizacion'])) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-<?= $estadoColor ?> px-3 py-2 fs-6">
                        <i class="bi bi-<?= $estadoIcon ?> me-1"></i><?= $estado ?>
                    </span>
                    <div class="text-muted small mt-1"><?= $estadoTexto ?></div>
                </div>
            </div>
        </div>

        <!-- Contenido según estado -->
        <div class="p-4">
            
            <?php if ($estado === 'PENDIENTE'): ?>
            <!-- CV Pendiente de Evaluación -->
            <div class="alert alert-warning border-warning">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-info-circle-fill fs-3"></i>
                    <div class="flex-fill">
                        <h6 class="fw-bold mb-2">Tu CV está pendiente de evaluación</h6>
                        <p class="mb-3">El área de Recursos Humanos procesará tu curriculum próximamente. Te notificaremos cuando esté listo.</p>
                        
                        <?php if (!$p['actualizado']): ?>
                        <div class="alert alert-light border mb-2">
                            <strong>¿Quieres actualizar tu CV?</strong><br>
                            Puedes reemplazar tu archivo <strong>una sola vez</strong> mientras esté pendiente.
                        </div>
                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalActualizarCV" 
                                data-id="<?= $p['id_curriculum'] ?>"
                                data-titulo="<?= htmlspecialchars($p['conv_titulo']) ?>">
                            <i class="bi bi-upload me-1"></i> Actualizar mi CV
                        </button>
                        <?php else: ?>
                        <div class="alert alert-info border-info">
                            <i class="bi bi-check-circle me-1"></i>
                            Ya actualizaste tu CV. No puedes volver a cambiarlo.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php elseif ($estado === 'EN REVISIÓN' && $p['id_evaluacion']): ?>
            <!-- CV Evaluado, pendiente de verificación RRHH -->
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 text-center h-100">
                        <div class="text-muted small mb-2">Puntaje ML</div>
                        <div class="display-4 fw-bold <?= $p['puntaje'] >= 70 ? 'text-success' : ($p['puntaje'] >= 50 ? 'text-warning' : 'text-danger') ?>">
                            <?= $p['puntaje'] ?>
                        </div>
                        <div class="text-muted small">/100 puntos</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 text-center h-100">
                        <div class="text-muted small mb-2">Tu posición</div>
                        <div class="display-4 fw-bold text-primary">
                            #<?= $p['ranking'] ?>
                        </div>
                        <div class="text-muted small">en ranking</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 text-center h-100">
                        <div class="text-muted small mb-2">Coincidencia</div>
                        <div class="display-4 fw-bold <?= $p['porcentaje_coincidencia'] >= 70 ? 'text-success' : 'text-warning' ?>">
                            <?= $p['porcentaje_coincidencia'] ?>%
                        </div>
                        <div class="text-muted small">con el perfil</div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info border-info">
                <i class="bi bi-clock-history me-2"></i>
                <strong>En revisión por RRHH</strong> · Tu CV obtuvo buenos resultados en el análisis automático. 
                Ahora está siendo revisado por el área de Recursos Humanos para la decisión final.
            </div>
            
            <?php else: ?>
            <!-- CV Verificado - Resultado Final -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="border rounded-3 p-4 h-100">
                        <h6 class="fw-semibold mb-3 text-primary">
                            <i class="bi bi-robot me-2"></i>Evaluación Automática (ML)
                        </h6>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="fw-bold fs-2 <?= $p['puntaje'] >= 70 ? 'text-success' : 'text-warning' ?>">
                                        <?= $p['puntaje'] ?>
                                    </div>
                                    <div class="text-muted small">Puntaje /100</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="fw-bold fs-2 text-primary">#<?= $p['ranking'] ?></div>
                                    <div class="text-muted small">Posición</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">Coincidencia</span>
                                <span class="fw-semibold"><?= $p['porcentaje_coincidencia'] ?>%</span>
                            </div>
                            <div class="ranking-bar" style="height:12px">
                                <div class="ranking-bar-fill <?= $p['porcentaje_coincidencia'] >= 70 ? 'score-high' : 'score-medium' ?>"
                                     style="width:<?= $p['porcentaje_coincidencia'] ?>%"></div>
                            </div>
                        </div>
                        <div class="text-muted small mt-2">
                            <i class="bi bi-clock me-1"></i>
                            Evaluado el <?= date('d/m/Y', strtotime($p['fecha_evaluacion'])) ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="border rounded-3 p-4 h-100 bg-<?= $estadoColor ?> bg-opacity-10">
                        <h6 class="fw-semibold mb-3">
                            <i class="bi bi-person-check me-2"></i>Decisión Final de RRHH
                        </h6>
                        <div class="text-center mb-3">
                            <div class="badge bg-<?= $estadoColor ?> px-4 py-3 fs-5">
                                <i class="bi bi-<?= $estadoIcon ?> me-2"></i><?= $estado ?>
                            </div>
                        </div>
                        <?php if ($p['comentario_verificacion']): ?>
                        <div class="alert alert-light border mb-3">
                            <div class="small fw-semibold mb-1">Comentario de RRHH:</div>
                            <p class="mb-0 small"><?= nl2br(htmlspecialchars($p['comentario_verificacion'])) ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="text-muted small">
                            <i class="bi bi-calendar-check me-1"></i>
                            Verificado el <?= date('d/m/Y', strtotime($p['fecha_verificacion'])) ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($p['resultado_verificacion'] === 'ACEPTADO'): ?>
            <div class="alert alert-success border-success">
                <h6 class="fw-bold mb-2">
                    <i class="bi bi-trophy me-2"></i>¡Felicitaciones!
                </h6>
                <p class="mb-0">
                    Has sido seleccionado para esta convocatoria. El área de Recursos Humanos se pondrá en contacto contigo próximamente para los siguientes pasos del proceso de selección.
                </p>
            </div>
            <?php elseif ($p['resultado_verificacion'] === 'EN_ESPERA'): ?>
            <div class="alert alert-info border-info">
                <h6 class="fw-bold mb-2">
                    <i class="bi bi-list-check me-2"></i>Lista de espera
                </h6>
                <p class="mb-0">
                    Estás en nuestra lista de reserva. Si surge una vacante o necesitamos más candidatos, te contactaremos.
                </p>
            </div>
            <?php endif; ?>

            <!-- Botón descargar PDF -->
            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>/modules/evaluaciones/pdf_constancia_postulante.php?id=<?= $p['id_evaluacion'] ?>&public=1" 
                   target="_blank"
                   class="btn btn-danger">
                    <i class="bi bi-file-pdf me-2"></i>
                    Descargar Constancia Oficial
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal: Actualizar CV -->
<div class="modal fade" id="modalActualizarCV" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="actualizar_cv.php" enctype="multipart/form-data">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="bi bi-upload me-2"></i>Actualizar mi CV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_curriculum" id="actualizar_id">
                    
                    <div class="alert alert-warning border-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Importante:</strong> Solo puedes actualizar tu CV <strong>una vez</strong>. 
                        Asegúrate de que el nuevo archivo esté correcto antes de enviarlo.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Convocatoria</label>
                        <div class="form-control-plaintext fw-bold" id="actualizar_titulo"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold required">Nuevo archivo CV (PDF o DOCX)</label>
                        <input type="file" 
                               name="cv" 
                               class="form-control" 
                               accept=".pdf,.doc,.docx"
                               required>
                        <div class="form-text">
                            Formatos permitidos: PDF, DOC, DOCX. Tamaño máximo: 10MB
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-upload me-1"></i> Actualizar CV
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Rellenar modal de actualización
document.getElementById('modalActualizarCV').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('actualizar_id').value = btn.dataset.id;
    document.getElementById('actualizar_titulo').textContent = btn.dataset.titulo;
});
</script>

<?php include __DIR__ . '/includes/portal_foot.php'; ?>
