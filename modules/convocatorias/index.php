<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$db = getDB();
$pageTitle = 'Convocatorias';

$filtroEstado = $_GET['estado'] ?? '';
$filtroBuscar = $_GET['buscar'] ?? '';

$where  = ['1=1'];
$params = [];
if ($filtroEstado !== '') { $where[] = 'c.estado = ?'; $params[] = $filtroEstado; }
if ($filtroBuscar !== '') { $where[] = 'c.titulo LIKE ?'; $params[] = "%$filtroBuscar%"; }

$whereStr = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT c.*, a.nombre AS area_nombre,
           (SELECT COUNT(*) FROM curriculums cv WHERE cv.id_convocatoria = c.id_convocatoria) AS total_postulantes,
           (SELECT COUNT(*) FROM curriculums cv WHERE cv.id_convocatoria = c.id_convocatoria AND cv.procesado = 0) AS cvs_pendientes
    FROM convocatorias c
    LEFT JOIN areas a ON a.id_area = c.id_area
    WHERE $whereStr
    ORDER BY 
        (SELECT COUNT(*) FROM curriculums cv WHERE cv.id_convocatoria = c.id_convocatoria AND cv.procesado = 0) DESC,
        c.fecha_registro DESC
");
$stmt->execute($params);
$convocatorias = $stmt->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-megaphone me-2"></i>Convocatorias</h4>
    <?php if (puedeGestionarRRHH()): ?>
    <a href="crear.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nueva convocatoria
    </a>
    <?php endif; ?>
</div>

<!-- Filtros -->
<div class="form-section mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-5">
            <label class="form-label small">Buscar</label>
            <input type="text" name="buscar" class="form-control form-control-sm"
                   placeholder="Título..." value="<?= htmlspecialchars($filtroBuscar) ?>">
        </div>
        <div class="col-sm-3">
            <label class="form-label small">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="ACTIVA"    <?= $filtroEstado==='ACTIVA'    ?'selected':'' ?>>Activa</option>
                <option value="BORRADOR"  <?= $filtroEstado==='BORRADOR'  ?'selected':'' ?>>Borrador</option>
                <option value="CERRADA"   <?= $filtroEstado==='CERRADA'   ?'selected':'' ?>>Cerrada</option>
                <option value="CANCELADA" <?= $filtroEstado==='CANCELADA' ?'selected':'' ?>>Cancelada</option>
            </select>
        </div>
        <div class="col-sm-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search"></i> Filtrar</button>
            <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
        </div>
    </form>
</div>

<div class="row g-3">
    <?php if (empty($convocatorias)): ?>
    <div class="col-12">
        <div class="table-card p-4 text-center text-muted">
            <i class="bi bi-megaphone display-5 d-block mb-2 opacity-25"></i>
            No se encontraron convocatorias.
        </div>
    </div>
    <?php else: ?>
    <?php foreach ($convocatorias as $c): ?>
    <div class="col-md-6 col-xl-4">
        <div class="table-card h-100 p-3 d-flex flex-column position-relative">
            <?php if ($c['cvs_pendientes'] > 0): ?>
            <!-- Badge de notificación flotante -->
            <div class="position-absolute top-0 end-0 m-2" style="z-index: 10;">
                <span class="badge rounded-pill bg-warning text-dark d-flex align-items-center gap-1 px-2 py-1 shadow-sm"
                      style="font-size: 0.85rem; animation: pulse 2s ease-in-out infinite;"
                      title="<?= $c['cvs_pendientes'] ?> CV(s) pendientes de procesar">
                    <i class="bi bi-bell-fill"></i>
                    <strong><?= $c['cvs_pendientes'] ?></strong>
                </span>
            </div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge-estado badge-<?= strtolower($c['estado']) ?>"><?= $c['estado'] ?></span>
                <small class="text-muted"><?= date('d/m/Y', strtotime($c['fecha_registro'])) ?></small>
            </div>
            <h6 class="fw-bold text-primary-custom mb-1"><?= htmlspecialchars($c['titulo']) ?></h6>
            <p class="text-muted small mb-2"><?= htmlspecialchars($c['area_nombre'] ?? 'Sin área') ?></p>
            <?php if ($c['descripcion']): ?>
            <p class="small text-muted mb-2"><?= htmlspecialchars(mb_strimwidth($c['descripcion'], 0, 100, '…')) ?></p>
            <?php endif; ?>
            <div class="d-flex gap-3 text-muted small mb-3">
                <?php if ($c['fecha_inicio']): ?>
                <span><i class="bi bi-calendar-event me-1"></i><?= date('d/m/Y', strtotime($c['fecha_inicio'])) ?></span>
                <?php endif; ?>
                <?php if ($c['fecha_fin']): ?>
                <span><i class="bi bi-calendar-x me-1"></i><?= date('d/m/Y', strtotime($c['fecha_fin'])) ?></span>
                <?php endif; ?>
            </div>
            <div class="mt-auto">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge bg-light text-dark">
                        <i class="bi bi-people me-1"></i><?= $c['total_postulantes'] ?> postulante(s)
                    </span>
                    <?php if ($c['salario_referencial']): ?>
                    <span class="text-success fw-semibold small">S/ <?= number_format($c['salario_referencial'], 2) ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <a href="ver.php?id=<?= $c['id_convocatoria'] ?>" class="btn btn-outline-primary btn-sm flex-fill">
                        <i class="bi bi-eye me-1"></i> Ver
                    </a>
                    <?php if (puedeGestionarRRHH()): ?>
                    <a href="editar.php?id=<?= $c['id_convocatoria'] ?>" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <a href="eliminar.php?id=<?= $c['id_convocatoria'] ?>"
                       class="btn btn-outline-danger btn-sm"
                       data-confirm="¿Eliminar esta convocatoria?">
                        <i class="bi bi-trash"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
