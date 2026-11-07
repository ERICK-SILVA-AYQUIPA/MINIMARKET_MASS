<?php
declare(strict_types=1);
/**
 * V: panel de inicio de la tienda del usuario, armado para su rol ($perfil):
 * - admin: ventas de hoy y del mes con su variación, gráfico de 14 días y de las horas de hoy, más vendidos,
 *   medios de pago, lo que falta y lo que vence, cajas abiertas y últimos movimientos.
 * - cajero: su turno (ventas, total y efectivo cobrado), promociones de hoy, lo que más sale y lo que falta.
 * - almacen: por reponer, agotados, lo que vence, pedidos por llegar y últimos movimientos.
 * @var Usuario $usuario
 * @var Tienda $tienda
 * @var string $perfil  admin, cajero o almacen
 * @var string $saludo
 * @var array{comprobantes: int, total: float, ticketPromedio: float, anuladas: int} $hoyResumen
 * @var array{comprobantes: int, total: float, ticketPromedio: float, anuladas: int} $ayerResumen
 * @var ?array{comprobantes: int, total: float, ticketPromedio: float, anuladas: int} $mesResumen
 * @var ?array{comprobantes: int, total: float, ticketPromedio: float, anuladas: int} $mesAnterior
 * @var array<string, float> $ventasPorDia
 * @var array<int, float> $porHora  hora => total de hoy
 * @var array<int, array<string, mixed>> $pagosHoy  metodo, pagos, monto, pct
 * @var array<int, array<string, mixed>> $topSemana  producto, unidad, cantidad, importe
 * @var Promocion[] $promocionesHoy
 * @var array<int, array<string, string>> $porReponer
 * @var int $totalPorReponer
 * @var int $agotados
 * @var array<int, array<string, string>> $turnos
 * @var ?CajaTurno $miTurno
 * @var ?array<string, string> $miTurnoDatos  la fila de $turnos del turno del cajero
 * @var MovimientoStock[] $movimientos
 * @var array<int, Producto> $productosPorId
 * @var array<int, TipoMovimiento> $tipos
 * @var bool $esAdministrador
 * @var bool $veVencimientos
 * @var array<int, array{nombre: string, dias: int, vencido: bool, fecha_vencimiento: string}> $porVencer  lotes de esta semana
 * @var OrdenCompra[] $ordenesAbiertas
 * @var int $totalOrdenes
 * @var array<int, string> $proveedores  id => razon social
 */
$titulo = 'Inicio';
require __DIR__ . '/../layout_inicio.php';

$diasSemana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fechaLarga = $diasSemana[(int) date('w')] . ' ' . (int) date('j') . ' de ' . $meses[(int) date('n')];
$rolTexto = ['admin' => 'Administración', 'cajero' => 'Caja', 'almacen' => 'Almacén'][$perfil];

// Cambio contra el periodo anterior (▲ verde si sube, ▼ rojo si baja): [variacion, contra que]
$cambios = $perfil === 'admin' ? [
    'total'   => [Tablero::variacion($hoyResumen['total'], $ayerResumen['total']), 'ayer'],
    'compro'  => [Tablero::variacion($hoyResumen['comprobantes'], $ayerResumen['comprobantes']), 'ayer'],
    'ticket'  => [Tablero::variacion($hoyResumen['ticketPromedio'], $ayerResumen['ticketPromedio']), 'ayer'],
    'mes'     => [Tablero::variacion($mesResumen['total'] ?? 0.0, $mesAnterior['total'] ?? 0.0), 'el mes pasado'],
] : [];
$numero = static fn(string $valor): string => rtrim(rtrim($valor, '0'), '.') ?: '0';
?>
<div class="cabecera bienvenida tablero-cabeza">
    <div>
        <p class="tablero-rol"><?php if ($perfil === 'admin'): ?><?= icono('reportes') ?><?php elseif ($perfil === 'cajero'): ?><?= icono('caja') ?><?php else: ?><?= icono('stock') ?><?php endif; ?><?= e($rolTexto) ?> · <?= e($tienda->getNombreTienda()) ?></p>
        <h1><?= e($saludo) ?>, <span class="resalte"><?= e($usuario->getNombres()) ?></span></h1>
        <p class="sutil">Hoy es <?= e($fechaLarga) ?>. <?= e(match ($perfil) {
            'admin'  => 'Así va tu tienda.',
            'cajero' => 'Esto es lo que necesitas para tu turno.',
            default  => 'Esto es lo que pide el almacén hoy.',
        }) ?></p>
    </div>
    <div class="acciones">
        <?php if ($usuario->puedeVender()): ?><a class="boton acento" href="/?ruta=venta_nueva"><?= icono('venta') ?>Nueva venta</a><?php endif; ?>
        <?php if ($usuario->puedeRegistrarIngreso($tienda->getIdTienda())): ?><a class="boton acento" href="/?ruta=ingresos#nuevo-ingreso"><?= icono('ingresos') ?>Recibir mercadería</a><?php endif; ?>
        <?php if ($perfil === 'admin'): ?><a class="boton secundario" href="/?ruta=reportes"><?= icono('reportes') ?>Reportes</a><?php endif; ?>
        <?php if ($perfil === 'almacen'): ?><a class="boton secundario" href="/?ruta=reponer"><?= icono('orden') ?>Qué pedir</a><?php endif; ?>
        <a class="boton secundario" href="/?ruta=productos"><?= icono('buscar') ?>Buscar producto</a>
    </div>
