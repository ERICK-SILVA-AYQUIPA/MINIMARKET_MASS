<?php
declare(strict_types=1);
/**
 * V: el ticket para la ticketera termica (papel de 58 u 80 mm) o para guardarlo como PDF.
 * Pagina suelta, sin menu: solo lo que va en el papel, y arriba (sin imprimirse) los botones.
 * Mientras la venta no se envie a SUNAT por un OSE/PSE, sale rotulado como TICKET INTERNO.
 * @var Venta $venta
 * @var SerieComprobante $serie
 * @var Tienda $tienda
 * @var ?Caja $caja
 * @var ?Usuario $cajero
 * @var string $cliente
 * @var array<int, array{detalle: DetalleVenta, producto: ?Producto, unidad: string}> $lineas
 * @var PagoVenta[] $pagos
 * @var ?AnulacionVenta $anulacion
 * @var array<int, string> $nombresPromocion
 * @var ?array{origen: string, nombre: string, porcentaje: ?float, monto: float, motivo: ?string} $descuentoVenta
 * @var float $subtotal
 * @var float $descuentoProductos
 * @var Emisor $emisor
 * @var DesgloseIgv $desglose
 * @var bool $ticketInterno
 * @var AnchoTicket $ancho
 */
require_once __DIR__ . '/../parciales/funciones.php';
$numero = $serie->formatear($venta->getNumeroCorrelativo());
$urlTicket = '/?ruta=comprobante_imprimir&serie=' . urlencode($serie->getSerie()) . '&numero=' . $venta->getNumeroCorrelativo();
$urlComprobante = '/?ruta=comprobante&serie=' . urlencode($serie->getSerie()) . '&numero=' . $venta->getNumeroCorrelativo();
$centimos = static fn(int $c): string => number_format($c / 100, 2);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($numero) ?> · <?= e($emisor->getNombreComercial()) ?></title>
    <style>
        /* Papel de <?= (int) $ancho->getPapelMm() ?> mm: la cabeza termica imprime <?= (int) $ancho->getImprimibleMm() ?> mm */
        @page { size: <?= (int) $ancho->getPapelMm() ?>mm auto; margin: 0; }
        body { font-family: "Courier New", ui-monospace, monospace; font-size: <?= (int) $ancho->getLetraPx() ?>px; color: #000; background: #e9ebee; margin: 0; }
        .ticket { width: <?= (int) $ancho->getImprimibleMm() ?>mm; margin: 16px auto; background: #fff; padding: 4mm; box-shadow: 0 1px 4px rgba(0,0,0,.2); overflow-wrap: anywhere; }
        .centro { text-align: center; }
        h1 { font-size: 1.25em; margin: 0; }
        h2 { font-size: 1.1em; margin: 6px 0 2px; }
        p { margin: 2px 0; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td.num { text-align: right; white-space: nowrap; }
        .total td { font-weight: 700; font-size: 1.15em; padding-top: 4px; }
        .anulada, .interno { border: 2px solid #000; text-align: center; font-weight: 700; padding: 4px; margin: 6px 0; }
        .anulada { letter-spacing: 2px; }
        .interno small { display: block; font-weight: 400; }
        .acciones { width: 80mm; max-width: calc(100% - 32px); margin: 16px auto; font-family: system-ui, sans-serif; font-size: 14px; }
        .acciones .fila { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px; }
        .acciones button, .acciones .ancho a { font: inherit; padding: 8px 14px; border-radius: 6px; border: 1px solid #1f6f43; text-decoration: none; }
        .acciones button { background: #1f6f43; color: #fff; font-weight: 600; cursor: pointer; }
        .acciones .ancho a { background: #fff; color: #1f6f43; }
        .acciones .ancho a[aria-current="true"] { background: #e3f1e8; font-weight: 700; }
        .acciones .falta { background: #fff4d6; border: 1px solid #c99a00; padding: 8px; border-radius: 6px; }
        @media print {
            body { background: #fff; }
            .ticket { margin: 0; box-shadow: none; padding: 0 <?= (int) (($ancho->getPapelMm() - $ancho->getImprimibleMm()) / 2) ?>mm; }
            .acciones { display: none; }
        }
    </style>
    <script src="/js/ticket.js" defer></script>
</head>
<body data-ancho="<?= (int) $ancho->getPapelMm() ?>" data-volver="<?= e($urlComprobante) ?>">
<div class="acciones">
    <div class="fila">
        <button type="button" data-imprimir hidden>Imprimir ticket</button>
        <span class="ancho" aria-label="Ancho del papel">
            <?php foreach (AnchoTicket::ANCHOS as $mm): ?>
                <a href="<?= e($urlTicket . '&ancho=' . $mm) ?>" data-ancho-elegir="<?= (int) $mm ?>" aria-current="<?= $mm === $ancho->getPapelMm() ? 'true' : 'false' ?>"><?= (int) $mm ?> mm</a>
            <?php endforeach; ?>
        </span>
    </div>
    <p>Imprime en la ticketera térmica (elígela como impresora, márgenes: ninguno) o guárdalo en PDF. Sin el botón: <strong>Ctrl + P</strong>.</p>
    <?php if (!$emisor->estaConfigurado()): ?>
        <p class="falta">Faltan la razón social y el RUC de la empresa: configura EMPRESA_RAZON_SOCIAL y EMPRESA_RUC en el archivo .env (docs/facturacion_electronica.md).</p>
    <?php endif; ?>
    <p><a href="<?= e($urlComprobante) ?>">← Volver al comprobante</a></p>
</div>
<div class="ticket">
    <div class="centro">
        <h1><?= e(mb_strtoupper($emisor->getNombreComercial())) ?></h1>
        <?php if ($emisor->getRazonSocial() !== ''): ?><p><?= e($emisor->getRazonSocial()) ?></p><?php endif; ?>
        <?php if ($emisor->getRuc() !== ''): ?><p>RUC <?= e($emisor->getRuc()) ?></p><?php endif; ?>
        <?php if ($emisor->getDireccionFiscal() !== ''): ?><p><?= e($emisor->getDireccionFiscal()) ?></p><?php endif; ?>
        <p><?= e($tienda->getNombreTienda()) ?></p>
        <?php if (!$tienda->direccionPendiente()): ?><p><?= e($tienda->getDireccion()) ?></p><?php endif; ?>
        <?php if ($tienda->getTelefono() !== null): ?><p>Tel. <?= e($tienda->getTelefono()) ?></p><?php endif; ?>
        <?php if ($ticketInterno): ?>
            <h2>TICKET DE VENTA INTERNO</h2>
            <p>N.° <strong><?= e($numero) ?></strong></p>
        <?php else: ?>
            <h2><?= e(mb_strtoupper($serie->nombreComprobante())) ?> ELECTRÓNICA</h2>
            <p><strong><?= e($numero) ?></strong></p>
        <?php endif; ?>
    </div>
    <?php if ($ticketInterno): ?>
        <div class="interno">NO VÁLIDO COMO COMPROBANTE DE PAGO<small>Control interno de la tienda: no se envía a SUNAT.</small></div>
    <?php endif; ?>
    <?php if ($anulacion !== null): ?><div class="anulada">ANULADA</div><?php endif; ?>
    <hr>
    <p>Fecha: <?= e(fecha($venta->getFechaHora())) ?></p>
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
            <?php if ($d->getDescuento() > 0): ?>
                <tr><td>&nbsp; <?= e($nombresPromocion[$d->getIdPromocion()] ?? 'Promoción') ?></td><td class="num">-<?= e(number_format($d->getDescuento(), 2)) ?></td></tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($descuentoProductos > 0 || $descuentoVenta !== null): ?>
            <tr><td colspan="2"><hr></td></tr>
            <tr><td>SUBTOTAL</td><td class="num"><?= e(number_format($subtotal, 2)) ?></td></tr>
            <?php if ($descuentoProductos > 0): ?><tr><td>PROMOCIONES</td><td class="num">-<?= e(number_format($descuentoProductos, 2)) ?></td></tr><?php endif; ?>
            <?php if ($descuentoVenta !== null): ?><tr><td><?= e(mb_strtoupper($descuentoVenta['nombre'])) ?></td><td class="num">-<?= e(number_format($descuentoVenta['monto'], 2)) ?></td></tr><?php endif; ?>
        <?php endif; ?>
    </table>
    <hr>
    <table>
        <tr><td>OP. GRAVADA</td><td class="num"><?= e($centimos($desglose->getGravada())) ?></td></tr>
        <tr><td>IGV <?= (int) DesgloseIgv::TASA_PORCENTAJE ?>%</td><td class="num"><?= e($centimos($desglose->getIgv())) ?></td></tr>
        <tr class="total"><td>TOTAL</td><td class="num">S/ <?= e($centimos($desglose->getTotal())) ?></td></tr>
        <?php if ($descuentoProductos > 0 || $descuentoVenta !== null): ?>
            <tr><td colspan="2" class="centro">Usted ahorró S/ <?= e(number_format($descuentoProductos + ($descuentoVenta['monto'] ?? 0), 2)) ?></td></tr>
        <?php endif; ?>
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
        <p>Anulada el <?= e(fecha($anulacion->getFechaHora())) ?>: <?= e($anulacion->getMotivo()) ?></p>
    <?php else: ?>
        <p class="centro">¡Gracias por su compra!</p>
    <?php endif; ?>
</div>
</body>
</html>
