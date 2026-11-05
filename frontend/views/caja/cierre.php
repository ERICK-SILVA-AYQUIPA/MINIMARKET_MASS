<?php
declare(strict_types=1);
/**
 * V: el comprobante de cierre de un turno. Todo lo calcula el sistema: lo vendido por medio de pago,
 * el efectivo que debia haber, lo que conto el cajero y la diferencia. Se imprime en 80 mm (Ctrl + P).
 * @var Tienda $tienda
 * @var array{turno: CajaTurno, efectivoCobrado: float, devoluciones: float, ingresos: float, retiros: float, esperado: float, diferencia: ?float} $fila
 * @var CajaTurno $turno
 * @var array{ventas: int, anuladas: int, efectivo: float, tarjeta: float, debito: float, credito: float, digital: float} $ventas
 * @var ?array{fecha_hora: string, tipo: string, contado: float, esperado: float, observacion: ?string, id_empleado: int, detalle: array<string, int>} $conteo
 * @var string $numeroCaja
 * @var string $cajero
 * @var bool $esSuyo  el turno es del usuario actual (puede abrir otro desde aqui)
 */
$idTurno = $turno->getIdCajaTurno();
$titulo = 'Cierre del turno ' . $idTurno;
require __DIR__ . '/../layout_inicio.php';

$diferencia = Dinero::centimos($fila['diferencia'] ?? 0.0);
$resultado = $diferencia === 0 ? 'cuadra' : ($diferencia < 0 ? 'falta' : 'sobra');
$titular = match ($resultado) {
    'cuadra' => 'Caja cuadrada',
    'falta'  => 'Faltante de ' . soles(abs((float) $fila['diferencia'])),
    default  => 'Sobrante de ' . soles((float) $fila['diferencia']),
};
$estadoTexto = match ($turno->getEstado()) {
    'OBSERVADO' => 'La diferencia pasa la tolerancia de la tienda: el administrador la revisará antes de que abras otro turno.',
    'RECONTEO'  => 'El administrador pidió que vuelvas a contar la gaveta.',
    default     => $diferencia === 0 ? 'Lo contado es igual a lo que calculó el sistema. El turno quedó cerrado.' : 'La diferencia está dentro de la tolerancia de la tienda. El turno quedó cerrado.',
};
// Lo esperado quedo guardado al cerrar; si la cuenta de hoy no da lo mismo, se muestra la diferencia como ajuste
$ajuste = Dinero::centimos($fila['esperado']) - (Dinero::centimos($turno->getMontoInicial()) + Dinero::centimos($fila['efectivoCobrado'])
    - Dinero::centimos($fila['devoluciones']) + Dinero::centimos($fila['ingresos']) - Dinero::centimos($fila['retiros']));
$totalVendido = round($ventas['efectivo'] + $ventas['tarjeta'] + $ventas['digital'], 2);
// Cada bloque del ticket: [concepto, valor, en negrita]
$bloques = [
    [['Cajero', $cajero, false], ['Apertura', fecha($turno->getFechaHoraApertura()), false],
     ['Cierre', fecha((string) $turno->getFechaHoraCierre()), false], ['Ventas emitidas', (string) $ventas['ventas'], false],
     ...($ventas['anuladas'] > 0 ? [['Ventas anuladas', (string) $ventas['anuladas'], false]] : [])],
    [['Efectivo', soles($ventas['efectivo']), false], ['Tarjeta de débito', soles($ventas['debito']), false],
     ['Tarjeta de crédito', soles($ventas['credito']), false],
     ['Billetera Sip', soles($ventas['digital']), false], ['TOTAL VENDIDO', soles($totalVendido), true]],
    array_values(array_filter([
        ['Fondo inicial', soles($turno->getMontoInicial()), false],
        ['+ Ventas en efectivo', soles($fila['efectivoCobrado']), false],
        $fila['devoluciones'] > 0 ? ['− Devoluciones', soles($fila['devoluciones']), false] : null,
        $fila['ingresos'] > 0 ? ['+ Ingresos de caja', soles($fila['ingresos']), false] : null,
        $fila['retiros'] > 0 ? ['− Retiros de caja', soles($fila['retiros']), false] : null,
        $ajuste !== 0 ? [($ajuste > 0 ? '+' : '−') . ' Ajuste posterior', soles(abs($ajuste) / 100), false] : null,
        ['= Efectivo esperado', soles($fila['esperado']), true],
        ['Efectivo contado', soles((float) $turno->getMontoCierre()), true],
        ['DIFERENCIA', ($diferencia > 0 ? '+' : ($diferencia < 0 ? '−' : '')) . soles(abs((float) ($fila['diferencia'] ?? 0.0))), true],
    ])),
];
?>
<a class="volver" href="/?ruta=caja"><?= icono('volver') ?>Volver a la caja</a>
<div class="cabecera">
    <div>
        <h1>Cierre del turno <?= (int) $idTurno ?></h1>
        <p class="sutil">Caja <?= e($numeroCaja) ?> · <?= e($cajero) ?> · <?= e(fecha((string) $turno->getFechaHoraCierre())) ?></p>
    </div>
    <div class="acciones">
        <button type="button" class="secundario" data-imprimir hidden><?= icono('imprimir') ?>Imprimir cierre</button>
        <?php if ($esSuyo && $turno->getEstado() === 'CERRADO'): ?>
            <a class="boton acento" href="/?ruta=caja"><?= icono('caja') ?>Abrir nuevo turno</a>
        <?php endif; ?>
    </div>
