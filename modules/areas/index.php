<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_AREAS);

$db      = getDB();
$pageTitle = 'Áreas de la Municipalidad';
$errores   = [];

// ── Procesar POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);

    if ($accion === 'crear') {
        if ($nombre === '') {
            $errores[] = 'El nombre es obligatorio.';
        } else {
            try {
                $db->prepare("INSERT INTO areas (nombre, codigo) VALUES (?, ?)")
                   ->execute([$nombre, $codigo ?: null]);
                registrarBitacora('CREAR', 'areas', (int)$db->lastInsertId(), "Área creada: $nombre");
                $_SESSION['success'] = "Área \"$nombre\" creada correctamente.";
                header('Location: index.php');
                exit;
            } catch (PDOException) {
                $errores[] = 'Ya existe un área con ese nombre.';
            }
        }

    } elseif ($accion === 'editar') {
        if ($nombre === '') {
            $errores[] = 'El nombre es obligatorio.';
        } else {
            try {
                $db->prepare("UPDATE areas SET nombre = ?, codigo = ? WHERE id_area = ?")
                   ->execute([$nombre, $codigo ?: null, $id]);
                registrarBitacora('EDITAR', 'areas', $id, "Área actualizada: $nombre");
                $_SESSION['success'] = "Área actualizada correctamente.";
                header('Location: index.php');
                exit;
            } catch (PDOException) {
                $errores[] = 'Ya existe otra área con ese nombre.';
            }
        }

    } elseif ($accion === 'toggle') {
        // Verificar que el área no tenga usuarios activos antes de desactivar
        $stmt = $db->prepare("SELECT activo FROM areas WHERE id_area = ?");
        $stmt->execute([$id]);
        $area = $stmt->fetch();

        if ($area && $area['activo']) {
            // Va a desactivar — revisar si hay usuarios activos en esta área
            $usrsActivos = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE id_area = ? AND estado = 1");
            $usrsActivos->execute([$id]);
            if ((int)$usrsActivos->fetchColumn() > 0) {
                $_SESSION['error'] = 'No se puede desactivar: hay usuarios activos asignados a esta área.';
                header('Location: index.php');
                exit;
            }
        }

        $db->prepare("UPDATE areas SET activo = NOT activo WHERE id_area = ?")->execute([$id]);
        $accionLog = $area['activo'] ? 'DESACTIVAR_AREA' : 'ACTIVAR_AREA';
        registrarBitacora($accionLog, 'areas', $id, 'Estado de área cambiado');
        header('Location: index.php');
        exit;
    }
}

// ── Cargar área a editar si viene ?editar=ID ──────────────
$editando = null;
if (!empty($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM areas WHERE id_area = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $editando = $stmt->fetch();
}

// ── Listar áreas agrupadas por estado ────────────────────
$areas = $db->query("
    SELECT a.*,
           (SELECT COUNT(*) FROM usuarios u WHERE u.id_area = a.id_area AND u.estado = 1) AS total_usuarios,
           (SELECT COUNT(*) FROM documentos d WHERE d.id_area_origen = a.id_area OR d.id_area_destino = a.id_area) AS total_docs
    FROM areas a
    ORDER BY a.activo DESC, a.nombre ASC
")->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<h4 class="page-title"><i class="bi bi-diagram-3 me-2"></i>Áreas de la Municipalidad</h4>

<div class="row g-3">

    <!-- ── Panel izquierdo: Crear / Editar ── -->
    <div class="col-lg-4">
        <div class="form-section">
            <?php if ($editando): ?>
            <h6 class="text-warning">
                <i class="bi bi-pencil me-1"></i> Editando área
            </h6>
            <form method="POST">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id"     value="<?= $editando['id_area'] ?>">
            <?php else: ?>
            <h6><i class="bi bi-plus-circle me-1"></i> Nueva área</h6>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
            <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required
                           maxlength="100"
                           placeholder="Ej: Gerencia de Desarrollo Urbano"
                           value="<?= htmlspecialchars($editando['nombre'] ?? $_POST['nombre'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Código</label>
                    <input type="text" name="codigo" class="form-control"
                           maxlength="20" style="text-transform:uppercase"
                           placeholder="Ej: GDU, RRHH, TI"
                           value="<?= htmlspecialchars($editando['codigo'] ?? $_POST['codigo'] ?? '') ?>">
                    <div class="form-text">Abreviatura interna. Se muestra como etiqueta en las listas.</div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn <?= $editando ? 'btn-warning' : 'btn-primary' ?>">
                        <i class="bi bi-<?= $editando ? 'save' : 'plus-lg' ?> me-1"></i>
                        <?= $editando ? 'Guardar cambios' : 'Agregar área' ?>
                    </button>
                    <?php if ($editando): ?>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x me-1"></i> Cancelar edición
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Info -->
        <div class="alert alert-light border small mt-3">
            <i class="bi bi-info-circle text-info me-1"></i>
            Solo puedes <strong>desactivar</strong> un área si no tiene usuarios activos asignados.<br>
            Los documentos y expedientes existentes no se eliminan al desactivar.
        </div>
    </div>

    <!-- ── Panel derecho: Tabla de áreas ── -->
    <div class="col-lg-8">
        <div class="table-card">
            <div class="table-card-header">
                <h5><i class="bi bi-list-ul me-1"></i> <?= count($areas) ?> área(s) registradas</h5>
                <input type="text" class="form-control form-control-sm w-auto"
                       id="buscarArea" placeholder="Buscar..."
                       onkeyup="filtrarTabla('buscarArea','tablaAreas')">
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="tablaAreas">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th class="text-center">Usuarios</th>
                            <th class="text-center">Docs.</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($areas as $a): ?>
                        <tr class="<?= !$a['activo'] ? 'opacity-50' : '' ?>
                                   <?= $editando && $editando['id_area'] == $a['id_area'] ? 'table-warning' : '' ?>">
                            <td>
                                <span class="badge <?= $a['activo'] ? 'bg-secondary' : 'bg-light text-muted' ?>">
                                    <?= htmlspecialchars($a['codigo'] ?? '—') ?>
                                </span>
                            </td>
                            <td class="fw-semibold"><?= htmlspecialchars($a['nombre']) ?></td>
                            <td class="text-center">
                                <span class="badge <?= $a['total_usuarios'] > 0 ? 'bg-primary' : 'bg-light text-muted' ?>">
                                    <?= $a['total_usuarios'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark"><?= $a['total_docs'] ?></span>
                            </td>
                            <td>
                                <?= $a['activo']
                                    ? '<span class="badge bg-success">Activa</span>'
                                    : '<span class="badge bg-secondary">Inactiva</span>' ?>
                            </td>
                            <td>
                                <!-- Editar -->
                                <a href="index.php?editar=<?= $a['id_area'] ?>"
                                   class="btn btn-action bg-warning text-dark"
                                   title="Editar nombre y código">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <!-- Activar / Desactivar -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="accion" value="toggle">
                                    <input type="hidden" name="id"     value="<?= $a['id_area'] ?>">
                                    <button type="submit"
                                            class="btn btn-action <?= $a['activo'] ? 'bg-danger text-white' : 'bg-success text-white' ?>"
                                            title="<?= $a['activo'] ? 'Desactivar' : 'Activar' ?>"
                                            <?= ($a['activo'] && $a['total_usuarios'] > 0)
                                                ? 'data-confirm="Esta área tiene usuarios activos asignados. ¿Desactivar de todos modos?"'
                                                : '' ?>>
                                        <i class="bi bi-<?= $a['activo'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /row -->

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