</div>

<?php if ($usuario->puedeManejarCaja()): ?>
    <div class="panel destacado">
        <?php if ($miTurno === null): ?>
            <div><strong>No tienes un turno abierto.</strong> Ábrelo para poder vender.</div> <a class="boton" href="/?ruta=caja"><?= icono('caja') ?>Abrir turno</a>
        <?php else: ?>
            <div><strong>Tu turno <?= $miTurno->getIdCajaTurno() ?> está abierto</strong> desde <?= e(fecha($miTurno->getFechaHoraApertura())) ?>
            con <?= e(soles($miTurno->getMontoInicial())) ?> iniciales.</div> <a class="boton" href="/?ruta=caja">Ver mi turno</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Indicadores: los 4 o 5 números del día de cada rol -->
<section class="tarjetas tablero-kpi" aria-label="Indicadores de hoy">
<?php if ($perfil === 'admin'): ?>
    <div class="kpi-marca">
        <span>Vendido hoy</span>
        <strong><?= e(soles($hoyResumen['total'])) ?></strong>
        <?php [$variacion, $contra] = $cambios['total']; require __DIR__ . '/cambio.php'; ?>
    </div>
    <div>
        <span>Comprobantes</span>
        <strong><?= $hoyResumen['comprobantes'] ?></strong>
        <?php [$variacion, $contra] = $cambios['compro']; require __DIR__ . '/cambio.php'; ?>
    </div>
    <div>
        <span>Ticket promedio</span>
        <strong><?= e(soles($hoyResumen['ticketPromedio'])) ?></strong>
        <?php [$variacion, $contra] = $cambios['ticket']; require __DIR__ . '/cambio.php'; ?>
    </div>
    <div>
        <span>En lo que va del mes</span>
        <strong><?= e(soles($mesResumen['total'] ?? 0.0)) ?></strong>
        <?php [$variacion, $contra] = $cambios['mes']; require __DIR__ . '/cambio.php'; ?>
    </div>
    <div class="<?= $totalPorReponer > 0 ? 'kpi-alerta' : 'kpi-bien' ?>">
        <span>Por reponer</span>
        <strong><?= $totalPorReponer ?></strong>
        <small class="kpi-pie"><?= (int) $agotados ?> agotados · <?= $hoyResumen['anuladas'] ?> anuladas hoy</small>
    </div>
