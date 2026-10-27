<?php
declare(strict_types=1);
/**
 * V: panel de inicio de la tienda del usuario.
 * @var Usuario $usuario
 * @var Tienda $tienda
 * @var array{comprobantes: int, total: float, ticketPromedio: float, anuladas: int} $hoyResumen
 * @var array{comprobantes: int, total: float, ticketPromedio: float, anuladas: int} $ayerResumen
 * @var array<string, float> $ventasPorDia
 * @var array<int, array<string, string>> $porReponer
 * @var int $totalPorReponer
 * @var array<int, array<string, string>> $turnos
 * @var ?CajaTurno $miTurno
 * @var MovimientoStock[] $movimientos
 * @var array<int, Producto> $productosPorId
 * @var array<int, TipoMovimiento> $tipos
 * @var bool $esAdministrador
 */
$titulo = 'Inicio';
require __DIR__ . '/../layout_inicio.php';

// Variacion de hoy contra ayer, en porcentaje (null si ayer no hubo ventas)
$variacion = $ayerResumen['total'] > 0
    ? round(($hoyResumen['total'] - $ayerResumen['total']) / $ayerResumen['total'] * 100, 1)
    : null;
$maximo = max(1.0, ...array_values($ventasPorDia));
$totalPeriodo = array_sum($ventasPorDia);
?>
<div class="cabecera bienvenida">
    <div>
        <h1>Hola, <span class="resalte"><?= e($usuario->getNombres()) ?></span></h1>
        <p class="sutil"><?= e($tienda->getNombreTienda()) ?> · <?= e(ucfirst(strtolower($usuario->getNombreRol()))) ?> · <?= e(date('d/m/Y')) ?></p>
    </div>
    <div class="acciones">
        <?php if ($usuario->puedeVender()): ?><a class="boton acento" href="/?ruta=venta_nueva"><?= icono('venta') ?>Nueva venta</a><?php endif; ?>
        <?php if ($usuario->puedeRegistrarIngreso($tienda->getIdTienda())): ?><a class="boton acento" href="/?ruta=ingresos#nuevo-ingreso"><?= icono('ingresos') ?>Recibir mercadería</a><?php endif; ?>
        <a class="boton secundario" href="/?ruta=productos"><?= icono('buscar') ?>Buscar producto</a>
    </div>
</div>

<?php if ($usuario->puedeManejarCaja()): ?>
    <div class="panel destacado">
        <?php if ($miTurno === null): ?>
            <div><strong>No tienes un turno abierto.</strong> Ábrelo para poder vender.</div> <a class="boton" href="/?ruta=caja"><?= icono('caja') ?>Abrir turno</a>
        <?php else: ?>
            <div><strong>Tu turno <?= $miTurno->getIdCajaTurno() ?> está abierto</strong> desde <?= e($miTurno->getFechaHoraApertura()) ?>
            con <?= e(soles($miTurno->getMontoInicial())) ?> iniciales.</div> <a class="boton" href="/?ruta=caja">Ver mi turno</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<section class="tarjetas" aria-label="Indicadores de hoy">
    <div>
        <span>Vendido hoy</span>
        <strong><?= e(soles($hoyResumen['total'])) ?></strong>
        <small>Ayer <?= e(soles($ayerResumen['total'])) ?>
            <?php if ($variacion !== null): ?>
                · <span class="<?= $variacion >= 0 ? 'texto-ok' : 'texto-error' ?>"><?= $variacion >= 0 ? '▲' : '▼' ?> <?= e(number_format(abs($variacion), 1)) ?>%</span>
            <?php endif; ?>
        </small>
    </div>
    <div>
        <span>Comprobantes</span>
        <strong><?= $hoyResumen['comprobantes'] ?></strong>
        <small>Ayer <?= $ayerResumen['comprobantes'] ?></small>
    </div>
    <div>
        <span>Ticket promedio</span>
        <strong><?= e(soles($hoyResumen['ticketPromedio'])) ?></strong>
        <small>Ayer <?= e(soles($ayerResumen['ticketPromedio'])) ?></small>
    </div>
    <div>
        <span>Anuladas hoy</span>
        <strong><?= $hoyResumen['anuladas'] ?></strong>
        <small>Ayer <?= $ayerResumen['anuladas'] ?></small>
    </div>
    <div>
        <span>Por reponer</span>
        <strong class="<?= $totalPorReponer > 0 ? 'texto-error' : 'texto-ok' ?>"><?= $totalPorReponer ?></strong>
        <small>productos en su mínimo</small>
    </div>
