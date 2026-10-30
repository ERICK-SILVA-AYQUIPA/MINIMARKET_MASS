<?php
declare(strict_types=1);
/** @var string $mensaje */
$titulo = 'No encontrado';
$anchoMain = 'angosto';
require __DIR__ . '/../layout_inicio.php';
?>
<div class="vacio">
    <?= icono('buscar') ?>
    <strong>404 · No encontrado</strong>
    <p><?= e($mensaje) ?></p>
    <a class="boton secundario" href="/?ruta=inicio">Volver al inicio</a>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