<?php elseif ($perfil === 'cajero'): ?>
    <div class="kpi-marca">
        <span>Vendido en tu turno</span>
        <strong><?= e(soles((float) ($miTurnoDatos['total'] ?? 0))) ?></strong>
        <small class="kpi-pie"><?php if ($miTurnoDatos === null): ?>Sin turno abierto<?php else: ?><?= (int) $miTurnoDatos['ventas'] ?> ventas<?php endif; ?></small>
    </div>
    <div>
        <span>Cobrado en efectivo</span>
        <strong><?= e(soles((float) ($miTurnoDatos['efectivo'] ?? 0))) ?></strong>
        <small class="kpi-pie">más <?= e(soles((float) ($miTurnoDatos['monto_inicial'] ?? 0))) ?> iniciales</small>
    </div>
    <div>
        <span>Ticket promedio</span>
        <strong><?= e(soles($miTurnoDatos !== null && (int) $miTurnoDatos['ventas'] > 0 ? (float) $miTurnoDatos['total'] / (int) $miTurnoDatos['ventas'] : 0.0)) ?></strong>
        <small class="kpi-pie">de tu turno</small>
    </div>
    <div class="<?= $promocionesHoy !== [] ? 'kpi-bien' : '' ?>">
        <span>Promociones hoy</span>
        <strong><?= count($promocionesHoy) ?></strong>
        <small class="kpi-pie">se aplican solas al cobrar</small>
    </div>
    <div class="<?= $totalPorReponer > 0 ? 'kpi-alerta' : 'kpi-bien' ?>">
        <span>Por reponer</span>
        <strong><?= $totalPorReponer ?></strong>
        <small class="kpi-pie"><?= (int) $agotados ?> agotados</small>
    </div>
<?php else: ?>
    <div class="<?= $totalPorReponer > 0 ? 'kpi-alerta' : 'kpi-bien' ?>">
        <span>Por reponer</span>
        <strong><?= $totalPorReponer ?></strong>
        <small class="kpi-pie">en su stock mínimo</small>
    </div>
    <div class="<?= $agotados > 0 ? 'kpi-alerta' : 'kpi-bien' ?>">
        <span>Agotados</span>
        <strong><?= (int) $agotados ?></strong>
        <small class="kpi-pie">ya no se pueden vender</small>
    </div>
    <div class="<?= $porVencer !== [] ? 'kpi-alerta' : 'kpi-bien' ?>">
        <span>Vencen en 7 días</span>
        <strong><?= count($porVencer) ?></strong>
        <small class="kpi-pie"><?= (int) count(array_filter($porVencer, fn(array $f): bool => $f['vencido'])) ?> ya vencidos</small>
    </div>
    <div class="kpi-marca">
        <span>Pedidos por llegar</span>
        <strong><?= (int) $totalOrdenes ?></strong>
        <small class="kpi-pie">órdenes de compra abiertas</small>
    </div>
<?php endif; ?>
</section>

