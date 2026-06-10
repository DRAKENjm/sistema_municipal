<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

$pageTitle = 'Convocatorias disponibles';
$post = $_SESSION['postulante'] ?? null;
if (!$post) { header('Location: ' . BASE_URL . '/portal/login.php'); exit; }

$db = getDB();

$buscar = $_GET['buscar'] ?? '';
$params = [];
$where  = "c.estado = 'ACTIVA'";
if ($buscar !== '') {
    $where .= ' AND (c.titulo LIKE ? OR c.descripcion LIKE ?)';
    $params = ["%$buscar%", "%$buscar%"];
}

$stmt = $db->prepare("
    SELECT c.*, a.nombre AS area_nombre,
           (SELECT COUNT(*) FROM curriculums cv WHERE cv.id_convocatoria=c.id_convocatoria) AS total_postulantes
    FROM convocatorias c
    LEFT JOIN areas a ON a.id_area=c.id_area
    WHERE $where
    ORDER BY c.fecha_registro DESC
");
$stmt->execute($params);
$convocatorias = $stmt->fetchAll();

// IDs donde ya postuló este postulante
$yaPostulo = $db->prepare("SELECT id_convocatoria FROM curriculums WHERE id_postulante=?");
$yaPostulo->execute([$post['id_postulante']]);
$idsYaPostulo = $yaPostulo->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/includes/portal_head.php';
?>

<div class="portal-card mb-4">
    <div class="portal-card-header">
        <h4 class="portal-card-title">
            <i class="bi bi-megaphone"></i>
            Convocatorias disponibles
        </h4>
        <p class="text-muted mb-0">Explora las oportunidades laborales disponibles en nuestra institución</p>
    </div>
</div>

<form method="GET" class="mb-4">
    <div class="input-group shadow-sm">
        <span class="input-group-text bg-white">
            <i class="bi bi-search"></i>
        </span>
        <input type="text" name="buscar" class="form-control border-start-0"
               placeholder="Buscar por título, descripción o requisitos..."
               value="<?= htmlspecialchars($buscar) ?>">
        <button type="submit" class="btn btn-portal-primary">Buscar</button>
        <?php if ($buscar): ?>
        <a href="convocatorias.php" class="btn btn-portal-secondary">
            <i class="bi bi-x-lg"></i>
        </a>
        <?php endif; ?>
    </div>
</form>

<?php if ($buscar): ?>
<div class="mb-3">
    <span class="text-muted">
        Se encontraron <strong><?= count($convocatorias) ?></strong> resultado(s) para: 
        <strong>"<?= htmlspecialchars($buscar) ?>"</strong>
    </span>
</div>
<?php endif; ?>

<?php if (empty($convocatorias)): ?>
<div class="empty-state">
    <div class="empty-state-icon">
        <i class="bi bi-megaphone"></i>
    </div>
    <h5>No se encontraron convocatorias</h5>
    <p>No hay convocatorias que coincidan con tu búsqueda. Intenta con otros términos.</p>
    <?php if ($buscar): ?>
    <a href="convocatorias.php" class="btn btn-portal-primary">
        <i class="bi bi-arrow-left me-1"></i>Ver todas las convocatorias
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($convocatorias as $c): ?>
    <div class="convocatoria-card">
        <div class="convocatoria-header">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge-portal success">
                        <i class="bi bi-circle-fill pulse" style="font-size:0.5rem"></i>
                        Activa
                    </span>
                    <?php if ($c['area_nombre']): ?>
                    <span class="badge-portal secondary">
                        <i class="bi bi-building"></i><?= htmlspecialchars($c['area_nombre']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (in_array($c['id_convocatoria'], $idsYaPostulo)): ?>
                    <span class="badge-portal info">
                        <i class="bi bi-check-circle"></i>Ya postulaste
                    </span>
                    <?php endif; ?>
                </div>
                <?php if ($c['salario_referencial']): ?>
                <div class="text-success fw-bold fs-5">
                    S/ <?= number_format($c['salario_referencial'], 2) ?>
                </div>
                <?php endif; ?>
            </div>
            
            <h5 class="convocatoria-title"><?= htmlspecialchars($c['titulo']) ?></h5>
            
            <div class="convocatoria-meta">
                <?php if ($c['fecha_inicio']): ?>
                <span>
                    <i class="bi bi-calendar-event"></i>
                    Apertura: <?= date('d/m/Y', strtotime($c['fecha_inicio'])) ?>
                </span>
                <?php endif; ?>
                <?php if ($c['fecha_fin']): ?>
                <span>
                    <i class="bi bi-calendar-x"></i>
                    Cierre: <?= date('d/m/Y', strtotime($c['fecha_fin'])) ?>
                </span>
                <?php endif; ?>
                <span>
                    <i class="bi bi-people"></i>
                    <?= $c['total_postulantes'] ?> postulante(s)
                </span>
            </div>
        </div>

        <div class="convocatoria-body">
            <?php if ($c['descripcion']): ?>
            <div class="mb-3">
                <h6 class="fw-semibold text-muted small mb-2">Descripción</h6>
                <p class="small mb-0">
                    <?= nl2br(htmlspecialchars(mb_strimwidth($c['descripcion'], 0, 250, '...'))) ?>
                </p>
            </div>
            <?php endif; ?>

            <?php if ($c['requisitos']): ?>
            <div class="mb-2">
                <h6 class="fw-semibold text-muted small mb-2">Requisitos principales</h6>
                <p class="small mb-0 text-muted">
                    <?= nl2br(htmlspecialchars(mb_strimwidth($c['requisitos'], 0, 200, '...'))) ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <div class="convocatoria-footer">
            <div class="small text-muted">
                <i class="bi bi-clock me-1"></i>
                Publicado el <?= date('d/m/Y', strtotime($c['fecha_registro'])) ?>
            </div>
            <a href="postular.php?id=<?= $c['id_convocatoria'] ?>"
               class="btn btn-portal-primary">
                <?= in_array($c['id_convocatoria'], $idsYaPostulo)
                    ? '<i class="bi bi-arrow-repeat me-1"></i>Actualizar CV'
                    : '<i class="bi bi-upload me-1"></i>Postular ahora' ?>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/portal_foot.php'; ?>
