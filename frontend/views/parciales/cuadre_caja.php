<?php
declare(strict_types=1);
/**
 * V: el cuadre de caja como una cuenta, en el orden en que entra y sale el efectivo de la gaveta:
 *   fondo inicial + ventas en efectivo − devoluciones + ingresos − retiros = efectivo esperado
 * y, si ya se contó, el efectivo contado y la diferencia (contado − esperado). Lo usan el cierre y su comprobante.
 * @var array{fondo: float, efectivo: float, devoluciones: float, ingresos: float, retiros: float, esperado: float} $cuadre
 * @var ?float $cuadreContado  null = todavía no se contó
 */
$cuadreContado ??= null;
$filasCuadre = [
    ['', 'Fondo inicial', 'contado al abrir el turno', $cuadre['fondo']],
    ['+', 'Ventas en efectivo', 'lo cobrado, sin el vuelto', $cuadre['efectivo']],
    ['−', 'Devoluciones', 'efectivo devuelto a clientes', $cuadre['devoluciones']],
    ['+', 'Ingresos de caja', 'sencillo y otros ingresos', $cuadre['ingresos']],
    ['−', 'Retiros de caja', 'al depósito y gastos', $cuadre['retiros']],
];
// La cuenta se rehace aqui con lo que se muestra: si no da lo guardado (algo cambio despues del cierre), se ve el ajuste
$sumaCuadre = Dinero::centimos($cuadre['fondo']) + Dinero::centimos($cuadre['efectivo']) - Dinero::centimos($cuadre['devoluciones'])
    + Dinero::centimos($cuadre['ingresos']) - Dinero::centimos($cuadre['retiros']);
$ajusteCuadre = Dinero::centimos($cuadre['esperado']) - $sumaCuadre;
$difCuadre = $cuadreContado === null ? null : Dinero::centimos($cuadreContado) - Dinero::centimos($cuadre['esperado']);
$claseDifCuadre = $difCuadre === null ? '' : ($difCuadre === 0 ? 'cuadra' : ($difCuadre < 0 ? 'falta' : 'sobra'));
?>
<table class="cuadre">
    <caption class="visualmente-oculto">Cuadre del efectivo de la gaveta</caption>
    <colgroup><col class="cuadre-col-signo"><col><col class="cuadre-col-monto"></colgroup>
    <tbody>
        <?php foreach ($filasCuadre as [$signo, $concepto, $nota, $monto]): ?>
            <tr<?= Dinero::centimos($monto) === 0 && $signo !== '' ? ' class="en-cero"' : '' ?>>
                <td class="cuadre-signo" aria-hidden="true"><?= e($signo) ?></td>
                <th scope="row"><?= e($concepto) ?> <small><?= e($nota) ?></small></th>
                <td class="num"><?= e(soles($monto)) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($ajusteCuadre !== 0): ?>
            <tr>
                <td class="cuadre-signo" aria-hidden="true"><?= $ajusteCuadre > 0 ? '+' : '−' ?></td>
                <th scope="row">Ajuste posterior al cierre <small>lo esperado quedó guardado al cerrar</small></th>
                <td class="num"><?= e(soles(abs($ajusteCuadre) / 100)) ?></td>
            </tr>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr class="cuadre-esperado">
            <td class="cuadre-signo" aria-hidden="true">=</td>
            <th scope="row">Efectivo esperado en la gaveta</th>
            <td class="num"><?= e(soles($cuadre['esperado'])) ?></td>
        </tr>
        <?php if ($cuadreContado !== null): ?>
            <tr>
                <td class="cuadre-signo" aria-hidden="true"></td>
                <th scope="row">Efectivo contado</th>
                <td class="num"><?= e(soles($cuadreContado)) ?></td>
            </tr>
            <tr class="cuadre-diferencia <?= e($claseDifCuadre) ?>">
                <td class="cuadre-signo" aria-hidden="true"></td>
                <th scope="row"><?= e($difCuadre === 0 ? 'Diferencia: cuadra' : ($difCuadre < 0 ? 'Diferencia: faltante' : 'Diferencia: sobrante')) ?> <small>contado − esperado</small></th>
                <td class="num"><?= e(($difCuadre > 0 ? '+ ' : ($difCuadre < 0 ? '− ' : '')) . soles(abs((int) $difCuadre) / 100)) ?></td>
            </tr>
        <?php endif; ?>
    </tfoot>
</table>
<?php unset($cuadreContado, $filasCuadre, $sumaCuadre, $ajusteCuadre, $difCuadre, $claseDifCuadre); ?>