<?php if ($perfil === 'admin'): ?>
<?php
$maximo = max(1.0, ...array_values($ventasPorDia));
$totalPeriodo = array_sum($ventasPorDia);
$diasConVenta = count(array_filter($ventasPorDia, fn(float $t): bool => $t > 0));
$mejorDia = (string) array_search(max($ventasPorDia), $ventasPorDia, true);
$maximoHora = max(1.0, ...array_values($porHora));
$horaPico = max($porHora) > 0 ? (int) array_search(max($porHora), $porHora, true) : null;
?>
<div class="rejilla-tablero">
    <section class="panel">
        <div class="panel-cabeza">
            <h2>Ventas de los últimos <?= count($ventasPorDia) ?> días</h2>
            <a class="enlace-sutil" href="/?ruta=reportes">Ver el reporte completo</a>
        </div>
        <div class="tablero-cifras">
            <span>Total <strong><?= e(soles($totalPeriodo)) ?></strong></span>
            <span>Promedio por día <strong><?= e(soles($diasConVenta > 0 ? $totalPeriodo / $diasConVenta : 0.0)) ?></strong></span>
            <?php if ($totalPeriodo > 0): ?><span>Mejor día <strong><?= e(substr($mejorDia, 8, 2) . '/' . substr($mejorDia, 5, 2)) ?></strong></span><?php endif; ?>
        </div>
        <div class="grafico" role="img" aria-label="Ventas emitidas por día">
            <?php foreach ($ventasPorDia as $dia => $total): ?>
                <?php $alto = (int) round($total / $maximo * 100); ?>
                <div class="barra<?= $dia === array_key_last($ventasPorDia) ? ' hoy' : '' ?>" title="<?= e($dia . ': ' . soles($total)) ?>">
                    <span style="height: <?= $alto ?>%"></span>
                    <small><?= e(substr($dia, 8, 2)) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-cabeza">
            <h2>Hoy, hora por hora</h2>
            <a class="enlace-sutil" href="/?ruta=estadisticas">Estadísticas</a>
        </div>
        <div class="tablero-cifras">
            <?php if ($horaPico !== null): ?>
                <span>Hora más movida <strong><?= e(sprintf('%02d:00', $horaPico)) ?></strong></span>
            <?php else: ?>
                <span>Todavía no hay ventas hoy.</span>
            <?php endif; ?>
        </div>
        <div class="grafico grafico-horas" role="img" aria-label="Ventas de hoy por hora">
            <?php foreach ($porHora as $hora => $total): ?>
                <?php $alto = (int) round($total / $maximoHora * 100); ?>
                <div class="barra<?= $hora === $horaPico ? ' pico' : '' ?>" title="<?= e(sprintf('%02d:00', $hora) . ': ' . soles($total)) ?>">
                    <span style="height: <?= $alto ?>%"></span>
                    <small><?= (int) $hora ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<div class="rejilla-tablero">
    <section class="panel">
        <div class="panel-cabeza">
            <h2>Lo más vendido de la semana</h2>
            <a class="enlace-sutil" href="/?ruta=reportes">Por producto</a>
        </div>
        <?php if ($topSemana === []): ?>
            <p class="sutil">Sin ventas en los últimos 7 días.</p>
        <?php else: ?>
            <?php $mayor = (float) $topSemana[0]['importe']; ?>
            <ol class="ranking">
                <?php foreach ($topSemana as $i => $f): ?>
                    <li>
                        <span class="ranking-puesto"><?= (int) $i + 1 ?></span>
                        <span class="ranking-nombre"><?= e((string) $f['producto']) ?><small><?= e($numero((string) $f['cantidad']) . ' ' . $f['unidad']) ?></small></span>
                        <span class="ranking-valor"><?= e(soles((float) $f['importe'])) ?></span>
                        <span class="ranking-pista"><span style="width: <?= (int) Tablero::ancho((float) $f['importe'], $mayor) ?>%"></span></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-cabeza"><h2>Cómo pagaron hoy</h2></div>
        <?php if ($pagosHoy === []): ?>
            <p class="sutil">Todavía no hay pagos hoy.</p>
        <?php else: ?>
            <ul class="ranking ranking-simple">
                <?php foreach ($pagosHoy as $f): ?>
                    <li>
                        <span class="ranking-nombre"><?= e(ucfirst(strtolower((string) $f['metodo']))) ?><small><?= (int) $f['pagos'] ?> <?= (int) $f['pagos'] === 1 ? 'pago' : 'pagos' ?></small></span>
                        <span class="ranking-valor"><?= e(soles((float) $f['monto'])) ?> <small><?= (int) $f['pct'] ?>%</small></span>
                        <span class="ranking-pista"><span style="width: <?= (int) $f['pct'] ?>%"></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>

