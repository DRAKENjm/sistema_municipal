<?php
/**
 * Página pública de registro para postulantes.
 * No requiere sesión. El postulante crea su cuenta y puede acceder al portal.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

// Si ya tiene sesión como postulante, redirigir a su portal
if (!empty($_SESSION['postulante'])) {
    header('Location: ' . BASE_URL . '/portal/index.php');
    exit;
}

$errores = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();

    $dni        = trim($_POST['dni']               ?? '');
    $ap_pat     = trim($_POST['apellido_paterno']  ?? '');
    $ap_mat     = trim($_POST['apellido_materno']  ?? '');
    $nombres    = trim($_POST['nombres']           ?? '');
    $correo     = trim($_POST['correo']            ?? '');
    $telefono   = trim($_POST['telefono']          ?? '');
    $direccion  = trim($_POST['direccion']         ?? '');
    $fec_nac    = $_POST['fecha_nacimiento']       ?? '';
    $usuario    = trim($_POST['usuario']           ?? '');
    $password   = $_POST['password']              ?? '';
    $password2  = $_POST['password2']             ?? '';

    // Validaciones
    if (!preg_match('/^\d{8}$/', $dni))         $errores[] = 'El DNI debe tener exactamente 8 dígitos.';
    if ($ap_pat  === '')                          $errores[] = 'El apellido paterno es obligatorio.';
    if ($nombres === '')                          $errores[] = 'Los nombres son obligatorios.';
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingrese un correo electrónico válido.';
    if (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $usuario)) $errores[] = 'El usuario debe tener entre 4 y 30 caracteres (letras, números y guion bajo).';
    if (strlen($password) < 8)                  $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    if ($password !== $password2)               $errores[] = 'Las contraseñas no coinciden.';

    if (empty($errores)) {
        // Unicidad
        $check = $db->prepare("SELECT id_postulante FROM postulantes WHERE dni=? OR usuario=? OR correo=?");
        $check->execute([$dni, $usuario, $correo]);
        if ($check->fetch()) {
            $errores[] = 'Ya existe una cuenta con ese DNI, usuario o correo. Intente iniciar sesión.';
        }
    }

    if (empty($errores)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare("
            INSERT INTO postulantes
                (dni, apellido_paterno, apellido_materno, nombres, correo,
                 telefono, direccion, fecha_nacimiento, usuario, password_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $dni, $ap_pat, $ap_mat, $nombres, $correo,
            $telefono ?: null, $direccion ?: null, $fec_nac ?: null,
            $usuario, $hash
        ]);
        $ok = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Postulante — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body style="background:#eef2f7">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <!-- Encabezado -->
            <div class="text-center mb-4">
                <i class="bi bi-building" style="font-size:2.5rem;color:var(--primary)"></i>
                <h3 class="fw-bold mt-2" style="color:var(--primary)"><?= APP_NAME ?></h3>
                <p class="text-muted">Registro de postulante — Portal de convocatorias</p>
            </div>

            <?php if ($ok): ?>
            <!-- Éxito -->
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
                <h4 class="mt-3 fw-bold">¡Cuenta creada exitosamente!</h4>
                <p class="text-muted">Ya puede ingresar al portal con su usuario y contraseña para ver convocatorias activas y postular.</p>
                <a href="<?= BASE_URL ?>/portal/login.php" class="btn btn-primary px-5 mt-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Ir al portal
                </a>
            </div>

            <?php else: ?>

            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errores as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4 p-4">
                <h5 class="fw-bold mb-4" style="color:var(--primary)">
                    <i class="bi bi-person-plus me-2"></i>Crear cuenta de postulante
                </h5>

                <form method="POST" autocomplete="off">
                    <!-- Datos personales -->
                    <div class="border rounded-3 p-3 mb-4">
                        <h6 class="fw-semibold text-muted mb-3"><i class="bi bi-person me-1"></i> Datos personales</h6>
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <label class="form-label">DNI <span class="text-danger">*</span></label>
                                <input type="text" name="dni" class="form-control" required
                                       maxlength="8" pattern="\d{8}" placeholder="12345678"
                                       value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label">Apellido paterno <span class="text-danger">*</span></label>
                                <input type="text" name="apellido_paterno" class="form-control" required
                                       value="<?= htmlspecialchars($_POST['apellido_paterno'] ?? '') ?>">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label">Apellido materno</label>
                                <input type="text" name="apellido_materno" class="form-control"
                                       value="<?= htmlspecialchars($_POST['apellido_materno'] ?? '') ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Nombres <span class="text-danger">*</span></label>
                                <input type="text" name="nombres" class="form-control" required
                                       value="<?= htmlspecialchars($_POST['nombres'] ?? '') ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Fecha de nacimiento</label>
                                <input type="date" name="fecha_nacimiento" class="form-control"
                                       value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                                <input type="email" name="correo" class="form-control" required
                                       placeholder="correo@ejemplo.com"
                                       value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="telefono" class="form-control"
                                       placeholder="999 999 999"
                                       value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Dirección</label>
                                <input type="text" name="direccion" class="form-control"
                                       value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Acceso -->
                    <div class="border rounded-3 p-3 mb-4">
                        <h6 class="fw-semibold text-muted mb-3"><i class="bi bi-key me-1"></i> Datos de acceso al portal</h6>
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <label class="form-label">Usuario <span class="text-danger">*</span></label>
                                <input type="text" name="usuario" class="form-control" required
                                       minlength="4" maxlength="30"
                                       placeholder="min. 4 caracteres"
                                       value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                                <div class="form-text">Solo letras, números y _</div>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" required minlength="8">
                                <div class="form-text">Mínimo 8 caracteres</div>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                                <input type="password" name="password2" class="form-control" required minlength="8">
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary py-2 fw-semibold">
                            <i class="bi bi-person-check me-1"></i> Crear mi cuenta
                        </button>
                        <div class="text-center text-muted small">
                            ¿Ya tiene cuenta?
                            <a href="<?= BASE_URL ?>/portal/login.php">Iniciar sesión</a>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
