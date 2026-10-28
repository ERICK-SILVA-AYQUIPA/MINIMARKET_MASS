<?php
declare(strict_types=1);
/**
 * V: lista de reposicion de la tienda: lo que llego a su stock minimo, cuanto pedir y que otra tienda tiene sobrante.
 * Las cantidades llegan en milesimas (ver models/Reposicion.php).
 * @var Tienda $tienda
 * @var array<int, array{id_producto: int, nombre: string, unidad: string, al_peso: bool, actual: int, minimo: int,
 *            ventaDiaria: int, cobertura: ?int, sugerido: int, otras: array<int, array{tienda: string, sobrante: int}>}> $filas
 * @var int $diasVenta
 * @var int $diasObjetivo
 * @var int $sinStock
 * @var int $conSobrante  productos que otra tienda puede trasladar
 * @var bool $puedeTrasladar
 */
$titulo = 'Reponer';
require __DIR__ . '/../layout_inicio.php';
?>
<div class="cabecera">
    <div>
        <h1>Reponer en <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Productos en su stock mínimo o por debajo. Lo sugerido cubre <?= (int) $diasObjetivo ?> días de venta (promedio de los últimos <?= (int) $diasVenta ?>) y nunca es menos que el doble del mínimo.</p>
    </div>
    <?php if ($filas !== []): ?><div class="acciones"><a class="boton secundario" href="/?ruta=reponer_csv"><?= icono('descarga') ?>Lista de pedido en CSV</a></div><?php endif; ?>
</div>

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
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">Producto</th><th scope="col" class="num">Hay</th><th scope="col" class="num">Mínimo</th><th scope="col" class="num">Venta diaria</th><th scope="col" class="num">Alcanza</th><th scope="col" class="num">Pedir</th><th scope="col">Sobrante en otras tiendas</th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
        <?php
        $hay = cantidad($f['actual'], $f['al_peso']) . ' ' . $f['unidad'];
        $alcanza = $f['cobertura'] === null ? 'sin ventas' : ($f['cobertura'] === 1 ? '1 día' : $f['cobertura'] . ' días');
        $claseHay = $f['actual'] === 0 ? 'error' : 'reponer';
        $otras = implode(' · ', array_map(fn(array $o): string => $o['tienda'] . ': ' . cantidad($o['sobrante'], $f['al_peso']), $f['otras']));
        ?>
        <tr>
            <td><a class="fila-enlace" href="/?ruta=productos&amp;ver=<?= (int) $f['id_producto'] ?>"><?= e($f['nombre']) ?></a></td>
            <td class="num"><span class="etiqueta <?= e($claseHay) ?>"><?= e($hay) ?></span></td>
            <td class="num"><?= e(cantidad($f['minimo'], $f['al_peso'])) ?></td>
            <td class="num"><?= e(Cantidad::aTexto($f['ventaDiaria'])) ?></td>
            <td class="num"><?= e($alcanza) ?></td>
            <td class="num"><strong><?= e(cantidad($f['sugerido'], $f['al_peso']) . ' ' . $f['unidad']) ?></strong></td>
            <td><?= $otras === '' ? '<span class="sutil">ninguna</span>' : e($otras) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="sutil bloque">
    Para traer de otra tienda, su almacenero registra un <a href="/?ruta=traslados">traslado</a> hacia esta.
    <?php if ($puedeTrasladar): ?>Lo que llegue de un proveedor se registra en <a href="/?ruta=ingresos">Ingresos</a>.<?php endif; ?>
</p>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
