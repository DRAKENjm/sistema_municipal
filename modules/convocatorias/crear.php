<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$db = getDB();
$pageTitle = 'Nueva Convocatoria';
$errores = [];

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
    $estado     = $_POST['estado']                 ?? 'ACTIVA';
    $usr        = usuarioActual();

    if ($titulo === '') $errores[] = 'El título es obligatorio.';
    if ($palabras === '' && $perfil === '') $errores[] = 'Ingrese palabras clave o perfil requerido para el análisis ML.';

    if (empty($errores)) {
        $stmt = $db->prepare("
            INSERT INTO convocatorias
                (id_area, titulo, descripcion, requisitos, palabras_clave,
                 perfil_requerido, salario_referencial, fecha_inicio, fecha_fin, estado, id_usuario)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_area, $titulo, $descripcion ?: null, $requisitos ?: null,
            $palabras ?: null, $perfil ?: null,
            $salario !== '' ? (float)$salario : null,
            $fecha_ini ?: null, $fecha_fin ?: null,
            $estado, $usr['id_usuario']
        ]);
        $newId = $db->lastInsertId();
        registrarBitacora('CREAR', 'convocatorias', $newId, "Convocatoria creada: $titulo");
        $_SESSION['success'] = 'Convocatoria creada correctamente.';
        header('Location: ver.php?id=' . $newId);
        exit;
    }
}

$areas = $db->query("SELECT * FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll();
include __DIR__ . '/../../includes/layout_head.php';
?>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h4 class="page-title mb-0">Nueva Convocatoria</h4>
</div>

<form method="POST">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="form-section">
                <h6><i class="bi bi-info-circle me-1"></i> Información general</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Título del puesto <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" required
                               placeholder="Ej: Asistente Administrativo"
                               value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Área solicitante</label>
                        <select name="id_area" class="form-select">
                            <option value="">— Sin área —</option>
                            <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id_area'] ?>"
                                <?= ($_POST['id_area'] ?? '') == $a['id_area'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($a['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Salario referencial (S/)</label>
                        <input type="number" name="salario_referencial" class="form-control"
                               min="0" step="0.01" placeholder="0.00"
                               value="<?= htmlspecialchars($_POST['salario_referencial'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción del puesto</label>
                        <textarea name="descripcion" class="form-control" rows="3"
                                  placeholder="Funciones y responsabilidades del puesto..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Requisitos</label>
                        <textarea name="requisitos" class="form-control" rows="3"
                                  placeholder="Formación académica, experiencia, etc."><?= htmlspecialchars($_POST['requisitos'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Sección ML -->
            <div class="form-section border-start border-4 border-info">
                <h6><i class="bi bi-robot me-1 text-info"></i> Configuración para análisis ML</h6>
                <p class="text-muted small mb-3">
                    Estos campos alimentan el modelo de Machine Learning para evaluar y puntuar los currículos recibidos.
                </p>
                <div class="mb-3">
                    <label class="form-label">
                        Palabras clave del perfil
                        <span class="text-danger">*</span>
                        <small class="text-muted">(separadas por coma)</small>
                    </label>
                    <input type="text" name="palabras_clave" class="form-control"
                           placeholder="Ej: contabilidad, excel, tributación, balances, SAP"
                           value="<?= htmlspecialchars($_POST['palabras_clave'] ?? '') ?>">
                    <div class="form-text">El modelo buscará estas palabras en los CVs para calcular la coincidencia.</div>
                </div>
                <div>
                    <label class="form-label">Perfil requerido detallado</label>
                    <textarea name="perfil_requerido" class="form-control" rows="4"
                              placeholder="Describa en detalle el perfil ideal: habilidades, herramientas, años de experiencia, certificaciones..."><?= htmlspecialchars($_POST['perfil_requerido'] ?? '') ?></textarea>
                    <div class="form-text">Texto más completo usado para análisis de similitud semántica (TF-IDF / coseno).</div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-section">
                <h6><i class="bi bi-calendar me-1"></i> Fechas y estado</h6>
                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="ACTIVA"   <?= ($_POST['estado']??'ACTIVA')==='ACTIVA'   ?'selected':'' ?>>Activa</option>
                        <option value="BORRADOR" <?= ($_POST['estado']??'')==='BORRADOR' ?'selected':'' ?>>Borrador</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha de inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control"
                           value="<?= htmlspecialchars($_POST['fecha_inicio'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha de cierre</label>
                    <input type="date" name="fecha_fin" class="form-control"
                           value="<?= htmlspecialchars($_POST['fecha_fin'] ?? '') ?>">
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Crear convocatoria
                </button>
                <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
