<?php
declare(strict_types=1);

// Paso 3: prueba del Repository, ahora leyendo de MySQL
require __DIR__ . '/../../backend/config/Database.php';
require __DIR__ . '/../../backend/models/ProductoRepository.php';
require __DIR__ . '/../../backend/models/TiendaRepository.php';

$db = Database::conexion();
$repo = new ProductoRepository($db);
$tiendas = new TiendaRepository($db);
$encontrado = $repo->buscarPorCodigo('7750000000011');
$noExiste = $repo->buscarPorCodigo('0000000000000');

$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>POO · Repository</title></head>
<body>
<h1>ProductoRepository</h1>
<p>Productos en la lista: <?= count($repo->todos()) ?></p>
<p>buscarPorCodigo('7750000000011'): <?= $encontrado === null ? 'null' : $e($encontrado->getNombreProducto()) ?></p>
<p>buscarPorCodigo('0000000000000'): <?= $noExiste === null ? 'null (no existe)' : $e($noExiste->getNombreProducto()) ?></p>
<h2>Productos que hay que reponer</h2>
<ul>
<?php foreach ($tiendas->todas() as $idTienda => $tienda): ?>
    <?php foreach ($repo->todos() as $p): ?>
        <?php $s = $repo->stockEnTienda($p->getIdProducto(), $idTienda); ?>
        <?php if ($s !== null && $s->necesitaReponer()): ?>
            <li><?= $e($tienda->getNombreTienda()) ?>: <?= $e($p->getNombreProducto()) ?> (<?= number_format($s->getCantidadActual(), 3) ?> de mínimo <?= number_format($s->getStockMinimo(), 3) ?>)</li>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endforeach; ?>
</ul>
</body>
</html>
