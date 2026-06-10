<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_EXPEDIENTES);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM expedientes WHERE id_expediente = ?");
$stmt->execute([$id]);
$exp = $stmt->fetch();

if (!$exp) {
    $_SESSION['error'] = 'Expediente no encontrado.';
    header('Location: index.php');
    exit;
}

$pageTitle = 'Editar Expediente';
$errores   = [];

// Documentos ya vinculados
$docsVinculados = $db->prepare("SELECT id_documento FROM expediente_documento WHERE id_expediente=?");
$docsVinculados->execute([$id]);
$docsVinculadosIds = $docsVinculados->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero   = trim($_POST['numero_expediente'] ?? '');
    $asunto   = trim($_POST['asunto']            ?? '');
    $id_area  = (int)($_POST['id_area']          ?? 0) ?: null;
    $estado   = $_POST['estado']                 ?? $exp['estado'];
    $docs_sel = $_POST['documentos']             ?? [];

    if ($numero === '') $errores[] = 'Número obligatorio.';
    if ($asunto === '') $errores[] = 'Asunto obligatorio.';

    if (empty($errores)) {
        $check = $db->prepare("SELECT id_expediente FROM expedientes WHERE numero_expediente=? AND id_expediente<>?");
        $check->execute([$numero, $id]);
        if ($check->fetch()) $errores[] = "Ya existe otro expediente con número $numero.";
    }

    if (empty($errores)) {
        $db->prepare("UPDATE expedientes SET numero_expediente=?,asunto=?,id_area=?,estado=? WHERE id_expediente=?")
           ->execute([$numero, $asunto, $id_area, $estado, $id]);

        // Re-vincular documentos
        $db->prepare("DELETE FROM expediente_documento WHERE id_expediente=?")->execute([$id]);
        foreach ($docs_sel as $orden => $docId) {
            $db->prepare("INSERT INTO expediente_documento (id_expediente,id_documento,orden) VALUES (?,?,?)")
               ->execute([$id, (int)$docId, $orden + 1]);
        }

        registrarBitacora('EDITAR', 'expedientes', $id, "Expediente actualizado: $numero");
        $_SESSION['success'] = 'Expediente actualizado.';
        header('Location: ver.php?id=' . $id);
        exit;
    }
    $docsVinculadosIds = array_map('intval', $docs_sel);
    $exp = array_merge($exp, $_POST);
}

$areas = $db->query("SELECT * FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll();
$documentos = $db->query("
    SELECT d.id_documento, d.asunto, d.numero_documento, t.abreviatura
    FROM documentos d JOIN tipos_documento t ON t.id_tipo_documento=d.id_tipo_documento
    WHERE d.estado != 'ANULADO' ORDER BY d.fecha_registro DESC LIMIT 200
")->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="ver.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h4 class="page-title mb-0">Editar Expediente</h4>
</div>

<form method="POST">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="form-section">
                <h6>Datos del expediente</h6>
                <div class="row g-3">
                    <div class="col-sm-5">
                        <label class="form-label">Número <span class="text-danger">*</span></label>
                        <input type="text" name="numero_expediente" class="form-control" required value="<?= htmlspecialchars($exp['numero_expediente']) ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Área</label>
                        <select name="id_area" class="form-select">
                            <option value="">— Ninguna —</option>
                            <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id_area'] ?>" <?= $exp['id_area'] == $a['id_area'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($a['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <?php foreach (['ABIERTO','EN_PROCESO','CERRADO','ARCHIVADO'] as $e): ?>
                            <option value="<?= $e ?>" <?= $exp['estado']===$e?'selected':'' ?>><?= $e ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Asunto <span class="text-danger">*</span></label>
                        <input type="text" name="asunto" class="form-control" required value="<?= htmlspecialchars($exp['asunto'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h6>Documentos vinculados</h6>
                <input type="text" class="form-control form-control-sm mb-2" id="buscarDoc" placeholder="Buscar...">
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
                                           <?= in_array((int)$doc['id_documento'], $docsVinculadosIds) ? 'checked':'' ?>>
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
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Guardar</button>
                <a href="ver.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
