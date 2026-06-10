<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

$pageTitle = 'Mi perfil';
$post = $_SESSION['postulante'] ?? null;
if (!$post) { header('Location: ' . BASE_URL . '/portal/login.php'); exit; }

$db = getDB();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'datos') {
        $ap_pat    = trim($_POST['apellido_paterno']  ?? '');
        $ap_mat    = trim($_POST['apellido_materno']  ?? '');
        $nombres   = trim($_POST['nombres']           ?? '');
        $correo    = trim($_POST['correo']            ?? '');
        $telefono  = trim($_POST['telefono']          ?? '');
        $direccion = trim($_POST['direccion']         ?? '');
        $fec_nac   = $_POST['fecha_nacimiento']       ?? '';

        if ($ap_pat  === '') $errores[] = 'Apellido paterno obligatorio.';
        if ($nombres === '') $errores[] = 'Nombres obligatorios.';
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'Correo inválido.';

        if (empty($errores)) {
            $check = $db->prepare("SELECT id_postulante FROM postulantes WHERE correo=? AND id_postulante<>?");
            $check->execute([$correo, $post['id_postulante']]);
            if ($check->fetch()) $errores[] = 'Ese correo ya está en uso por otra cuenta.';
        }

        if (empty($errores)) {
            $db->prepare("
                UPDATE postulantes SET apellido_paterno=?,apellido_materno=?,nombres=?,
                correo=?,telefono=?,direccion=?,fecha_nacimiento=?
                WHERE id_postulante=?
            ")->execute([$ap_pat, $ap_mat, $nombres, $correo, $telefono ?: null, $direccion ?: null, $fec_nac ?: null, $post['id_postulante']]);

            // Refrescar sesión
            $stmt = $db->prepare("SELECT * FROM postulantes WHERE id_postulante=?");
            $stmt->execute([$post['id_postulante']]);
            $_SESSION['postulante'] = $stmt->fetch();
            $post = $_SESSION['postulante'];
            $_SESSION['success'] = 'Datos actualizados correctamente.';
            header('Location: perfil.php');
            exit;
        }

    } elseif ($accion === 'password') {
        $actual   = $_POST['password_actual']  ?? '';
        $nueva    = $_POST['password_nueva']   ?? '';
        $nueva2   = $_POST['password_nueva2']  ?? '';

        if (!password_verify($actual, $post['password_hash'])) $errores[] = 'La contraseña actual es incorrecta.';
        if (strlen($nueva) < 8)                                $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        if ($nueva !== $nueva2)                                $errores[] = 'Las nuevas contraseñas no coinciden.';

        if (empty($errores)) {
            $hash = password_hash($nueva, PASSWORD_BCRYPT);
            $db->prepare("UPDATE postulantes SET password_hash=? WHERE id_postulante=?")->execute([$hash, $post['id_postulante']]);
            // Refrescar sesión
            $stmt = $db->prepare("SELECT * FROM postulantes WHERE id_postulante=?");
            $stmt->execute([$post['id_postulante']]);
            $_SESSION['postulante'] = $stmt->fetch();
            $_SESSION['success'] = 'Contraseña actualizada correctamente.';
            header('Location: perfil.php');
            exit;
        }
    }
}

include __DIR__ . '/includes/portal_head.php';
?>

<div class="portal-card mb-4">
    <div class="portal-card-header">
        <h4 class="portal-card-title">
            <i class="bi bi-person-circle"></i>
            Mi perfil
        </h4>
        <p class="text-muted mb-0">Administra tu información personal y configuración de cuenta</p>
    </div>
</div>

