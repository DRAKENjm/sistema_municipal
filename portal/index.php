<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

$pageTitle = 'Inicio';
$post = $_SESSION['postulante'] ?? null;
if (!$post) { header('Location: ' . BASE_URL . '/portal/login.php'); exit; }

$db = getDB();

// Recargar datos actualizados del postulante
$stmt = $db->prepare("SELECT * FROM postulantes WHERE id_postulante = ?");
$stmt->execute([$post['id_postulante']]);
$post = $stmt->fetch();
$_SESSION['postulante'] = $post;

// Estadísticas del postulante
$totalPostulaciones = $db->prepare("SELECT COUNT(*) FROM curriculums WHERE id_postulante=?");
$totalPostulaciones->execute([$post['id_postulante']]);
$totalPostulaciones = (int)$totalPostulaciones->fetchColumn();

$totalEvaluadas = $db->prepare("SELECT COUNT(*) FROM evaluaciones_ml WHERE id_postulante=?");
$totalEvaluadas->execute([$post['id_postulante']]);
$totalEvaluadas = (int)$totalEvaluadas->fetchColumn();

$mejorPuntaje = $db->prepare("SELECT MAX(puntaje) FROM evaluaciones_ml WHERE id_postulante=?");
$mejorPuntaje->execute([$post['id_postulante']]);
$mejorPuntaje = $mejorPuntaje->fetchColumn();

