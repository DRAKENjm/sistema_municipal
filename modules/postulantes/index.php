<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$db = getDB();
$pageTitle = 'Postulantes';

$buscar = $_GET['buscar'] ?? '';
$params = [];
$where  = '1=1';

if ($buscar !== '') {
    $where = "(p.dni LIKE ? OR p.nombres LIKE ? OR p.apellido_paterno LIKE ? OR p.apellido_materno LIKE ?)";
    $params = ["%$buscar%", "%$buscar%", "%$buscar%", "%$buscar%"];
}

$stmt = $db->prepare("
    SELECT p.*,
           COUNT(DISTINCT cv.id_convocatoria) AS total_postulaciones,
           MAX(e.puntaje) AS mejor_puntaje
    FROM postulantes p
    LEFT JOIN curriculums cv ON cv.id_postulante = p.id_postulante
    LEFT JOIN evaluaciones_ml e ON e.id_postulante = p.id_postulante
    WHERE $where
    GROUP BY p.id_postulante
    ORDER BY p.fecha_registro DESC
");
$stmt->execute($params);
$postulantes = $stmt->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-people me-2"></i>Postulantes</h4>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/registro.php" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-box-arrow-up-right me-1"></i> Portal público
        </a>
    </div>
</div>

<div class="form-section mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-6">
            <label class="form-label small">Buscar por nombre o DNI</label>
            <input type="text" name="buscar" class="form-control form-control-sm"
                   placeholder="Nombre, apellido o DNI..." value="<?= htmlspecialchars($buscar) ?>">
        </div>
        <div class="col-sm-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search"></i> Buscar</button>
            <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-card-header">
        <h5><i class="bi bi-list-ul me-1"></i> Total: <?= count($postulantes) ?> postulante(s)</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead>
                <tr>
                    <th>DNI</th>
                    <th>Apellidos y nombres</th>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Postulaciones</th>
                    <th>Mejor puntaje</th>
                    <th>Registrado</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($postulantes)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No se encontraron postulantes.</td></tr>
                <?php else: ?>
                <?php foreach ($postulantes as $p): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($p['dni']) ?></td>
                    <td>
                        <a href="ver.php?id=<?= $p['id_postulante'] ?>" class="text-decoration-none text-dark fw-semibold">
                            <?= htmlspecialchars($p['apellido_paterno'] . ' ' . $p['apellido_materno'] . ', ' . $p['nombres']) ?>
                        </a>
                    </td>
                    <td><code class="small"><?= htmlspecialchars($p['usuario'] ?? '—') ?></code></td>
                    <td class="small"><?= htmlspecialchars($p['correo'] ?? '—') ?></td>
                    <td class="small"><?= htmlspecialchars($p['telefono'] ?? '—') ?></td>
                    <td class="text-center">
                        <span class="badge bg-secondary"><?= $p['total_postulaciones'] ?></span>
                    </td>
                    <td>
                        <?php if ($p['mejor_puntaje'] !== null): ?>
                        <span class="fw-bold <?= $p['mejor_puntaje'] >= 70 ? 'text-success' : ($p['mejor_puntaje'] >= 40 ? 'text-warning' : 'text-danger') ?>">
                            <?= $p['mejor_puntaje'] ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= date('d/m/Y', strtotime($p['fecha_registro'])) ?></td>
                    <td>
                        <?= $p['estado']
                            ? '<span class="badge bg-success">Activo</span>'
                            : '<span class="badge bg-danger">Bloqueado</span>' ?>
                    </td>
                    <td>
                        <a href="ver.php?id=<?= $p['id_postulante'] ?>"
                           class="btn btn-action bg-info text-white" title="Ver detalle">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php if ($p['estado']): ?>
                        <a href="cambiar_estado.php?id=<?= $p['id_postulante'] ?>"
                           class="btn btn-action bg-danger text-white" title="Bloquear cuenta"
                           data-confirm="¿Bloquear la cuenta de este postulante?">
                            <i class="bi bi-person-x"></i>
                        </a>
                        <?php else: ?>
                        <a href="cambiar_estado.php?id=<?= $p['id_postulante'] ?>"
                           class="btn btn-action bg-success text-white" title="Activar cuenta"
                           data-confirm="¿Activar la cuenta de este postulante?">
                            <i class="bi bi-person-check"></i>
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

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
