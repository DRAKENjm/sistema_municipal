<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM postulantes WHERE id_postulante = ?");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    $_SESSION['error'] = 'Postulante no encontrado.';
    header('Location: index.php');
    exit;
}

// Historial de postulaciones con evaluaciones
$historial = $db->prepare("
    SELECT cv.id_curriculum, cv.ruta_archivo, cv.nombre_archivo, cv.procesado, cv.fecha_carga,
           c.titulo AS convocatoria, c.id_convocatoria, c.estado AS conv_estado,
           e.id_evaluacion, e.puntaje, e.porcentaje_coincidencia, e.ranking,
           e.revisado_rrhh, e.fecha_evaluacion
    FROM curriculums cv
    JOIN convocatorias c ON c.id_convocatoria = cv.id_convocatoria
    LEFT JOIN evaluaciones_ml e ON e.id_curriculum = cv.id_curriculum
    WHERE cv.id_postulante = ?
    ORDER BY cv.fecha_carga DESC
");
$historial->execute([$id]);
$historial = $historial->fetchAll();

$pageTitle = $post['apellido_paterno'] . ' ' . $post['nombres'];
include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h4 class="page-title mb-0 flex-fill">
        <?= htmlspecialchars($post['apellido_paterno'] . ' ' . $post['apellido_materno'] . ', ' . $post['nombres']) ?>
    </h4>
    <?php if (esAdmin()): ?>
    <a href="cambiar_estado.php?id=<?= $id ?>"
       class="btn btn-sm <?= $post['estado'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
       data-confirm="<?= $post['estado'] ? '¿Bloquear la cuenta de este postulante?' : '¿Activar la cuenta de este postulante?' ?>">
        <i class="bi bi-<?= $post['estado'] ? 'person-x' : 'person-check' ?> me-1"></i>
        <?= $post['estado'] ? 'Bloquear cuenta' : 'Activar cuenta' ?>
    </a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- Datos del postulante -->
    <div class="col-lg-4">
        <div class="form-section">
            <h6><i class="bi bi-person me-1"></i> Datos personales</h6>
            <dl class="row small mb-0">
                <dt class="col-5 text-muted">DNI</dt>
                <dd class="col-7 fw-bold"><?= htmlspecialchars($post['dni']) ?></dd>
                <dt class="col-5 text-muted">Usuario</dt>
                <dd class="col-7"><code><?= htmlspecialchars($post['usuario'] ?? '—') ?></code></dd>
                <dt class="col-5 text-muted">Estado</dt>
                <dd class="col-7">
                    <?= $post['estado']
                        ? '<span class="badge bg-success">Activo</span>'
                        : '<span class="badge bg-danger">Bloqueado</span>' ?>
                </dd>
                <dt class="col-5 text-muted">Correo</dt>
                <dd class="col-7"><?= htmlspecialchars($post['correo'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Teléfono</dt>
                <dd class="col-7"><?= htmlspecialchars($post['telefono'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Nacimiento</dt>
                <dd class="col-7"><?= $post['fecha_nacimiento'] ? date('d/m/Y', strtotime($post['fecha_nacimiento'])) : '—' ?></dd>
                <dt class="col-5 text-muted">Dirección</dt>
                <dd class="col-7"><?= htmlspecialchars($post['direccion'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Registrado</dt>
                <dd class="col-7"><?= date('d/m/Y', strtotime($post['fecha_registro'])) ?></dd>
            </dl>
        </div>

        <!-- Nota informativa: el postulante gestiona su propio CV -->
        <div class="alert alert-light border small">
            <i class="bi bi-info-circle text-info me-1"></i>
            El postulante sube y gestiona sus propios CVs desde el
            <a href="<?= BASE_URL ?>/portal/login.php" target="_blank">portal de postulantes</a>.
        </div>
    </div>

    <!-- Historial de postulaciones -->
    <div class="col-lg-8">
        <div class="table-card">
            <div class="table-card-header">
                <h5><i class="bi bi-briefcase me-1"></i> Historial de postulaciones (<?= count($historial) ?>)</h5>
                <?php if (puedeGestionarRRHH() && !empty($historial)): ?>
                <a href="<?= BASE_URL ?>/modules/evaluaciones/procesar.php"
                   class="btn btn-sm btn-info text-white">
                    <i class="bi bi-robot me-1"></i> Procesar pendientes
                </a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Convocatoria</th>
                            <th>CV</th>
                            <th>Coincidencia</th>
                            <th>Puntaje</th>
                            <th>Ranking</th>
                            <th>Fecha CV</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historial)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Sin postulaciones registradas.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($historial as $h): ?>
                        <tr>
                            <td>
                                <a href="<?= BASE_URL ?>/modules/convocatorias/ver.php?id=<?= $h['id_convocatoria'] ?>"
                                   class="text-decoration-none fw-semibold">
                                    <?= htmlspecialchars(mb_strimwidth($h['convocatoria'], 0, 35, '…')) ?>
                                </a>
                                <div>
                                    <span class="badge-estado badge-<?= strtolower($h['conv_estado']) ?>" style="font-size:.65rem">
                                        <?= $h['conv_estado'] ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php if ($h['ruta_archivo']): ?>
                                <a href="<?= BASE_URL ?>/uploads/curriculums/<?= urlencode($h['ruta_archivo']) ?>"
                                   target="_blank" class="btn btn-outline-secondary btn-sm py-0" title="Ver CV">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                                <?php if (!$h['procesado']): ?>
                                <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td style="min-width:120px">
                                <?php if ($h['porcentaje_coincidencia'] !== null): ?>
                                <div class="ranking-bar mb-1">
                                    <div class="ranking-bar-fill <?= $h['porcentaje_coincidencia'] >= 70 ? 'score-high' : ($h['porcentaje_coincidencia'] >= 40 ? 'score-medium' : 'score-low') ?>"
                                         style="width:<?= $h['porcentaje_coincidencia'] ?>%"></div>
                                </div>
                                <small class="fw-semibold"><?= $h['porcentaje_coincidencia'] ?>%</small>
                                <?php else: ?>
                                <small class="text-muted">Sin evaluar</small>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold <?= $h['puntaje'] !== null ? ($h['puntaje'] >= 70 ? 'text-success' : ($h['puntaje'] >= 40 ? 'text-warning' : 'text-danger')) : '' ?>">
                                <?= $h['puntaje'] ?? '—' ?>
                            </td>
                            <td class="fw-semibold">
                                <?= $h['ranking'] ? '#' . $h['ranking'] : '—' ?>
                            </td>
                            <td class="small text-muted">
                                <?= date('d/m/Y', strtotime($h['fecha_carga'])) ?>
                            </td>
                            <td>
                                <?php if ($h['id_evaluacion']): ?>
                                <a href="<?= BASE_URL ?>/modules/evaluaciones/ver.php?id=<?= $h['id_evaluacion'] ?>"
                                   class="btn btn-action bg-info text-white" title="Ver evaluación ML">
                                    <i class="bi bi-robot"></i>
                                </a>
                                <?php endif; ?>
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

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