</section>

<div class="rejilla">
    <section class="panel">
        <h2>Ventas de los últimos <?= count($ventasPorDia) ?> días</h2>
        <p class="sutil">Total emitido del periodo: <strong><?= e(soles($totalPeriodo)) ?></strong></p>
        <div class="grafico" role="img" aria-label="Ventas emitidas por día">
            <?php foreach ($ventasPorDia as $dia => $total): ?>
                <?php $alto = (int) round($total / $maximo * 100); ?>
                <div class="barra" title="<?= e($dia . ': ' . soles($total)) ?>">
                    <span style="height: <?= $alto ?>%"></span>
                    <small><?= e(substr($dia, 8, 2)) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($esAdministrador): ?><p><a href="/?ruta=reportes">Ver el reporte completo</a></p><?php endif; ?>
    </section>

    <section class="panel">
        <h2>Por reponer</h2>
        <?php if ($porReponer === []): ?>
            <div class="aviso ok">Todos los productos están sobre su stock mínimo.</div>
        <?php else: ?>
            <div class="tabla">
            <table>
                <tr><th scope="col">Producto</th><th scope="col" class="num">Hay</th><th scope="col" class="num">Mínimo</th></tr>
                <?php foreach ($porReponer as $f): ?>
                    <tr>
                        <td><a class="fila-enlace" href="/?ruta=productos&amp;ver=<?= (int) $f['id_producto'] ?>"><?= e($f['nombre_producto']) ?></a></td>
                        <td class="num"><span class="etiqueta reponer"><?= e(rtrim(rtrim($f['cantidad_actual'], '0'), '.') ?: '0') ?> <?= e($f['abreviatura']) ?></span></td>
                        <td class="num"><?= e(rtrim(rtrim($f['stock_minimo'], '0'), '.') ?: '0') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            </div>
            <?php if ($totalPorReponer > count($porReponer)): ?>
                <p class="sutil">Y <?= $totalPorReponer - count($porReponer) ?> más. <a href="/?ruta=productos">Ver el catálogo</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<section class="panel">
    <h2>Cajas abiertas</h2>
    <?php if ($turnos === []): ?>
        <p class="sutil">No hay turnos abiertos en esta tienda ahora mismo.</p>
    <?php else: ?>
        <div class="tabla">
        <table>
            <tr><th scope="col">Caja</th><th scope="col">Cajero</th><th scope="col">Desde</th><th scope="col" class="num">Inicial</th><th scope="col" class="num">Ventas</th><th scope="col" class="num">Efectivo cobrado</th></tr>
            <?php foreach ($turnos as $t): ?>
                <tr>
                    <td><?= (int) $t['numero_caja'] ?></td>
                    <td><?= e($t['cajero']) ?></td>
                    <td><?= e($t['fecha_hora_apertura']) ?></td>
                    <td class="num"><?= e(soles((float) $t['monto_inicial'])) ?></td>
                    <td class="num"><?= (int) $t['ventas'] ?></td>
                    <td class="num"><?= e(soles((float) $t['efectivo'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Últimos movimientos de stock</h2>
    <?php if ($movimientos === []): ?>
        <p class="sutil">Todavía no hay movimientos de stock en esta tienda.</p>
    <?php else: ?>
        <div class="tabla">
        <table>
            <tr><th scope="col">Fecha</th><th scope="col">Producto</th><th scope="col">Tipo</th><th scope="col" class="num">Cantidad</th><th scope="col" class="num">Queda</th></tr>
            <?php foreach ($movimientos as $m): ?>
                <?php
                $tipo = $tipos[$m->getIdTipoMovimiento()] ?? null;
                $producto = $productosPorId[$m->getIdProducto()] ?? null;
                $decimales = $producto !== null && $producto->esAlPeso() ? 3 : 0;
                ?>
                <tr>
                    <td><?= e($m->getFechaHora()) ?></td>
                    <td><?= e($producto?->getNombreProducto() ?? '-') ?></td>
                    <td><span class="etiqueta <?= $tipo?->esEntrada() ? 'ok' : 'reponer' ?>"><?= e($tipo?->getNombreMovimiento() ?? '-') ?></span></td>
                    <td class="num"><?= $tipo?->esEntrada() ? '+' : '−' ?><?= e(number_format($m->getCantidad(), $decimales)) ?></td>
                    <td class="num"><?= e(number_format($m->getStockNuevo(), $decimales)) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        </div>
        <p><a href="/?ruta=kardex">Ver el kardex completo</a></p>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../layout_fin.php'; ?>
