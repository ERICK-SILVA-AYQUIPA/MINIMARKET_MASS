<?php
declare(strict_types=1);
/**
 * V: ventas de una tienda. Solo muestra; las reglas estan en Venta.
 * @var array<int, Tienda> $tiendas
 * @var Tienda $tienda
 * @var string $rutaSelector
 * @var array<int, array{venta: Venta, serie: SerieComprobante, cajero: ?Usuario, cliente: string, pagosCuadran: bool}> $filas
 * @var float $totalEmitido
 */
$titulo = 'Ventas';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Ventas de <?= e($tienda->getNombreTienda()) ?></h1>
<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<div class="tabla">
<table>
    <thead>
        <tr><th>Comprobante</th><th>Fecha y hora</th><th>Cajero</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Pagos</th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $fila): ?>
        <?php
        $v = $fila['venta'];
        $s = $fila['serie'];
        $enlace = '/?ruta=comprobante&serie=' . urlencode($s->getSerie()) . '&numero=' . $v->getNumeroCorrelativo();
        ?>
        <tr>
            <td><a href="<?= e($enlace) ?>"><?= e($s->formatear($v->getNumeroCorrelativo())) ?></a></td>
            <td><?= e($v->getFechaHora()) ?></td>
            <td><?= e($fila['cajero']?->nombreCompleto() ?? '-') ?></td>
            <td><?= e($fila['cliente']) ?></td>
            <td class="num"><?= e(soles($v->getTotal())) ?></td>
            <td><span class="etiqueta <?= $v->estaAnulada() ? 'anulada' : 'ok' ?>"><?= e($v->getEstado()) ?></span></td>
            <td><?= $fila['pagosCuadran'] ? 'Cuadran' : '<span class="etiqueta reponer">No cuadran</span>' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p><?= count($filas) ?> comprobantes. Total emitido (sin anuladas): <strong><?= e(soles($totalEmitido)) ?></strong></p>
<?php require __DIR__ . '/../layout_fin.php'; ?>
