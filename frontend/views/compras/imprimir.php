<?php
declare(strict_types=1);
/**
 * V: la orden de compra en una hoja A4, para imprimirla o guardarla en PDF (Ctrl + P) y enviarla al proveedor.
 * Pagina suelta, sin menu, como el ticket de venta.
 * @var OrdenCompra $orden
 * @var Tienda $tienda
 * @var ?Proveedor $proveedor
 * @var array<int, DetalleOrdenCompra> $detalle
 * @var array<int, Producto> $productos
 * @var array<int, string> $unidades
 * @var ?Usuario $emitio
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($orden->numero()) ?> · Minimarket Mass</title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; font-size: 13px; color: #111; background: #e9ebee; margin: 0; }
        .hoja { max-width: 190mm; margin: 16px auto; background: #fff; padding: 12mm; box-shadow: 0 1px 4px rgba(0,0,0,.2); }
        header { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #111; padding-bottom: 8px; }
        h1 { font-size: 20px; margin: 0; }
        h2 { font-size: 15px; margin: 0; text-align: right; }
        p { margin: 2px 0; }
        .partes { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 12px 0; }
        .partes h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #555; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border-bottom: 1px solid #ccc; padding: 6px 4px; text-align: left; vertical-align: top; }
        th { font-size: 11px; text-transform: uppercase; color: #555; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: 700; border-bottom: 0; font-size: 14px; }
        .estado { display: inline-block; border: 2px solid #111; padding: 2px 8px; font-weight: 700; letter-spacing: 1px; margin-top: 4px; }
        .firmas { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; margin-top: 48px; text-align: center; }
        .firmas div { border-top: 1px solid #111; padding-top: 4px; }
        .acciones { max-width: 190mm; margin: 0 auto 16px; font-size: 13px; }
        @media print {
            body { background: #fff; }
            .hoja { margin: 0; box-shadow: none; padding: 0; max-width: none; }
            .acciones { display: none; }
        }
    </style>
</head>
<body>
<div class="hoja">
    <header>
        <div>
            <h1>TIENDA MASS</h1>
            <p><?= e($tienda->getNombreTienda()) ?></p>
            <p><?= e($tienda->getDireccion()) ?></p>
            <?php if ($tienda->getTelefono() !== null): ?><p>Tel. <?= e($tienda->getTelefono()) ?></p><?php endif; ?>
        </div>
        <div>
            <h2>ORDEN DE COMPRA</h2>
            <p style="text-align:right"><strong><?= e($orden->numero()) ?></strong></p>
            <?php if (!$orden->estaAbierta() || $orden->getEstado() !== 'PENDIENTE'): ?><p style="text-align:right"><span class="estado"><?= e($orden->getEstado()) ?></span></p><?php endif; ?>
        </div>
    </header>
    <div class="partes">
        <div>
            <h3>Proveedor</h3>
            <p><strong><?= e($proveedor?->getRazonSocial() ?? '-') ?></strong></p>
            <p>RUC <?= e($proveedor?->getRuc() ?? '-') ?></p>
            <?php if ($proveedor?->getContacto() !== null): ?><p>Atención: <?= e($proveedor->getContacto()) ?></p><?php endif; ?>
            <?php if ($proveedor !== null && ($proveedor->getTelefono() !== null || $proveedor->getCorreo() !== null)): ?><p><?= e(implode(' · ', array_filter([$proveedor->getTelefono(), $proveedor->getCorreo()]))) ?></p><?php endif; ?>
        </div>
        <div>
            <h3>Pedido</h3>
            <p>Emitida: <?= e(fecha($orden->getFechaHora())) ?></p>
            <p>Entregar en: <?= e($tienda->getNombreTienda()) ?></p>
            <p>Fecha de entrega: <?= e($orden->getFechaEntrega() !== null ? fecha($orden->getFechaEntrega()) : 'a coordinar') ?></p>
            <p>Emitida por: <?= e($emitio?->nombreCompleto() ?? '-') ?></p>
        </div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Producto</th><th class="num">Cantidad</th><th>Unidad</th><th class="num">Costo unit.</th><th class="num">Importe</th></tr></thead>
        <tbody>
        <?php $n = 0; foreach ($detalle as $idProducto => $d): $n++; ?>
            <?php $p = $productos[$idProducto] ?? null; ?>
            <tr>
                <td><?= (int) $n ?></td>
                <td><?= e($p?->getNombreProducto() ?? '-') ?><?php if ($p?->getCodigoBarras() !== null): ?><br><small><?= e($p->getCodigoBarras()) ?></small><?php endif; ?></td>
                <td class="num"><?= e(cantidad($d->getPedida(), $p?->esAlPeso() === true)) ?></td>
                <td><?= e($unidades[$idProducto] ?? '') ?></td>
                <td class="num"><?= e(number_format($d->getCostoUnitario() / 100, 2)) ?></td>
                <td class="num"><?= e(number_format($d->getCostoTotal() / 100, 2)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="5" class="num">TOTAL</td><td class="num"><?= e(soles($orden->getTotal())) ?></td></tr></tfoot>
    </table>
    <?php if ($orden->getObservacion() !== null): ?><p style="margin-top:12px">Observación: <?= e($orden->getObservacion()) ?></p><?php endif; ?>
    <p style="margin-top:12px">Al entregar, indique el número <strong><?= e($orden->numero()) ?></strong> en la guía o la factura.</p>
    <div class="firmas"><div>Emitido por Tienda Mass</div><div>Recibido por el proveedor</div></div>
</div>
<div class="acciones">
    <p>Para imprimir o guardar en PDF: <strong>Ctrl + P</strong> (en el celular, Compartir → Imprimir) y elige «Guardar como PDF».</p>
    <p><a href="/?ruta=ordenes#oc-<?= $orden->getIdOrdenCompra() ?>">← Volver a la orden</a></p>
</div>
</body>
</html>
