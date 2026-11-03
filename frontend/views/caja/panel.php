<?php
declare(strict_types=1);
/**
 * V: la caja. El cajero abre su turno contando el fondo, vende, pausa, hace retiros e ingresos y cierra contando.
 * El administrador ve las cajas en vivo, hace arqueos sorpresa, revisa los cierres con diferencia y fija las reglas.
 * Cada turno abre su detalle en un panel lateral (#turno-ID).
 * @var Usuario $usuario
 * @var bool $esCajero
 * @var bool $esAdministrador
 * @var bool $puedeCambiarReglas
 * @var Tienda $tienda
 * @var Caja[] $cajasTienda
 * @var array<int, int> $numeroCaja  id_caja => numero_caja
 * @var array<int, float> $fondos    id_caja => fondo fijo
 * @var array{tolerancia: float, tope: float, ciego: bool, horas: int} $regla
 * @var ?CajaTurno $miTurno
 * @var ?CajaTurno $miPendiente      cierre del cajero en OBSERVADO o RECONTEO
 * @var array<int, array{turno: CajaTurno, efectivoCobrado: float, devoluciones: float, ingresos: float, retiros: float, esperado: float, diferencia: ?float}> $arqueo
 * @var ?array $miArqueo
 * @var array<int, array> $turnoDeCaja  id_caja => fila del arqueo del turno abierto
 * @var array<int, array> $porRevisar   filas de turnos OBSERVADOS
 * @var array<int, array<int, array{fecha_hora: string, tipo: string, contado: float, esperado: float, observacion: ?string, id_empleado: int, detalle: array<string, int>}>> $arqueosPorTurno
 * @var array<int, array<int, array{fecha_hora: string, tipo: string, monto: float, motivo: string}>> $movimientos
 * @var array<int, string> $nombresEmpleado
 * @var Closure $horasAbierto
 * @var ?string $error
 */
$titulo = 'Caja';
require __DIR__ . '/../layout_inicio.php';

$etiquetaEstado = fn(string $estado): string => match ($estado) {
    'ABIERTO' => 'ok', 'OBSERVADO' => 'alerta', 'RECONTEO' => 'info', default => 'neutra',
};
$textoDiferencia = function (?float $d): string {
    if ($d === null) {
        return '—';
    }
    $c = Dinero::centimos($d);
    return $c === 0 ? 'Cuadra' : ($c < 0 ? 'Faltan ' : 'Sobran ') . soles(abs($d));
};
$claseDiferencia = function (?float $d) use ($regla): string {
    if ($d === null) {
        return 'neutra';
    }
    $c = abs(Dinero::centimos($d));
    return $c === 0 ? 'ok' : ($c <= Dinero::centimos($regla['tolerancia']) ? 'info' : 'reponer');
};
$tipoArqueo = ['APERTURA' => 'Apertura', 'PARCIAL' => 'Arqueo sorpresa', 'CIERRE' => 'Cierre'];
?>
<div class="cabecera">
    <div>
        <h1>Caja · <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil">
            Tolerancia <?= e(soles($regla['tolerancia'])) ?> · tope de gaveta <?= e(soles($regla['tope'])) ?>
            · cierre <?= $regla['ciego'] ? 'ciego' : 'con el esperado a la vista' ?> · turnos de hasta <?= (int) $regla['horas'] ?> h
        </p>
    </div>
    <div class="acciones">
        <?php if ($miTurno !== null && !$miTurno->estaEnPausa()): ?>
            <a class="boton acento grande" href="/?ruta=venta_nueva"><?= icono('venta') ?>Nueva venta</a>
        <?php endif; ?>
        <?php if ($puedeCambiarReglas): ?>
            <a class="boton secundario" href="#reglas-caja"><?= icono('editar') ?>Reglas y fondos</a>
        <?php endif; ?>
    </div>
