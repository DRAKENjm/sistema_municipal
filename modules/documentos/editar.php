<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_DOCUMENTOS);

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
$doc = $db->prepare("SELECT * FROM documentos WHERE id_documento = ?");
$doc->execute([$id]);
$doc = $doc->fetch();

if (!$doc) {
    $_SESSION['error'] = 'Documento no encontrado.';
    header('Location: index.php');
    exit;
}

$pageTitle = 'Editar Documento';
$errores   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tipo     = (int)($_POST['id_tipo_documento'] ?? 0);
    $numero      = trim($_POST['numero_documento']  ?? '');
    $asunto      = trim($_POST['asunto']             ?? '');
    $descripcion = trim($_POST['descripcion']        ?? '');
    $fecha_doc   = $_POST['fecha_documento']         ?? '';
    $id_origen   = (int)($_POST['id_area_origen']   ?? 0) ?: null;
    $id_destino  = (int)($_POST['id_area_destino']  ?? 0) ?: null;
    $estado      = $_POST['estado']                  ?? $doc['estado'];

    if ($id_tipo === 0) $errores[] = 'Seleccione el tipo de documento.';
    if ($asunto  === '') $errores[] = 'El asunto es obligatorio.';

    // Nuevo archivo
    $ruta_archivo   = $doc['ruta_archivo'];
    $nombre_archivo = $doc['nombre_archivo'];
    if (!empty($_FILES['archivo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_DOC_EXT)) {
            $errores[] = 'Tipo de archivo no permitido.';
        } elseif ($_FILES['archivo']['size'] > MAX_FILE_SIZE) {
            $errores[] = 'El archivo supera el tamaño máximo de 10 MB.';
        } else {
            $nuevoNombre = 'DOC_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['archivo']['tmp_name'], UPLOAD_DOCS . $nuevoNombre)) {
                // Eliminar anterior
                if ($ruta_archivo && file_exists(UPLOAD_DOCS . $ruta_archivo)) {
                    unlink(UPLOAD_DOCS . $ruta_archivo);
                }
                $ruta_archivo   = $nuevoNombre;
                $nombre_archivo = $_FILES['archivo']['name'];
            }
        }
    }

    if (empty($errores)) {
        $stmt = $db->prepare("
            UPDATE documentos SET
                id_tipo_documento = ?, numero_documento = ?, asunto = ?,
                descripcion = ?, fecha_documento = ?, id_area_origen = ?,
                id_area_destino = ?, ruta_archivo = ?, nombre_archivo = ?, estado = ?
            WHERE id_documento = ?
        ");
        $stmt->execute([
            $id_tipo, $numero ?: null, $asunto, $descripcion ?: null,
            $fecha_doc ?: null, $id_origen, $id_destino,
            $ruta_archivo, $nombre_archivo, $estado, $id
        ]);
        registrarBitacora('EDITAR', 'documentos', $id, "Documento actualizado: $asunto");
        $_SESSION['success'] = 'Documento actualizado correctamente.';
        header('Location: ver.php?id=' . $id);
        exit;
    }
    // Repoblar doc con los valores del POST para mostrar en el formulario
    $doc = array_merge($doc, $_POST);
}

$tipos = $db->query("SELECT * FROM tipos_documento WHERE activo=1 ORDER BY nombre")->fetchAll();
$areas = $db->query("SELECT * FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="ver.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h4 class="page-title mb-0">Editar Documento #<?= $id ?></h4>
</div>

<form method="POST" enctype="multipart/form-data">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="form-section">
                <h6><i class="bi bi-info-circle me-1"></i> Datos del documento</h6>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="id_tipo_documento" class="form-select" required>
                            <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t['id_tipo_documento'] ?>"
                                <?= $doc['id_tipo_documento'] == $t['id_tipo_documento'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Número</label>
                        <input type="text" name="numero_documento" class="form-control"
                               value="<?= htmlspecialchars($doc['numero_documento'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Asunto <span class="text-danger">*</span></label>
                        <input type="text" name="asunto" class="form-control" required
                               value="<?= htmlspecialchars($doc['asunto']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="4"><?= htmlspecialchars($doc['descripcion'] ?? '') ?></textarea>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Fecha documento</label>
                        <input type="date" name="fecha_documento" class="form-control"
                               value="<?= htmlspecialchars($doc['fecha_documento'] ?? '') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Área origen</label>
                        <select name="id_area_origen" class="form-select">
                            <option value="">— Ninguna —</option>
                            <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id_area'] ?>"
                                <?= $doc['id_area_origen'] == $a['id_area'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($a['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Área destino</label>
                        <select name="id_area_destino" class="form-select">
                            <option value="">— Ninguna —</option>
                            <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id_area'] ?>"
                                <?= $doc['id_area_destino'] == $a['id_area'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($a['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="form-section">
                <h6><i class="bi bi-sliders me-1"></i> Estado y archivo</h6>
                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <?php foreach (['REGISTRADO','EN_TRAMITE','DERIVADO','ARCHIVADO','ANULADO'] as $e): ?>
                        <option value="<?= $e ?>" <?= $doc['estado'] === $e ? 'selected':'' ?>><?= $e ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($doc['nombre_archivo']): ?>
                <div class="alert alert-light py-2 small">
                    <i class="bi bi-paperclip me-1"></i> Archivo actual: <strong><?= htmlspecialchars($doc['nombre_archivo']) ?></strong>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label">Reemplazar archivo</label>
                    <input type="file" name="archivo" class="form-control"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                           data-preview="fname">
                    <div class="form-text" id="fname">Deje vacío para mantener el actual</div>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Guardar cambios
                </button>
                <a href="ver.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
