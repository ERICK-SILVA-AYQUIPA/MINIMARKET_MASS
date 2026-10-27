<?php
declare(strict_types=1);
/**
 * V: el comprobante en formato ticket de 80 mm, para imprimir o guardar como PDF desde el navegador (Ctrl + P).
 * Pagina suelta, sin menu: solo lo que va en el papel.
 * @var Venta $venta
 * @var SerieComprobante $serie
 * @var Tienda $tienda
 * @var ?Caja $caja
 * @var ?Usuario $cajero
 * @var string $cliente
 * @var array<int, array{detalle: DetalleVenta, producto: ?Producto, unidad: string}> $lineas
 * @var PagoVenta[] $pagos
 * @var ?AnulacionVenta $anulacion
 */
require_once __DIR__ . '/../parciales/funciones.php';
$numero = $serie->formatear($venta->getNumeroCorrelativo());
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($numero) ?> · Minimarket Mass</title>
    <style>
        @page { size: 80mm auto; margin: 4mm; }
        body { font-family: "Courier New", ui-monospace, monospace; font-size: 12px; color: #000; background: #e9ebee; margin: 0; }
        .ticket { width: 72mm; margin: 16px auto; background: #fff; padding: 4mm; box-shadow: 0 1px 4px rgba(0,0,0,.2); }
        .centro { text-align: center; }
        h1 { font-size: 15px; margin: 0; }
        h2 { font-size: 13px; margin: 6px 0 2px; }
        p { margin: 2px 0; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td.num { text-align: right; white-space: nowrap; }
        .total td { font-weight: 700; font-size: 14px; padding-top: 4px; }
        .anulada { border: 2px solid #000; text-align: center; font-weight: 700; padding: 4px; margin: 6px 0; letter-spacing: 2px; }
        .acciones { width: 72mm; margin: 0 auto 16px; font-family: system-ui, sans-serif; font-size: 13px; }
        @media print {
            body { background: #fff; }
            .ticket { margin: 0; box-shadow: none; width: auto; padding: 0; }
            .acciones { display: none; }
        }
    </style>
</head>
<body>
<div class="ticket">
    <div class="centro">
        <h1>TIENDA MASS</h1>
        <p><?= e($tienda->getNombreTienda()) ?></p>
        <p><?= e($tienda->getDireccion()) ?></p>
        <?php if ($tienda->getTelefono() !== null): ?><p>Tel. <?= e($tienda->getTelefono()) ?></p><?php endif; ?>
        <h2><?= e(mb_strtoupper($serie->nombreComprobante())) ?></h2>
        <p><strong><?= e($numero) ?></strong></p>
    </div>
    <?php if ($anulacion !== null): ?><div class="anulada">ANULADA</div><?php endif; ?>
    <hr>
    <p>Fecha: <?= e($venta->getFechaHora()) ?></p>
    <p>Caja <?= $caja?->getNumeroCaja() ?? '-' ?> · Cajero: <?= e($cajero?->nombreCompleto() ?? '-') ?></p>
    <p>Cliente: <?= e($cliente) ?></p>
    <hr>
    <table>
        <?php foreach ($lineas as $linea): ?>
            <?php $d = $linea['detalle']; $p = $linea['producto']; ?>
            <tr><td colspan="2"><?= e($p?->getNombreProducto() ?? '(producto)') ?></td></tr>
            <tr>
                <td><?= e(rtrim(rtrim(number_format($d->getCantidad(), 3, '.', ''), '0'), '.')) ?> <?= e($linea['unidad']) ?> x <?= e(number_format($d->getPrecioUnitario(), 2)) ?></td>
                <td class="num"><?= e(number_format($d->getImporte(), 2)) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="total"><td>TOTAL</td><td class="num"><?= e(soles($venta->getTotal())) ?></td></tr>
    </table>
    <hr>
    <table>
        <?php foreach ($pagos as $pago): ?>
            <tr><td><?= e($pago->getNombreMetodo()) ?><?= $pago->esValido() ? '' : ' (anulado)' ?></td><td class="num"><?= e(number_format($pago->getMontoPagado(), 2)) ?></td></tr>
            <tr><td colspan="2"><small><?= e($pago->detalle()) ?></small></td></tr>
        <?php endforeach; ?>
    </table>
    <hr>
    <?php if ($anulacion !== null): ?>
        <p>Anulada el <?= e($anulacion->getFechaHora()) ?>: <?= e($anulacion->getMotivo()) ?></p>
    <?php else: ?>
        <p class="centro">¡Gracias por su compra!</p>
    <?php endif; ?>
</div>
<div class="acciones">
    <p>Para imprimir o guardar en PDF: <strong>Ctrl + P</strong> (en el celular, Compartir → Imprimir) y elige «Guardar como PDF».</p>
    <p><a href="/?ruta=comprobante&amp;serie=<?= e($serie->getSerie()) ?>&amp;numero=<?= $venta->getNumeroCorrelativo() ?>">← Volver al comprobante</a></p>
</div>
</body>
</html>
