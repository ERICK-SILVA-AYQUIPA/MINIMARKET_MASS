<?php
declare(strict_types=1);
/**
 * V: lista de reposicion de la tienda: lo que llego a su stock minimo, cuanto pedir y que otra tienda tiene sobrante.
 * Las cantidades llegan en milesimas (ver models/Reposicion.php).
 * @var Tienda $tienda
 * @var array<int, array{id_producto: int, nombre: string, unidad: string, al_peso: bool, actual: int, minimo: int,
 *            ventaDiaria: int, cobertura: ?int, porLlegar: int, sugerido: int, proveedor: ?array{id_proveedor: int, proveedor: string, costo: int},
 *            otras: array<int, array{tienda: string, sobrante: int}>}> $filas
 * @var int $diasVenta
 * @var int $diasObjetivo
 * @var int $sinStock
 * @var int $conSobrante  productos que otra tienda puede trasladar
 * @var bool $puedeTrasladar
 * @var bool $puedePedir
 * @var array<int, array{nombre: string, productos: int}> $porProveedor  id_proveedor => a quien se le compro por ultima vez
 * @var int $porPedir  productos con algo que pedir (lo demas ya esta pedido en una orden)
 */
$titulo = 'Reponer';
require __DIR__ . '/../layout_inicio.php';
?>
<div class="cabecera">
    <div>
        <h1>Reponer en <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Productos en su stock mínimo o por debajo. Lo sugerido cubre <?= (int) $diasObjetivo ?> días de venta (promedio de los últimos <?= (int) $diasVenta ?>), nunca es menos que el doble del mínimo y descuenta lo que ya está pedido en una orden de compra.</p>
    </div>
    <?php if ($filas !== []): ?>
    <div class="acciones">
        <a class="boton secundario" href="/?ruta=reponer_csv"><?= icono('descarga') ?>Lista de pedido en CSV</a>
        <?php if ($puedePedir && $porPedir > 0): ?><a class="boton acento" href="/?ruta=ordenes&amp;desde=reponer#nueva-orden"><?= icono('orden') ?>Armar orden de compra</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php
$guiaClave = 'reponer';
$guiaTitulo = 'Cómo decidir qué pedir';
$guiaPasos = [
    'Aquí salen los productos que llegaron a su stock mínimo, del más urgente al menos urgente.',
    '"Alcanza" dice cuántos días dura lo que queda al ritmo de venta de las últimas 2 semanas. "Pedir" sugiere cuánto comprar.',
    'Si otra tienda tiene de sobra, pide un traslado en vez de comprar.',
    'Para comprar, pulsa "Pedir a ..." o "Armar orden de compra": la orden llega llena con lo sugerido y el último costo. Lo ya pedido sale en "Pedido" y no se vuelve a sugerir.',
];
require __DIR__ . '/../parciales/guia.php';
?>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = 'Nada por reponer';
    $vacioTexto = 'Todos los productos activos están sobre su stock mínimo.';
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<section class="tarjetas" aria-label="Resumen">
    <div><span>Por reponer</span><strong><?= count($filas) ?></strong><small>en su mínimo o debajo</small></div>
    <div><span>Sin stock</span><strong><?= (int) $sinStock ?></strong><small>no se pueden vender</small></div>
    <div><span>Con sobrante en otra tienda</span><strong><?= (int) $conSobrante ?></strong><small>se pueden trasladar</small></div>
</section>
<?php if ($puedePedir && $porProveedor !== []): ?>
<nav class="bloque acciones" aria-label="Pedir a un proveedor">
    <?php foreach ($porProveedor as $idProveedor => $grupo): ?>
        <a class="boton secundario chico" href="/?ruta=ordenes&amp;desde=reponer&amp;proveedor=<?= (int) $idProveedor ?>#nueva-orden"><?= icono('orden') ?>Pedir a <?= e($grupo['nombre']) ?> (<?= (int) $grupo['productos'] ?>)</a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">Producto</th><th scope="col" class="num">Hay</th><th scope="col" class="num">Mínimo</th><th scope="col" class="num">Venta diaria</th><th scope="col" class="num">Alcanza</th><th scope="col" class="num">Pedido</th><th scope="col" class="num">Pedir</th><th scope="col">Último proveedor</th><th scope="col">Sobrante en otras tiendas</th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
        <?php
        $hay = cantidad($f['actual'], $f['al_peso']) . ' ' . $f['unidad'];
        $alcanza = $f['cobertura'] === null ? 'sin ventas' : ($f['cobertura'] === 1 ? '1 día' : $f['cobertura'] . ' días');
        // Por unidad, un decimal basta ("2.5 und al día"); al peso, en kg con 3
        $diaria = $f['al_peso'] ? Cantidad::aTexto($f['ventaDiaria']) : number_format($f['ventaDiaria'] / 1000, 1);
        $claseHay = $f['actual'] === 0 ? 'error' : 'reponer';
        $otras = implode(' · ', array_map(fn(array $o): string => $o['tienda'] . ': ' . cantidad($o['sobrante'], $f['al_peso']), $f['otras']));
        ?>
        <tr>
            <td><a class="fila-enlace" href="/?ruta=productos&amp;ver=<?= (int) $f['id_producto'] ?>"><?= e($f['nombre']) ?></a></td>
            <td class="num"><span class="etiqueta <?= e($claseHay) ?>"><?= e($hay) ?></span></td>
            <td class="num"><?= e(cantidad($f['minimo'], $f['al_peso'])) ?></td>
            <td class="num"><?= e($diaria) ?></td>
            <td class="num"><?= e($alcanza) ?></td>
            <td class="num"><?php if ($f['porLlegar'] > 0): ?><a href="/?ruta=ordenes&amp;estado=ABIERTAS"><?= e(cantidad($f['porLlegar'], $f['al_peso'])) ?></a><?php else: ?><span class="sutil">-</span><?php endif; ?></td>
            <td class="num"><?php if ($f['sugerido'] > 0): ?><strong><?= e(cantidad($f['sugerido'], $f['al_peso']) . ' ' . $f['unidad']) ?></strong><?php elseif ($f['porLlegar'] > 0): ?><span class="etiqueta ok">ya pedido</span><?php else: ?><span class="sutil">0</span><?php endif; ?></td>
            <td><?= $f['proveedor'] === null ? '<span class="sutil">sin compras</span>' : e($f['proveedor']['proveedor']) ?></td>
            <td><?= $otras === '' ? '<span class="sutil">ninguna</span>' : e($otras) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="sutil bloque">
    Para traer de otra tienda, su almacenero registra un <a href="/?ruta=traslados">traslado</a> hacia esta.
    Lo que se compra va en una <a href="/?ruta=ordenes">orden de compra</a>; cuando llega, se recibe en <a href="/?ruta=ingresos">Ingresos</a> y sube el stock.
</p>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
