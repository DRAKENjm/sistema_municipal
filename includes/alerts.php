<?php
/**
 * Muestra alertas de sesión (éxito / error / info)
 */
if (!empty($_SESSION['success'])):
?>
<div class="alert alert-success alert-dismissible fade show alert-auto" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success']); endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show alert-auto" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['error']); endif; ?>

<?php if (!empty($_SESSION['info'])): ?>
<div class="alert alert-info alert-dismissible fade show alert-auto" role="alert">
    <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['info']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['info']); endif; ?>
