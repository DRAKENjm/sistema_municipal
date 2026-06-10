<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_BITACORA);

$db        = getDB();
$pageTitle = 'Bitácora del Sistema';

// ── Filtros ───────────────────────────────────────────────
$buscar        = trim($_GET['buscar']   ?? '');
$filtroUsuario = (int)($_GET['usuario'] ?? 0);
$filtroAccion  = trim($_GET['accion']  ?? '');
$filtroFechaD  = $_GET['fecha_d']      ?? '';   // desde
$filtroFechaH  = $_GET['fecha_h']      ?? '';   // hasta
$pagina        = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina     = 30;
$offset        = ($pagina - 1) * $porPagina;

// ── Construir WHERE ───────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($buscar !== '') {
    $where[]  = '(b.accion LIKE ? OR b.tabla_afectada LIKE ? OR b.descripcion LIKE ? OR u.usuario LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}
if ($filtroUsuario > 0) {
    $where[]  = 'b.id_usuario = ?';
    $params[] = $filtroUsuario;
}
if ($filtroAccion !== '') {
    $where[]  = 'b.accion = ?';
    $params[] = $filtroAccion;
}
if ($filtroFechaD !== '') {
    $where[]  = 'DATE(b.fecha) >= ?';
    $params[] = $filtroFechaD;
}
if ($filtroFechaH !== '') {
    $where[]  = 'DATE(b.fecha) <= ?';
    $params[] = $filtroFechaH;
}

$whereStr = implode(' AND ', $where);

