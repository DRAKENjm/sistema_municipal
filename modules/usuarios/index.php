<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_USUARIOS);

$db        = getDB();
$pageTitle = 'Gestión de Usuarios';

// ── Filtros ───────────────────────────────────────────────
$filtroRol    = (int)($_GET['rol']    ?? 0);
$filtroEstado = $_GET['estado']       ?? '';
$buscar       = trim($_GET['buscar']  ?? '');

$where  = ['1=1'];
$params = [];

if ($filtroRol > 0) {
    $where[]  = 'u.id_rol = ?';
    $params[] = $filtroRol;
}
if ($filtroEstado !== '') {
    $where[]  = 'u.estado = ?';
    $params[] = (int)$filtroEstado;
}
if ($buscar !== '') {
    $where[]  = '(u.dni LIKE ? OR u.usuario LIKE ? OR u.nombres LIKE ? OR u.apellido_paterno LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$whereStr = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT u.*, r.nombre AS rol_nombre, a.nombre AS area_nombre
    FROM usuarios u
    JOIN roles r ON r.id_rol = u.id_rol
    LEFT JOIN areas a ON a.id_area = u.id_area
    WHERE $whereStr
    ORDER BY u.estado DESC, r.id_rol ASC, u.apellido_paterno ASC
");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$roles  = $db->query("SELECT * FROM roles ORDER BY id_rol")->fetchAll();

// Stats rápidas
$statsPorRol = $db->query("
    SELECT r.nombre, COUNT(u.id_usuario) AS total,
           SUM(u.estado) AS activos
    FROM roles r
    LEFT JOIN usuarios u ON u.id_rol = r.id_rol
    GROUP BY r.id_rol
    ORDER BY r.id_rol
")->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="page-title mb-0">
        <i class="bi bi-person-gear me-2"></i>Usuarios del sistema
    </h4>
    <a href="crear.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nuevo usuario
    </a>
</div>

<!-- Stats por rol -->
<div class="row g-2 mb-4">
    <?php
    $colores = ['blue', 'green', 'orange', 'purple'];
    foreach ($statsPorRol as $i => $s):
    ?>
    <div class="col-6 col-xl-3">
        <div class="stat-card <?= $colores[$i % 4] ?>" style="padding:.9rem 1rem">
            <i class="bi bi-person-badge stat-icon" style="font-size:1.6rem"></i>
            <div>
                <div class="stat-value" style="font-size:1.4rem"><?= $s['activos'] ?><span class="fs-6 fw-normal opacity-75">/<?= $s['total'] ?></span></div>
                <div class="stat-label"><?= htmlspecialchars($s['nombre']) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filtros -->
<div class="form-section mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-4">
            <label class="form-label small">Buscar</label>
            <input type="text" name="buscar" class="form-control form-control-sm"
                   placeholder="DNI, usuario, nombre…"
                   value="<?= htmlspecialchars($buscar) ?>">
        </div>
        <div class="col-sm-3">
            <label class="form-label small">Rol</label>
            <select name="rol" class="form-select form-select-sm">
                <option value="">Todos los roles</option>
                <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id_rol'] ?>" <?= $filtroRol === (int)$r['id_rol'] ? 'selected':'' ?>>
                    <?= htmlspecialchars($r['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-2">
            <label class="form-label small">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="1" <?= $filtroEstado === '1' ? 'selected':'' ?>>Activos</option>
                <option value="0" <?= $filtroEstado === '0' ? 'selected':'' ?>>Inactivos</option>
            </select>
        </div>
        <div class="col-sm-3 d-flex gap-2">
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
            <?= count($usuarios) ?> usuario(s) encontrado(s)
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead>
                <tr>
                    <th>DNI</th>
                    <th>Apellidos y nombres</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Área asignada</th>
                    <th>Correo</th>
                    <th>Estado</th>
                    <th>Registro</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        No se encontraron usuarios con los filtros aplicados.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($usuarios as $u): ?>
                <tr class="<?= !$u['estado'] ? 'opacity-50' : '' ?>">
                    <td class="fw-semibold"><?= htmlspecialchars($u['dni']) ?></td>
                    <td>
                        <div class="fw-semibold">
                            <?= htmlspecialchars($u['apellido_paterno'] . ' ' . $u['apellido_materno']) ?>
                        </div>
                        <div class="text-muted small"><?= htmlspecialchars($u['nombres']) ?></div>
                    </td>
                    <td><code class="small"><?= htmlspecialchars($u['usuario']) ?></code></td>
                    <td>
                        <?php
                        $rolColors = [
                            1 => 'bg-danger',
                            2 => 'bg-primary',
                            3 => 'bg-warning text-dark',
                            4 => 'bg-success',
                        ];
                        $rc = $rolColors[$u['id_rol']] ?? 'bg-secondary';
                        ?>
                        <span class="badge <?= $rc ?>"><?= htmlspecialchars($u['rol_nombre']) ?></span>
                    </td>
                    <td class="small"><?= htmlspecialchars($u['area_nombre'] ?? '—') ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($u['correo'] ?? '—') ?></td>
                    <td>
                        <?= $u['estado']
                            ? '<span class="badge bg-success">Activo</span>'
                            : '<span class="badge bg-secondary">Inactivo</span>' ?>
                    </td>
                    <td class="small text-muted"><?= date('d/m/Y', strtotime($u['fecha_registro'])) ?></td>
                    <td>
                        <!-- Editar -->
                        <a href="editar.php?id=<?= $u['id_usuario'] ?>"
                           class="btn btn-action bg-warning text-dark" title="Editar datos">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <!-- Activar / Desactivar (no puede hacerlo con su propia cuenta) -->
                        <?php
                        $propioUsuario = ($u['id_usuario'] === (int)(usuarioActual()['id_usuario'] ?? 0));
                        ?>
                        <?php if (!$propioUsuario): ?>
                        <a href="cambiar_estado.php?id=<?= $u['id_usuario'] ?>"
                           class="btn btn-action <?= $u['estado'] ? 'bg-danger text-white' : 'bg-success text-white' ?>"
                           title="<?= $u['estado'] ? 'Desactivar usuario' : 'Activar usuario' ?>"
                           data-confirm="<?= $u['estado'] ? '¿Desactivar acceso de este usuario?' : '¿Activar acceso de este usuario?' ?>">
                            <i class="bi bi-<?= $u['estado'] ? 'person-x' : 'person-check' ?>"></i>
                        </a>
                        <?php else: ?>
                        <span class="btn btn-action bg-light text-muted"
                              title="No puede desactivar su propia cuenta">
                            <i class="bi bi-lock"></i>
                        </span>
                        <?php endif; ?>
                        <!-- Resetear contraseña -->
                        <a href="resetear_password.php?id=<?= $u['id_usuario'] ?>"
                           class="btn btn-action bg-info text-white"
                           title="Resetear contraseña"
                           data-confirm="¿Resetear la contraseña de este usuario? Se asignará: Reset2026@">
                            <i class="bi bi-key"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
