<?php
declare(strict_types=1);
/**
 * V: la foto del producto o, si no tiene, la ilustracion de su categoria (icono sobre su color).
 * Es decorativa (alt=""): el nombre del producto siempre va escrito al lado.
 * @var array{url: ?string, icono: string, tono: int, categoria: string} $foto  ver FotoProducto::datos()
 * @var string $fotoClase  tamano: '' (tarjeta), 'chica' (lista de venta) o 'grande' (ficha)
 */
$fotoClase ??= '';
?>
<span class="foto <?= e($fotoClase) ?> tono-<?= (int) $foto['tono'] ?>">
    <?php if ($foto['url'] !== null): ?>
        <img src="<?= e($foto['url']) ?>" alt="" loading="lazy" decoding="async">
    <?php else: ?>
        <svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#<?= e($foto['icono']) ?>"></use></svg>
    <?php endif; ?>
</span>
<?php unset($fotoClase); ?>
