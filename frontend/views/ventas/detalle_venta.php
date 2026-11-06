<?php
declare(strict_types=1);
/**
 * V: cuerpo de un comprobante (bloques de sp_boleta: cabecera, detalle y pagos).
 * La usan la pagina del comprobante y el panel lateral de la lista de ventas.
 * @var Venta $venta
 * @var SerieComprobante $serie
 * @var Tienda $tienda
 * @var ?Caja $caja
 * @var ?Usuario $cajero
 * @var string $cliente
 * @var array<int, array{detalle: DetalleVenta, producto: ?Producto, unidad: string}> $lineas
 * @var PagoVenta[] $pagos
 * @var array<int, string> $nombresPromocion  id_promocion => nombre
 * @var ?array{origen: string, nombre: string, porcentaje: ?float, monto: float, motivo: ?string} $descuentoVenta
 * @var float $subtotal  suma de importes (cantidad x precio de lista)
 * @var float $descuentoProductos  suma de los descuentos de las promociones de producto
 * @var DesgloseIgv $desglose
 * @var bool $ticketInterno
 */
$hayDescuentos = $descuentoVenta !== null || array_filter($lineas, fn(array $l): bool => $l['detalle']->getDescuento() > 0) !== [];
?>
<dl class="datos">
    <dt>Fecha</dt><dd><?= e(fecha($venta->getFechaHora())) ?></dd>
    <dt>Tienda</dt><dd><?= e($tienda->getNombreTienda()) ?> · <?= e($tienda->getDireccion()) ?></dd>
    <dt>Caja y cajero</dt><dd>Caja <?= $caja?->getNumeroCaja() ?? '-' ?> · <?= e($cajero?->nombreCompleto() ?? '-') ?></dd>
    <dt>Cliente</dt><dd><?= e($cliente) ?></dd>
    <dt>Estado</dt><dd><span class="etiqueta <?= $venta->estaAnulada() ? 'anulada' : 'ok' ?>"><?= e($venta->getEstado()) ?></span></dd>
</dl>

<h3 class="bloque">Productos</h3>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">#</th><th scope="col">Producto</th><th scope="col">Unidad</th><th scope="col" class="num">Cantidad</th><th scope="col" class="num">P. unit.</th><th scope="col" class="num">Importe</th></tr>
    </thead>
    <tbody>
    <?php foreach ($lineas as $i => $linea): ?>
        <?php $d = $linea['detalle']; $p = $linea['producto']; ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= e($p?->getNombreProducto() ?? '(producto no encontrado)') ?></td>
            <td><?= e($linea['unidad']) ?></td>
            <td class="num"><?= e(number_format($d->getCantidad(), 3)) ?></td>
            <td class="num"><?= e(soles($d->getPrecioUnitario())) ?></td>
            <td class="num"><?= e(soles($d->getImporte())) ?></td>
        </tr>
        <?php if ($d->getDescuento() > 0): ?>
        <tr class="fila-descuento">
            <td></td>
            <td colspan="4"><?= icono('oferta') ?><?= e($nombresPromocion[$d->getIdPromocion()] ?? 'Promoción') ?></td>
            <td class="num">−<?= e(soles($d->getDescuento())) ?></td>
        </tr>
        <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <?php if ($hayDescuentos): ?>
            <tr><th scope="row" colspan="5">Subtotal</th><td class="num"><?= e(soles($subtotal)) ?></td></tr>
            <?php if ($descuentoProductos > 0): ?>
                <tr class="fila-descuento"><th scope="row" colspan="5">Promociones de productos</th><td class="num">−<?= e(soles($descuentoProductos)) ?></td></tr>
            <?php endif; ?>
            <?php if ($descuentoVenta !== null): ?>
                <tr class="fila-descuento"><th scope="row" colspan="5"><?= e($descuentoVenta['nombre'] . ($descuentoVenta['porcentaje'] !== null ? ' (' . rtrim(rtrim(number_format($descuentoVenta['porcentaje'], 2, '.', ''), '0'), '.') . ' %)' : '') . ($descuentoVenta['motivo'] !== null ? ': ' . $descuentoVenta['motivo'] : '')) ?></th><td class="num">−<?= e(soles($descuentoVenta['monto'])) ?></td></tr>
            <?php endif; ?>
        <?php endif; ?>
        <tr><th scope="row" colspan="5">Op. gravada</th><td class="num"><?= e(soles($desglose->getGravada() / 100)) ?></td></tr>
        <tr><th scope="row" colspan="5">IGV <?= (int) DesgloseIgv::TASA_PORCENTAJE ?>%</th><td class="num"><?= e(soles($desglose->getIgv() / 100)) ?></td></tr>
        <tr><th scope="row" colspan="5">TOTAL</th><td class="num"><?= e(soles($venta->getTotal())) ?></td></tr>
    </tfoot>
</table>
</div>
<?php if ($ticketInterno): ?>
<p class="sutil"><small>Ticket interno: esta venta no se envía a SUNAT y su ticket no es válido como comprobante de pago. El precio incluye IGV.</small></p>
<?php endif; ?>

<h3>Pagos</h3>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">Método</th><th scope="col" class="num">Monto</th><th scope="col">Estado</th><th scope="col">Detalle</th></tr>
    </thead>
    <tbody>
    <?php foreach ($pagos as $pago): ?>
        <tr>
            <td><?= e($pago->getNombreMetodo()) ?></td>
            <td class="num"><?= e(soles($pago->getMontoPagado())) ?></td>
            <td><span class="etiqueta <?= $pago->getEstado() === 'ANULADO' ? 'anulada' : 'ok' ?>"><?= e($pago->getEstado()) ?></span></td>
            <td><?= e($pago->detalle()) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
