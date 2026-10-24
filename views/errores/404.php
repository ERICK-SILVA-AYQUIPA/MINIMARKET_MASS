<?php
declare(strict_types=1);
/** @var string $mensaje */
$titulo = 'No encontrado';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>404 · No encontrado</h1>
<p><?= e($mensaje) ?></p>
<p><a href="/?ruta=productos">Ir al catálogo</a></p>
<?php require __DIR__ . '/../layout_fin.php'; ?>
