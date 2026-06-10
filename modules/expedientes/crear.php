<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_EXPEDIENTES);

$db = getDB();
$pageTitle = 'Nuevo Expediente';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero   = trim($_POST['numero_expediente'] ?? '');
    $asunto   = trim($_POST['asunto']            ?? '');
    $id_area  = (int)($_POST['id_area']          ?? 0) ?: null;
    $estado   = $_POST['estado']                 ?? 'ABIERTO';
    $docs_sel = $_POST['documentos']             ?? [];
    $usr      = usuarioActual();

    if ($numero === '') $errores[] = 'El número de expediente es obligatorio.';
    if ($asunto === '') $errores[] = 'El asunto es obligatorio.';

    if (empty($errores)) {
        $check = $db->prepare("SELECT id_expediente FROM expedientes WHERE numero_expediente=?");
        $check->execute([$numero]);
        if ($check->fetch()) $errores[] = "Ya existe un expediente con número $numero.";
    }

    if (empty($errores)) {
        $db->prepare("
            INSERT INTO expedientes (numero_expediente, asunto, id_area, id_usuario, estado)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$numero, $asunto, $id_area, $usr['id_usuario'], $estado]);
        $newId = $db->lastInsertId();

        // Vincular documentos seleccionados
        foreach ($docs_sel as $orden => $docId) {
            $db->prepare("INSERT INTO expediente_documento (id_expediente, id_documento, orden) VALUES (?,?,?)")
               ->execute([$newId, (int)$docId, $orden + 1]);
        }

        registrarBitacora('CREAR', 'expedientes', $newId, "Expediente creado: $numero");
        $_SESSION['success'] = 'Expediente creado correctamente.';
        header('Location: ver.php?id=' . $newId);
        exit;
    }
}

$areas = $db->query("SELECT * FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll();
$documentos = $db->query("
    SELECT d.id_documento, d.asunto, d.numero_documento, t.abreviatura
    FROM documentos d JOIN tipos_documento t ON t.id_tipo_documento=d.id_tipo_documento
    WHERE d.estado != 'ANULADO'
    ORDER BY d.fecha_registro DESC
    LIMIT 200
")->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h4 class="page-title mb-0">Nuevo Expediente</h4>
</div>

<form method="POST">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="form-section">
                <h6><i class="bi bi-folder2-open me-1"></i> Datos del expediente</h6>
                <div class="row g-3">
                    <div class="col-sm-5">
                        <label class="form-label">Número de expediente <span class="text-danger">*</span></label>
                        <input type="text" name="numero_expediente" class="form-control" required
                               placeholder="Ej: EXP-001-2026"
                               value="<?= htmlspecialchars($_POST['numero_expediente'] ?? '') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Área responsable</label>
                        <select name="id_area" class="form-select">
                            <option value="">— Ninguna —</option>
                            <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id_area'] ?>"
                                <?= ($_POST['id_area'] ?? '') == $a['id_area'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($a['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="ABIERTO">Abierto</option>
                            <option value="EN_PROCESO">En proceso</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Asunto <span class="text-danger">*</span></label>
                        <input type="text" name="asunto" class="form-control" required
                               value="<?= htmlspecialchars($_POST['asunto'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h6><i class="bi bi-file-earmark-plus me-1"></i> Vincular documentos</h6>
                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm"
                           placeholder="Buscar documento..."
                           onkeyup="filtrarTabla('buscarDoc','tablaDocumentos')">
                </div>
                <div style="max-height:300px;overflow-y:auto">
                    <table class="table table-sm table-hover" id="tablaDocumentos">
                        <thead class="sticky-top bg-white">
                            <tr><th></th><th>Tipo</th><th>N°</th><th>Asunto</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documentos as $doc): ?>
                            <tr>
                                <td><input type="checkbox" name="documentos[]"
                                           value="<?= $doc['id_documento'] ?>"
                                           <?= in_array($doc['id_documento'], $_POST['documentos'] ?? []) ? 'checked':'' ?>>
                                </td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($doc['abreviatura']) ?></span></td>
                                <td class="small"><?= htmlspecialchars($doc['numero_documento'] ?? '—') ?></td>
                                <td class="small"><?= htmlspecialchars(mb_strimwidth($doc['asunto'], 0, 60, '…')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="d-grid gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Crear expediente
                </button>
                <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('buscarDoc').addEventListener('keyup', function() {
    filtrarTabla('buscarDoc', 'tablaDocumentos');
});
</script>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
