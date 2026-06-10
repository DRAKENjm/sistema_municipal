<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT e.*,
           CONCAT(p.nombres,' ',p.apellido_paterno,' ',p.apellido_materno) AS postulante,
           p.dni, p.correo, p.telefono,
           c.titulo AS convocatoria, c.id_convocatoria,
           cv.nombre_archivo, cv.ruta_archivo
    FROM evaluaciones_ml e
    JOIN postulantes p ON p.id_postulante = e.id_postulante
    JOIN convocatorias c ON c.id_convocatoria = e.id_convocatoria
    JOIN curriculums cv ON cv.id_curriculum = e.id_curriculum
    WHERE e.id_evaluacion = ?
");
$stmt->execute([$id]);
$ev = $stmt->fetch();

if (!$ev) {
    $_SESSION['error'] = 'Evaluación no encontrada.';
    header('Location: index.php');
    exit;
}

$detalles = json_decode($ev['detalles_json'] ?? '{}', true) ?: [];
$pageTitle = 'Evaluación ML — ' . mb_strimwidth($ev['postulante'], 0, 30, '…');
include __DIR__ . '/../../includes/layout_head.php';
?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
    <a href="index.php?conv=<?= $ev['id_convocatoria'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="page-title mb-0 flex-fill">Resultado de Evaluación ML</h4>
    <button class="btn btn-outline-secondary btn-sm" onclick="imprimirSeccion('eval-detalle')">
        <i class="bi bi-printer me-1"></i> Imprimir
    </button>
</div>

