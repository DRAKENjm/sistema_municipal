<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_TIPOS_DOC);

$db = getDB();
$pageTitle = 'Tipos de Documento';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre']      ?? '');
        $abrev  = strtoupper(trim($_POST['abreviatura'] ?? ''));
        if ($nombre === '') {
            $errores[] = 'El nombre es obligatorio.';
        } else {
            try {
                $db->prepare("INSERT INTO tipos_documento (nombre, abreviatura) VALUES (?,?)")
                   ->execute([$nombre, $abrev ?: null]);
                registrarBitacora('CREAR', 'tipos_documento', (int)$db->lastInsertId(), "Tipo creado: $nombre");
                $_SESSION['success'] = 'Tipo de documento creado.';
            } catch (PDOException) {
                $errores[] = 'Ya existe un tipo con ese nombre.';
            }
            if (empty($errores)) { header('Location: index.php'); exit; }
        }

    } elseif ($accion === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE tipos_documento SET activo = NOT activo WHERE id_tipo_documento = ?")
           ->execute([$id]);
        header('Location: index.php');
        exit;

    } elseif ($accion === 'editar') {
        $id     = (int)($_POST['id']        ?? 0);
        $nombre = trim($_POST['nombre']     ?? '');
        $abrev  = strtoupper(trim($_POST['abreviatura'] ?? ''));
        if ($nombre === '') {
            $errores[] = 'El nombre es obligatorio.';
        } else {
            try {
                $db->prepare("UPDATE tipos_documento SET nombre=?, abreviatura=? WHERE id_tipo_documento=?")
                   ->execute([$nombre, $abrev ?: null, $id]);
                registrarBitacora('EDITAR', 'tipos_documento', $id, "Tipo actualizado: $nombre");
                $_SESSION['success'] = 'Tipo de documento actualizado.';
            } catch (PDOException) {
                $errores[] = 'Ya existe un tipo con ese nombre.';
            }
            if (empty($errores)) { header('Location: index.php'); exit; }
        }
    }
}

$tipos = $db->query("SELECT * FROM tipos_documento ORDER BY activo DESC, nombre")->fetchAll();

// Si viene parámetro editar, cargar el tipo a editar
$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM tipos_documento WHERE id_tipo_documento = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $editando = $stmt->fetch();
}

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<h4 class="page-title"><i class="bi bi-card-list me-2"></i>Tipos de Documento</h4>

<div class="row g-3">
    <!-- Formulario crear / editar -->
    <div class="col-lg-4">
        <div class="form-section">
            <h6><?= $editando ? '<i class="bi bi-pencil me-1"></i> Editar tipo' : '<i class="bi bi-plus-lg me-1"></i> Nuevo tipo' ?></h6>

            <form method="POST">
                <?php if ($editando): ?>
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id" value="<?= $editando['id_tipo_documento'] ?>">
                <?php else: ?>
                <input type="hidden" name="accion" value="crear">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required
                           placeholder="Ej: Oficio, Resolución…"
                           value="<?= htmlspecialchars($editando['nombre'] ?? $_POST['nombre'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Abreviatura</label>
                    <input type="text" name="abreviatura" class="form-control"
                           maxlength="10" placeholder="Ej: OF, RES, MEM"
                           value="<?= htmlspecialchars($editando['abreviatura'] ?? $_POST['abreviatura'] ?? '') ?>">
                    <div class="form-text">Se usa como etiqueta corta en las listas.</div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>
                        <?= $editando ? 'Guardar cambios' : 'Agregar tipo' ?>
                    </button>
                    <?php if ($editando): ?>
                    <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de tipos -->
    <div class="col-lg-8">
        <div class="table-card">
            <div class="table-card-header">
                <h5>Total: <?= count($tipos) ?> tipo(s)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Abrev.</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tipos as $t): ?>
                        <tr class="<?= !$t['activo'] ? 'text-muted' : '' ?>">
                            <td>
                                <span class="badge bg-<?= $t['activo'] ? 'secondary' : 'light text-muted' ?>">
                                    <?= htmlspecialchars($t['abreviatura'] ?? '—') ?>
                                </span>
                            </td>
                            <td class="fw-semibold"><?= htmlspecialchars($t['nombre']) ?></td>
                            <td>
                                <?= $t['activo']
                                    ? '<span class="badge bg-success">Activo</span>'
                                    : '<span class="badge bg-secondary">Inactivo</span>' ?>
                            </td>
                            <td>
                                <a href="index.php?editar=<?= $t['id_tipo_documento'] ?>"
                                   class="btn btn-action bg-warning text-dark" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="accion" value="toggle">
                                    <input type="hidden" name="id" value="<?= $t['id_tipo_documento'] ?>">
                                    <button type="submit"
                                            class="btn btn-action <?= $t['activo'] ? 'bg-danger text-white' : 'bg-success text-white' ?>"
                                            title="<?= $t['activo'] ? 'Desactivar' : 'Activar' ?>">
                                        <i class="bi bi-<?= $t['activo'] ? 'toggle-on' : 'toggle-off' ?>"></i>
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
</div>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
