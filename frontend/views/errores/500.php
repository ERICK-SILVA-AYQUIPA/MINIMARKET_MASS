<?php
declare(strict_types=1);
/** @var string $mensaje */
$titulo = 'Error';
$anchoMain = 'angosto';
require __DIR__ . '/../layout_inicio.php';
?>
<div class="vacio">
    <?= icono('alerta') ?>
    <strong>Algo salió mal</strong>
    <p><?= e($mensaje) ?></p>
    <p class="sutil">El detalle técnico quedó en el registro de errores de PHP (con <code>php -S</code>, en la terminal).</p>
    <a class="boton secundario" href="/?ruta=inicio">Volver al inicio</a>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
