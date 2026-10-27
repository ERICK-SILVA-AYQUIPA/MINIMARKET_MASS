<?php
declare(strict_types=1);
/** @var string $mensaje */
$titulo = 'Error';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Algo salió mal</h1>
<p><?= e($mensaje) ?></p>
<p>El detalle quedó en la terminal donde corre <code>php -S</code>.</p>
<p><a href="/?ruta=inicio">Volver al inicio</a></p>
<?php require __DIR__ . '/../layout_fin.php'; ?>