<?php if (!empty($errores)): ?>
<div class="alert-portal alert-portal-danger">
    <i class="bi bi-exclamation-triangle-fill alert-icon"></i>
    <div>
        <strong>Errores encontrados:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errores as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Datos personales -->
    <div class="col-md-7">
        <div class="portal-card">
            <h6 class="fw-bold mb-4" style="color:var(--portal-primary)">
                <i class="bi bi-person me-2"></i>Datos personales
            </h6>
            <form method="POST">
                <input type="hidden" name="accion" value="datos">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold text-muted">DNI</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($post['dni']) ?>" disabled>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold text-muted">Usuario</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($post['usuario']) ?>" disabled>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Apellido paterno <span class="text-danger">*</span></label>
                        <input type="text" name="apellido_paterno" class="form-control" required value="<?= htmlspecialchars($_POST['apellido_paterno'] ?? $post['apellido_paterno']) ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Apellido materno</label>
                        <input type="text" name="apellido_materno" class="form-control" value="<?= htmlspecialchars($_POST['apellido_materno'] ?? $post['apellido_materno']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Nombres <span class="text-danger">*</span></label>
                        <input type="text" name="nombres" class="form-control" required value="<?= htmlspecialchars($_POST['nombres'] ?? $post['nombres']) ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Correo electrónico <span class="text-danger">*</span></label>
                        <input type="email" name="correo" class="form-control" required value="<?= htmlspecialchars($_POST['correo'] ?? $post['correo']) ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($_POST['telefono'] ?? $post['telefono'] ?? '') ?>" placeholder="+51 999 999 999">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Fecha de nacimiento</label>
                        <input type="date" name="fecha_nacimiento" class="form-control" value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? $post['fecha_nacimiento'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Dirección</label>
                        <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($_POST['direccion'] ?? $post['direccion'] ?? '') ?>" placeholder="Dirección completa">
                    </div>
                </div>
                <button type="submit" class="btn btn-portal-primary mt-4">
                    <i class="bi bi-save me-2"></i>Guardar cambios
                </button>
            </form>
        </div>
    </div>

    <!-- Sidebar: Seguridad e Info -->
    <div class="col-md-5">
        <!-- Cambio de contraseña -->
        <div class="portal-card mb-4">
            <h6 class="fw-bold mb-4" style="color:var(--portal-primary)">
                <i class="bi bi-key me-2"></i>Cambiar contraseña
            </h6>
            <form method="POST">
                <input type="hidden" name="accion" value="password">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Contraseña actual</label>
                    <input type="password" name="password_actual" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nueva contraseña</label>
                    <input type="password" name="password_nueva" class="form-control" required minlength="8">
                    <div class="form-text">Mínimo 8 caracteres</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Confirmar nueva contraseña</label>
                    <input type="password" name="password_nueva2" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-portal-secondary w-100">
                    <i class="bi bi-shield-lock me-2"></i>Actualizar contraseña
                </button>
            </form>
        </div>

        <!-- Info de cuenta -->
        <div class="portal-card" style="background:linear-gradient(135deg, #f8faff 0%, #eef2fb 100%)">
            <h6 class="fw-bold mb-3" style="color:var(--portal-primary)">
                <i class="bi bi-info-circle me-2"></i>Información de cuenta
            </h6>
            <dl class="row small mb-0">
                <dt class="col-6 text-muted mb-2">Usuario:</dt>
                <dd class="col-6 mb-2 fw-semibold"><?= htmlspecialchars($post['usuario']) ?></dd>
                
                <dt class="col-6 text-muted mb-2">DNI:</dt>
                <dd class="col-6 mb-2 fw-semibold"><?= htmlspecialchars($post['dni']) ?></dd>
                
                <dt class="col-6 text-muted mb-2">Registrado:</dt>
                <dd class="col-6 mb-2"><?= date('d/m/Y', strtotime($post['fecha_registro'])) ?></dd>
                
                <dt class="col-6 text-muted mb-0">Estado:</dt>
                <dd class="col-6 mb-0">
                    <?php if ($post['estado']): ?>
                    <span class="badge-portal success">
                        <i class="bi bi-check-circle"></i>Activo
                    </span>
                    <?php else: ?>
                    <span class="badge-portal danger">
                        <i class="bi bi-x-circle"></i>Bloqueado
                    </span>
                    <?php endif; ?>
                </dd>
            </dl>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/portal_foot.php'; ?>
