<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_DOCUMENTOS);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT d.*, t.nombre AS tipo, t.abreviatura,
           ao.nombre AS area_origen, ad.nombre AS area_destino,
           CONCAT(u.nombres,' ',u.apellido_paterno) AS registrado_por
    FROM documentos d
    JOIN tipos_documento t ON t.id_tipo_documento = d.id_tipo_documento
    LEFT JOIN areas ao ON ao.id_area = d.id_area_origen
    LEFT JOIN areas ad ON ad.id_area = d.id_area_destino
    LEFT JOIN usuarios u ON u.id_usuario = d.id_usuario
    WHERE d.id_documento = ?
");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    $_SESSION['error'] = 'Documento no encontrado.';
    header('Location: index.php');
    exit;
}

// Expedientes vinculados
$expedientes = $db->prepare("
    SELECT e.numero_expediente, e.asunto, e.estado
    FROM expediente_documento ed
    JOIN expedientes e ON e.id_expediente = ed.id_expediente
    WHERE ed.id_documento = ?
");
$expedientes->execute([$id]);
$expedientes = $expedientes->fetchAll();

$pageTitle = 'Ver Documento';
include __DIR__ . '/../../includes/layout_head.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h4 class="page-title mb-0">Detalle del Documento</h4>
    <div class="ms-auto d-flex gap-2">
        <a href="editar.php?id=<?= $id ?>" class="btn btn-warning btn-sm">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <button class="btn btn-outline-secondary btn-sm" onclick="imprimirSeccion('doc-detalle')">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
    </div>
</div>

<div id="doc-detalle">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="form-section">
                <h6><i class="bi bi-file-earmark-text me-1"></i> Información del documento</h6>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <div class="text-muted small">Tipo</div>
                        <div class="fw-semibold"><?= htmlspecialchars($doc['tipo']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Número</div>
                        <div class="fw-semibold"><?= htmlspecialchars($doc['numero_documento'] ?? '—') ?></div>
                    </div>
                    <div class="col-12 mt-2">
                        <div class="text-muted small">Asunto</div>
                        <div class="fw-semibold fs-5"><?= htmlspecialchars($doc['asunto']) ?></div>
                    </div>
                    <?php if ($doc['descripcion']): ?>
                    <div class="col-12 mt-2">
                        <div class="text-muted small">Descripción</div>
                        <div class="mt-1" style="white-space:pre-wrap"><?= htmlspecialchars($doc['descripcion']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($expedientes)): ?>
            <div class="form-section">
                <h6><i class="bi bi-folder2-open me-1"></i> Expedientes vinculados</h6>
                <ul class="list-group list-group-flush">
                    <?php foreach ($expedientes as $exp): ?>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>
                            <strong><?= htmlspecialchars($exp['numero_expediente']) ?></strong>
                            — <?= htmlspecialchars($exp['asunto'] ?? '') ?>
                        </span>
                        <span class="badge-estado badge-<?= strtolower($exp['estado']) ?>"><?= $exp['estado'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="form-section">
                <h6><i class="bi bi-info-circle me-1"></i> Metadatos</h6>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Estado</dt>
                    <dd class="col-7">
                        <span class="badge-estado badge-<?= strtolower($doc['estado']) ?>"><?= $doc['estado'] ?></span>
                    </dd>
                    <dt class="col-5 text-muted">Fecha doc.</dt>
                    <dd class="col-7"><?= $doc['fecha_documento'] ? date('d/m/Y', strtotime($doc['fecha_documento'])) : '—' ?></dd>
                    <dt class="col-5 text-muted">Área origen</dt>
                    <dd class="col-7"><?= htmlspecialchars($doc['area_origen'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Área destino</dt>
                    <dd class="col-7"><?= htmlspecialchars($doc['area_destino'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Registrado por</dt>
                    <dd class="col-7"><?= htmlspecialchars($doc['registrado_por'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Fecha registro</dt>
                    <dd class="col-7"><?= date('d/m/Y H:i', strtotime($doc['fecha_registro'])) ?></dd>
                </dl>
            </div>

            <?php if ($doc['ruta_archivo']): ?>
            <div class="form-section">
                <h6><i class="bi bi-paperclip me-1"></i> Archivo adjunto</h6>
                <p class="small text-muted mb-2"><?= htmlspecialchars($doc['nombre_archivo'] ?? $doc['ruta_archivo']) ?></p>
                <a href="<?= BASE_URL ?>/uploads/documentos/<?= urlencode($doc['ruta_archivo']) ?>"
                   target="_blank" class="btn btn-outline-primary btn-sm w-100">
                    <i class="bi bi-download me-1"></i> Descargar
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
