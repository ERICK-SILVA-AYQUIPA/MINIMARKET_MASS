<?php
declare(strict_types=1);
/**
 * V: pestañas para elegir la tienda. Lo usan el catalogo, las ventas y el inventario.
 * @var array<int, Tienda> $tiendas
 * @var Tienda $tienda        la tienda elegida
 * @var string $rutaSelector  la ruta de la pantalla ('productos', 'ventas', ...)
 */
?>
<nav class="tiendas" aria-label="Elegir tienda">
    <span class="rotulo">Tienda</span>
    <?php foreach ($tiendas as $id => $t): ?>
        <?php if ($id === $tienda->getIdTienda()): ?>
            <strong aria-current="true"><?= e($t->getNombreTienda()) ?></strong>
        <?php else: ?>
            <a href="/?ruta=<?= e($rutaSelector) ?>&amp;tienda=<?= $id ?>"><?= e($t->getNombreTienda()) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
