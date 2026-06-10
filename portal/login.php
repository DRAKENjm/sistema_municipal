<?php
/**
 * Login del portal de postulantes (acceso externo).
 * Independiente del login de empleados municipales.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

if (!empty($_SESSION['postulante'])) {
    header('Location: ' . BASE_URL . '/portal/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario  = trim($_POST['usuario']  ?? '');
    $password = $_POST['password']     ?? '';

    if ($usuario === '' || $password === '') {
        $error = 'Complete todos los campos.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM postulantes WHERE (usuario = ? OR correo = ?) AND estado = 1 LIMIT 1");
        $stmt->execute([$usuario, $usuario]);
        $post = $stmt->fetch();

        if ($post && password_verify($password, $post['password_hash'])) {
            $_SESSION['postulante'] = $post;
            header('Location: ' . BASE_URL . '/portal/index.php');
            exit;
        } else {
            $error = 'Usuario/correo o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Postulante — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/portal.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .login-container {
            width: 100%;
            max-width: 450px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
            color: #fff;
        }
        .login-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .login-header p {
            opacity: 0.95;
            font-size: 1.1rem;
        }
        .login-card {
            background: #fff;
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-card h5 {
            font-weight: 700;
            color: var(--portal-primary);
            margin-bottom: 0.5rem;
        }
        .login-card .subtitle {
            color: #6c757d;
            margin-bottom: 2rem;
        }
        .form-label {
            font-weight: 600;
            color: var(--portal-primary);
            font-size: 0.9rem;
        }
        .input-group-text {
            background: #f8f9fa;
            border-right: none;
            color: var(--portal-primary);
        }
        .form-control {
            border-left: none;
            padding: 0.75rem;
        }
        .form-control:focus {
            border-color: #ced4da;
            box-shadow: none;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.875rem;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: #6c757d;
            font-size: 0.875rem;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }
        .divider::before {
            margin-right: 1rem;
        }
        .divider::after {
            margin-left: 1rem;
        }
        .link-secondary {
            color: var(--portal-primary);
            font-weight: 600;
            text-decoration: none;
        }
        .link-secondary:hover {
            color: #764ba2;
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="login-container fade-in-up">
    <div class="login-header">
        <div class="mb-3">
            <i class="bi bi-building-fill" style="font-size: 4rem; color: var(--portal-secondary); filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));"></i>
        </div>
        <h1>Municipalidad Provincial de Yauli</h1>
        <p>Portal de Postulantes</p>
    </div>

    <div class="login-card">
        <h5>Iniciar sesión</h5>
        <p class="subtitle">Ingresa tus credenciales para acceder</p>

        <?php if ($error): ?>
        <div class="alert-portal alert-portal-danger mb-3">
            <i class="bi bi-exclamation-triangle-fill alert-icon"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Usuario o correo</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-person-fill"></i>
                    </span>
                    <input type="text" name="usuario" class="form-control" required
                           placeholder="Ingresa tu usuario o correo"
                           value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock-fill"></i>
                    </span>
                    <input type="password" name="password" id="pwd" class="form-control" required
                           placeholder="Ingresa tu contraseña">
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()" tabindex="-1">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-login w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar al portal
            </button>
        </form>

        <div class="divider">o</div>

        <div class="text-center">
            <p class="text-muted small mb-2">¿No tienes cuenta?</p>
            <a href="<?= BASE_URL ?>/registro.php" class="link-secondary">
                <i class="bi bi-person-plus me-1"></i>Regístrate aquí
            </a>
        </div>

        <div class="divider"></div>

        <div class="text-center">
            <a href="<?= BASE_URL ?>/login.php" class="text-muted text-decoration-none small">
                <i class="bi bi-building me-1"></i>
                ¿Eres personal municipal? Accede aquí
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
    const pwd  = document.getElementById('pwd');
    const icon = document.getElementById('eyeIcon');
    pwd.type   = pwd.type === 'password' ? 'text' : 'password';
    icon.className = pwd.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
</body>
</html>
