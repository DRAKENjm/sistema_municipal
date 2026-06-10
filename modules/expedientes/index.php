<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_EXPEDIENTES);

$db = getDB();
$pageTitle = 'Expedientes';

$buscar = $_GET['buscar'] ?? '';
$estado = $_GET['estado'] ?? '';
$params = [];
$where  = ['1=1'];

if ($buscar !== '') {
    $where[] = '(e.numero_expediente LIKE ? OR e.asunto LIKE ?)';
    $params[] = "%$buscar%"; $params[] = "%$buscar%";
}
if ($estado !== '') {
    $where[] = 'e.estado = ?';
    $params[] = $estado;
}

$stmt = $db->prepare("
    SELECT e.*, a.nombre AS area_nombre,
           CONCAT(u.nombres,' ',u.apellido_paterno) AS creado_por,
           COUNT(ed.id_documento) AS total_documentos
    FROM expedientes e
    LEFT JOIN areas a ON a.id_area = e.id_area
    LEFT JOIN usuarios u ON u.id_usuario = e.id_usuario
    LEFT JOIN expediente_documento ed ON ed.id_expediente = e.id_expediente
    WHERE " . implode(' AND ', $where) . "
    GROUP BY e.id_expediente
    ORDER BY e.fecha_creacion DESC
");
$stmt->execute($params);
$expedientes = $stmt->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-folder2-open me-2"></i>Expedientes</h4>
    <a href="crear.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nuevo expediente</a>
</div>

<div class="form-section mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-5">
            <label class="form-label small">Buscar</label>
            <input type="text" name="buscar" class="form-control form-control-sm"
                   placeholder="Número o asunto..." value="<?= htmlspecialchars($buscar) ?>">
        </div>
        <div class="col-sm-3">
            <label class="form-label small">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="ABIERTO"     <?= $estado==='ABIERTO'     ?'selected':'' ?>>Abierto</option>
                <option value="EN_PROCESO"  <?= $estado==='EN_PROCESO'  ?'selected':'' ?>>En proceso</option>
                <option value="CERRADO"     <?= $estado==='CERRADO'     ?'selected':'' ?>>Cerrado</option>
                <option value="ARCHIVADO"   <?= $estado==='ARCHIVADO'   ?'selected':'' ?>>Archivado</option>
            </select>
        </div>
        <div class="col-sm-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search"></i></button>
            <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-card-header">
        <h5><i class="bi bi-list-ul me-1"></i> <?= count($expedientes) ?> expediente(s)</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead>
                <tr>
                    <th>N° Expediente</th>
                    <th>Asunto</th>
                    <th>Área</th>
                    <th>Estado</th>
                    <th>Docs.</th>
                    <th>Creado por</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expedientes)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No se encontraron expedientes.</td></tr>
                <?php else: ?>
                <?php foreach ($expedientes as $exp): ?>
                <tr>
                    <td class="fw-bold text-primary-custom"><?= htmlspecialchars($exp['numero_expediente']) ?></td>
                    <td>
                        <a href="ver.php?id=<?= $exp['id_expediente'] ?>" class="text-decoration-none text-dark">
                            <?= htmlspecialchars(mb_strimwidth($exp['asunto'] ?? '', 0, 50, '…')) ?>
                        </a>
                    </td>
                    <td class="small"><?= htmlspecialchars($exp['area_nombre'] ?? '—') ?></td>
                    <td><span class="badge-estado badge-<?= strtolower($exp['estado']) ?>"><?= $exp['estado'] ?></span></td>
                    <td><span class="badge bg-light text-dark"><?= $exp['total_documentos'] ?></span></td>
                    <td class="small"><?= htmlspecialchars($exp['creado_por'] ?? '—') ?></td>
                    <td class="small"><?= date('d/m/Y', strtotime($exp['fecha_creacion'])) ?></td>
                    <td>
                        <a href="ver.php?id=<?= $exp['id_expediente'] ?>"
                           class="btn btn-action bg-info text-white" title="Ver"><i class="bi bi-eye"></i></a>
                        <a href="editar.php?id=<?= $exp['id_expediente'] ?>"
                           class="btn btn-action bg-warning text-dark" title="Editar"><i class="bi bi-pencil"></i></a>
                        <a href="eliminar.php?id=<?= $exp['id_expediente'] ?>"
                           class="btn btn-action bg-danger text-white"
                           data-confirm="¿Eliminar este expediente?"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
