<?php
declare(strict_types=1);
/**
 * V: turno del cajero y arqueo de la tienda.
 * @var Usuario $usuario
 * @var bool $esCajero
 * @var bool $esAdministrador
 * @var Tienda $tienda
 * @var Caja[] $cajasTienda
 * @var array<int, int> $numeroCaja  id_caja => numero_caja
 * @var ?CajaTurno $miTurno
 * @var array<int, array{turno: CajaTurno, efectivoCobrado: float, esperado: float, diferencia: ?float}> $arqueo
 * @var array<int, string> $nombresEmpleado
 * @var ?string $error
 */
$titulo = 'Caja';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Caja · <?= e($tienda->getNombreTienda()) ?></h1>
<?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>

<?php if ($esCajero): ?>
    <div class="panel">
        <?php if ($miTurno === null): ?>
            <h2>Abrir turno</h2>
            <form class="formulario" method="post" action="/?ruta=turno_abrir">
                <?= campoCsrf($csrf) ?>
                <label for="caja">Caja</label>
                <select id="caja" name="caja">
                    <?php foreach ($cajasTienda as $c): ?>
                        <option value="<?= $c->getIdCaja() ?>">Caja <?= $c->getNumeroCaja() ?> (<?= e($c->getEstado()) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <label for="monto_inicial">Monto inicial en efectivo</label>
                <input id="monto_inicial" name="monto_inicial" inputmode="decimal" required value="100.00">
                <p><button type="submit">Abrir turno</button></p>
            </form>
        <?php else: ?>
            <h2>Tu turno <?= $miTurno->getIdCajaTurno() ?> está abierto</h2>
            <p>Caja <?= $numeroCaja[$miTurno->getIdCaja()] ?? '-' ?> · desde <?= e($miTurno->getFechaHoraApertura()) ?>
               · inicial <?= e(soles($miTurno->getMontoInicial())) ?> · <a href="/?ruta=venta_nueva">Nueva venta</a></p>
            <form class="formulario" method="post" action="/?ruta=turno_cerrar">
                <?= campoCsrf($csrf) ?>
                <label for="monto_cierre">Efectivo contado al cerrar</label>
                <input id="monto_cierre" name="monto_cierre" inputmode="decimal" required>
                <p><button class="peligro" type="submit">Cerrar turno</button></p>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<h2><?= $esAdministrador ? 'Arqueo de los turnos de la tienda' : 'Tus turnos' ?></h2>
<p>Esperado = monto inicial + efectivo válido cobrado. Diferencia = monto de cierre − esperado.</p>
<div class="tabla">
<table>
    <tr><th>Turno</th><th>Caja</th><th>Cajero</th><th>Apertura</th><th>Cierre</th><th>Inicial</th><th>Efectivo cobrado</th><th>Esperado</th><th>Cierre</th><th>Diferencia</th></tr>
    <?php foreach ($arqueo as $fila): ?>
        <?php $t = $fila['turno']; $d = $fila['diferencia']; ?>
        <tr>
            <td><?= $t->getIdCajaTurno() ?> <span class="etiqueta <?= $t->estaAbierto() ? 'ok' : 'anulada' ?>"><?= e($t->getEstado()) ?></span></td>
            <td><?= $numeroCaja[$t->getIdCaja()] ?? '-' ?></td>
            <td><?= e($nombresEmpleado[$t->getIdEmpleado()] ?? '-') ?></td>
            <td><?= e($t->getFechaHoraApertura()) ?></td>
            <td><?= e($t->getFechaHoraCierre() ?? '—') ?></td>
            <td class="num"><?= e(soles($t->getMontoInicial())) ?></td>
            <td class="num"><?= e(soles($fila['efectivoCobrado'])) ?></td>
            <td class="num"><?= e(soles($fila['esperado'])) ?></td>
            <td class="num"><?= $t->getMontoCierre() === null ? '—' : e(soles($t->getMontoCierre())) ?></td>
            <td class="num">
                <?php if ($d === null): ?>—
                <?php else: ?><span class="etiqueta <?= Dinero::centimos($d) === 0 ? 'ok' : 'reponer' ?>"><?= e(soles($d)) ?></span><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
