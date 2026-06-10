<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_DOCUMENTOS);

$db = getDB();
$pageTitle = 'Nuevo Documento';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tipo      = (int)($_POST['id_tipo_documento'] ?? 0);
    $numero       = trim($_POST['numero_documento']  ?? '');
    $asunto       = trim($_POST['asunto']             ?? '');
    $descripcion  = trim($_POST['descripcion']        ?? '');
    $fecha_doc    = $_POST['fecha_documento']         ?? '';
    $id_origen    = (int)($_POST['id_area_origen']   ?? 0) ?: null;
    $id_destino   = (int)($_POST['id_area_destino']  ?? 0) ?: null;
    $estado       = $_POST['estado']                  ?? 'REGISTRADO';
    $usr          = usuarioActual();

    if ($id_tipo   === 0) $errores[] = 'Seleccione el tipo de documento.';
    if ($asunto    === '') $errores[] = 'El asunto es obligatorio.';
    if ($fecha_doc === '') $errores[] = 'La fecha del documento es obligatoria.';

    // Subida de archivo
    $ruta_archivo   = null;
    $nombre_archivo = null;
    if (!empty($_FILES['archivo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_DOC_EXT)) {
            $errores[] = 'Tipo de archivo no permitido. Use: ' . implode(', ', ALLOWED_DOC_EXT);
        } elseif ($_FILES['archivo']['size'] > MAX_FILE_SIZE) {
            $errores[] = 'El archivo supera el tamaño máximo de 10 MB.';
        } else {
            $nombre_archivo = $_FILES['archivo']['name'];
            $ruta_archivo   = 'DOC_' . time() . '_' . uniqid() . '.' . $ext;
            if (!move_uploaded_file($_FILES['archivo']['tmp_name'], UPLOAD_DOCS . $ruta_archivo)) {
                $errores[] = 'Error al subir el archivo. Intente de nuevo.';
                $ruta_archivo = null;
            }
        }
    }

    if (empty($errores)) {
        $stmt = $db->prepare("
            INSERT INTO documentos
                (id_tipo_documento, numero_documento, asunto, descripcion,
                 fecha_documento, id_area_origen, id_area_destino,
                 ruta_archivo, nombre_archivo, estado, id_usuario)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_tipo, $numero ?: null, $asunto, $descripcion ?: null,
            $fecha_doc ?: null, $id_origen, $id_destino,
            $ruta_archivo, $nombre_archivo, $estado, $usr['id_usuario']
        ]);
        $newId = $db->lastInsertId();
        registrarBitacora('CREAR', 'documentos', $newId, "Documento creado: $asunto");
        $_SESSION['success'] = 'Documento registrado correctamente.';
        header('Location: ' . BASE_URL . '/modules/documentos/index.php');
        exit;
    }
}

$tipos = $db->query("SELECT * FROM tipos_documento WHERE activo=1 ORDER BY nombre")->fetchAll();
$areas = $db->query("SELECT * FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errores as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="page-title mb-0">Nuevo Documento</h4>
</div>

<form method="POST" enctype="multipart/form-data">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="form-section">
                <h6><i class="bi bi-info-circle me-1"></i> Datos del documento</h6>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label">Tipo de documento <span class="text-danger">*</span></label>
                        <select name="id_tipo_documento" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t['id_tipo_documento'] ?>"
                                <?= ($_POST['id_tipo_documento'] ?? '') == $t['id_tipo_documento'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Número de documento</label>
                        <input type="text" name="numero_documento" class="form-control"
                               placeholder="Ej: OF-001-2026"
                               value="<?= htmlspecialchars($_POST['numero_documento'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Asunto <span class="text-danger">*</span></label>
                        <input type="text" name="asunto" class="form-control" required
                               placeholder="Descripción breve del asunto"
                               value="<?= htmlspecialchars($_POST['asunto'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción / Contenido</label>
                        <textarea name="descripcion" class="form-control" rows="4"
                                  placeholder="Detalle del documento..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Fecha del documento <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_documento" class="form-control" required
                               value="<?= htmlspecialchars($_POST['fecha_documento'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Área de origen</label>
                        <select name="id_area_origen" class="form-select">
                            <option value="">— Ninguna —</option>
                            <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id_area'] ?>"
                                <?= ($_POST['id_area_origen'] ?? '') == $a['id_area'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($a['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Área de destino</label>
                        <select name="id_area_destino" class="form-select">
                            <option value="">— Ninguna —</option>
                            <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id_area'] ?>"
                                <?= ($_POST['id_area_destino'] ?? '') == $a['id_area'] ? 'selected':'' ?>>
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
                        <option value="REGISTRADO" <?= ($_POST['estado']??'REGISTRADO')==='REGISTRADO'?'selected':'' ?>>Registrado</option>
                        <option value="EN_TRAMITE"  <?= ($_POST['estado']??'')==='EN_TRAMITE' ?'selected':'' ?>>En trámite</option>
                        <option value="DERIVADO"    <?= ($_POST['estado']??'')==='DERIVADO'   ?'selected':'' ?>>Derivado</option>
                        <option value="ARCHIVADO"   <?= ($_POST['estado']??'')==='ARCHIVADO'  ?'selected':'' ?>>Archivado</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Archivo adjunto</label>
                    <input type="file" name="archivo" class="form-control"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                           data-preview="fname">
                    <div class="form-text" id="fname">PDF, Word, Excel, Imagen (máx. 10 MB)</div>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Guardar documento
                </button>
                <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
