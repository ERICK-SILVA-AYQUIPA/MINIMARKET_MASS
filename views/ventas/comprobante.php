<?php
declare(strict_types=1);
/**
 * V: boleta o factura completa, en los tres bloques de sp_boleta: cabecera, detalle y pagos.
 * @var Venta $venta
 * @var SerieComprobante $serie
 * @var Tienda $tienda
 * @var ?Caja $caja
 * @var ?Usuario $cajero
 * @var string $cliente
 * @var array<int, array{detalle: DetalleVenta, producto: ?Producto, unidad: string}> $lineas
 * @var PagoVenta[] $pagos
 * @var ?AnulacionVenta $anulacion
 * @var ?Usuario $anuladaPor
 * @var Usuario[] $puedenAnular
 * @var bool $detalleCuadra
 * @var bool $pagosCuadran
 */
$numero = $serie->formatear($venta->getNumeroCorrelativo());
$titulo = $numero;
require __DIR__ . '/../layout_inicio.php';
?>
<p><a href="/?ruta=ventas&amp;tienda=<?= $tienda->getIdTienda() ?>">← Ventas de <?= e($tienda->getNombreTienda()) ?></a></p>

<div class="comprobante">
    <h2><?= e($serie->nombreComprobante()) ?> <?= e($numero) ?>
        <span class="etiqueta <?= $venta->estaAnulada() ? 'anulada' : 'ok' ?>"><?= e($venta->getEstado()) ?></span></h2>
    <p><?= e($tienda->getNombreTienda()) ?> · <?= e($tienda->getDireccion()) ?></p>
    <p>Fecha: <?= e($venta->getFechaHora()) ?> · Caja <?= $caja?->getNumeroCaja() ?? '-' ?> · Cajero: <?= e($cajero?->nombreCompleto() ?? '-') ?></p>
    <p>Cliente: <?= e($cliente) ?></p>

    <table>
        <tr><th>#</th><th>Producto</th><th>Unidad</th><th>Cantidad</th><th>P. unit.</th><th>Importe</th></tr>
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
        <?php endforeach; ?>
        <tr><th colspan="5">TOTAL</th><th class="num"><?= e(soles($venta->getTotal())) ?></th></tr>
    </table>
    <p><small>El diseño no guarda subtotal ni IGV aparte (boleta.sql), por eso solo se muestra el total.</small></p>

    <h3>Pagos</h3>
    <table>
        <tr><th>Método</th><th>Monto</th><th>Estado</th><th>Detalle</th></tr>
        <?php foreach ($pagos as $pago): ?>
            <tr>
                <td><?= e($pago->getNombreMetodo()) ?></td>
                <td class="num"><?= e(soles($pago->getMontoPagado())) ?></td>
                <td><?= e($pago->getEstado()) ?></td>
                <td><?= e($pago->detalle()) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h3>Revisión</h3>
    <ul>
        <li>Importes del detalle suman el total: <?= $detalleCuadra ? 'Sí' : 'No' ?></li>
        <li><?= $venta->estaAnulada() ? 'Todos los pagos están anulados' : 'Pagos válidos suman el total' ?>: <?= $pagosCuadran ? 'Sí' : 'No' ?></li>
    </ul>

    <?php if ($anulacion !== null): ?>
        <h3>Anulación</h3>
        <p><?= e($anulacion->getFechaHora()) ?> · <?= e($anulacion->getMotivo()) ?></p>
        <p>Anulada por: <?= e($anuladaPor?->nombreCompleto() ?? '-') ?> (<?= e($anuladaPor?->getNombreRol() ?? '-') ?>)</p>
    <?php else: ?>
        <h3>¿Quién puede anularla?</h3>
        <p>Solo un ADMINISTRADOR activo de <?= e($tienda->getNombreTienda()) ?>:
            <?= e(implode(', ', array_map(fn(Usuario $u): string => $u->nombreCompleto(), $puedenAnular)) ?: 'nadie') ?></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