<div id="eval-detalle">
<div class="row g-3">
    <!-- Puntaje principal -->
    <div class="col-md-4">
        <div class="table-card p-3 text-center">
            <div class="text-muted small mb-1">Puntaje total</div>
            <div class="display-3 fw-bold <?= $ev['puntaje'] >= 70 ? 'text-success' : ($ev['puntaje'] >= 40 ? 'text-warning' : 'text-danger') ?>">
                <?= $ev['puntaje'] ?>
            </div>
            <div class="text-muted">/100 puntos</div>
            <hr>
            <div class="text-muted small mb-1">Coincidencia con perfil</div>
            <div class="ranking-bar mb-1" style="height:14px">
                <div class="ranking-bar-fill <?= $ev['porcentaje_coincidencia'] >= 70 ? 'score-high' : ($ev['porcentaje_coincidencia'] >= 40 ? 'score-medium' : 'score-low') ?>"
                     style="width:<?= $ev['porcentaje_coincidencia'] ?>%"></div>
            </div>
            <div class="fw-bold fs-4"><?= $ev['porcentaje_coincidencia'] ?>%</div>
            <hr>
            <?php if ($ev['ranking']): ?>
            <div class="text-muted small">Posición en ranking</div>
            <div class="display-5 fw-bold text-primary-custom">#<?= $ev['ranking'] ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Datos del postulante y convocatoria -->
    <div class="col-md-4">
        <div class="form-section h-100">
            <h6><i class="bi bi-person me-1"></i> Postulante</h6>
            <dl class="row small mb-0">
                <dt class="col-4 text-muted">Nombre</dt>
                <dd class="col-8 fw-semibold"><?= htmlspecialchars($ev['postulante']) ?></dd>
                <dt class="col-4 text-muted">DNI</dt>
                <dd class="col-8"><?= htmlspecialchars($ev['dni']) ?></dd>
                <dt class="col-4 text-muted">Correo</dt>
                <dd class="col-8"><?= htmlspecialchars($ev['correo'] ?? '—') ?></dd>
                <dt class="col-4 text-muted">Teléfono</dt>
                <dd class="col-8"><?= htmlspecialchars($ev['telefono'] ?? '—') ?></dd>
            </dl>
            <hr>
            <h6><i class="bi bi-megaphone me-1"></i> Convocatoria</h6>
            <p class="fw-semibold small"><?= htmlspecialchars($ev['convocatoria']) ?></p>
            <?php if ($ev['nombre_archivo']): ?>
            <a href="<?= BASE_URL ?>/uploads/curriculums/<?= urlencode(basename($ev['nombre_archivo'])) ?>"
               target="_blank" class="btn btn-outline-primary btn-sm w-100 mt-1">
                <i class="bi bi-download me-1"></i> Descargar CV
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Metadatos ML -->
    <div class="col-md-4">
        <div class="form-section h-100">
            <h6><i class="bi bi-robot me-1"></i> Análisis ML</h6>
            <dl class="row small mb-0">
                <dt class="col-5 text-muted">Modelo</dt>
                <dd class="col-7"><?= htmlspecialchars($ev['modelo_version'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Fecha</dt>
                <dd class="col-7"><?= date('d/m/Y H:i', strtotime($ev['fecha_evaluacion'])) ?></dd>
            </dl>

            <?php if ($ev['verificado']): ?>
            <hr>
            <h6><i class="bi bi-check-circle me-1"></i> Verificación RRHH</h6>
            <dl class="row small mb-0">
                <dt class="col-5 text-muted">Resultado</dt>
                <dd class="col-7">
                    <?php
                    $resultado = $ev['resultado_verificacion'] ?? 'EN_ESPERA';
                    $badgeClass = $resultado === 'ACEPTADO' ? 'success' : ($resultado === 'RECHAZADO' ? 'danger' : 'secondary');
                    ?>
                    <span class="badge bg-<?= $badgeClass ?>"><?= str_replace('_', ' ', $resultado) ?></span>
                </dd>
                <dt class="col-5 text-muted">Fecha</dt>
                <dd class="col-7"><?= $ev['fecha_verificacion'] ? date('d/m/Y H:i', strtotime($ev['fecha_verificacion'])) : '—' ?></dd>
                <dt class="col-5 text-muted">Por</dt>
                <dd class="col-7"><?= htmlspecialchars($ev['verificado_por'] ?? '—') ?></dd>
            </dl>
            <?php if ($ev['comentario_verificacion']): ?>
            <div class="alert alert-light border small mt-2 mb-0">
                <strong>Comentario:</strong><br>
                <?= nl2br(htmlspecialchars($ev['comentario_verificacion'])) ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($ev['observaciones']): ?>
            <hr>
            <h6>Observaciones ML</h6>
            <p class="small"><?= htmlspecialchars($ev['observaciones']) ?></p>
            <?php endif; ?>

            <?php if (!$ev['verificado'] && puedeGestionarRRHH()): ?>
            <hr>
            <button class="btn btn-success btn-sm w-100"
                    data-bs-toggle="modal"
                    data-bs-target="#modalVerificar"
                    data-id="<?= $id ?>"
                    data-postulante="<?= htmlspecialchars($ev['postulante']) ?>"
                    data-puntaje="<?= $ev['puntaje'] ?>">
                <i class="bi bi-check-circle me-1"></i> Verificar CV
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detalles del análisis -->
    <?php if (!empty($detalles)): ?>
    <div class="col-12">
        <div class="form-section">
            <h6><i class="bi bi-bar-chart me-1"></i> Desglose del análisis</h6>
            <div class="row g-3">
                <?php foreach ($detalles as $key => $val): ?>
                <div class="col-sm-6 col-md-4">
                    <div class="p-3 bg-light rounded">
                        <div class="text-muted small"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></div>
                        <?php if (is_array($val)): ?>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <?php foreach ($val as $item): ?>
                                <span class="badge bg-info text-dark"><?= htmlspecialchars($item) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="fw-semibold"><?= htmlspecialchars((string)$val) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: VERIFICAR CV
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerificar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="verificar_cv.php">
                <div class="modal-header" style="background:#198754;color:#fff">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle me-2"></i>Verificar Evaluación
                    </h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_evaluacion" value="<?= $id ?>">
                    
                    <div class="text-center mb-3 pb-3 border-bottom">
                        <div class="fw-bold fs-5"><?= htmlspecialchars($ev['postulante']) ?></div>
                        <div class="mt-2">
                            <span class="badge bg-primary fs-6 px-3 py-2">
                                Puntaje ML: <?= $ev['puntaje'] ?>/100
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
                    <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> Guardar verificación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
