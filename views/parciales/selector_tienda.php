<?php
declare(strict_types=1);
/**
 * V: enlaces para elegir la tienda. Lo usan el catalogo y las ventas.
 * @var array<int, Tienda> $tiendas
 * @var Tienda $tienda        la tienda elegida
 * @var string $rutaSelector  'productos' o 'ventas'
 */
?>
<nav class="tiendas">
    Tienda:
    <?php foreach ($tiendas as $id => $t): ?>
        <?php if ($id === $tienda->getIdTienda()): ?>
            <strong><?= e($t->getNombreTienda()) ?></strong>
        <?php else: ?>
            <a href="/?ruta=<?= e($rutaSelector) ?>&amp;tienda=<?= $id ?>"><?= e($t->getNombreTienda()) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