// ── Total de registros ────────────────────────────────────
$stmtTotal = $db->prepare("
    SELECT COUNT(*)
    FROM bitacora b
    LEFT JOIN usuarios u ON u.id_usuario = b.id_usuario
    WHERE $whereStr
");
$stmtTotal->execute($params);
$total   = (int)$stmtTotal->fetchColumn();
$paginas = max(1, (int)ceil($total / $porPagina));

// ── Registros paginados ───────────────────────────────────
$stmtReg = $db->prepare("
    SELECT b.*,
           u.usuario,
           CONCAT(u.nombres, ' ', u.apellido_paterno) AS nombre_completo,
           r.nombre AS rol_nombre
    FROM bitacora b
    LEFT JOIN usuarios u ON u.id_usuario = b.id_usuario
    LEFT JOIN roles    r ON r.id_rol     = u.id_rol
    WHERE $whereStr
    ORDER BY b.fecha DESC
    LIMIT $porPagina OFFSET $offset
");
$stmtReg->execute($params);
$registros = $stmtReg->fetchAll();

// ── Datos para filtros ────────────────────────────────────
$usuarios = $db->query("
    SELECT DISTINCT u.id_usuario, u.usuario,
           CONCAT(u.nombres,' ',u.apellido_paterno) AS nombre_completo
    FROM bitacora b
    JOIN usuarios u ON u.id_usuario = b.id_usuario
    ORDER BY u.usuario
")->fetchAll();

$acciones = $db->query("
    SELECT DISTINCT accion FROM bitacora ORDER BY accion
")->fetchAll(PDO::FETCH_COLUMN);

// ── Estadísticas del resumen (últimos 30 días) ────────────
$stats = $db->query("
    SELECT
        COUNT(*)                                          AS total_30d,
        SUM(accion = 'LOGIN')                             AS logins,
        SUM(accion = 'LOGOUT')                            AS logouts,
        SUM(accion LIKE 'CREAR%')                         AS creaciones,
        SUM(accion LIKE 'EDITAR%')                        AS ediciones,
        SUM(accion LIKE 'ELIMINAR%')                      AS eliminaciones,
        SUM(accion LIKE 'BLOQUEAR%' OR accion LIKE 'DESACTIVAR%') AS bloqueos
    FROM bitacora
    WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch();

// ── Actividad por día (últimos 14 días, para mini gráfico) ─
$actividad14 = $db->query("
    SELECT DATE(fecha) AS dia, COUNT(*) AS total
    FROM bitacora
    WHERE fecha >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(fecha)
    ORDER BY dia ASC
")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Últimas IPs únicas ────────────────────────────────────
$ultimasIPs = $db->query("
    SELECT DISTINCT ip_origen, MAX(fecha) AS ultima_vez,
           COUNT(*) AS accesos
    FROM bitacora
    WHERE ip_origen IS NOT NULL
    GROUP BY ip_origen
    ORDER BY ultima_vez DESC
    LIMIT 5
")->fetchAll();

// ── Exportar CSV ──────────────────────────────────────────
if (isset($_GET['exportar'])) {
    $stmtExp = $db->prepare("
        SELECT b.fecha, u.usuario, b.accion, b.tabla_afectada,
               b.id_registro, b.descripcion, b.ip_origen
        FROM bitacora b
        LEFT JOIN usuarios u ON u.id_usuario = b.id_usuario
        WHERE $whereStr
        ORDER BY b.fecha DESC
    ");
    $stmtExp->execute($params);
    $filas = $stmtExp->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bitacora_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel
    fputcsv($out, ['Fecha', 'Usuario', 'Acción', 'Tabla', 'ID Registro', 'Descripción', 'IP'], ';');
    foreach ($filas as $f) {
        fputcsv($out, [
            $f['fecha'], $f['usuario'] ?? 'Sistema', $f['accion'],
            $f['tabla_afectada'] ?? '', $f['id_registro'] ?? '',
            $f['descripcion'] ?? '', $f['ip_origen'] ?? ''
        ], ';');
    }
    fclose($out);
    exit;
}

// ── Helper: color de badge por acción ────────────────────
function badgeAccion(string $accion): string {
    if (str_starts_with($accion, 'LOGIN'))            return 'bg-success';
    if (str_starts_with($accion, 'LOGOUT'))           return 'bg-secondary';
    if (str_starts_with($accion, 'CREAR'))            return 'bg-primary';
    if (str_starts_with($accion, 'EDITAR'))           return 'bg-warning text-dark';
    if (str_starts_with($accion, 'ELIMINAR'))         return 'bg-danger';
    if (str_starts_with($accion, 'PROCESAR'))         return 'bg-info text-dark';
    if (str_starts_with($accion, 'BLOQUEAR') ||
        str_starts_with($accion, 'DESACTIVAR'))       return 'bg-danger';
    if (str_starts_with($accion, 'ACTIVAR'))          return 'bg-success';
    if (str_starts_with($accion, 'REVISAR'))          return 'bg-info text-dark';
    return 'bg-secondary';
}

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="page-title mb-0">
        <i class="bi bi-journal-text me-2"></i>Bitácora del Sistema
    </h4>
    <a href="?<?= http_build_query(array_merge($_GET, ['exportar' => 1])) ?>"
       class="btn btn-outline-success btn-sm">
        <i class="bi bi-filetype-csv me-1"></i> Exportar CSV
    </a>
</div>

<!-- ══ RESUMEN ESTADÍSTICO (últimos 30 días) ══ -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="bg-white rounded-3 shadow-sm p-3">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="fw-bold mb-0" style="color:var(--primary)">
                    <i class="bi bi-graph-up me-1"></i> Actividad — últimos 30 días
                </h6>
                <span class="badge bg-primary"><?= number_format($stats['total_30d']) ?> acciones</span>
            </div>

            <!-- Mini gráfico de barras -->
            <div class="d-flex align-items-end gap-1 mb-3" style="height:48px" title="Actividad diaria">
                <?php
                // Rellenar días sin actividad
                $maxAct = max(array_values($actividad14) ?: [1]);
                $hoy = new DateTime();
                for ($i = 13; $i >= 0; $i--):
                    $dia = (clone $hoy)->modify("-$i days")->format('Y-m-d');
                    $cnt = $actividad14[$dia] ?? 0;
                    $h   = $maxAct > 0 ? round(($cnt / $maxAct) * 100) : 0;
                ?>
                <div style="flex:1;height:<?= max(4, $h) ?>%;background:var(--primary-light);
                            border-radius:3px 3px 0 0;min-height:4px;opacity:.75;cursor:default"
                     title="<?= $dia ?>: <?= $cnt ?> acción(es)"></div>
                <?php endfor; ?>
            </div>

            <!-- Contadores por tipo -->
            <div class="row g-2 text-center">
                <?php
                $bloques = [
                    ['bg-success',       'bi-box-arrow-in-right', 'Logins',       $stats['logins']],
                    ['bg-secondary',     'bi-box-arrow-right',    'Logouts',      $stats['logouts']],
                    ['bg-primary',       'bi-plus-circle',        'Creaciones',   $stats['creaciones']],
                    ['bg-warning',       'bi-pencil',             'Ediciones',    $stats['ediciones']],
                    ['bg-danger',        'bi-trash',              'Eliminaciones',$stats['eliminaciones']],
                    ['bg-dark',          'bi-lock',               'Bloqueos',     $stats['bloqueos']],
                ];
                foreach ($bloques as [$bg, $icon, $label, $val]):
                ?>
                <div class="col-6 col-sm-4 col-lg-2">
                    <div class="p-2 rounded-3" style="background:#f4f6fb">
                        <i class="bi <?= $icon ?> <?= $bg === 'bg-warning' ? 'text-warning' : str_replace('bg-', 'text-', $bg) ?> d-block fs-4 mb-1"></i>
                        <div class="fw-bold fs-5"><?= number_format((int)$val) ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?= $label ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- ══ FILTROS ══ -->
    <div class="col-lg-3">
        <div class="form-section h-100">
            <h6 class="fw-bold" style="color:var(--primary)">
                <i class="bi bi-funnel me-1"></i> Filtros
            </h6>
            <form method="GET" id="formFiltro">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Buscar texto</label>
                    <input type="text" name="buscar" class="form-control form-control-sm"
                           placeholder="Acción, tabla, descripción…"
                           value="<?= htmlspecialchars($buscar) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Usuario</label>
                    <select name="usuario" class="form-select form-select-sm">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id_usuario'] ?>"
                                <?= $filtroUsuario === (int)$u['id_usuario'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['usuario'] . ' — ' . $u['nombre_completo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Tipo de acción</label>
                    <select name="accion" class="form-select form-select-sm">
                        <option value="">Todas las acciones</option>
                        <?php foreach ($acciones as $ac): ?>
                        <option value="<?= htmlspecialchars($ac) ?>"
                                <?= $filtroAccion === $ac ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ac) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Desde</label>
                    <input type="date" name="fecha_d" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($filtroFechaD) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Hasta</label>
                    <input type="date" name="fecha_h" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($filtroFechaH) ?>">
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i> Aplicar filtros
                    </button>
                    <a href="?" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x me-1"></i> Limpiar
                    </a>
                </div>
            </form>

            <!-- IPs recientes -->
            <?php if (!empty($ultimasIPs)): ?>
            <hr>
            <h6 class="fw-bold small" style="color:var(--primary)">
                <i class="bi bi-router me-1"></i> IPs recientes
            </h6>
            <?php foreach ($ultimasIPs as $ip): ?>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <code class="small"><?= htmlspecialchars($ip['ip_origen']) ?></code>
                <span class="badge bg-light text-dark" title="Última actividad: <?= date('d/m/Y H:i', strtotime($ip['ultima_vez'])) ?>">
                    <?= $ip['accesos'] ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ TABLA DE REGISTROS ══ -->
    <div class="col-lg-9">
        <div class="table-card">
            <div class="table-card-header">
                <h5>
                    <i class="bi bi-list-ul me-1"></i>
                    <?= number_format($total) ?> registro(s)
                    <?php if ($total > $porPagina): ?>
                    <span class="text-muted fw-normal small">
                        — página <?= $pagina ?> de <?= $paginas ?>
                    </span>
                    <?php endif; ?>
                </h5>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width:130px">Fecha y hora</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Módulo / Tabla</th>
                            <th>Descripción</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-search d-block fs-2 mb-2 opacity-25"></i>
                                No se encontraron registros con los filtros aplicados.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($registros as $r): ?>
                        <tr>
                            <!-- Fecha -->
                            <td class="text-muted" style="font-size:.78rem;white-space:nowrap">
                                <div><?= date('d/m/Y', strtotime($r['fecha'])) ?></div>
                                <div class="fw-semibold"><?= date('H:i:s', strtotime($r['fecha'])) ?></div>
                            </td>

                            <!-- Usuario -->
                            <td>
                                <?php if ($r['usuario']): ?>
                                <div class="fw-semibold small"><?= htmlspecialchars($r['usuario']) ?></div>
                                <div class="text-muted" style="font-size:.72rem">
                                    <?= htmlspecialchars($r['rol_nombre'] ?? '') ?>
                                </div>
                                <?php else: ?>
                                <span class="text-muted small"><i class="bi bi-gear me-1"></i>Sistema</span>
                                <?php endif; ?>
                            </td>

                            <!-- Acción -->
                            <td>
                                <span class="badge <?= badgeAccion($r['accion']) ?>"
                                      style="font-size:.72rem">
                                    <?= htmlspecialchars($r['accion']) ?>
                                </span>
                            </td>

                            <!-- Módulo -->
                            <td class="small">
                                <?php if ($r['tabla_afectada']): ?>
                                <span class="badge bg-light text-dark">
                                    <?= htmlspecialchars($r['tabla_afectada']) ?>
                                </span>
                                <?php if ($r['id_registro']): ?>
                                <span class="text-muted ms-1">#<?= $r['id_registro'] ?></span>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Descripción -->
                            <td class="small text-muted" style="max-width:260px">
                                <?php
                                $desc = $r['descripcion'] ?? '';
                                if (strlen($desc) > 80):
                                ?>
                                <span title="<?= htmlspecialchars($desc) ?>">
                                    <?= htmlspecialchars(mb_strimwidth($desc, 0, 80, '…')) ?>
                                </span>
                                <?php else: ?>
                                <?= htmlspecialchars($desc ?: '—') ?>
                                <?php endif; ?>
                            </td>

                            <!-- IP -->
                            <td>
                                <code style="font-size:.72rem" class="text-muted">
                                    <?= htmlspecialchars($r['ip_origen'] ?? '—') ?>
                                </code>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($paginas > 1): ?>
            <div class="p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="text-muted small">
                    Mostrando <?= ($offset + 1) ?>–<?= min($offset + $porPagina, $total) ?>
                    de <?= number_format($total) ?>
                </span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <!-- Anterior -->
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        <?php
                        // Mostrar máx. 7 páginas centradas en la actual
                        $inicio = max(1, $pagina - 3);
                        $fin    = min($paginas, $pagina + 3);
                        if ($inicio > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">1</a>
                        </li>
                        <?php if ($inicio > 2): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                        <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($fin < $paginas): ?>
                        <?php if ($fin < $paginas - 1): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $paginas])) ?>">
                                <?= $paginas ?>
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- Siguiente -->
                        <li class="page-item <?= $pagina >= $paginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