</div>

<section class="cierre-resultado <?= e($resultado) ?>" aria-label="Resultado del cierre">
    <span class="cierre-resultado-icono"><?php if ($resultado === 'cuadra'): ?><?= icono('ok') ?><?php else: ?><?= icono('alerta') ?><?php endif; ?></span>
    <div>
        <strong><?= e($titular) ?></strong>
        <p><?= e($estadoTexto) ?></p>
    </div>
    <span class="etiqueta <?= e($turno->getEstado() === 'CERRADO' ? 'ok' : ($turno->getEstado() === 'OBSERVADO' ? 'alerta' : 'info')) ?>"><?= e($turno->getEstado()) ?></span>
</section>

<div class="cierre-rejilla">
    <article class="ticket-cierre" aria-label="Comprobante de cierre">
        <header>
            <strong><?= e(mb_strtoupper($tienda->getNombreTienda())) ?></strong>
            <span>CIERRE DE CAJA</span>
            <span>Turno <?= (int) $idTurno ?> · Caja <?= e($numeroCaja) ?></span>
        </header>
        <?php foreach ($bloques as $bloque): ?>
            <div class="ticket-bloque">
                <?php foreach ($bloque as [$concepto, $valor, $fuerte]): ?>
                    <div class="ticket-linea<?= $fuerte ? ' fuerte' : '' ?>"><span><?= e($concepto) ?></span><span><?= e($valor) ?></span></div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <p class="ticket-sello <?= e($resultado) ?>"><?= e(mb_strtoupper($titular)) ?></p>
        <?php if ($conteo !== null && $conteo['detalle'] !== []): ?>
            <div class="ticket-bloque">
                <span class="ticket-subtitulo">Billetes y monedas contados</span>
                <?php foreach ($conteo['detalle'] as $denominacion => $cantidad): ?>
                    <?php $valorDen = (float) $denominacion; ?>
                    <div class="ticket-linea"><span><?= e($cantidad . ' × ' . ($valorDen >= 1 ? 'S/ ' . (int) $valorDen : (int) round($valorDen * 100) . ' cént.')) ?></span><span><?= e(soles($cantidad * $valorDen)) ?></span></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($conteo !== null && $conteo['observacion'] !== null && $conteo['observacion'] !== ''): ?>
            <p class="ticket-nota">Obs.: <?= e($conteo['observacion']) ?></p>
        <?php endif; ?>
        <footer>Tarjeta y billetera se cuadran con sus vouchers: no entran al arqueo.</footer>
    </article>

    <aside class="panel cierre-como">
        <h2>Cuadre de caja</h2>
        <?php
        $cuadre = [
            'fondo' => $turno->getMontoInicial(), 'efectivo' => $fila['efectivoCobrado'], 'devoluciones' => $fila['devoluciones'],
            'ingresos' => $fila['ingresos'], 'retiros' => $fila['retiros'], 'esperado' => $fila['esperado'],
        ];
        $cuadreContado = (float) $turno->getMontoCierre();
        require __DIR__ . '/../parciales/cuadre_caja.php';
        ?>
        <h3>Lo que hizo el sistema</h3>
        <ol class="cierre-como-lista">
            <li><strong>Sumó las ventas por medio de pago.</strong> <?= (int) $ventas['ventas'] ?> <?= $ventas['ventas'] === 1 ? 'venta' : 'ventas' ?>, <?= e(soles($totalVendido)) ?> en total<?= e($ventas['anuladas'] > 0 ? '; ' . $ventas['anuladas'] . ' anulada' . ($ventas['anuladas'] === 1 ? '' : 's') . ' no cuenta' . ($ventas['anuladas'] === 1 ? '' : 'n') : '') ?>. Tarjeta y Sip van aparte: se cuadran con sus vouchers.</li>
            <li><strong>Calculó el efectivo que debía haber</strong> con la cuenta de arriba: <?= e(soles($fila['esperado'])) ?>.</li>
            <li><strong>Lo comparó con el conteo.</strong> Se contaron <?= e(soles((float) $turno->getMontoCierre())) ?>: <?= e($resultado === 'cuadra' ? 'la caja cuadra' : ($resultado === 'falta' ? 'faltan ' : 'sobran ') . soles(abs((float) $fila['diferencia']))) ?>.</li>
            <li><strong>Cerró el turno.</strong> <?= e($turno->getEstado() === 'OBSERVADO' ? 'Pasa la tolerancia de la tienda: quedó en revisión del administrador.' : ($turno->getEstado() === 'RECONTEO' ? 'Falta el nuevo conteo.' : 'Ya nadie puede vender con él.')) ?></li>
        </ol>
        <p class="sutil">Para guardarlo en PDF: <strong>Ctrl + P</strong> y elige «Guardar como PDF».</p>
    </aside>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
