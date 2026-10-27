<?php
declare(strict_types=1);
/**
 * V: ventas de una tienda. Solo muestra; las reglas estan en Venta.
 * @var array<int, Tienda> $tiendas
 * @var Tienda $tienda
 * @var string $rutaSelector
 * @var array<int, array{venta: Venta, serie: SerieComprobante, cajero: ?Usuario, cliente: string, pagosCuadran: bool}> $filas
 * @var float $totalEmitido  de todas las paginas, sin anuladas
 * @var array{desde: ?string, hasta: ?string, estado: ?string} $filtro
 * @var Paginacion $paginacion
 * @var string $urlPagina
 */
$titulo = 'Ventas';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Ventas de <?= e($tienda->getNombreTienda()) ?></h1>
<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="ventas">
    <input type="hidden" name="tienda" value="<?= $tienda->getIdTienda() ?>">
    <label>Desde <input type="date" name="desde" value="<?= e($filtro['desde'] ?? '') ?>"></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($filtro['hasta'] ?? '') ?>"></label>
    <label>Estado
        <select name="estado">
            <option value="">Todos</option>
            <?php foreach (['EMITIDA', 'ANULADA'] as $opcion): ?>
                <option value="<?= e($opcion) ?>" <?= $filtro['estado'] === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Filtrar</button>
    <a href="/?ruta=ventas&amp;tienda=<?= $tienda->getIdTienda() ?>">Quitar filtros</a>
</form>

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
<?php require __DIR__ . '/../parciales/paginacion.php'; ?>
<p><?= $paginacion->getTotalFilas() ?> comprobantes. Total emitido (sin anuladas): <strong><?= e(soles($totalEmitido)) ?></strong></p>
<?php require __DIR__ . '/../layout_fin.php'; ?>
