<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT c.*, a.nombre AS area_nombre,
           CONCAT(u.nombres,' ',u.apellido_paterno) AS creado_por
    FROM convocatorias c
    LEFT JOIN areas a ON a.id_area = c.id_area
    LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario
    WHERE c.id_convocatoria = ?
");
$stmt->execute([$id]);
$conv = $stmt->fetch();

if (!$conv) {
    $_SESSION['error'] = 'Convocatoria no encontrada.';
    header('Location: index.php');
    exit;
}

// Postulantes con sus evaluaciones
$postulantes = $db->prepare("
    SELECT p.*, cv.id_curriculum, cv.nombre_archivo, cv.procesado, cv.fecha_carga,
           e.puntaje, e.porcentaje_coincidencia, e.ranking
    FROM curriculums cv
    JOIN postulantes p ON p.id_postulante = cv.id_postulante
    LEFT JOIN evaluaciones_ml e ON e.id_curriculum = cv.id_curriculum
    WHERE cv.id_convocatoria = ?
    ORDER BY COALESCE(e.puntaje, -1) DESC
");
$postulantes->execute([$id]);
$postulantes = $postulantes->fetchAll();

$pageTitle = 'Convocatoria: ' . mb_strimwidth($conv['titulo'], 0, 40, '…');
include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h4 class="page-title mb-0 flex-fill"><?= htmlspecialchars($conv['titulo']) ?></h4>
</div>

<div class="row g-3">
    <!-- Detalle de la convocatoria -->
    <div class="col-lg-4">
        <div class="form-section">
            <h6><i class="bi bi-info-circle me-1"></i> Información</h6>
            <dl class="row small mb-0">
                <dt class="col-5 text-muted">Estado</dt>
                <dd class="col-7">
                    <span class="badge-estado badge-<?= strtolower($conv['estado']) ?>"><?= $conv['estado'] ?></span>
                </dd>
                <dt class="col-5 text-muted">Área</dt>
                <dd class="col-7"><?= htmlspecialchars($conv['area_nombre'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Salario</dt>
                <dd class="col-7"><?= $conv['salario_referencial'] ? 'S/ ' . number_format($conv['salario_referencial'], 2) : '—' ?></dd>
                <dt class="col-5 text-muted">Inicio</dt>
                <dd class="col-7"><?= $conv['fecha_inicio'] ? date('d/m/Y', strtotime($conv['fecha_inicio'])) : '—' ?></dd>
                <dt class="col-5 text-muted">Cierre</dt>
                <dd class="col-7"><?= $conv['fecha_fin'] ? date('d/m/Y', strtotime($conv['fecha_fin'])) : '—' ?></dd>
                <dt class="col-5 text-muted">Creado por</dt>
                <dd class="col-7"><?= htmlspecialchars($conv['creado_por'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Postulantes</dt>
                <dd class="col-7 fw-bold"><?= count($postulantes) ?></dd>
            </dl>
        </div>

        <?php if ($conv['requisitos']): ?>
        <div class="form-section">
            <h6><i class="bi bi-list-check me-1"></i> Requisitos</h6>
            <p class="small" style="white-space:pre-wrap"><?= htmlspecialchars($conv['requisitos']) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($conv['palabras_clave']): ?>
        <div class="form-section">
            <h6><i class="bi bi-robot me-1 text-info"></i> Palabras clave ML</h6>
            <div class="d-flex flex-wrap gap-1">
                <?php foreach (explode(',', $conv['palabras_clave']) as $kw): ?>
                <span class="badge bg-info text-dark"><?= htmlspecialchars(trim($kw)) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Lista de postulantes + ranking -->
    <div class="col-lg-8">
        <div class="table-card">
            <div class="table-card-header">
                <h5><i class="bi bi-people me-1"></i> Postulantes y ranking ML</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Postulante</th>
                            <th width="150" class="text-center">Estado</th>
                            <th width="150" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($postulantes)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">
                            <i class="bi bi-inbox display-6 d-block mb-2 opacity-25"></i>
                            No hay postulantes registrados para esta convocatoria.
                        </td></tr>
                        <?php else: ?>
                        <?php foreach ($postulantes as $i => $p): ?>
                        <tr>
                            <td class="fw-bold text-muted align-middle"><?= $i + 1 ?></td>
                            <td class="align-middle">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                         style="width:36px;height:36px;font-size:0.85rem;font-weight:600;flex-shrink:0">
                                        <?= strtoupper(substr($p['nombres'], 0, 1) . substr($p['apellido_paterno'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($p['nombres'] . ' ' . $p['apellido_paterno'] . ' ' . $p['apellido_materno']) ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($p['dni']) ?>
                                            <span class="mx-2">•</span>
                                            <i class="bi bi-calendar-check me-1"></i><?= date('d/m/Y', strtotime($p['fecha_carga'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center align-middle">
                                <?php if (!$p['procesado']): ?>
                                <span class="badge bg-warning text-dark px-3 py-2">
                                    <i class="bi bi-hourglass-split me-1"></i> Pendiente
                                </span>
                                <?php else: ?>
                                <span class="badge bg-success px-3 py-2">
                                    <i class="bi bi-check-circle me-1"></i> Evaluado
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center align-middle">
                                <button class="btn btn-info btn-sm text-white"
                                        onclick="verDetallePostulante(<?= $p['id_postulante'] ?>, <?= $p['id_curriculum'] ?>, <?= $p['procesado'] ?>)"
                                        title="Ver detalles">
                                    <i class="bi bi-eye me-1"></i> Ver
                                </button>
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

<!-- Modal de Detalle del Postulante -->
<div class="modal fade" id="modalDetallePostulante" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title"><i class="bi bi-person-circle me-2"></i>Detalle del Postulante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalDetalleContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Función para ver detalle del postulante
async function verDetallePostulante(idPostulante, idCurriculum, procesado) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetallePostulante'));
    const content = document.getElementById('modalDetalleContent');
    
    modal.show();
    
    try {
        const response = await fetch('<?= BASE_URL ?>/modules/convocatorias/ajax_detalle_postulante.php?id=' + idPostulante + '&cv=' + idCurriculum + '&conv=<?= $id ?>');
        const data = await response.json();
        
        if (data.error) {
            content.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
            return;
        }
        
        // Construir HTML del modal
        let html = `
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="text-muted mb-2"><i class="bi bi-person-badge me-1"></i> Información Personal</h6>
                    <dl class="row small mb-0">
                        <dt class="col-5">Nombre completo:</dt>
                        <dd class="col-7">${data.nombres} ${data.apellido_paterno} ${data.apellido_materno}</dd>
                        <dt class="col-5">DNI:</dt>
                        <dd class="col-7">${data.dni}</dd>
                        <dt class="col-5">Correo:</dt>
                        <dd class="col-7">${data.correo || '—'}</dd>
                        <dt class="col-5">Teléfono:</dt>
                        <dd class="col-7">${data.telefono || '—'}</dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted mb-2"><i class="bi bi-file-earmark-text me-1"></i> Currículum</h6>
                    <dl class="row small mb-0">
                        <dt class="col-5">Archivo:</dt>
                        <dd class="col-7">
                            ${data.ruta_archivo ? (() => {
                                const ext = data.ruta_archivo.split('.').pop().toLowerCase();
                                const isPdf = ext === 'pdf';
                                const isWord = ['doc', 'docx'].includes(ext);
                                
                                let icon = 'bi-file-earmark';
                                let btnText = 'Abrir CV';
                                let btnClass = 'btn-outline-primary';
                                
                                if (isPdf) {
                                    icon = 'bi-file-pdf';
                                    btnText = 'Ver CV';
                                    btnClass = 'btn-outline-danger';
                                } else if (isWord) {
                                    icon = 'bi-file-word';
                                    btnText = 'Descargar CV';
                                    btnClass = 'btn-outline-info';
                                }
                                
                                return `<a href="<?= BASE_URL ?>/uploads/curriculums/${data.ruta_archivo}" target="_blank" class="btn btn-sm ${btnClass}">
                                    <i class="${icon} me-1"></i> ${btnText}
                                </a>`;
                            })() : '<span class="text-muted">Sin archivo</span>'}
                        </dd>
                        <dt class="col-5">Fecha de entrega:</dt>
                        <dd class="col-7">${data.fecha_carga}</dd>
                        <dt class="col-5">Estado de evaluación:</dt>
                        <dd class="col-7">
                            ${data.procesado == 1 ? 
                                '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Evaluado</span>' : 
                                '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> Pendiente</span>'}
                        </dd>
                    </dl>
                </div>
            </div>`;
        
        // Si está evaluado, mostrar resultados ML
        if (data.evaluacion) {
            html += `
                <hr class="my-3">
                <h6 class="text-muted mb-3"><i class="bi bi-robot me-1 text-info"></i> Resultados de Evaluación ML</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Puntaje Total</div>
                                        <div class="fs-3 fw-bold text-primary">${data.evaluacion.puntaje}</div>
                                    </div>
                                    <i class="bi bi-star-fill text-warning fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Coincidencia</div>
                                        <div class="fs-3 fw-bold text-success">${data.evaluacion.porcentaje_coincidencia}%</div>
                                    </div>
                                    <i class="bi bi-graph-up text-success fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
        }
        
        html += `
            <hr class="my-3">
            <div class="d-flex justify-content-end gap-2">
                ${data.procesado == 0 ? 
                    `<a href="<?= BASE_URL ?>/modules/evaluaciones/index.php?cv=${idCurriculum}" class="btn btn-primary">
                        <i class="bi bi-robot me-1"></i> Evaluar con ML
                    </a>` : ''}
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>`;
        
        content.innerHTML = html;
        
    } catch (error) {
        content.innerHTML = '<div class="alert alert-danger">Error al cargar los datos</div>';
    }
}
</script>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
