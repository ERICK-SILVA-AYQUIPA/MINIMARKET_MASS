<?php
declare(strict_types=1);
/** @var string $mensaje */
$titulo = 'Sin permiso';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>403 · Sin permiso</h1>
<p><?= e($mensaje) ?></p>
<p><a href="/?ruta=productos">Ir al catálogo</a></p>
<?php require __DIR__ . '/../layout_fin.php'; ?>
