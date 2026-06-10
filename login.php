<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

// Si ya está logueado redirigir al dashboard
if (!empty($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario  = trim($_POST['usuario']  ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($usuario === '' || $password === '') {
        $error = 'Complete todos los campos.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT u.*, r.nombre AS rol_nombre, a.nombre AS area_nombre
            FROM usuarios u
            JOIN roles r ON r.id_rol = u.id_rol
            LEFT JOIN areas a ON a.id_area = u.id_area
            WHERE u.usuario = ? AND u.estado = 1
            LIMIT 1
        ");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['usuario'] = $user;
            registrarBitacora('LOGIN', 'usuarios', $user['id_usuario'], 'Inicio de sesión exitoso');
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-building logo-icon"></i>
            <h4><?= APP_NAME ?></h4>
            <p>Sistema de Gestión Documental y ML</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2">
            <i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label fw-semibold">Usuario</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="usuario" class="form-control"
                           placeholder="Ingrese su usuario" required
                           value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="pwd" class="form-control"
                           placeholder="Ingrese su contraseña" required>
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="togglePwd()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar
            </button>
        </form>

        <hr class="my-3">
        <div class="text-center">
            <a href="<?= BASE_URL ?>/modules/evaluaciones/consulta_resultado.php" 
               class="btn btn-outline-info w-100 mb-2">
                <i class="bi bi-search me-1"></i> Consultar resultado de evaluación
            </a>
        </div>
        <div class="text-center small text-muted">
            ¿Es postulante a una convocatoria?
            <a href="<?= BASE_URL ?>/portal/login.php" class="fw-semibold">Acceda aquí</a>
            · <a href="<?= BASE_URL ?>/registro.php">Regístrese</a>
        </div>
        <p class="text-center text-muted mt-2 mb-0" style="font-size:.75rem">
            Municipalidad Provincial de Yau — Sistema Administrativo <?= date('Y') ?>
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
    const pwd  = document.getElementById('pwd');
    const icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
