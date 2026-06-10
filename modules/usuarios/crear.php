<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
requireRol(ROLES_USUARIOS);

$db = getDB();
$pageTitle = 'Nuevo Usuario';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_rol   = (int)($_POST['id_rol']           ?? 0);
    $id_area  = (int)($_POST['id_area']          ?? 0) ?: null;
    $dni      = trim($_POST['dni']               ?? '');
    $ap_pat   = trim($_POST['apellido_paterno']  ?? '');
    $ap_mat   = trim($_POST['apellido_materno']  ?? '');
    $nombres  = trim($_POST['nombres']           ?? '');
    $correo   = trim($_POST['correo']            ?? '');
    $telefono = trim($_POST['telefono']          ?? '');
    $usuario  = trim($_POST['usuario']           ?? '');
    $password = $_POST['password']              ?? '';
    $password2= $_POST['password2']             ?? '';

    if ($id_rol === 0)             $errores[] = 'Seleccione un rol.';
    if (strlen($dni) !== 8 || !ctype_digit($dni)) $errores[] = 'DNI inválido.';
    if ($ap_pat   === '')          $errores[] = 'Apellido paterno obligatorio.';
    if ($nombres  === '')          $errores[] = 'Nombres obligatorios.';
    if ($usuario  === '')          $errores[] = 'Nombre de usuario obligatorio.';
    if (strlen($password) < 8)    $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    if ($password !== $password2)  $errores[] = 'Las contraseñas no coinciden.';

    if (empty($errores)) {
        $check = $db->prepare("SELECT id_usuario FROM usuarios WHERE dni=? OR usuario=?");
        $check->execute([$dni, $usuario]);
        if ($check->fetch()) $errores[] = 'Ya existe un usuario con ese DNI o nombre de usuario.';
    }

    if (empty($errores)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare("
            INSERT INTO usuarios (id_rol, id_area, dni, apellido_paterno, apellido_materno, nombres, correo, telefono, usuario, password_hash)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ")->execute([$id_rol, $id_area, $dni, $ap_pat, $ap_mat, $nombres, $correo ?: null, $telefono ?: null, $usuario, $hash]);
        $newId = $db->lastInsertId();
        registrarBitacora('CREAR', 'usuarios', $newId, "Usuario creado: $usuario");
        $_SESSION['success'] = 'Usuario creado correctamente.';
        header('Location: index.php');
        exit;
    }
}

$roles = $db->query("SELECT * FROM roles ORDER BY id_rol")->fetchAll();
$areas = $db->query("SELECT * FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll();

include __DIR__ . '/../../includes/layout_head.php';
?>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h4 class="page-title mb-0">Nuevo Usuario</h4>
</div>

<form method="POST">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="form-section">
                <h6>Datos personales</h6>
                <div class="row g-3">
                    <div class="col-sm-4">
                        <label class="form-label">DNI <span class="text-danger">*</span></label>
                        <input type="text" name="dni" class="form-control" required maxlength="8" pattern="\d{8}" value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Apellido paterno <span class="text-danger">*</span></label>
                        <input type="text" name="apellido_paterno" class="form-control" required value="<?= htmlspecialchars($_POST['apellido_paterno'] ?? '') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Apellido materno</label>
                        <input type="text" name="apellido_materno" class="form-control" value="<?= htmlspecialchars($_POST['apellido_materno'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Nombres <span class="text-danger">*</span></label>
                        <input type="text" name="nombres" class="form-control" required value="<?= htmlspecialchars($_POST['nombres'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h6>Acceso al sistema</h6>
                <div class="row g-3">
                    <div class="col-sm-4">
                        <label class="form-label">Rol <span class="text-danger">*</span></label>
                        <select name="id_rol" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id_rol'] ?>" <?= ($_POST['id_rol'] ?? '') == $r['id_rol'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($r['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Área</label>
                        <select name="id_area" class="form-select">
                            <option value="">— Sin área —</option>
                            <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id_area'] ?>" <?= ($_POST['id_area'] ?? '') == $a['id_area'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($a['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Usuario <span class="text-danger">*</span></label>
                        <input type="text" name="usuario" class="form-control" required value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                        <div class="form-text">Mínimo 8 caracteres</div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                        <input type="password" name="password2" class="form-control" required minlength="8">
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="d-grid gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Crear usuario</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/layout_foot.php'; ?>
