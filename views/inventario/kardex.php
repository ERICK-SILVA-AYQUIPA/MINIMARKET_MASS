<?php
declare(strict_types=1);
/**
 * V: kardex (movimiento_stock) de una tienda, del mas reciente al mas antiguo.
 * Cada movimiento abre su detalle en un panel lateral (#mov-ID).
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
<div class="cabecera">
    <div>
        <h1>Kardex de <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil"><?= $paginacion->getTotalFilas() ?> movimientos<?= e($idProducto !== null ? ' de ' . $productos[$idProducto]->getNombreProducto() : '') ?>. Cada entrada y salida de stock, con su saldo.</p>
    </div>
    <div class="acciones">
        <a class="boton secundario" href="<?= e('/?' . http_build_query(['ruta' => 'kardex_csv', 'tienda' => $tienda->getIdTienda(), 'producto' => $idProducto])) ?>"><?= icono('descarga') ?>Descargar CSV</a>
    </div>
</div>
<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="kardex">
    <input type="hidden" name="tienda" value="<?= $tienda->getIdTienda() ?>">
    <label for="producto">Producto
        <select id="producto" name="producto">
            <option value="">Todos</option>
            <?php foreach ($productos as $id => $p): ?>
                <option value="<?= $id ?>" <?= $id === $idProducto ? 'selected' : '' ?>><?= e($p->getNombreProducto()) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Ver</button>
    <?php if ($idProducto !== null): ?><a class="boton secundario" href="/?ruta=kardex&amp;tienda=<?= $tienda->getIdTienda() ?>">Todos los productos</a><?php endif; ?>
</form>

<?php if ($movimientos === []): ?>
    <?php
    $vacioTitulo = 'Sin movimientos';
    $vacioTexto = 'Aquí aparece cada venta, ingreso, traslado, merma o conteo que cambie el stock.';
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">#</th><th scope="col">Fecha</th><th scope="col">Producto</th><th scope="col">Movimiento</th><th scope="col" class="num">Cantidad</th><th scope="col" class="num">Saldo</th><th scope="col">Origen</th><th scope="col"><span class="visualmente-oculto">Detalle</span></th></tr>
    </thead>
    <tbody>
    <?php foreach ($movimientos as $m): ?>
        <?php
        $tipo = $tipos[$m->getIdTipoMovimiento()] ?? null;
        $producto = $productos[$m->getIdProducto()] ?? null;
        $decimales = $producto !== null && $producto->esAlPeso() ? 3 : 0;
        ?>
        <tr id="fila-mov-<?= $m->getIdMovimiento() ?>">
            <td><?= $m->getIdMovimiento() ?></td>
            <td class="fecha"><?= e($m->getFechaHora()) ?></td>
            <td><?= e($producto?->getNombreProducto() ?? '-') ?></td>
            <td><span class="etiqueta <?= $tipo?->esEntrada() ? 'ok' : 'reponer' ?>"><?= e($tipo?->getNombreMovimiento() ?? '-') ?></span></td>
            <td class="num"><?= $tipo?->esEntrada() ? '+' : '−' ?><?= e(number_format($m->getCantidad(), $decimales)) ?></td>
            <td class="num"><?= e(number_format($m->getStockNuevo(), $decimales)) ?></td>
            <td><?= e($m->origen()) ?></td>
            <td><a class="boton secundario chico" href="#mov-<?= $m->getIdMovimiento() ?>"><?= icono('ver') ?>Ver</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../parciales/paginacion.php'; ?>

<?php foreach ($movimientos as $m): ?>
    <?php
    $tipo = $tipos[$m->getIdTipoMovimiento()] ?? null;
    $producto = $productos[$m->getIdProducto()] ?? null;
    $decimales = $producto !== null && $producto->esAlPeso() ? 3 : 0;
    // El saldo anterior se deduce: entrada resta, salida suma
    $anterior = $tipo?->esEntrada() ? $m->getStockNuevo() - $m->getCantidad() : $m->getStockNuevo() + $m->getCantidad();
    $drawerId = 'mov-' . $m->getIdMovimiento();
    $drawerTitulo = 'Movimiento ' . $m->getIdMovimiento();
    $drawerSubtitulo = ($tipo?->getNombreMovimiento() ?? '-') . ' · ' . $m->getFechaHora();
    $drawerCerrar = '#fila-mov-' . $m->getIdMovimiento();
    require __DIR__ . '/../parciales/drawer_abrir.php';
    ?>
    <dl class="datos">
        <dt>Producto</dt><dd><?= e($producto?->getNombreProducto() ?? '-') ?></dd>
        <dt>Tipo</dt><dd><span class="etiqueta <?= $tipo?->esEntrada() ? 'ok' : 'reponer' ?>"><?= e($tipo?->getNombreMovimiento() ?? '-') ?></span> <?= $tipo?->esEntrada() ? 'entrada' : 'salida' ?></dd>
        <dt>Saldo anterior</dt><dd><?= e(number_format($anterior, $decimales)) ?></dd>
        <dt>Cantidad</dt><dd><?= $tipo?->esEntrada() ? '+' : '−' ?><?= e(number_format($m->getCantidad(), $decimales)) ?></dd>
        <dt>Saldo nuevo</dt><dd><strong><?= e(number_format($m->getStockNuevo(), $decimales)) ?></strong></dd>
        <dt>Origen</dt><dd><?= e($m->origen()) ?></dd>
        <dt>Empleado</dt><dd><?= e($nombresEmpleado[$m->getIdEmpleado()] ?? '-') ?></dd>
        <dt>Observación</dt><dd><?= e($m->getObservacion() ?? '-') ?></dd>
    </dl>
    <div class="acciones bloque">
        <a class="boton secundario" href="/?ruta=kardex&amp;tienda=<?= $tienda->getIdTienda() ?>&amp;producto=<?= $m->getIdProducto() ?>"><?= icono('kardex') ?>Solo este producto</a>
    </div>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
