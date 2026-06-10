<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_RRHH);

$db  = getDB();
$pageTitle = 'Evaluaciones ML';

// ── Datos para el modal de procesamiento ─────────────────
$convocatorias = $db->query("
    SELECT c.id_convocatoria, c.titulo, c.estado,
           COUNT(cv.id_curriculum)                          AS total_cvs,
           SUM(cv.procesado = 0)                            AS pendientes,
           SUM(cv.procesado = 1)                            AS procesados
    FROM convocatorias c
    LEFT JOIN curriculums cv ON cv.id_convocatoria = c.id_convocatoria
    GROUP BY c.id_convocatoria
    ORDER BY c.estado = 'ACTIVA' DESC, c.fecha_registro DESC
")->fetchAll();

$totalPendientes = array_sum(array_column($convocatorias, 'pendientes'));

// ── Grupos de evaluaciones por convocatoria ───────────────
$grupos = $db->query("
    SELECT c.id_convocatoria, c.titulo AS conv_titulo, c.estado AS conv_estado,
           COUNT(e.id_evaluacion)          AS total_eval,
           MAX(e.puntaje)                  AS puntaje_max,
           ROUND(AVG(e.puntaje), 1)        AS puntaje_avg,
           SUM(e.revisado_rrhh = 0)        AS sin_revisar
    FROM evaluaciones_ml e
    JOIN convocatorias c ON c.id_convocatoria = e.id_convocatoria
    GROUP BY c.id_convocatoria
    ORDER BY c.fecha_registro DESC
")->fetchAll();

// ── CVs por convocatoria con sus evaluaciones ─────────────
$evaluacionesPorConv = [];
foreach ($grupos as $g) {
    $stmt = $db->prepare("
        SELECT e.id_evaluacion, e.puntaje, e.porcentaje_coincidencia,
               e.ranking, e.revisado_rrhh, e.modelo_version, e.fecha_evaluacion,
               e.observaciones, e.verificado, e.resultado_verificacion, 
               e.comentario_verificacion, e.fecha_verificacion, e.verificado_por,
               CONCAT(p.apellido_paterno,' ',p.apellido_materno,', ',p.nombres) AS postulante,
               p.dni, p.correo, p.telefono,
               cv.id_curriculum, cv.nombre_archivo, cv.ruta_archivo,
               cv.procesado, cv.fecha_carga
        FROM evaluaciones_ml e
        JOIN postulantes p  ON p.id_postulante  = e.id_postulante
        JOIN curriculums cv ON cv.id_curriculum  = e.id_curriculum
        WHERE e.id_convocatoria = ?
        ORDER BY e.ranking ASC
    ");
    $stmt->execute([$g['id_convocatoria']]);
    $evaluacionesPorConv[$g['id_convocatoria']] = $stmt->fetchAll();
}

// ── CVs pendientes detallados para el modal ───────────────
$cvsPendientes = $db->query("
    SELECT cv.id_curriculum, cv.nombre_archivo, cv.fecha_carga,
           c.id_convocatoria, c.titulo AS conv_titulo,
           CONCAT(p.apellido_paterno,' ',p.apellido_materno,', ',p.nombres) AS postulante,
           p.dni
    FROM curriculums cv
    JOIN convocatorias c ON c.id_convocatoria = cv.id_convocatoria
    JOIN postulantes   p ON p.id_postulante   = cv.id_postulante
    WHERE cv.procesado = 0
    ORDER BY c.id_convocatoria, cv.fecha_carga ASC
")->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<!-- ══ CABECERA ══ -->
<div class="mb-4">
    <h4 class="page-title mb-0">
        <i class="bi bi-robot me-2"></i>Evaluaciones con Machine Learning
    </h4>
    <p class="text-muted small mb-0 mt-1">
        Análisis automático de curriculums · Verificación y selección de candidatos
    </p>
</div>

<!-- ══ ALERTA CVs PENDIENTES ══ -->
<?php if ($totalPendientes > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4 shadow-sm">
    <i class="bi bi-bell-fill fs-4"></i>
    <div class="flex-fill">
        <strong><?= $totalPendientes ?> curriculum(s) pendientes de análisis ML.</strong>
        <br>
        <small class="text-muted">Haz clic en "Evaluar" en cada convocatoria para procesarlos.</small>
    </div>
</div>
<?php endif; ?>

<!-- ══ CONTENIDO: sin evaluaciones ══ -->
<?php if (empty($grupos) && $totalPendientes === 0): ?>
<div class="table-card p-5 text-center">
    <i class="bi bi-robot display-3 d-block mb-3 opacity-15"></i>
    <h5 class="text-muted">No hay evaluaciones todavía</h5>
    <p class="text-muted small mb-4">
        Sube CVs a las convocatorias activas y luego procésalos aquí.
    </p>
    <a href="<?= BASE_URL ?>/modules/convocatorias/index.php" class="btn btn-primary">
        <i class="bi bi-megaphone me-1"></i> Ver convocatorias
    </a>
</div>
<?php else: ?>

<!-- ══ RANKING POR CONVOCATORIA ══ -->
<?php 
// Agrupar CVs pendientes por convocatoria
$convocatoriasConPendientes = [];
$cvsPorConvocatoria = [];

// Agrupar CVs por convocatoria
foreach ($cvsPendientes as $cv) {
    $convId = $cv['id_convocatoria'];
    if (!isset($cvsPorConvocatoria[$convId])) {
        $cvsPorConvocatoria[$convId] = [
            'id_convocatoria' => $convId,
            'conv_titulo' => $cv['conv_titulo'],
            'conv_estado' => 'ACTIVA', // Asumimos que si tiene CVs pendientes, está activa
            'cvs' => []
        ];
    }
    $cvsPorConvocatoria[$convId]['cvs'][] = $cv;
}

// Convertir a array indexado para iterar
foreach ($cvsPorConvocatoria as $convData) {
    $convocatoriasConPendientes[] = [
        'grupo' => $convData,
        'cvs_pendientes' => $convData['cvs']
    ];
}

// Paleta de colores bonitos para las convocatorias
$colores = [
    ['bg' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', 'border' => '#667eea', 'text' => '#fff'],
    ['bg' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', 'border' => '#f093fb', 'text' => '#fff'],
    ['bg' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)', 'border' => '#4facfe', 'text' => '#fff'],
    ['bg' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)', 'border' => '#43e97b', 'text' => '#fff'],
    ['bg' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)', 'border' => '#fa709a', 'text' => '#fff'],
    ['bg' => 'linear-gradient(135deg, #30cfd0 0%, #330867 100%)', 'border' => '#30cfd0', 'text' => '#fff'],
    ['bg' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)', 'border' => '#a8edea', 'text' => '#333'],
    ['bg' => 'linear-gradient(135deg, #ff9a56 0%, #ff6a88 100%)', 'border' => '#ff9a56', 'text' => '#fff'],
];

if (empty($convocatoriasConPendientes)): ?>
<div class="text-center py-5">
    <i class="bi bi-check-circle display-1 text-success opacity-25"></i>
    <h5 class="text-muted mt-3">¡Todo al día!</h5>
    <p class="text-muted">No hay CVs pendientes de evaluación en este momento.</p>
</div>
<?php else: ?>

<?php foreach ($convocatoriasConPendientes as $index => $data):
    $g = $data['grupo'];
    $cvsPendientesConv = $data['cvs_pendientes'];
    $color = $colores[$index % count($colores)];
?>
<div class="card mb-4 shadow-sm border-0 overflow-hidden" id="conv-<?= $g['id_convocatoria'] ?>">
    
    <!-- Header con color -->
    <div class="card-header" style="background: <?= $color['bg'] ?>; color: <?= $color['text'] ?>; border: none; padding: 1.5rem;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="flex-fill">
                <h5 class="mb-2 fw-bold" style="font-size: 1.3rem;">
                    <i class="bi bi-megaphone me-2"></i><?= htmlspecialchars($g['conv_titulo']) ?>
                </h5>
                <div class="d-flex gap-3 flex-wrap align-items-center" style="opacity: 0.95;">
                    <span class="badge" style="background: rgba(255,255,255,0.3); backdrop-filter: blur(10px); padding: 0.4rem 0.8rem;">
                        <i class="bi bi-hourglass-split me-1"></i><?= count($cvsPendientesConv) ?> CVs pendientes
                    </span>
                    <span style="font-size: 0.9rem;">
                        <i class="bi bi-calendar-check me-1"></i>
                        <?= $g['conv_estado'] ?>
                    </span>
                </div>
            </div>
            <button class="btn btn-light btn-lg shadow-sm" 
                    data-bs-toggle="modal" 
                    data-bs-target="#modalProcesar"
                    data-conv="<?= $g['id_convocatoria'] ?>"
                    style="font-weight: 600; padding: 0.75rem 1.5rem;">
                <i class="bi bi-robot me-2"></i>Evaluar
            </button>
        </div>
    </div>

    <!-- Lista de CVs pendientes -->
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;" class="text-center">#</th>
                        <th>Postulante</th>
                        <th style="width: 200px;">Archivo CV</th>
                        <th style="width: 150px;" class="text-center">Fecha de carga</th>
                        <th style="width: 100px;" class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cvsPendientesConv as $i => $cv): ?>
                    <tr>
                        <td class="text-center fw-bold text-muted"><?= $i + 1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                     style="width: 40px; height: 40px; background: <?= $color['border'] ?>; font-size: 0.85rem;">
                                    <?php 
                                    $nombres = explode(' ', $cv['postulante']);
                                    echo strtoupper(substr($nombres[0], 0, 1) . (isset($nombres[1]) ? substr($nombres[1], 0, 1) : ''));
                                    ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($cv['postulante']) ?></div>
                                    <div class="text-muted small">
                                        <i class="bi bi-person-badge me-1"></i>DNI: <?= htmlspecialchars($cv['dni']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="small text-muted">
                                <i class="bi bi-file-earmark-pdf me-1"></i>
                                <?= htmlspecialchars(mb_strimwidth($cv['nombre_archivo'] ?? 'CV.pdf', 0, 25, '…')) ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark">
                                <?= date('d/m/Y', strtotime($cv['fecha_carga'])) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-warning text-dark px-3 py-2">
                                <i class="bi bi-hourglass-split me-1"></i>Pendiente
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Footer con resumen -->
    <div class="card-footer bg-light text-muted small d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-info-circle me-1"></i>
            Total de CVs a evaluar: <strong><?= count($cvsPendientesConv) ?></strong>
        </span>
        <span class="text-end">
            Haz clic en "Evaluar Todo" para procesar con ML
        </span>
    </div>

</div><!-- /card convocatoria -->
<?php endforeach; ?>
<?php endif; ?>


<?php endif; ?>


<!-- ══════════════════════════════════════════════════════════
     MODAL: PROCESAR CVs
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalProcesar" tabindex="-1" aria-labelledby="modalProcesarLabel">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <!-- Paso 1: Selección -->
            <div id="paso-seleccion">
                <div class="modal-header" style="background:var(--primary);color:#fff">
                    <h5 class="modal-title" id="modalProcesarLabel">
                        <i class="bi bi-robot me-2"></i>Procesar CVs con Machine Learning
                    </h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Selecciona la convocatoria y los curriculums que deseas analizar.
                        El sistema calculará el puntaje y el ranking automáticamente.
                    </p>

                    <!-- Selector de convocatoria -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-megaphone me-1"></i> Convocatoria
                        </label>
                        <select id="selectConvocatoria" class="form-select"
                                onchange="filtrarCVsPendientes(this.value)">
                            <option value="">— Todas las convocatorias —</option>
                            <?php foreach ($convocatorias as $c): ?>
                            <option value="<?= $c['id_convocatoria'] ?>"
                                    data-pendientes="<?= $c['pendientes'] ?>"
                                    data-titulo="<?= htmlspecialchars($c['conv_titulo'] ?? $c['titulo']) ?>">
                                <?= htmlspecialchars($c['titulo']) ?>
                                <?php if ($c['pendientes'] > 0): ?>
                                (<?= $c['pendientes'] ?> pendientes)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Lista de CVs pendientes -->
                    <div id="lista-cvs-container">
                        <?php if (empty($cvsPendientes)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            No hay CVs pendientes de evaluación.
                            Puedes re-evaluar seleccionando una convocatoria arriba.
                        </div>
                        <?php else: ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-semibold mb-0">
                                <i class="bi bi-file-earmark-person me-1"></i>
                                CVs a evaluar
                            </label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                        onclick="seleccionarTodos(true)">
                                    Seleccionar todos
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        onclick="seleccionarTodos(false)">
                                    Deseleccionar
                                </button>
                            </div>
                        </div>
                        <div style="max-height:300px;overflow-y:auto;border:1px solid #dee2e6;border-radius:8px">
                            <table class="table table-hover table-sm mb-0" id="tablaCVsPendientes">
                                <thead class="sticky-top" style="background:#f4f6fb">
                                    <tr>
                                        <th style="width:40px">
                                            <input type="checkbox" id="checkAll" checked
                                                   onchange="seleccionarTodos(this.checked)">
                                        </th>
                                        <th>Postulante</th>
                                        <th>Convocatoria</th>
                                        <th>Archivo</th>
                                        <th>Subido</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cvsPendientes as $cv): ?>
                                    <tr class="cv-row"
                                        data-conv="<?= $cv['id_convocatoria'] ?>">
                                        <td>
                                            <input type="checkbox"
                                                   class="cv-check"
                                                   name="cvs[]"
                                                   value="<?= $cv['id_curriculum'] ?>"
                                                   checked>
                                        </td>
                                        <td>
                                            <div class="fw-semibold small">
                                                <?= htmlspecialchars($cv['postulante']) ?>
                                            </div>
                                            <div class="text-muted" style="font-size:.72rem">
                                                DNI: <?= htmlspecialchars($cv['dni']) ?>
                                            </div>
                                        </td>
                                        <td class="small text-muted">
                                            <?= htmlspecialchars(mb_strimwidth($cv['conv_titulo'], 0, 30, '…')) ?>
                                        </td>
                                        <td class="small text-muted">
                                            <i class="bi bi-file-earmark me-1"></i>
                                            <?= htmlspecialchars(mb_strimwidth($cv['nombre_archivo'] ?? '—', 0, 20, '…')) ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?= date('d/m/Y', strtotime($cv['fecha_carga'])) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-muted small mt-1">
                            <span id="countSeleccionados"><?= count($cvsPendientes) ?></span>
                            de <?= count($cvsPendientes) ?> seleccionados
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary fw-semibold"
                            id="btnIniciarProceso"
                            onclick="iniciarProcesamiento()"
                            <?= empty($cvsPendientes) ? 'disabled' : '' ?>>
                        <i class="bi bi-play-circle me-1"></i>
                        Iniciar evaluación ML
                    </button>
                </div>
            </div>

            <!-- Paso 2: Animación de procesamiento -->
            <div id="paso-procesando" style="display:none">
                <div class="modal-body text-center py-5">
                    <div class="mb-4">
                        <!-- Animación robot -->
                        <div id="robot-animacion" style="font-size:4rem;animation:pulse 1s infinite">
                            🤖
                        </div>
                    </div>
                    <h4 class="fw-bold mb-2" style="color:var(--primary)">
                        Analizando curriculums...
                    </h4>
                    <p class="text-muted mb-4" id="texto-progreso">
                        Extrayendo texto y calculando coincidencias con el perfil...
                    </p>
                    <!-- Barra de progreso -->
                    <div class="progress mb-3" style="height:12px;border-radius:6px">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                             id="barraProgreso"
                             style="width:0%;transition:width .5s ease"></div>
                    </div>
                    <div class="text-muted small">
                        <span id="cvActual">0</span> de <span id="cvTotal">0</span> curriculums procesados
                    </div>
                    <!-- Detalle en tiempo real -->
                    <div id="log-procesamiento"
                         style="background:#f8faff;border-radius:8px;padding:1rem;margin-top:1.5rem;
                                text-align:left;max-height:160px;overflow-y:auto;font-size:.78rem">
                        <div class="text-muted">Iniciando proceso...</div>
                    </div>
                </div>
            </div>

            <!-- Paso 3: Resultado final -->
            <div id="paso-resultado" style="display:none">
                <div class="modal-body text-center py-5">
                    <div style="font-size:4rem" class="mb-3" id="resultado-icon">✅</div>
                    <h4 class="fw-bold mb-2 text-success" id="resultado-titulo">
                        ¡Evaluación finalizada!
                    </h4>
                    <p class="text-muted mb-4" id="resultado-desc"></p>

                    <div class="row g-3 justify-content-center mb-4" id="resultado-stats">
                        <!-- Se rellena por JS -->
                    </div>

                    <div class="alert alert-light border text-start small" id="resultado-detalle"
                         style="display:none">
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal"
                            onclick="location.reload()">
                        Cerrar
                    </button>
                    <a href="<?= BASE_URL ?>/modules/reportes/index.php" class="btn btn-primary fw-semibold">
                        <i class="bi bi-bar-chart-fill me-1"></i>
                        Ver Reportes
                    </a>
                </div>
            </div>

        </div><!-- /modal-content -->
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL: DETALLE DE EVALUACIÓN
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--primary);color:#fff">
                <h5 class="modal-title">
                    <i class="bi bi-bar-chart me-2"></i>Detalle de evaluación ML
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleModalBody">
                <!-- Rellenado por JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">Cerrar</button>
                <a href="#" id="btnVerDetalle" class="btn btn-primary">
                    <i class="bi bi-eye me-1"></i> Ver evaluación completa
                </a>
            </div>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL: VERIFICAR CV
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerificar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="verificar_cv.php" id="formVerificar">
                <div class="modal-header" style="background:#198754;color:#fff">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle me-2"></i>Verificar Evaluación
                    </h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_evaluacion" id="verificar_id">
                    
                    <div class="text-center mb-3 pb-3 border-bottom">
                        <div class="fw-bold fs-5" id="verificar_postulante"></div>
                        <div class="mt-2">
                            <span class="badge bg-primary fs-6 px-3 py-2">
                                Puntaje ML: <span id="verificar_puntaje"></span>/100
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold required">
                            <i class="bi bi-clipboard-check me-1"></i> Resultado de la verificación
                        </label>
                        <select name="resultado" class="form-select" required>
                            <option value="">— Seleccionar —</option>
                            <option value="ACEPTADO">✓ Aceptado para el puesto</option>
                            <option value="EN_ESPERA">⏳ En espera / Lista de reserva</option>
                            <option value="RECHAZADO">✗ Rechazado</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold required">
                            <i class="bi bi-chat-text me-1"></i> Comentario / Motivo
                        </label>
                        <textarea name="comentario" class="form-control" rows="4" 
                                  placeholder="Describe el motivo de la decisión, observaciones sobre el CV, resultados de entrevista, etc."
                                  required></textarea>
                        <div class="form-text">
                            Este comentario será visible para el postulante en su consulta de resultados.
                        </div>
                    </div>

                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Nota:</strong> Una vez verificado, el postulante podrá consultar el resultado 
                        y descargar el PDF de su evaluación.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> Guardar verificación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<style>
@keyframes pulse {
    0%,100% { transform: scale(1); }
    50%      { transform: scale(1.15); }
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
</style>

<script>
// ── Datos de convocatorias pasados desde PHP ──────────────
const BASE_URL   = '<?= BASE_URL ?>';
const CONV_ID_PARAM = new URLSearchParams(window.location.search).get('conv');

// ── Filtrar CVs por convocatoria en el modal ──────────────
function filtrarCVsPendientes(convId) {
    const rows = document.querySelectorAll('.cv-row');
    rows.forEach(row => {
        const mostrar = !convId || row.dataset.conv === convId;
        row.style.display = mostrar ? '' : 'none';
    });
    actualizarContador();

    const btn = document.getElementById('btnIniciarProceso');
    const visibles = [...document.querySelectorAll('.cv-row')]
        .filter(r => r.style.display !== 'none');
    btn.disabled = visibles.length === 0;
}

// ── Seleccionar / deseleccionar todos ────────────────────
function seleccionarTodos(estado) {
    document.querySelectorAll('.cv-check').forEach(cb => {
        const row = cb.closest('tr');
        if (row.style.display !== 'none') cb.checked = estado;
    });
    const checkAll = document.getElementById('checkAll');
    if (checkAll) checkAll.checked = estado;
    actualizarContador();
}

// ── Contar seleccionados ──────────────────────────────────
function actualizarContador() {
    const total     = [...document.querySelectorAll('.cv-check')]
                        .filter(c => c.closest('tr').style.display !== 'none').length;
    const marcados  = [...document.querySelectorAll('.cv-check:checked')]
                        .filter(c => c.closest('tr').style.display !== 'none').length;
    const el = document.getElementById('countSeleccionados');
    if (el) el.textContent = marcados;

    const btn = document.getElementById('btnIniciarProceso');
    if (btn) btn.disabled = marcados === 0;
}

document.querySelectorAll('.cv-check').forEach(cb => {
    cb.addEventListener('change', actualizarContador);
});

// ── Pre-seleccionar convocatoria si viene del botón ───────
document.addEventListener('DOMContentLoaded', function() {
    const modalProcesar = document.getElementById('modalProcesar');
    
    modalProcesar.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const convId = button?.getAttribute('data-conv');
        
        if (convId) {
            // Seleccionar la convocatoria específica
            const selectConv = document.getElementById('selectConvocatoria');
            if (selectConv) {
                selectConv.value = convId;
                filtrarCVsPendientes(convId);
            }
        } else {
            // Si no hay convocatoria específica, mostrar todos
            const selectConv = document.getElementById('selectConvocatoria');
            if (selectConv) {
                selectConv.value = '';
                filtrarCVsPendientes('');
            }
        }
    });
});

// ── INICIAR PROCESAMIENTO ─────────────────────────────────
async function iniciarProcesamiento() {
    // Obtener IDs de CVs seleccionados visibles
    const cvsSeleccionados = [...document.querySelectorAll('.cv-check:checked')]
        .filter(c => c.closest('tr').style.display !== 'none');
    
    const cvIds = cvsSeleccionados.map(c => c.value);
    const cvNombres = cvsSeleccionados.map(c => {
        const row = c.closest('tr');
        const nombreEl = row.querySelector('.fw-semibold');
        return nombreEl ? nombreEl.textContent.trim() : 'Postulante';
    });

    const convId = document.getElementById('selectConvocatoria')?.value || '';

    if (cvIds.length === 0) {
        alert('Selecciona al menos un curriculum para procesar.');
        return;
    }

    // Cambiar a vista de procesamiento
    document.getElementById('paso-seleccion').style.display  = 'none';
    document.getElementById('paso-procesando').style.display = 'block';
    document.getElementById('paso-resultado').style.display  = 'none';

    const barra    = document.getElementById('barraProgreso');
    const txtProg  = document.getElementById('texto-progreso');
    const cvActual = document.getElementById('cvActual');
    const cvTotal  = document.getElementById('cvTotal');
    const log      = document.getElementById('log-procesamiento');

    cvTotal.textContent  = cvIds.length;
    cvActual.textContent = '0';
    log.innerHTML = '<div class="text-info"><i class="bi bi-hourglass-split me-2"></i>Iniciando proceso...</div>';

    let procesados = 0;
    let errores    = [];

    // Llamar al servidor (procesar.php) con los IDs seleccionados
    try {
        const formData = new FormData();
        cvIds.forEach(id => formData.append('cv_ids[]', id));
        if (convId) formData.append('conv', convId);

        // Simular progreso mientras esperamos respuesta
        let progresoSimulado = 0;
        const intervaloProgreso = setInterval(() => {
            if (progresoSimulado < 90) {
                progresoSimulado += 2;
                barra.style.width = progresoSimulado + '%';
            }
        }, 100);

        // Simular mensajes por cada CV
        for (let i = 0; i < cvIds.length; i++) {
            const nombrePostulante = cvNombres[i];
            
            // Simular pasos de evaluación
            await new Promise(r => setTimeout(r, 400));
            txtProg.innerHTML = `<strong>${nombrePostulante}</strong> - Evaluación en proceso...`;
            log.innerHTML += `<div class="text-primary"><i class="bi bi-arrow-right me-2"></i>Evaluando CV de <strong>${nombrePostulante}</strong></div>`;
            log.scrollTop = log.scrollHeight;
            
            cvActual.textContent = (i + 1);
            const pct = Math.round(((i + 1) / cvIds.length) * 90);
            if (progresoSimulado < pct) {
                progresoSimulado = pct;
                clearInterval(intervaloProgreso);
                barra.style.width = pct + '%';
            }
        }

        // Llamada real al servidor
        const resp = await fetch(BASE_URL + '/modules/evaluaciones/procesar_ajax.php', {
            method: 'POST',
            body:   formData,
        });

        const data = await resp.json();
        clearInterval(intervaloProgreso);

        barra.style.width = '100%';
        barra.classList.remove('progress-bar-animated');

        procesados = data.procesados ?? 0;
        errores    = data.errores    ?? [];

        // Log final
        log.innerHTML = '';
        if (data.log && data.log.length) {
            data.log.forEach(line => {
                const d = document.createElement('div');
                d.className = line.ok ? 'text-success' : 'text-danger';
                d.innerHTML = (line.ok ? '✅ ' : '❌ ') + line.msg;
                log.appendChild(d);
            });
        } else {
            // Mostrar resultado resumido
            cvIds.forEach((id, i) => {
                const nombrePostulante = cvNombres[i];
                log.innerHTML += `<div class="text-success"><i class="bi bi-check-circle me-2"></i><strong>${nombrePostulante}</strong> - Evaluado correctamente</div>`;
            });
        }

        txtProg.innerHTML = '<strong class="text-success">✓ Evaluación completada</strong>';

        // Pequeña pausa visual
        await new Promise(r => setTimeout(r, 1000));

        mostrarResultado(procesados, errores, cvIds.length);

    } catch (err) {
        clearInterval(intervaloProgreso);
        log.innerHTML += `<div class="text-danger"><i class="bi bi-x-circle me-2"></i>Error: ${err.message}</div>`;
        await new Promise(r => setTimeout(r, 1500));
        mostrarResultado(0, ['Error de conexión: ' + err.message], cvIds.length);
    }
}

// ── Mostrar pantalla de resultado ────────────────────────
function mostrarResultado(procesados, errores, total) {
    document.getElementById('paso-procesando').style.display = 'none';
    document.getElementById('paso-resultado').style.display  = 'block';

    const icon   = document.getElementById('resultado-icon');
    const titulo = document.getElementById('resultado-titulo');
    const desc   = document.getElementById('resultado-desc');
    const stats  = document.getElementById('resultado-stats');

    if (procesados > 0 && errores.length === 0) {
        icon.textContent   = '✅';
        titulo.textContent = '¡Evaluación finalizada!';
        titulo.className   = 'fw-bold mb-2 text-success';
        desc.textContent   = `Se analizaron ${procesados} curriculum(s) correctamente. El ranking ha sido actualizado.`;
    } else if (procesados > 0 && errores.length > 0) {
        icon.textContent   = '⚠️';
        titulo.textContent = 'Evaluación completada con advertencias';
        titulo.className   = 'fw-bold mb-2 text-warning';
        desc.textContent   = `${procesados} procesados, ${errores.length} con errores.`;
    } else {
        icon.textContent   = '❌';
        titulo.textContent = 'No se pudo completar la evaluación';
        titulo.className   = 'fw-bold mb-2 text-danger';
        desc.textContent   = 'Revisa los errores a continuación.';
    }

    stats.innerHTML = `
        <div class="col-4">
            <div class="p-3 bg-success bg-opacity-10 rounded-3 text-center">
                <div class="fw-bold fs-3 text-success">${procesados}</div>
                <div class="text-muted small">Evaluados</div>
            </div>
        </div>
        <div class="col-4">
            <div class="p-3 bg-danger bg-opacity-10 rounded-3 text-center">
                <div class="fw-bold fs-3 text-danger">${errores.length}</div>
                <div class="text-muted small">Errores</div>
            </div>
        </div>
        <div class="col-4">
            <div class="p-3 bg-primary bg-opacity-10 rounded-3 text-center">
                <div class="fw-bold fs-3 text-primary">${total}</div>
                <div class="text-muted small">Total</div>
            </div>
        </div>
    `;

    if (errores.length > 0) {
        const det = document.getElementById('resultado-detalle');
        det.style.display = '';
        det.innerHTML = '<strong>Detalles de errores:</strong><br>' +
            errores.map(e => '• ' + e).join('<br>');
    }
}

// ── Modal de detalle: rellenar datos ─────────────────────
document.getElementById('modalDetalle').addEventListener('show.bs.modal', function(e) {
    const btn  = e.relatedTarget;
    if (!btn) return;

    const id          = btn.dataset.id;
    const postulante  = btn.dataset.postulante;
    const puntaje     = parseFloat(btn.dataset.puntaje);
    const coincidencia= parseFloat(btn.dataset.coincidencia);
    const ranking     = btn.dataset.ranking;
    const obs         = btn.dataset.obs;
    const modelo      = btn.dataset.modelo;
    const fecha       = btn.dataset.fecha;

    const scoreColor = puntaje >= 70 ? '#198754' : (puntaje >= 50 ? '#f0a500' : '#dc3545');
    const scoreClass = puntaje >= 70 ? 'score-high' : (puntaje >= 50 ? 'score-medium' : 'score-low');

    document.getElementById('detalleModalBody').innerHTML = `
        <div class="text-center mb-3 pb-3 border-bottom">
            <div class="fw-bold fs-5">${postulante}</div>
            <div class="mt-3 d-flex justify-content-center gap-4">
                <div>
                    <div style="font-size:3rem;font-weight:700;color:${scoreColor};line-height:1">
                        ${puntaje}
                    </div>
                    <div class="text-muted small">/ 100 pts</div>
                </div>
                <div class="vr"></div>
                <div>
                    <div style="font-size:3rem;font-weight:700;color:var(--primary);line-height:1">
                        #${ranking}
                    </div>
                    <div class="text-muted small">Ranking</div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between mb-1">
                <span class="fw-semibold small">Coincidencia con el perfil</span>
                <span class="fw-bold">${coincidencia}%</span>
            </div>
            <div class="ranking-bar" style="height:14px;border-radius:7px">
                <div class="ranking-bar-fill ${scoreClass}"
                     style="width:${coincidencia}%;height:100%;border-radius:7px"></div>
            </div>
        </div>

        <dl class="row small mb-3">
            <dt class="col-5 text-muted">Modelo ML</dt>
            <dd class="col-7">${modelo || '—'}</dd>
            <dt class="col-5 text-muted">Fecha evaluación</dt>
            <dd class="col-7">${fecha}</dd>
        </dl>

        ${obs ? `<div class="alert alert-light border small">
            <i class="bi bi-chat-text me-1"></i><strong>Observación:</strong> ${obs}
        </div>` : ''}
    `;

    document.getElementById('btnVerDetalle').href = BASE_URL + '/modules/evaluaciones/ver.php?id=' + id;
});

// ── Modal de verificación: rellenar datos ────────────────
document.getElementById('modalVerificar').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    if (!btn) return;

    document.getElementById('verificar_id').value = btn.dataset.id;
    document.getElementById('verificar_postulante').textContent = btn.dataset.postulante;
    document.getElementById('verificar_puntaje').textContent = btn.dataset.puntaje;
    
    // Reset form
    document.getElementById('formVerificar').reset();
    document.getElementById('verificar_id').value = btn.dataset.id;
});

// ── Reset modal al cerrar ─────────────────────────────────
document.getElementById('modalProcesar').addEventListener('hidden.bs.modal', function() {
    document.getElementById('paso-seleccion').style.display  = 'block';
    document.getElementById('paso-procesando').style.display = 'none';
    document.getElementById('paso-resultado').style.display  = 'none';
    const barra = document.getElementById('barraProgreso');
    if (barra) {
        barra.style.width = '0%';
        barra.classList.add('progress-bar-animated');
    }
});
</script>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
