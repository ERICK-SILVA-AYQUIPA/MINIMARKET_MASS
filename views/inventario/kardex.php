<?php
declare(strict_types=1);
/**
 * V: kardex (movimiento_stock) de una tienda, del mas reciente al mas antiguo.
 * @var array<int, Tienda> $tiendas
 * @var Tienda $tienda
 * @var string $rutaSelector
 * @var array<int, Producto> $productos
 * @var ?int $idProducto
 * @var array<int, TipoMovimiento> $tipos
 * @var array<int, string> $nombresEmpleado
 * @var MovimientoStock[] $movimientos
 * @var Paginacion $paginacion
 * @var string $urlPagina
 */
$titulo = 'Kardex';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Kardex de <?= e($tienda->getNombreTienda()) ?></h1>
<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<form method="get" action="/">
    <input type="hidden" name="ruta" value="kardex">
    <input type="hidden" name="tienda" value="<?= $tienda->getIdTienda() ?>">
    <label for="producto">Producto</label>
    <select id="producto" name="producto">
        <option value="">Todos</option>
        <?php foreach ($productos as $id => $p): ?>
            <option value="<?= $id ?>" <?= $id === $idProducto ? 'selected' : '' ?>><?= e($p->getNombreProducto()) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Ver</button>
</form>
<br>
<div class="tabla">
<table>
    <tr><th>#</th><th>Fecha</th><th>Producto</th><th>Movimiento</th><th>Cantidad</th><th>Saldo</th><th>Origen</th><th>Empleado</th></tr>
    <?php foreach ($movimientos as $m): ?>
        <?php
        $tipo = $tipos[$m->getIdTipoMovimiento()] ?? null;
        $producto = $productos[$m->getIdProducto()] ?? null;
        $decimales = $producto !== null && $producto->esAlPeso() ? 3 : 0;
        $origen = match (true) {
            $m->getIdVenta() !== null    => 'Venta ' . $m->getIdVenta(),
            $m->getIdTraslado() !== null => 'Traslado ' . $m->getIdTraslado(),
            $m->getIdMerma() !== null    => 'Merma ' . $m->getIdMerma(),
            default                      => 'Ajuste ' . $m->getIdAjuste(),
        };
        ?>
        <tr>
            <td><?= $m->getIdMovimiento() ?></td>
            <td><?= e($m->getFechaHora()) ?></td>
            <td><?= e($producto?->getNombreProducto() ?? '-') ?></td>
            <td><span class="etiqueta <?= $tipo?->esEntrada() ? 'ok' : 'reponer' ?>"><?= e($tipo?->getNombreMovimiento() ?? '-') ?></span></td>
            <td class="num"><?= $tipo?->esEntrada() ? '+' : '−' ?><?= e(number_format($m->getCantidad(), $decimales)) ?></td>
            <td class="num"><?= e(number_format($m->getStockNuevo(), $decimales)) ?></td>
            <td><?= e($origen) ?></td>
            <td><?= e($nombresEmpleado[$m->getIdEmpleado()] ?? '-') ?></td>
        </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/../parciales/paginacion.php'; ?>
<p><?= $paginacion->getTotalFilas() ?> movimientos.</p>
<?php require __DIR__ . '/../layout_fin.php'; ?>