<?php if ($perfil === 'cajero'): ?>
<div class="rejilla-tablero">
    <section class="panel">
        <div class="panel-cabeza"><h2>Lo que más sale esta semana</h2></div>
        <?php if ($topSemana === []): ?>
            <p class="sutil">Sin ventas en los últimos 7 días.</p>
        <?php else: ?>
            <?php $mayor = max(array_map(fn(array $f): float => (float) $f['cantidad'], $topSemana)); ?>
            <ol class="ranking">
                <?php foreach ($topSemana as $i => $f): ?>
                    <li>
                        <span class="ranking-puesto"><?= (int) $i + 1 ?></span>
                        <span class="ranking-nombre"><?= e((string) $f['producto']) ?></span>
                        <span class="ranking-valor"><?= e($numero((string) $f['cantidad']) . ' ' . $f['unidad']) ?></span>
                        <span class="ranking-pista"><span style="width: <?= (int) Tablero::ancho((float) $f['cantidad'], $mayor) ?>%"></span></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-cabeza">
            <h2>Promociones de hoy</h2>
            <a class="enlace-sutil" href="/?ruta=promociones">Ver todas</a>
        </div>
        <?php if ($promocionesHoy === []): ?>
            <p class="sutil">Hoy no hay promociones vigentes.</p>
        <?php else: ?>
            <ul class="lista-promos">
                <?php foreach (array_slice($promocionesHoy, 0, 6) as $p): ?>
                    <?php
                    $detalle = match ($p->getTipo()) {
                        'PORCENTAJE' => '−' . number_format((int) $p->getPorcentaje() / 100, 0) . ' %',
                        'PRECIO'     => 'a ' . soles((int) $p->getPrecio() / 100),
                        'PACK'       => $p->getCantidadPack() . ' x ' . soles((int) $p->getPrecio() / 100),
                        default      => '−' . number_format((int) $p->getPorcentaje() / 100, 0) . ' % desde ' . soles((int) $p->getMontoMinimo() / 100),
                    };
                    ?>
                    <li><span class="etiqueta ok"><?= e($detalle) ?></span> <?= e($p->getNombre()) ?> <small class="sutil">hasta <?= e(substr($p->getFechaFin(), 8, 2) . '/' . substr($p->getFechaFin(), 5, 2)) ?></small></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>

