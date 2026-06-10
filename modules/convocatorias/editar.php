<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM convocatorias WHERE id_convocatoria = ?");
$stmt->execute([$id]);
$conv = $stmt->fetch();

if (!$conv) {
    $_SESSION['error'] = 'Convocatoria no encontrada.';
    header('Location: index.php');
    exit;
}

$pageTitle = 'Editar Convocatoria';
$errores   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo     = trim($_POST['titulo']            ?? '');
    $descripcion= trim($_POST['descripcion']       ?? '');
    $requisitos = trim($_POST['requisitos']        ?? '');
    $palabras   = trim($_POST['palabras_clave']    ?? '');
    $perfil     = trim($_POST['perfil_requerido']  ?? '');
    $salario    = $_POST['salario_referencial']    ?? '';
    $id_area    = (int)($_POST['id_area']          ?? 0) ?: null;
    $fecha_ini  = $_POST['fecha_inicio']           ?? '';
    $fecha_fin  = $_POST['fecha_fin']              ?? '';
    $estado     = $_POST['estado']                 ?? $conv['estado'];

    if ($titulo === '') $errores[] = 'El título es obligatorio.';

    if (empty($errores)) {
        $db->prepare("
            UPDATE convocatorias SET
                id_area = ?, titulo = ?, descripcion = ?, requisitos = ?,
                palabras_clave = ?, perfil_requerido = ?, salario_referencial = ?,
                fecha_inicio = ?, fecha_fin = ?, estado = ?
            WHERE id_convocatoria = ?
        ")->execute([
            $id_area, $titulo, $descripcion ?: null, $requisitos ?: null,
            $palabras ?: null, $perfil ?: null,
            $salario !== '' ? (float)$salario : null,
            $fecha_ini ?: null, $fecha_fin ?: null, $estado, $id
        ]);
        registrarBitacora('EDITAR', 'convocatorias', $id, "Convocatoria actualizada: $titulo");
        $_SESSION['success'] = 'Convocatoria actualizada.';
        header('Location: ver.php?id=' . $id);
        exit;
    }
    $conv = array_merge($conv, $_POST);
}

$areas = $db->query("SELECT * FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll();
include __DIR__ . '/../../includes/layout_head.php';
?>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="ver.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h4 class="page-title mb-0">Editar Convocatoria</h4>
</div>

<form method="POST">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="form-section">
                <h6><i class="bi bi-info-circle me-1"></i> Información general</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" required value="<?= htmlspecialchars($conv['titulo']) ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Área</label>
                        <select name="id_area" class="form-select">
                            <option value="">— Sin área —</option>
                            <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id_area'] ?>" <?= $conv['id_area'] == $a['id_area'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($a['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Salario referencial (S/)</label>
                        <input type="number" name="salario_referencial" class="form-control"
                               min="0" step="0.01" value="<?= htmlspecialchars($conv['salario_referencial'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($conv['descripcion'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Requisitos</label>
                        <textarea name="requisitos" class="form-control" rows="3"><?= htmlspecialchars($conv['requisitos'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="form-section border-start border-4 border-info">
                <h6><i class="bi bi-robot me-1 text-info"></i> Configuración ML</h6>
                <div class="mb-3">
                    <label class="form-label">Palabras clave <small class="text-muted">(separadas por coma)</small></label>
                    <input type="text" name="palabras_clave" class="form-control" value="<?= htmlspecialchars($conv['palabras_clave'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Perfil requerido detallado</label>
                    <textarea name="perfil_requerido" class="form-control" rows="4"><?= htmlspecialchars($conv['perfil_requerido'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="form-section">
                <h6><i class="bi bi-calendar me-1"></i> Fechas y estado</h6>
                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <?php foreach (['ACTIVA','BORRADOR','CERRADA','CANCELADA'] as $e): ?>
                        <option value="<?= $e ?>" <?= $conv['estado'] === $e ? 'selected':'' ?>><?= $e ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($conv['fecha_inicio'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha cierre</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($conv['fecha_fin'] ?? '') ?>">
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Guardar cambios</button>
                <a href="ver.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
