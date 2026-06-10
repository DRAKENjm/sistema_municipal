<?php
/**
 * Consulta pública de resultados de evaluación ML
 * Los postulantes pueden ver su resultado usando su DNI
 * NOTA: Esta página NO requiere autenticación (acceso público)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

// Iniciar sesión pero NO requerir login (es acceso público)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDB();
$pageTitle = 'Consulta de Resultados';

$resultado = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    $id_convocatoria = (int)($_POST['id_convocatoria'] ?? 0);
    
    if (empty($dni)) {
        $error = 'Ingrese su número de DNI.';
    } elseif (!$id_convocatoria) {
        $error = 'Seleccione una convocatoria.';
    } else {
        $stmt = $db->prepare("
            SELECT e.id_evaluacion, e.puntaje, e.porcentaje_coincidencia, e.ranking,
                   e.verificado, e.resultado_verificacion, e.comentario_verificacion,
                   e.fecha_evaluacion, e.fecha_verificacion,
                   CONCAT(p.nombres,' ',p.apellido_paterno,' ',p.apellido_materno) AS postulante,
                   p.dni, p.correo,
                   c.titulo AS convocatoria
            FROM evaluaciones_ml e
            JOIN postulantes p ON p.id_postulante = e.id_postulante
            JOIN convocatorias c ON c.id_convocatoria = e.id_convocatoria
            WHERE p.dni = ? AND e.id_convocatoria = ? AND e.verificado = 1
            LIMIT 1
        ");
        $stmt->execute([$dni, $id_convocatoria]);
        $resultado = $stmt->fetch();
        
        if (!$resultado) {
            $error = 'No se encontró una evaluación verificada para este DNI en la convocatoria seleccionada.';
        }
    }
}

// Obtener convocatorias con evaluaciones verificadas
$convocatorias = $db->query("
    SELECT DISTINCT c.id_convocatoria, c.titulo
    FROM convocatorias c
    JOIN evaluaciones_ml e ON e.id_convocatoria = c.id_convocatoria
    WHERE e.verificado = 1
    ORDER BY c.fecha_registro DESC
")->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-8">
        
        <!-- Encabezado -->
        <div class="text-center mb-4">
            <i class="bi bi-search display-4 text-primary mb-3"></i>
            <h4 class="fw-bold">Consulta de Resultados de Evaluación</h4>
            <p class="text-muted">
                Ingresa tu DNI para consultar el resultado de tu evaluación de curriculum
            </p>
        </div>

        <!-- Formulario de consulta -->
        <div class="table-card mb-4">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-megaphone me-1"></i> Convocatoria
                    </label>
                    <select name="id_convocatoria" class="form-select" required>
                        <option value="">— Seleccionar convocatoria —</option>
                        <?php foreach ($convocatorias as $conv): ?>
                        <option value="<?= $conv['id_convocatoria'] ?>" 
                                <?= isset($_POST['id_convocatoria']) && $_POST['id_convocatoria'] == $conv['id_convocatoria'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($conv['titulo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-person-vcard me-1"></i> Número de DNI
                    </label>
                    <input type="text" 
                           name="dni" 
                           class="form-control" 
                           placeholder="Ej: 12345678"
                           maxlength="8"
                           pattern="[0-9]{8}"
                           value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>"
                           required>
                    <div class="form-text">Ingresa tu DNI de 8 dígitos</div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i> Consultar resultado
                </button>
            </form>
        </div>

        <!-- Error -->
        <?php if ($error): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Resultado -->
        <?php if ($resultado): ?>
        <div class="table-card">
            <div class="table-card-header" style="background:linear-gradient(135deg,#f8faff,#eef2fb)">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-file-earmark-check me-2"></i>
                    Resultado de tu Evaluación
                </h5>
            </div>

            <div class="p-4">
                <!-- Datos del postulante -->
                <div class="text-center mb-4 pb-4 border-bottom">
                    <h5 class="fw-bold"><?= htmlspecialchars($resultado['postulante']) ?></h5>
                    <p class="text-muted mb-0">DNI: <?= htmlspecialchars($resultado['dni']) ?></p>
                    <p class="text-muted small"><?= htmlspecialchars($resultado['convocatoria']) ?></p>
                </div>

                <!-- Resultado de verificación -->
                <div class="text-center mb-4">
                    <?php
                    $res = $resultado['resultado_verificacion'];
                    $badgeClass = $res === 'ACEPTADO' ? 'success' : ($res === 'RECHAZADO' ? 'danger' : 'secondary');
                    $icon = $res === 'ACEPTADO' ? 'check-circle' : ($res === 'RECHAZADO' ? 'x-circle' : 'clock');
                    $titulo = $res === 'ACEPTADO' ? '¡Felicidades!' : ($res === 'RECHAZADO' ? 'Resultado' : 'En evaluación');
                    ?>
                    <h4 class="fw-bold mb-3"><?= $titulo ?></h4>
                    <div class="mb-3">
                        <span class="badge bg-<?= $badgeClass ?> fs-5 px-4 py-2">
                            <i class="bi bi-<?= $icon ?> me-2"></i>
                            <?= str_replace('_', ' ', $res) ?>
                        </span>
                    </div>
                </div>

                <!-- Puntaje ML -->
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="text-muted small mb-1">Puntaje ML</div>
                            <div class="display-6 fw-bold text-primary">
                                <?= $resultado['puntaje'] ?>
                            </div>
                            <div class="text-muted small">/100 puntos</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="text-muted small mb-1">Ranking</div>
                            <div class="display-6 fw-bold text-primary">
                                #<?= $resultado['ranking'] ?>
                            </div>
                            <div class="text-muted small">Posición</div>
                        </div>
                    </div>
                </div>

                <!-- Coincidencia -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-semibold">Coincidencia con el perfil</span>
                        <span class="fw-bold text-primary"><?= $resultado['porcentaje_coincidencia'] ?>%</span>
                    </div>
                    <div class="progress" style="height:16px">
                        <div class="progress-bar bg-primary" 
                             style="width:<?= $resultado['porcentaje_coincidencia'] ?>%"></div>
                    </div>
                </div>

                <!-- Comentario de RRHH -->
                <?php if ($resultado['comentario_verificacion']): ?>
                <div class="alert alert-<?= $res === 'ACEPTADO' ? 'success' : ($res === 'RECHAZADO' ? 'danger' : 'info') ?> border">
                    <h6 class="fw-bold mb-2">
                        <i class="bi bi-chat-left-quote me-2"></i>
                        Comentario de Recursos Humanos
                    </h6>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($resultado['comentario_verificacion'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Fechas -->
                <div class="border-top pt-3 mt-3">
                    <div class="row small text-muted">
                        <div class="col-6">
                            <i class="bi bi-calendar me-1"></i>
                            <strong>Evaluado:</strong> <?= date('d/m/Y', strtotime($resultado['fecha_evaluacion'])) ?>
                        </div>
                        <div class="col-6">
                            <i class="bi bi-calendar-check me-1"></i>
                            <strong>Verificado:</strong> <?= date('d/m/Y', strtotime($resultado['fecha_verificacion'])) ?>
                        </div>
                    </div>
                </div>

                <!-- Botón descargar PDF -->
                <div class="text-center mt-4">
                    <a href="pdf_ficha.php?id=<?= $resultado['id_evaluacion'] ?>&public=1" 
                       target="_blank"
                       class="btn btn-danger">
                        <i class="bi bi-file-pdf me-2"></i>
                        Descargar constancia en PDF
                    </a>
                </div>
            </div>
        </div>

        <?php if ($res === 'ACEPTADO'): ?>
        <div class="alert alert-success mt-3">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Próximos pasos:</strong> El área de Recursos Humanos se pondrá en contacto contigo 
            para continuar con el proceso de selección.
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
