<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

$post = $_SESSION['postulante'] ?? null;
if (!$post) { header('Location: ' . BASE_URL . '/portal/login.php'); exit; }

$db = getDB();
$id_conv = (int)($_GET['id'] ?? 0);

$conv = $db->prepare("SELECT * FROM convocatorias WHERE id_convocatoria=? AND estado='ACTIVA'");
$conv->execute([$id_conv]);
$conv = $conv->fetch();

if (!$conv) {
    $_SESSION['error'] = 'Convocatoria no encontrada o ya cerrada.';
    header('Location: ' . BASE_URL . '/portal/convocatorias.php');
    exit;
}

// Verificar si ya tiene un CV en esta convocatoria
$cvExistente = $db->prepare("SELECT * FROM curriculums WHERE id_postulante=? AND id_convocatoria=?");
$cvExistente->execute([$post['id_postulante'], $id_conv]);
$cvExistente = $cvExistente->fetch();

$pageTitle = 'Postular a: ' . mb_strimwidth($conv['titulo'], 0, 40, '…');
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['archivo_cv']['name'])) {
        $errores[] = 'Debe seleccionar su archivo de CV.';
    } else {
        $ext = strtolower(pathinfo($_FILES['archivo_cv']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_CV_EXT)) {
            $errores[] = 'Solo se permiten archivos PDF o Word (.pdf, .doc, .docx).';
        } elseif ($_FILES['archivo_cv']['size'] > MAX_FILE_SIZE) {
            $errores[] = 'El archivo supera el límite de 10 MB.';
        }
    }

    if (empty($errores)) {
        $nombre = $_FILES['archivo_cv']['name'];
        $ruta   = 'CV_' . $post['id_postulante'] . '_' . $id_conv . '_' . time() . '.' . $ext;

        if (!move_uploaded_file($_FILES['archivo_cv']['tmp_name'], UPLOAD_CVS . $ruta)) {
            $errores[] = 'Error al guardar el archivo. Intente de nuevo.';
        } else {
            if ($cvExistente) {
                // Eliminar archivo anterior
                if (file_exists(UPLOAD_CVS . $cvExistente['ruta_archivo'])) {
                    unlink(UPLOAD_CVS . $cvExistente['ruta_archivo']);
                }
                // Actualizar y marcar como no procesado (se re-evaluará)
                $db->prepare("
                    UPDATE curriculums SET ruta_archivo=?, nombre_archivo=?,
                    texto_extraido=NULL, procesado=0, fecha_carga=NOW()
                    WHERE id_curriculum=?
                ")->execute([$ruta, $nombre, $cvExistente['id_curriculum']]);
                // Eliminar evaluación anterior para que se re-procese
                $db->prepare("DELETE FROM evaluaciones_ml WHERE id_curriculum=?")
                   ->execute([$cvExistente['id_curriculum']]);
                $_SESSION['success'] = 'Tu CV fue actualizado. Estará en evaluación próximamente.';
            } else {
                $db->prepare("
                    INSERT INTO curriculums (id_postulante, id_convocatoria, ruta_archivo, nombre_archivo, procesado)
                    VALUES (?, ?, ?, ?, 0)
                ")->execute([$post['id_postulante'], $id_conv, $ruta, $nombre]);
                $_SESSION['success'] = '¡Postulación enviada exitosamente! Tu CV está en cola de evaluación.';
            }
            header('Location: ' . BASE_URL . '/portal/mis_postulaciones.php');
            exit;
        }
    }
}

include __DIR__ . '/includes/portal_head.php';
?>

<a href="convocatorias.php" class="btn btn-outline-secondary btn-sm mb-4">
    <i class="bi bi-arrow-left me-1"></i> Volver a convocatorias
</a>

<div class="bg-white rounded-4 shadow-sm p-4 mb-4">
    <div class="d-flex gap-2 mb-2">
        <span class="badge bg-success">Activa</span>
        <?php if ($conv['area_nombre'] ?? false): ?>
        <span class="badge bg-light text-dark"><?= htmlspecialchars($conv['area_nombre']) ?></span>
        <?php endif; ?>
    </div>
    <h4 class="fw-bold" style="color:var(--primary)"><?= htmlspecialchars($conv['titulo']) ?></h4>

    <?php if ($conv['descripcion']): ?>
    <p class="text-muted"><?= htmlspecialchars($conv['descripcion']) ?></p>
    <?php endif; ?>

    <?php if ($conv['requisitos']): ?>
    <div class="border rounded-3 p-3 bg-light mb-0">
        <strong class="small">Requisitos:</strong>
        <p class="small text-muted mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($conv['requisitos']) ?></p>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-3 text-muted small mt-3 flex-wrap">
        <?php if ($conv['fecha_fin']): ?>
        <span><i class="bi bi-calendar-x me-1"></i>Cierre: <?= date('d/m/Y', strtotime($conv['fecha_fin'])) ?></span>
        <?php endif; ?>
        <?php if ($conv['salario_referencial']): ?>
        <span class="text-success fw-semibold">S/ <?= number_format($conv['salario_referencial'], 2) ?></span>
        <?php endif; ?>
    </div>
</div>

<!-- Formulario de postulación -->
<div class="bg-white rounded-4 shadow-sm p-4">
    <h5 class="fw-bold mb-1" style="color:var(--primary)">
        <i class="bi bi-upload me-2"></i>
        <?= $cvExistente ? 'Actualizar tu curriculum' : 'Subir tu curriculum' ?>
    </h5>
    <p class="text-muted small mb-4">
        <?= $cvExistente
            ? 'Ya tienes un CV enviado para esta convocatoria. Puedes reemplazarlo con uno nuevo.'
            : 'Sube tu CV en formato PDF o Word. El sistema lo analizará automáticamente.' ?>
    </p>

    <?php if (!empty($errores)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <?php if ($cvExistente): ?>
    <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-file-earmark-check fs-5"></i>
        <div>
            CV actual: <strong><?= htmlspecialchars($cvExistente['nombre_archivo']) ?></strong>
            — enviado el <?= date('d/m/Y', strtotime($cvExistente['fecha_carga'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="border-2 border rounded-3 p-4 text-center mb-4" style="border-style:dashed !important;background:#f8faff">
            <i class="bi bi-file-earmark-arrow-up display-4 text-primary opacity-50 d-block mb-2"></i>
            <label for="cvFile" class="form-label fw-semibold cursor-pointer">
                Selecciona tu archivo CV
            </label>
            <input type="file" name="archivo_cv" id="cvFile" class="form-control mt-2"
                   accept=".pdf,.doc,.docx" required data-preview="fnamePreview">
            <div class="text-muted small mt-2" id="fnamePreview">PDF o Word · Máximo 10 MB</div>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg fw-semibold">
                <i class="bi bi-send me-1"></i>
                <?= $cvExistente ? 'Actualizar postulación' : 'Enviar postulación' ?>
            </button>
            <a href="convocatorias.php" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/portal_foot.php'; ?>
