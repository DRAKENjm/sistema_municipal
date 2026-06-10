<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_EXPEDIENTES);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT e.*, a.nombre AS area_nombre,
           CONCAT(u.nombres,' ',u.apellido_paterno) AS creado_por
    FROM expedientes e
    LEFT JOIN areas a ON a.id_area = e.id_area
    LEFT JOIN usuarios u ON u.id_usuario = e.id_usuario
    WHERE e.id_expediente = ?
");
$stmt->execute([$id]);
$exp = $stmt->fetch();

if (!$exp) {
    $_SESSION['error'] = 'Expediente no encontrado.';
    header('Location: index.php');
    exit;
}

$documentos = $db->prepare("
    SELECT d.*, t.nombre AS tipo, t.abreviatura, ed.orden
    FROM expediente_documento ed
    JOIN documentos d ON d.id_documento = ed.id_documento
    JOIN tipos_documento t ON t.id_tipo_documento = d.id_tipo_documento
    WHERE ed.id_expediente = ?
    ORDER BY ed.orden
");
$documentos->execute([$id]);
$documentos = $documentos->fetchAll();

$pageTitle = 'Expediente ' . $exp['numero_expediente'];
include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h4 class="page-title mb-0 flex-fill">Expediente: <?= htmlspecialchars($exp['numero_expediente']) ?></h4>
    <a href="editar.php?id=<?= $id ?>" class="btn btn-warning btn-sm">
        <i class="bi bi-pencil me-1"></i> Editar
    </a>
    <button class="btn btn-outline-secondary btn-sm" onclick="imprimirSeccion('exp-detalle')">
        <i class="bi bi-printer me-1"></i> Imprimir
    </button>
</div>

<div id="exp-detalle">
<div class="row g-3">
    <div class="col-lg-4">
        <div class="form-section">
            <h6><i class="bi bi-info-circle me-1"></i> Información</h6>
            <dl class="row small mb-0">
                <dt class="col-5 text-muted">Número</dt>
                <dd class="col-7 fw-bold"><?= htmlspecialchars($exp['numero_expediente']) ?></dd>
                <dt class="col-5 text-muted">Estado</dt>
                <dd class="col-7">
                    <span class="badge-estado badge-<?= strtolower($exp['estado']) ?>"><?= $exp['estado'] ?></span>
                </dd>
                <dt class="col-5 text-muted">Área</dt>
                <dd class="col-7"><?= htmlspecialchars($exp['area_nombre'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Creado por</dt>
                <dd class="col-7"><?= htmlspecialchars($exp['creado_por'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Fecha</dt>
                <dd class="col-7"><?= date('d/m/Y H:i', strtotime($exp['fecha_creacion'])) ?></dd>
                <dt class="col-5 text-muted">Asunto</dt>
                <dd class="col-7"><?= htmlspecialchars($exp['asunto'] ?? '—') ?></dd>
            </dl>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="table-card">
            <div class="table-card-header">
                <h5><i class="bi bi-files me-1"></i> Documentos vinculados (<?= count($documentos) ?>)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr><th>#</th><th>Tipo</th><th>N°</th><th>Asunto</th><th>Estado</th><th>Fecha</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documentos)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">Sin documentos vinculados.</td></tr>
                        <?php else: ?>
                        <?php foreach ($documentos as $doc): ?>
                        <tr>
                            <td class="text-muted"><?= $doc['orden'] ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($doc['abreviatura']) ?></span></td>
                            <td class="small"><?= htmlspecialchars($doc['numero_documento'] ?? '—') ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>/modules/documentos/ver.php?id=<?= $doc['id_documento'] ?>"
                                   class="text-decoration-none text-dark">
                                    <?= htmlspecialchars(mb_strimwidth($doc['asunto'], 0, 50, '…')) ?>
                                </a>
                            </td>
                            <td><span class="badge-estado badge-<?= strtolower($doc['estado']) ?>"><?= $doc['estado'] ?></span></td>
                            <td class="small"><?= date('d/m/Y', strtotime($doc['fecha_documento'] ?? $doc['fecha_registro'])) ?></td>
                            <td>
                                <?php if ($doc['ruta_archivo']): ?>
                                <a href="<?= BASE_URL ?>/uploads/documentos/<?= urlencode($doc['ruta_archivo']) ?>"
                                   target="_blank" class="btn btn-outline-secondary btn-sm py-0" title="Descargar">
                                    <i class="bi bi-download"></i>
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
</div>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