<div class="rejilla-tablero">
    <section class="panel">
        <div class="panel-cabeza">
            <h2>Por reponer</h2>
            <?php if ($veVencimientos): ?><a class="enlace-sutil" href="/?ruta=reponer">Cuánto pedir</a><?php endif; ?>
        </div>
        <?php if ($porReponer === []): ?>
            <div class="aviso ok">Todos los productos están sobre su stock mínimo.</div>
        <?php else: ?>
            <div class="tabla">
            <table>
                <tr><th scope="col">Producto</th><th scope="col" class="num">Hay</th><th scope="col" class="num">Mínimo</th></tr>
                <?php foreach ($porReponer as $f): ?>
                    <tr>
                        <td><a class="fila-enlace" href="/?ruta=productos&amp;ver=<?= (int) $f['id_producto'] ?>"><?= e($f['nombre_producto']) ?></a></td>
                        <td class="num"><span class="etiqueta <?= (float) $f['cantidad_actual'] <= 0 ? 'error' : 'reponer' ?>"><?= e($numero($f['cantidad_actual'])) ?> <?= e($f['abreviatura']) ?></span></td>
                        <td class="num"><?= e($numero($f['stock_minimo'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            </div>
            <?php if ($totalPorReponer > count($porReponer)): ?>
                <p class="sutil">Y <?= $totalPorReponer - count($porReponer) ?> más.</p>
            <?php endif; ?>
            <?php if ($veVencimientos): ?>
                <p class="sutil"><a href="/?ruta=reponer">Ver cuánto pedir de cada uno</a></p>
            <?php else: ?>
                <p class="sutil">Avísale al almacén. <a href="/?ruta=productos">Ver el catálogo</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($veVencimientos): ?>
    <section class="panel">
        <div class="panel-cabeza">
            <h2>Vencen esta semana</h2>
            <a class="enlace-sutil" href="/?ruta=vencimientos">Ver todo lo que está por vencer</a>
        </div>
        <?php if ($porVencer === []): ?>
            <div class="aviso ok">Nada vence en los próximos 7 días.</div>
        <?php else: ?>
            <ul class="lista-vencimientos">
                <?php foreach (array_slice($porVencer, 0, 6) as $f): ?>
                    <?php $claseVence = $f['vencido'] ? 'error' : 'reponer'; ?>
                    <li><span class="etiqueta <?= e($claseVence) ?>"><?= e(Vencimiento::describir($f['dias'])) ?></span> <?= e($f['nombre']) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($porVencer) > 6): ?><p class="sutil">Y <?= (int) (count($porVencer) - 6) ?> lotes más.</p><?php endif; ?>
        <?php endif; ?>
    </section>
    <?php elseif ($perfil === 'cajero'): ?>
    <section class="panel">
        <div class="panel-cabeza"><h2>Cajas abiertas</h2></div>
        <?php if ($turnos === []): ?>
            <p class="sutil">No hay turnos abiertos en esta tienda ahora mismo.</p>
        <?php else: ?>
            <ul class="lista-cajas">
                <?php foreach ($turnos as $t): ?>
                    <li><span class="etiqueta ok">Caja <?= (int) $t['numero_caja'] ?></span> <?= e($t['cajero']) ?> <small class="sutil">desde <?= e(fecha($t['fecha_hora_apertura'])) ?></small></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>

<?php if ($perfil === 'almacen'): ?>
<section class="panel">
    <div class="panel-cabeza">
        <h2>Pedidos por llegar</h2>
        <a class="enlace-sutil" href="/?ruta=ordenes">Órdenes de compra</a>
    </div>
    <?php if ($ordenesAbiertas === []): ?>
        <p class="sutil">No hay órdenes de compra abiertas. Si falta algo, <a href="/?ruta=reponer">arma el pedido desde Reponer</a>.</p>
    <?php else: ?>
        <div class="tabla">
        <table>
            <tr><th scope="col">Orden</th><th scope="col">Proveedor</th><th scope="col">Entrega</th><th scope="col">Estado</th><th scope="col" class="num">Total</th></tr>
            <?php foreach ($ordenesAbiertas as $o): ?>
                <tr>
                    <td><a class="fila-enlace" href="/?ruta=ordenes#oc-<?= $o->getIdOrdenCompra() ?>">N.° <?= $o->getIdOrdenCompra() ?></a></td>
                    <td><?= e($proveedores[$o->getIdProveedor()] ?? '-') ?></td>
                    <td><?= e($o->getFechaEntrega() !== null ? fecha($o->getFechaEntrega()) : 'Sin fecha') ?></td>
                    <td><span class="etiqueta <?= $o->getEstado() === 'PARCIAL' ? 'reponer' : 'ok' ?>"><?= e($o->getEstado()) ?></span></td>
                    <td class="num"><?= e(soles($o->getTotal())) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        </div>
        <?php if ($totalOrdenes > count($ordenesAbiertas)): ?><p class="sutil">Y <?= (int) ($totalOrdenes - count($ordenesAbiertas)) ?> más.</p><?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($perfil === 'admin'): ?>
<section class="panel">
    <div class="panel-cabeza"><h2>Cajas abiertas</h2><a class="enlace-sutil" href="/?ruta=caja">Arqueo</a></div>
    <?php if ($turnos === []): ?>
        <p class="sutil">No hay turnos abiertos en esta tienda ahora mismo.</p>
    <?php else: ?>
        <div class="tabla">
        <table>
            <tr><th scope="col">Caja</th><th scope="col">Cajero</th><th scope="col">Desde</th><th scope="col" class="num">Inicial</th><th scope="col" class="num">Ventas</th><th scope="col" class="num">Vendido</th><th scope="col" class="num">Efectivo cobrado</th></tr>
            <?php foreach ($turnos as $t): ?>
                <tr>
                    <td><?= (int) $t['numero_caja'] ?></td>
                    <td><?= e($t['cajero']) ?></td>
                    <td><?= e(fecha($t['fecha_hora_apertura'])) ?></td>
                    <td class="num"><?= e(soles((float) $t['monto_inicial'])) ?></td>
                    <td class="num"><?= (int) $t['ventas'] ?></td>
                    <td class="num"><?= e(soles((float) $t['total'])) ?></td>
                    <td class="num"><?= e(soles((float) $t['efectivo'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($perfil !== 'cajero'): ?>
<section class="panel">
    <div class="panel-cabeza"><h2>Últimos movimientos de stock</h2><a class="enlace-sutil" href="/?ruta=kardex">Ver el kardex completo</a></div>
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
                    <td><?= e(fecha($m->getFechaHora())) ?></td>
                    <td><?= e($producto?->getNombreProducto() ?? '-') ?></td>
                    <td><span class="etiqueta <?= $tipo?->esEntrada() ? 'ok' : 'reponer' ?>"><?= e($tipo?->getNombreMovimiento() ?? '-') ?></span></td>
                    <td class="num"><?= $tipo?->esEntrada() ? '+' : '−' ?><?= e(number_format($m->getCantidad(), $decimales)) ?></td>
                    <td class="num"><?= e(number_format($m->getStockNuevo(), $decimales)) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
