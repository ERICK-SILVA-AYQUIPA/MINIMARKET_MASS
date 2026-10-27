<?php
declare(strict_types=1);
/**
 * V: turno del cajero y arqueo de la tienda. Cada turno abre su detalle en un panel lateral (#turno-ID).
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
<div class="cabecera">
    <div>
        <h1>Caja · <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Esperado = monto inicial + efectivo válido cobrado. Diferencia = monto de cierre − esperado.</p>
    </div>
    <?php if ($miTurno !== null): ?>
        <div class="acciones"><a class="boton acento grande" href="/?ruta=venta_nueva"><?= icono('venta') ?>Nueva venta</a></div>
    <?php endif; ?>
</div>
<?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>

<?php if ($esCajero): ?>
    <?php if ($miTurno === null): ?>
        <section class="panel destacado-borde">
            <h2>Abrir turno</h2>
            <p class="sutil">Para vender necesitas un turno abierto. Cuenta el sencillo con el que empiezas.</p>
            <form class="formulario" method="post" action="/?ruta=turno_abrir">
                <?= campoCsrf($csrf) ?>
                <div class="campos">
                    <div>
                        <label for="caja">Caja</label>
                        <select id="caja" name="caja">
                            <?php foreach ($cajasTienda as $c): ?>
                                <option value="<?= $c->getIdCaja() ?>">Caja <?= $c->getNumeroCaja() ?> (<?= e($c->getEstado()) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="monto_inicial">Monto inicial en efectivo (S/)</label>
                        <input id="monto_inicial" name="monto_inicial" inputmode="decimal" required value="100.00">
                    </div>
                </div>
                <div class="botones-formulario"><button class="acento grande" type="submit"><?= icono('caja') ?>Abrir turno</button></div>
            </form>
        </section>
    <?php else: ?>
        <section class="tarjetas" aria-label="Tu turno">
            <div><span>Turno abierto</span><strong>N.° <?= $miTurno->getIdCajaTurno() ?></strong><small>Caja <?= $numeroCaja[$miTurno->getIdCaja()] ?? '-' ?></small></div>
            <div><span>Desde</span><strong class="fecha"><?= e(substr($miTurno->getFechaHoraApertura(), 11, 5)) ?></strong><small><?= e(substr($miTurno->getFechaHoraApertura(), 0, 10)) ?></small></div>
            <div><span>Monto inicial</span><strong><?= e(soles($miTurno->getMontoInicial())) ?></strong><small>en efectivo</small></div>
        </section>
        <section class="panel">
            <h2>Cerrar turno</h2>
            <form class="formulario" method="post" action="/?ruta=turno_cerrar">
                <?= campoCsrf($csrf) ?>
                <label for="monto_cierre">Efectivo contado al cerrar (S/)</label>
                <span class="ayuda">Cuenta billetes y monedas de la gaveta. El sistema calcula la diferencia con lo esperado.</span>
                <input id="monto_cierre" name="monto_cierre" inputmode="decimal" required>
                <div class="botones-formulario"><button class="peligro" type="submit">Cerrar turno</button></div>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>

<h2><?= $esAdministrador ? 'Arqueo de los turnos de la tienda' : 'Tus turnos' ?></h2>
<?php if ($arqueo === []): ?>
    <?php
    $vacioTitulo = 'Todavía no hay turnos';
    $vacioTexto = 'Cada vez que un cajero abre y cierra su caja, el turno aparece aquí con su arqueo.';
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">Turno</th><th scope="col">Caja</th><th scope="col">Cajero</th><th scope="col">Apertura</th><th scope="col" class="num">Esperado</th><th scope="col" class="num">Cierre</th><th scope="col" class="num">Diferencia</th><th scope="col"><span class="visualmente-oculto">Detalle</span></th></tr>
    </thead>
    <tbody>
    <?php foreach ($arqueo as $fila): ?>
        <?php $t = $fila['turno']; $d = $fila['diferencia']; ?>
        <tr id="fila-turno-<?= $t->getIdCajaTurno() ?>">
            <td><?= $t->getIdCajaTurno() ?> <span class="etiqueta <?= $t->estaAbierto() ? 'ok' : 'neutra' ?>"><?= e($t->getEstado()) ?></span></td>
            <td><?= $numeroCaja[$t->getIdCaja()] ?? '-' ?></td>
            <td><?= e($nombresEmpleado[$t->getIdEmpleado()] ?? '-') ?></td>
            <td class="fecha"><?= e($t->getFechaHoraApertura()) ?></td>
            <td class="num"><?= e(soles($fila['esperado'])) ?></td>
            <td class="num"><?= $t->getMontoCierre() === null ? '—' : e(soles($t->getMontoCierre())) ?></td>
            <td class="num">
                <?php if ($d === null): ?>—
                <?php else: ?><span class="etiqueta <?= Dinero::centimos($d) === 0 ? 'ok' : 'reponer' ?>"><?= e(soles($d)) ?></span><?php endif; ?>
            </td>
            <td><a class="boton secundario chico" href="#turno-<?= $t->getIdCajaTurno() ?>"><?= icono('ver') ?>Ver</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php foreach ($arqueo as $fila): ?>
    <?php
    $t = $fila['turno'];
    $d = $fila['diferencia'];
    $drawerId = 'turno-' . $t->getIdCajaTurno();
    $drawerTitulo = 'Turno ' . $t->getIdCajaTurno();
    $drawerSubtitulo = 'Caja ' . ($numeroCaja[$t->getIdCaja()] ?? '-') . ' · ' . ($nombresEmpleado[$t->getIdEmpleado()] ?? '-');
    $drawerCerrar = '#fila-turno-' . $t->getIdCajaTurno();
    require __DIR__ . '/../parciales/drawer_abrir.php';
    ?>
    <dl class="datos">
        <dt>Estado</dt><dd><span class="etiqueta <?= $t->estaAbierto() ? 'ok' : 'neutra' ?>"><?= e($t->getEstado()) ?></span></dd>
        <dt>Apertura</dt><dd><?= e($t->getFechaHoraApertura()) ?></dd>
        <dt>Cierre</dt><dd><?= e($t->getFechaHoraCierre() ?? 'Sigue abierto') ?></dd>
        <dt>Monto inicial</dt><dd><?= e(soles($t->getMontoInicial())) ?></dd>
        <dt>+ Efectivo cobrado</dt><dd><?= e(soles($fila['efectivoCobrado'])) ?></dd>
        <dt>= Esperado en gaveta</dt><dd><?= e(soles($fila['esperado'])) ?></dd>
        <dt>Contado al cerrar</dt><dd><?= $t->getMontoCierre() === null ? '—' : e(soles($t->getMontoCierre())) ?></dd>
        <dt>Diferencia</dt>
        <dd>
            <?php if ($d === null): ?>Se calcula al cerrar el turno
            <?php elseif (Dinero::centimos($d) === 0): ?><span class="etiqueta ok">Cuadra exacto</span>
            <?php else: ?><span class="etiqueta reponer"><?= e(soles($d)) ?></span> <?= $d > 0 ? 'sobrante' : 'faltante' ?>
            <?php endif; ?>
        </dd>
    </dl>
    <p class="sutil bloque">Solo cuenta el efectivo VALIDO: los pagos con tarjeta o billetera digital no entran a la gaveta y los de ventas anuladas no suman.</p>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
