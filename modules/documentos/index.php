<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_DOCUMENTOS);

$db = getDB();
$pageTitle = 'Documentos';

// Filtros
$filtroEstado = $_GET['estado']  ?? '';
$filtroTipo   = $_GET['tipo']    ?? '';
$filtroBuscar = $_GET['buscar']  ?? '';
$pagina       = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina    = 15;
$offset       = ($pagina - 1) * $porPagina;

$where = ['1=1'];
$params = [];

if ($filtroEstado !== '') {
    $where[] = 'd.estado = ?';
    $params[] = $filtroEstado;
}
if ($filtroTipo !== '') {
    $where[] = 'd.id_tipo_documento = ?';
    $params[] = $filtroTipo;
}
if ($filtroBuscar !== '') {
    $where[] = '(d.asunto LIKE ? OR d.numero_documento LIKE ?)';
    $params[] = "%$filtroBuscar%";
    $params[] = "%$filtroBuscar%";
}

$whereStr = implode(' AND ', $where);

$total  = $db->prepare("SELECT COUNT(*) FROM documentos d WHERE $whereStr");
$total->execute($params);
$total  = (int)$total->fetchColumn();
$paginas = max(1, ceil($total / $porPagina));

$stmt = $db->prepare("
    SELECT d.*, t.nombre AS tipo, t.abreviatura,
           ao.nombre AS area_origen, ad.nombre AS area_destino,
           CONCAT(u.nombres, ' ', u.apellido_paterno) AS registrado_por
    FROM documentos d
    JOIN tipos_documento t ON t.id_tipo_documento = d.id_tipo_documento
    LEFT JOIN areas ao ON ao.id_area = d.id_area_origen
    LEFT JOIN areas ad ON ad.id_area = d.id_area_destino
    LEFT JOIN usuarios u ON u.id_usuario = d.id_usuario
    WHERE $whereStr
    ORDER BY d.fecha_registro DESC
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute($params);
$documentos = $stmt->fetchAll();

$tipos = $db->query("SELECT * FROM tipos_documento WHERE activo=1 ORDER BY nombre")->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0">
        <i class="bi bi-file-earmark-text me-2"></i>Documentos
    </h4>
    <a href="<?= BASE_URL ?>/modules/documentos/crear.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nuevo documento
    </a>
</div>

<!-- Filtros -->
<div class="form-section mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-4">
            <label class="form-label small">Buscar</label>
            <input type="text" name="buscar" class="form-control form-control-sm"
                   placeholder="Asunto o número..." value="<?= htmlspecialchars($filtroBuscar) ?>">
        </div>
        <div class="col-sm-3">
            <label class="form-label small">Tipo</label>
            <select name="tipo" class="form-select form-select-sm">
                <option value="">Todos los tipos</option>
                <?php foreach ($tipos as $t): ?>
                <option value="<?= $t['id_tipo_documento'] ?>"
                    <?= $filtroTipo == $t['id_tipo_documento'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-3">
            <label class="form-label small">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos los estados</option>
                <option value="REGISTRADO"  <?= $filtroEstado === 'REGISTRADO'  ? 'selected':'' ?>>Registrado</option>
                <option value="EN_TRAMITE"  <?= $filtroEstado === 'EN_TRAMITE'  ? 'selected':'' ?>>En trámite</option>
                <option value="DERIVADO"    <?= $filtroEstado === 'DERIVADO'    ? 'selected':'' ?>>Derivado</option>
                <option value="ARCHIVADO"   <?= $filtroEstado === 'ARCHIVADO'   ? 'selected':'' ?>>Archivado</option>
                <option value="ANULADO"     <?= $filtroEstado === 'ANULADO'     ? 'selected':'' ?>>Anulado</option>
            </select>
        </div>
        <div class="col-sm-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                <i class="bi bi-search"></i> Filtrar
            </button>
            <a href="?" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x"></i>
            </a>
        </div>
    </form>
</div>

<!-- Tabla -->
<div class="table-card">
    <div class="table-card-header">
        <h5>
            <i class="bi bi-list-ul me-1"></i>
            Resultados: <span class="text-muted fw-normal"><?= $total ?> documentos</span>
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>N° Doc.</th>
                    <th>Tipo</th>
                    <th>Asunto</th>
                    <th>Origen → Destino</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($documentos)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No se encontraron documentos.</td></tr>
                <?php else: ?>
                <?php foreach ($documentos as $doc): ?>
                <tr>
                    <td class="text-muted small"><?= $doc['id_documento'] ?></td>
                    <td><span class="badge bg-light text-dark"><?= htmlspecialchars($doc['numero_documento'] ?? '—') ?></span></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($doc['abreviatura'] ?? $doc['tipo']) ?></span></td>
                    <td>
                        <a href="ver.php?id=<?= $doc['id_documento'] ?>" class="text-decoration-none text-dark fw-semibold">
                            <?= htmlspecialchars(mb_strimwidth($doc['asunto'], 0, 50, '…')) ?>
                        </a>
                    </td>
                    <td class="small">
                        <?= htmlspecialchars($doc['area_origen'] ?? '—') ?>
                        <i class="bi bi-arrow-right text-muted"></i>
                        <?= htmlspecialchars($doc['area_destino'] ?? '—') ?>
                    </td>
                    <td><span class="badge-estado badge-<?= strtolower($doc['estado']) ?>"><?= $doc['estado'] ?></span></td>
                    <td class="small"><?= date('d/m/Y', strtotime($doc['fecha_registro'])) ?></td>
                    <td>
                        <a href="ver.php?id=<?= $doc['id_documento'] ?>"
                           class="btn btn-action btn-sm bg-info text-white" title="Ver detalle">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="editar.php?id=<?= $doc['id_documento'] ?>"
                           class="btn btn-action btn-sm bg-warning text-dark" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="eliminar.php?id=<?= $doc['id_documento'] ?>"
                           class="btn btn-action btn-sm bg-danger text-white"
                           data-confirm="¿Eliminar este documento? Esta acción no se puede deshacer."
                           title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($paginas > 1): ?>
    <div class="p-3">
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $paginas; $i++): ?>
                <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                    <a class="page-link"
                       href="?pagina=<?= $i ?>&estado=<?= urlencode($filtroEstado) ?>&tipo=<?= urlencode($filtroTipo) ?>&buscar=<?= urlencode($filtroBuscar) ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