</div>
<?php
$guiaClave = 'caja';
$guiaTitulo = 'Cómo funciona la caja';
$guiaPasos = $esCajero ? [
    'Elige una caja libre y cuenta el sobre del fondo por billetes y monedas. Si no cuadra con el fondo fijo, escribe por qué.',
    'Si te alejas, pon la caja en pausa: nadie puede vender con tu turno hasta que la reanudes.',
    'Anota cada retiro al depósito o ingreso de sencillo, así el cierre cuadra.',
    'Al terminar cuenta todo el efectivo. Si la diferencia pasa la tolerancia, el administrador la revisa antes de tu siguiente turno.',
] : [
    'Arriba ves cada caja en vivo: quién la tiene, desde cuándo y cuánto debería haber en la gaveta.',
    'Haz arqueos sorpresa contando la gaveta de un turno abierto: queda guardado con su diferencia.',
    'Los cierres que pasan la tolerancia esperan tu revisión: apruébalos con un comentario o pide que el cajero vuelva a contar.',
    'En Reglas y fondos defines la tolerancia, el tope de la gaveta, el cierre ciego y el fondo fijo de cada caja.',
];
require __DIR__ . '/../parciales/guia.php';
?>
<?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>

<?php if ($esCajero): ?>
    <?php if ($miPendiente !== null && $miPendiente->getEstado() === 'RECONTEO'): ?>
        <section class="panel destacado-borde">
            <h2>Vuelve a contar el turno <?= $miPendiente->getIdCajaTurno() ?></h2>
            <div class="aviso info"><span>El administrador pidió un nuevo conteo<?php if ($miPendiente->getObservacion() !== null): ?>: «<?= e($miPendiente->getObservacion()) ?>»<?php endif; ?>. Cuenta otra vez todo el efectivo de la gaveta.</span></div>
            <form class="formulario" method="post" action="/?ruta=turno_recontar">
                <?= campoCsrf($csrf) ?>
                <?php
                $conteoId = 'reconteo';
                $conteoCampoTotal = 'monto_cierre';
                $conteoValor = '';
                $conteoEsperado = $regla['ciego'] ? null : $miPendiente->getMontoEsperado();
                $conteoRotulo = 'lo esperado';
                require __DIR__ . '/../parciales/conteo_efectivo.php';
                ?>
                <label for="reconteo-observacion">Observación <span class="sutil">(opcional)</span></label>
                <input id="reconteo-observacion" name="observacion" maxlength="150" placeholder="Ej.: encontré un billete de 10 debajo de la bandeja">
                <div class="botones-formulario"><button class="acento grande" type="submit"><?= icono('caja') ?>Guardar el nuevo conteo</button></div>
            </form>
        </section>
    <?php elseif ($miPendiente !== null): ?>
        <div class="aviso alerta" role="status"><span>Tu cierre del turno <?= $miPendiente->getIdCajaTurno() ?> tuvo una diferencia de
            <strong><?= e($textoDiferencia($miPendiente->getDiferencia())) ?></strong> y lo está revisando el administrador.
            Podrás abrir otro turno cuando lo apruebe o te pida volver a contar.</span></div>
    <?php elseif ($miTurno === null): ?>
        <section class="panel destacado-borde">
            <h2>Abrir mi turno</h2>
            <p class="sutil">Elige una caja libre y cuenta el sobre del fondo. Para vender necesitas un turno abierto.</p>
            <form class="formulario" method="post" action="/?ruta=turno_abrir" data-apertura>
                <?= campoCsrf($csrf) ?>
                <fieldset>
                    <legend>Caja</legend>
                    <div class="cajas-elegir">
                        <?php $primeraLibre = true; ?>
                        <?php foreach ($cajasTienda as $c): ?>
                            <?php
                            $ocupada = $turnoDeCaja[$c->getIdCaja()] ?? null;
                            $libre = $ocupada === null && $c->getEstado() === 'ACTIVA';
                            ?>
                            <label class="caja-opcion<?= $libre ? '' : ' no-disponible' ?>">
                                <input type="radio" name="caja" value="<?= $c->getIdCaja() ?>" data-fondo="<?= e(number_format($fondos[$c->getIdCaja()] ?? 0, 2, '.', '')) ?>"<?= $libre && $primeraLibre ? ' checked' : '' ?><?= $libre ? '' : ' disabled' ?>>
                                <strong>Caja <?= $c->getNumeroCaja() ?></strong>
                                <span class="sutil">
                                    <?php if ($ocupada !== null): ?>Ocupada por <?= e($nombresEmpleado[$ocupada['turno']->getIdEmpleado()] ?? '-') ?>
                                    <?php elseif (!$libre): ?>Inactiva
                                    <?php else: ?>Libre · fondo <?= e(soles($fondos[$c->getIdCaja()] ?? 0)) ?><?php endif; ?>
                                </span>
                            </label>
                            <?php if ($libre) { $primeraLibre = false; } ?>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php
                $primerFondo = 100.0;
                foreach ($cajasTienda as $c) {
                    if (!isset($turnoDeCaja[$c->getIdCaja()]) && $c->getEstado() === 'ACTIVA') {
                        $primerFondo = $fondos[$c->getIdCaja()] ?? 100.0;
                        break;
                    }
                }
                $conteoId = 'apertura';
                $conteoCampoTotal = 'monto_inicial';
                $conteoValor = number_format($primerFondo, 2, '.', '');
                $conteoEsperado = $primerFondo;
                $conteoRotulo = 'el fondo fijo';
                require __DIR__ . '/../parciales/conteo_efectivo.php';
                ?>
                <label for="apertura-observacion">Si no cuadra con el fondo fijo, ¿por qué?</label>
                <input id="apertura-observacion" name="observacion" maxlength="150" placeholder="Ej.: el sobre vino con S/ 2 menos en monedas">
                <div class="botones-formulario"><button class="acento grande" type="submit"><?= icono('caja') ?>Abrir mi turno</button></div>
            </form>
        </section>
    <?php else: ?>
        <?php
        $enGaveta = $miArqueo['esperado'] ?? $miTurno->getMontoInicial();
        $horas = $horasAbierto($miTurno);
        ?>
        <?php if ($miTurno->estaEnPausa()): ?>
            <section class="panel pausa-caja">
                <?= icono('caja') ?>
                <h2>Caja en pausa</h2>
                <p class="sutil">Desde las <?= e(substr((string) $miTurno->getEnPausaDesde(), 11, 5)) ?>. Nadie puede vender con tu turno hasta que la reanudes.</p>
                <form method="post" action="/?ruta=turno_pausa">
                    <?= campoCsrf($csrf) ?>
                    <input type="hidden" name="accion" value="reanudar">
                    <button class="acento grande" type="submit">Reanudar y seguir vendiendo</button>
                </form>
            </section>
        <?php endif; ?>
        <section class="tarjetas" aria-label="Tu turno">
            <div><span>Turno abierto</span><strong>N.° <?= $miTurno->getIdCajaTurno() ?></strong><small>Caja <?= $numeroCaja[$miTurno->getIdCaja()] ?? '-' ?></small></div>
            <div><span>Desde</span><strong class="fecha"><?= e(substr($miTurno->getFechaHoraApertura(), 11, 5)) ?></strong><small><?= e(sprintf('hace %d h %02d min', (int) $horas, (int) round(($horas - (int) $horas) * 60))) ?></small></div>
            <div><span>Fondo contado</span><strong><?= e(soles($miTurno->getMontoInicial())) ?></strong><small>al abrir</small></div>
            <?php if ($regla['ciego']): ?>
                <div><span>Efectivo esperado</span><strong>Oculto</strong><small>cierre ciego: cuenta sin mirar</small></div>
            <?php else: ?>
                <div><span>En gaveta ahora</span><strong><?= e(soles($enGaveta)) ?></strong><small>lo que deberías contar</small></div>
            <?php endif; ?>
        </section>
        <?php if (Dinero::centimos($enGaveta) > Dinero::centimos($regla['tope'])): ?>
            <div class="aviso alerta" role="status"><span>La gaveta pasó el tope de <?= e(soles($regla['tope'])) ?>: haz un retiro al depósito.</span></div>
        <?php endif; ?>
        <?php if ($horas > $regla['horas']): ?>
            <div class="aviso alerta" role="status"><span>Tu turno lleva más de <?= (int) $regla['horas'] ?> horas abierto. Ciérralo al terminar.</span></div>
        <?php endif; ?>
        <?php if (!$miTurno->estaEnPausa()): ?>
            <form class="acciones-turno" method="post" action="/?ruta=turno_pausa">
                <?= campoCsrf($csrf) ?>
                <input type="hidden" name="accion" value="pausar">
                <button class="secundario" type="submit">Pausar mi caja</button>
                <span class="sutil">Si te alejas: nadie podrá vender con tu turno.</span>
            </form>
        <?php endif; ?>
        <div class="rejilla-caja">
            <section class="panel">
                <h2>Retiro o ingreso de efectivo</h2>
                <p class="sutil">Anota cuando sacas billetes para el depósito o un pago menor, o cuando te traen sencillo.</p>
                <form class="formulario" method="post" action="/?ruta=caja_movimiento">
                    <?= campoCsrf($csrf) ?>
                    <div class="campos">
                        <div>
                            <label for="tipo_movimiento">Tipo</label>
                            <select id="tipo_movimiento" name="tipo">
                                <option value="RETIRO">Retiro (sale de la gaveta)</option>
                                <option value="INGRESO">Ingreso de sencillo</option>
                            </select>
                        </div>
                        <div>
                            <label for="monto_movimiento">Monto (S/)</label>
                            <input id="monto_movimiento" name="monto" inputmode="decimal" required>
                        </div>
                    </div>
                    <label for="motivo_movimiento">Motivo</label>
                    <input id="motivo_movimiento" name="motivo" maxlength="150" required placeholder="Ej.: depósito al banco, pago de bolsas">
                    <div class="botones-formulario"><button class="secundario" type="submit"><?= icono('caja') ?>Registrar</button></div>
                </form>
            </section>
            <section class="panel">
                <h2>Cerrar mi turno</h2>
                <p class="sutil"><?= $regla['ciego'] ? 'Cuenta todo el efectivo sin mirar lo esperado: el sistema calcula la diferencia al guardar.' : 'Cuenta todo el efectivo de la gaveta: el sistema compara con lo esperado.' ?></p>
                <form class="formulario" method="post" action="/?ruta=turno_cerrar" data-confirmar="¿Cerrar tu turno? Después ya no podrás vender con él." data-confirmar-boton="Sí, cerrar turno">
                    <?= campoCsrf($csrf) ?>
                    <?php
                    $conteoId = 'cierre';
                    $conteoCampoTotal = 'monto_cierre';
                    $conteoValor = '';
                    $conteoEsperado = $regla['ciego'] ? null : $enGaveta;
                    $conteoRotulo = 'lo esperado';
                    require __DIR__ . '/../parciales/conteo_efectivo.php';
                    ?>
                    <label for="cierre-observacion">Observación <span class="sutil">(opcional)</span></label>
                    <input id="cierre-observacion" name="observacion" maxlength="150" placeholder="Ej.: un cliente pagó con billete roto">
                    <p class="ayuda">Tarjeta y billetera digital no entran a la gaveta: confírmalas con los vouchers.</p>
                    <div class="botones-formulario"><button class="peligro" type="submit">Cerrar turno</button></div>
                </form>
            </section>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($esAdministrador): ?>
    <h2>Cajas ahora</h2>
    <div class="cajas-vivo">
        <?php foreach ($cajasTienda as $c): ?>
            <?php $fila = $turnoDeCaja[$c->getIdCaja()] ?? null; ?>
            <article class="panel caja-vivo<?= $fila === null ? ' libre' : '' ?>">
                <header>
                    <strong>Caja <?= $c->getNumeroCaja() ?></strong>
                    <?php if ($fila === null): ?>
                        <span class="etiqueta neutra"><?= $c->getEstado() === 'ACTIVA' ? 'Libre' : 'Inactiva' ?></span>
                    <?php elseif ($fila['turno']->estaEnPausa()): ?>
                        <span class="etiqueta alerta">En pausa</span>
                    <?php else: ?>
                        <span class="etiqueta ok">Vendiendo</span>
                    <?php endif; ?>
                </header>
                <?php if ($fila === null): ?>
                    <p class="sutil">Fondo fijo <?= e(soles($fondos[$c->getIdCaja()] ?? 0)) ?></p>
                <?php else: ?>
                    <?php $t = $fila['turno']; $h = $horasAbierto($t); ?>
                    <p><?= e($nombresEmpleado[$t->getIdEmpleado()] ?? '-') ?> <span class="sutil">· turno <?= $t->getIdCajaTurno() ?> desde las <?= e(substr($t->getFechaHoraApertura(), 11, 5)) ?></span></p>
                    <p class="caja-vivo-monto"><?= e(soles($fila['esperado'])) ?> <span class="sutil">esperado en gaveta</span></p>
                    <?php if (Dinero::centimos($fila['esperado']) > Dinero::centimos($regla['tope'])): ?><p class="etiqueta alerta">Pasó el tope: pedir retiro</p><?php endif; ?>
                    <?php if ($h > $regla['horas']): ?><p class="etiqueta reponer">Abierto hace <?= (int) $h ?> h</p><?php endif; ?>
                    <a class="boton secundario chico" href="#sorpresa-<?= $t->getIdCajaTurno() ?>"><?= icono('conteo') ?>Arqueo sorpresa</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($porRevisar !== []): ?>
        <h2>Cierres por revisar <span class="etiqueta alerta"><?= count($porRevisar) ?></span></h2>
        <div class="por-revisar">
            <?php foreach ($porRevisar as $fila): ?>
                <?php $t = $fila['turno']; ?>
                <section class="panel destacado-borde">
                    <h3>Turno <?= $t->getIdCajaTurno() ?> · Caja <?= $numeroCaja[$t->getIdCaja()] ?? '-' ?> · <?= e($nombresEmpleado[$t->getIdEmpleado()] ?? '-') ?></h3>
                    <p>Contó <strong><?= e(soles((float) $t->getMontoCierre())) ?></strong>, se esperaba <strong><?= e(soles($fila['esperado'])) ?></strong>:
                        <span class="etiqueta reponer"><?= e($textoDiferencia($fila['diferencia'])) ?></span>
                        <a href="#turno-<?= $t->getIdCajaTurno() ?>">ver el detalle</a></p>
                    <form class="formulario" method="post" action="/?ruta=turno_revisar">
                        <?= campoCsrf($csrf) ?>
                        <input type="hidden" name="turno" value="<?= $t->getIdCajaTurno() ?>">
                        <label for="comentario-<?= $t->getIdCajaTurno() ?>">Comentario</label>
                        <input id="comentario-<?= $t->getIdCajaTurno() ?>" name="comentario" maxlength="200" required placeholder="Ej.: se descontará de su sueldo / vuelto mal dado a un cliente">
                        <div class="botones-formulario">
                            <button class="acento" type="submit" name="decision" value="APROBAR">Aprobar la diferencia</button>
                            <button class="secundario" type="submit" name="decision" value="RECONTEO">Pedir que vuelva a contar</button>
                        </div>
                    </form>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<h2><?= $esAdministrador ? 'Turnos de la tienda' : 'Tus turnos' ?></h2>
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
        <tr><th scope="col">Turno</th><th scope="col">Caja</th><th scope="col">Cajero</th><th scope="col">Apertura</th><th scope="col" class="num">Esperado</th><th scope="col" class="num">Contado</th><th scope="col" class="num">Diferencia</th><th scope="col"><span class="visualmente-oculto">Detalle</span></th></tr>
    </thead>
    <tbody>
    <?php foreach ($arqueo as $fila): ?>
        <?php $t = $fila['turno']; $d = $fila['diferencia']; ?>
        <tr id="fila-turno-<?= $t->getIdCajaTurno() ?>">
            <td><?= $t->getIdCajaTurno() ?> <span class="etiqueta <?= e($etiquetaEstado($t->getEstado())) ?>"><?= e($t->estaEnPausa() ? 'EN PAUSA' : $t->getEstado()) ?></span></td>
            <td><?= $numeroCaja[$t->getIdCaja()] ?? '-' ?></td>
            <td><?= e($nombresEmpleado[$t->getIdEmpleado()] ?? '-') ?></td>
            <td class="fecha"><?= e(fecha($t->getFechaHoraApertura())) ?></td>
            <td class="num"><?= e($esCajero && !$esAdministrador && $regla['ciego'] && $t->estaAbierto() ? 'Oculto' : soles($fila['esperado'])) ?></td>
            <td class="num"><?= $t->getMontoCierre() === null ? '—' : e(soles($t->getMontoCierre())) ?></td>
            <td class="num"><?php if ($d === null): ?>—<?php else: ?><span class="etiqueta <?= e($claseDiferencia($d)) ?>"><?= e($textoDiferencia($d)) ?></span><?php endif; ?></td>
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
    $ocultarEsperado = $esCajero && !$esAdministrador && $regla['ciego'] && $t->estaAbierto();
    $drawerId = 'turno-' . $t->getIdCajaTurno();
    $drawerTitulo = 'Turno ' . $t->getIdCajaTurno();
    $drawerSubtitulo = 'Caja ' . ($numeroCaja[$t->getIdCaja()] ?? '-') . ' · ' . ($nombresEmpleado[$t->getIdEmpleado()] ?? '-');
    $drawerCerrar = '#fila-turno-' . $t->getIdCajaTurno();
    $drawerAbierto = false;
    $drawerAncho = false;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    ?>
    <dl class="datos">
        <dt>Estado</dt><dd><span class="etiqueta <?= e($etiquetaEstado($t->getEstado())) ?>"><?= e($t->estaEnPausa() ? 'EN PAUSA' : $t->getEstado()) ?></span></dd>
        <dt>Apertura</dt><dd><?= e(fecha($t->getFechaHoraApertura())) ?></dd>
        <dt>Cierre</dt><dd><?= e($t->getFechaHoraCierre() === null ? 'Sigue abierto' : fecha($t->getFechaHoraCierre())) ?></dd>
        <dt>Fondo contado al abrir</dt><dd><?= e(soles($t->getMontoInicial())) ?></dd>
        <?php if (!$ocultarEsperado): ?>
            <dt>+ Efectivo cobrado</dt><dd><?= e(soles($fila['efectivoCobrado'])) ?></dd>
            <dt>− Devoluciones</dt><dd><?= e(soles($fila['devoluciones'])) ?></dd>
            <dt>+ Ingresos de sencillo</dt><dd><?= e(soles($fila['ingresos'])) ?></dd>
            <dt>− Retiros</dt><dd><?= e(soles($fila['retiros'])) ?></dd>
            <dt>= Esperado en gaveta</dt><dd><?= e(soles($fila['esperado'])) ?></dd>
        <?php endif; ?>
        <dt>Contado al cerrar</dt><dd><?= $t->getMontoCierre() === null ? '—' : e(soles($t->getMontoCierre())) ?></dd>
        <dt>Diferencia</dt><dd><?php if ($d === null): ?>Se calcula al cerrar el turno<?php else: ?><span class="etiqueta <?= e($claseDiferencia($d)) ?>"><?= e($textoDiferencia($d)) ?></span><?php endif; ?></dd>
        <?php if ($t->getIdEmpleadoRevisa() !== null): ?>
            <dt>Revisión</dt><dd><?= e(($nombresEmpleado[$t->getIdEmpleadoRevisa()] ?? '-') . ' · ' . fecha($t->getFechaHoraRevision()) . ': ' . ($t->getObservacion() ?? '')) ?></dd>
        <?php endif; ?>
    </dl>
    <?php if (($arqueosPorTurno[$t->getIdCajaTurno()] ?? []) !== []): ?>
        <h3>Conteos de la gaveta</h3>
        <ul class="lista-arqueos">
            <?php foreach ($arqueosPorTurno[$t->getIdCajaTurno()] as $a): ?>
                <li>
                    <div><strong><?= e($tipoArqueo[$a['tipo']] ?? $a['tipo']) ?></strong> <span class="fecha sutil"><?= e(fecha($a['fecha_hora'])) ?> · <?= e($nombresEmpleado[$a['id_empleado']] ?? '-') ?></span></div>
                    <div>Contó <?= e(soles($a['contado'])) ?><?php if (!$ocultarEsperado): ?> · <?= e($a['tipo'] === 'APERTURA' ? 'fondo fijo' : 'esperado') ?> <?= e(soles($a['esperado'])) ?><?php endif; ?></div>
                    <?php if ($a['detalle'] !== []): ?>
                        <div class="desglose"><?php foreach ($a['detalle'] as $den => $cant): ?><span><?= (int) $cant ?> × <?= e(soles((float) $den)) ?></span><?php endforeach; ?></div>
                    <?php endif; ?>
                    <?php if ($a['observacion'] !== null): ?><div class="sutil">«<?= e($a['observacion']) ?>»</div><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (($movimientos[$t->getIdCajaTurno()] ?? []) !== []): ?>
        <h3>Retiros e ingresos</h3>
        <ul class="lista-movimientos">
            <?php foreach ($movimientos[$t->getIdCajaTurno()] as $m): ?>
                <?php $signo = $m['tipo'] === 'RETIRO' ? '− ' : '+ '; ?>
                <li><span class="fecha"><?= e(substr($m['fecha_hora'], 11, 5)) ?></span> <?= e($signo . soles($m['monto'])) ?> · <?= e($m['motivo']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p class="sutil bloque">Solo cuenta el efectivo VÁLIDO: los pagos con tarjeta o billetera digital no entran a la gaveta y los de ventas anuladas no suman.</p>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($esAdministrador): ?>
    <?php foreach ($turnoDeCaja as $fila): ?>
        <?php
        $t = $fila['turno'];
        $drawerId = 'sorpresa-' . $t->getIdCajaTurno();
        $drawerTitulo = 'Arqueo sorpresa';
        $drawerSubtitulo = 'Caja ' . ($numeroCaja[$t->getIdCaja()] ?? '-') . ' · ' . ($nombresEmpleado[$t->getIdEmpleado()] ?? '-') . ' · turno ' . $t->getIdCajaTurno();
        $drawerCerrar = '#contenido';
        $drawerAbierto = false;
        $drawerAncho = true;
        require __DIR__ . '/../parciales/drawer_abrir.php';
        ?>
        <p class="sutil">Cuenta la gaveta delante del cajero. Queda guardado con lo esperado en este momento: <?= e(soles($fila['esperado'])) ?>.</p>
        <form class="formulario" method="post" action="/?ruta=arqueo_sorpresa">
            <?= campoCsrf($csrf) ?>
            <input type="hidden" name="turno" value="<?= $t->getIdCajaTurno() ?>">
            <?php
            $conteoId = 'sorpresa-' . $t->getIdCajaTurno();
            $conteoCampoTotal = 'monto';
            $conteoValor = '';
            $conteoEsperado = $fila['esperado'];
            $conteoRotulo = 'lo esperado';
            require __DIR__ . '/../parciales/conteo_efectivo.php';
            ?>
            <label for="sorpresa-obs-<?= $t->getIdCajaTurno() ?>">Observación <span class="sutil">(opcional)</span></label>
            <input id="sorpresa-obs-<?= $t->getIdCajaTurno() ?>" name="observacion" maxlength="150">
            <div class="botones-formulario"><button class="acento" type="submit">Guardar arqueo</button></div>
        </form>
        <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($puedeCambiarReglas): ?>
    <?php
    $drawerId = 'reglas-caja';
    $drawerTitulo = 'Reglas de caja';
    $drawerSubtitulo = $tienda->getNombreTienda();
    $drawerCerrar = '#contenido';
    $drawerAbierto = false;
    $drawerAncho = false;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    ?>
    <form class="formulario" method="post" action="/?ruta=regla_caja_guardar">
        <?= campoCsrf($csrf) ?>
        <label for="regla-tolerancia">Tolerancia de diferencia (S/)</label>
        <span class="ayuda">Si al cerrar la diferencia es igual o menor, el turno se cierra solo. Si pasa, te llega para revisar.</span>
        <input id="regla-tolerancia" name="tolerancia" inputmode="decimal" required value="<?= e(number_format($regla['tolerancia'], 2, '.', '')) ?>">
        <label for="regla-tope">Tope de efectivo en la gaveta (S/)</label>
        <span class="ayuda">Pasado el tope, el cajero ve el aviso de hacer un retiro al depósito.</span>
        <input id="regla-tope" name="tope" inputmode="decimal" required value="<?= e(number_format($regla['tope'], 2, '.', '')) ?>">
        <label for="regla-horas">Horas máximas de un turno</label>
        <input id="regla-horas" name="horas" inputmode="numeric" required value="<?= (int) $regla['horas'] ?>">
        <label class="casilla"><input type="checkbox" name="cierre_ciego" value="S"<?= $regla['ciego'] ? ' checked' : '' ?>> Cierre ciego: el cajero cuenta sin ver lo esperado</label>
        <h3>Fondo fijo de cada caja</h3>
        <div class="campos">
            <?php foreach ($cajasTienda as $c): ?>
                <div>
                    <label for="fondo-<?= $c->getIdCaja() ?>">Caja <?= $c->getNumeroCaja() ?> (S/)</label>
                    <input id="fondo-<?= $c->getIdCaja() ?>" name="fondo[<?= $c->getIdCaja() ?>]" inputmode="decimal" required value="<?= e(number_format($fondos[$c->getIdCaja()] ?? 0, 2, '.', '')) ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <div class="botones-formulario"><button class="acento" type="submit">Guardar reglas</button></div>
    </form>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