// Convocatorias activas disponibles
$convActivas = $db->query("
    SELECT c.*, a.nombre AS area_nombre,
           (SELECT COUNT(*) FROM curriculums cv WHERE cv.id_convocatoria=c.id_convocatoria) AS total_postulantes
    FROM convocatorias c
    LEFT JOIN areas a ON a.id_area=c.id_area
    WHERE c.estado='ACTIVA'
    ORDER BY c.fecha_registro DESC
    LIMIT 4
")->fetchAll();

// IDs de convocatorias donde ya postuló
$yaPostulo = $db->prepare("SELECT id_convocatoria FROM curriculums WHERE id_postulante=?");
$yaPostulo->execute([$post['id_postulante']]);
$idsYaPostulo = $yaPostulo->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/includes/portal_head.php';
?>

<!-- Hero Section con imagen de fondo -->
<div class="hero-banner" style="background: linear-gradient(135deg, rgba(26,58,92,0.85) 0%, rgba(44,82,130,0.75) 100%), url('<?= BASE_URL ?>/portal/municipalidadfondo.jpg') center/cover no-repeat; border-radius: 24px; padding: 3rem 2.5rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.15); position: relative; overflow: hidden;">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <h1 class="text-white fw-bold mb-3" style="font-size: 2.5rem;">
                👋 ¡Hola, <?= htmlspecialchars(explode(' ', $post['nombres'])[0]) ?>!
            </h1>
            <p class="text-white mb-4" style="font-size: 1.15rem; opacity: 0.95;">
                Encuentra oportunidades laborales en tu municipio
            </p>
            
            <!-- Stats integrados en el hero -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-mini">
                        <i class="bi bi-briefcase mb-2"></i>
                        <div class="stat-mini-value"><?= $totalPostulaciones ?></div>
                        <div class="stat-mini-label">Postulaciones enviadas</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-mini">
                        <i class="bi bi-check-circle mb-2"></i>
                        <div class="stat-mini-value"><?= $totalEvaluadas ?></div>
                        <div class="stat-mini-label">CV evaluados</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-mini">
                        <i class="bi bi-trophy mb-2"></i>
                        <div class="stat-mini-value"><?= $mejorPuntaje !== null ? number_format($mejorPuntaje, 1) : '—' ?></div>
                        <div class="stat-mini-label">Mejor puntaje obtenido</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-mini">
                        <i class="bi bi-calendar-event mb-2"></i>
                        <div class="stat-mini-value"><?= date('d') ?> jun.</div>
                        <div class="stat-mini-label">Próximo cierre importante</div>
                    </div>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="d-flex gap-3 flex-wrap">
                <a href="convocatorias.php" class="btn btn-warning btn-lg fw-bold px-4" style="border-radius: 12px;">
                    Ver convocatorias <i class="bi bi-arrow-right ms-2"></i>
                </a>
                <a href="perfil.php" class="btn btn-outline-light btn-lg px-4" style="border-radius: 12px; border-width: 2px;">
                    <i class="bi bi-person-fill me-2"></i>Completar mi CV
                </a>
            </div>
        </div>
        
        <div class="col-lg-5 d-none d-lg-block">
            <div class="text-end">
                <!-- Aquí iría la imagen del edificio, ya está en el fondo -->
            </div>
        </div>
    </div>
</div>

<!-- Filtros y búsqueda -->
<div class="bg-white rounded-4 p-4 shadow-sm mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-lg-5">
            <div class="input-group input-group-lg">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0" 
                       placeholder="Buscar convocatorias por cargo, área o palabra clave...">
            </div>
        </div>
        <div class="col-lg-2">
            <select class="form-select form-select-lg">
                <option>Área profesional</option>
                <option>Tecnología</option>
                <option>Administración</option>
                <option>Salud</option>
            </select>
        </div>
        <div class="col-lg-2">
            <select class="form-select form-select-lg">
                <option>Modalidad</option>
                <option>Presencial</option>
                <option>Remoto</option>
                <option>Híbrido</option>
            </select>
        </div>
        <div class="col-lg-2">
            <select class="form-select form-select-lg">
                <option>Ordenar por</option>
                <option>Más recientes</option>
                <option>Cierre próximo</option>
                <option>Mejor pagados</option>
            </select>
        </div>
        <div class="col-lg-1">
            <button class="btn btn-primary btn-lg w-100">
                <i class="bi bi-funnel-fill"></i>
            </button>
        </div>
    </div>
</div>

<!-- Layout de 2 columnas -->
<div class="row g-4">
    <!-- Columna izquierda: Postulaciones -->
    <div class="col-lg-8">
        <div class="bg-white rounded-4 p-4 shadow-sm mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0" style="color:var(--portal-primary)">
                    <i class="bi bi-file-earmark-text me-2"></i>Estado de mis postulaciones
                </h5>
                <a href="mis_postulaciones.php" class="text-primary text-decoration-none fw-semibold">
                    Ver todas <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            
            <?php 
            // Obtener últimas 3 postulaciones
            $misPostulaciones = $db->prepare("
                SELECT cv.*, c.titulo AS conv_titulo, c.estado AS conv_estado,
                       e.puntaje, e.verificado, e.resultado_verificacion,
                       cv.fecha_carga
                FROM curriculums cv
                JOIN convocatorias c ON c.id_convocatoria = cv.id_convocatoria
                LEFT JOIN evaluaciones_ml e ON e.id_curriculum = cv.id_curriculum
                WHERE cv.id_postulante = ?
                ORDER BY cv.fecha_carga DESC
                LIMIT 3
            ");
            $misPostulaciones->execute([$post['id_postulante']]);
            $misPostulaciones = $misPostulaciones->fetchAll();
            ?>
            
            <?php if (empty($misPostulaciones)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox display-4 opacity-25 d-block mb-3"></i>
                <p>No tienes postulaciones aún</p>
                <a href="convocatorias.php" class="btn btn-primary">Ver convocatorias</a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Convocatoria</th>
                            <th class="text-center">Fecha de postulación</th>
                            <th class="text-center">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($misPostulaciones as $mp): 
                            if (!$mp['procesado']) {
                                $estadoBadge = '<span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-hourglass-split me-1"></i>En evaluación</span>';
                            } elseif (!$mp['verificado']) {
                                $estadoBadge = '<span class="badge bg-info text-white px-3 py-2"><i class="bi bi-eye me-1"></i>Recepción de CV</span>';
                            } else {
                                $resultado = $mp['resultado_verificacion'];
                                if ($resultado === 'ACEPTADO') {
                                    $estadoBadge = '<span class="badge bg-success px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Aceptado</span>';
                                } elseif ($resultado === 'RECHAZADO') {
                                    $estadoBadge = '<span class="badge bg-danger px-3 py-2"><i class="bi bi-x-circle me-1"></i>No seleccionado</span>';
                                } else {
                                    $estadoBadge = '<span class="badge bg-secondary px-3 py-2"><i class="bi bi-clock me-1"></i>En espera</span>';
                                }
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($mp['conv_titulo']) ?></div>
                            </td>
                            <td class="text-center text-muted small">
                                <?= date('d/m/Y', strtotime($mp['fecha_carga'])) ?>
                            </td>
                            <td class="text-center">
                                <?= $estadoBadge ?>
                            </td>
                            <td class="text-end">
                                <a href="mis_resultados.php" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-3">
                <a href="mis_postulaciones.php" class="text-primary text-decoration-none fw-semibold">
                    Ver todas mis postulaciones <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Convocatorias recomendadas -->
        <div class="bg-white rounded-4 p-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0" style="color:var(--portal-primary)">
                    <i class="bi bi-star me-2"></i>Convocatorias recomendadas para ti
                </h5>
                <a href="convocatorias.php" class="text-primary text-decoration-none fw-semibold">
                    Ver todas <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            
            <div class="row g-3">
                <?php if (empty($convActivas)): ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-megaphone display-4 opacity-25 d-block mb-3"></i>
                    <p>No hay convocatorias activas en este momento</p>
                </div>
                <?php else: ?>
                <?php foreach (array_slice($convActivas, 0, 3) as $c): ?>
                <div class="col-12">
                    <div class="border rounded-3 p-3 hover-shadow">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-success px-3 py-2">Activa</span>
                            <?php if (in_array($c['id_convocatoria'], $idsYaPostulo)): ?>
                            <span class="badge bg-info px-2 py-1 small">Ya postulaste</span>
                            <?php endif; ?>
                        </div>
                        
                        <h6 class="fw-bold mb-2"><?= htmlspecialchars($c['titulo']) ?></h6>
                        
                        <div class="d-flex flex-wrap gap-3 small text-muted mb-3">
                            <span><i class="bi bi-building me-1"></i><?= htmlspecialchars($c['area_nombre'] ?? 'Sin área') ?></span>
                            <span><i class="bi bi-geo-alt me-1"></i>Chiclayo</span>
                            <span><i class="bi bi-briefcase me-1"></i>Presencial</span>
                            <span><i class="bi bi-cash me-1"></i>S/ <?= number_format($c['salario_referencial'] ?? 0, 0) ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="small">
                                <i class="bi bi-clock text-danger me-1"></i>
                                <span class="text-danger fw-semibold">Cierra en 5 días</span>
                                <span class="text-muted ms-2"><?= date('d \d\e \j\u\n\i\o \d\e Y', strtotime($c['fecha_fin'] ?? 'now')) ?></span>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="convocatorias.php" class="btn btn-sm btn-outline-primary">Ver detalles</a>
                                <a href="postular.php?id=<?= $c['id_convocatoria'] ?>" class="btn btn-sm btn-primary fw-semibold">
                                    Postular
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Columna derecha: Accesos rápidos -->
    <div class="col-lg-4">
        <!-- Accesos rápidos -->
        <div class="bg-white rounded-4 p-4 shadow-sm">
            <h6 class="fw-bold mb-4" style="color:var(--portal-primary)">
                Accesos rápidos
            </h6>
            
            <div class="d-grid gap-2">
                <a href="perfil.php" class="btn btn-outline-primary text-start d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 p-2 rounded">
                        <i class="bi bi-person-fill text-primary fs-5"></i>
                    </div>
                    <div class="flex-fill">
                        <div class="fw-semibold">Completar mi CV</div>
                        <small class="text-muted">Mejora tus oportunidades</small>
                    </div>
                    <i class="bi bi-arrow-right"></i>
                </a>
                
                <a href="mis_resultados.php" class="btn btn-outline-primary text-start d-flex align-items-center gap-3">
                    <div class="bg-success bg-opacity-10 p-2 rounded">
                        <i class="bi bi-clipboard-check text-success fs-5"></i>
                    </div>
                    <div class="flex-fill">
                        <div class="fw-semibold">Mis resultados</div>
                        <small class="text-muted">Ver estado de evaluaciones</small>
                    </div>
                    <i class="bi bi-arrow-right"></i>
                </a>
                
                <a href="mis_postulaciones.php" class="btn btn-outline-primary text-start d-flex align-items-center gap-3">
                    <div class="bg-info bg-opacity-10 p-2 rounded">
                        <i class="bi bi-briefcase text-info fs-5"></i>
                    </div>
                    <div class="flex-fill">
                        <div class="fw-semibold">Mis postulaciones</div>
                        <small class="text-muted">Historial completo</small>
                    </div>
                    <i class="bi bi-arrow-right"></i>
                </a>
                
                <a href="convocatorias.php" class="btn btn-outline-primary text-start d-flex align-items-center gap-3">
                    <div class="bg-warning bg-opacity-10 p-2 rounded">
                        <i class="bi bi-megaphone text-warning fs-5"></i>
                    </div>
                    <div class="flex-fill">
                        <div class="fw-semibold">Ver convocatorias</div>
                        <small class="text-muted">Encuentra oportunidades</small>
                    </div>
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.hero-banner {
    animation: fadeInUp 0.6s ease-out;
}

.stat-mini {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
    color: white;
    transition: all 0.3s ease;
}

.stat-mini:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-4px);
}

.stat-mini i {
    font-size: 1.5rem;
    opacity: 0.9;
}

.stat-mini-value {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-mini-label {
    font-size: 0.7rem;
    opacity: 0.9;
    font-weight: 500;
}

.hover-shadow {
    transition: all 0.3s ease;
}

.hover-shadow:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.activity-timeline {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.activity-item {
    display: flex;
    gap: 1rem;
    align-items-start;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
}
</style>

<?php include __DIR__ . '/includes/portal_foot.php'; ?>
