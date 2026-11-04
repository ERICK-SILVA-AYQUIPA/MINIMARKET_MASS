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
 * @var Caja[] $cajasActivas            las cajas de la tienda que se pueden usar (estado ACTIVA)
 * @var bool $puedeCambiarReglas
 * @var array<int, array<int, array{fecha_hora: string, tipo: string, contado: float, esperado: float, observacion: ?string, id_empleado: int, detalle: array<string, int>}>> $arqueosPorTurno
 * @var array<int, array<int, array{fecha_hora: string, tipo: string, categoria: string, monto: float, motivo: string, comprobante: ?string}>> $movimientos
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
        <?php if ($puedeCambiarReglas): ?>
            <a class="boton secundario" href="#reglas-caja"><?= icono('editar') ?>Reglas y fondos</a>
        <?php endif; ?>
    </div>
</div>
<?php
$guiaClave = 'caja';
$guiaTitulo = 'Cómo funciona la caja';
$guiaPasos = $esCajero ? [
    'Para abrir: elige una caja libre y cuenta el sobre del fondo. Si no cuadra con el fondo fijo, escribe por qué.',
    'Durante el turno solo anotas los movimientos de efectivo: retiros al depósito, gastos menores o sencillo que te traen.',
    'Si te alejas, toca Pausar caja: nadie puede vender con tu turno hasta que la reanudes.',
    'Al terminar toca Finalizar y arquear turno: cuentas a ciegas y el sistema calcula la diferencia al confirmar.',
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
        <section class="estado-turno info">
            <div class="estado-turno-icono"><?= icono('conteo') ?></div>
            <div class="estado-turno-texto">
                <h2>Vuelve a contar el turno <?= $miPendiente->getIdCajaTurno() ?></h2>
                <p>El administrador pidió un nuevo conteo<?php if ($miPendiente->getObservacion() !== null): ?>: «<?= e($miPendiente->getObservacion()) ?>»<?php endif; ?>. Cuenta otra vez todo el efectivo de la gaveta.</p>
            </div>
        </section>
        <section class="panel">
            <form class="formulario" method="post" action="/?ruta=turno_recontar" data-confirmar="¿Guardar el nuevo conteo de {total}?" data-confirmar-boton="Sí, guardar conteo">
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
        <section class="estado-turno alerta" role="status">
            <div class="estado-turno-icono"><?= icono('reloj') ?></div>
            <div class="estado-turno-texto">
                <h2>Tu cierre está en revisión</h2>
                <p>El turno <?= $miPendiente->getIdCajaTurno() ?> cerró con
                    <strong><?= e($textoDiferencia($miPendiente->getDiferencia())) ?></strong>, más que la tolerancia de <?= e(soles($regla['tolerancia'])) ?>.
                    Podrás abrir otro turno cuando el administrador lo apruebe o te pida volver a contar.</p>
            </div>
        </section>
    <?php elseif ($miTurno === null): ?>
        <?php
        $cajasLibres = array_values(array_filter($cajasTienda,
            fn(Caja $c): bool => $c->estadoAhora(isset($turnoDeCaja[$c->getIdCaja()])) === 'libre'));
        $primerFondo = $cajasLibres === [] ? 0.0 : ($fondos[$cajasLibres[0]->getIdCaja()] ?? 100.0);
        ?>
        <section class="apertura" aria-labelledby="apertura-titulo">
            <header class="apertura-cabeza">
                <div class="apertura-saludo">
                    <span class="apertura-icono"><?= icono('caja') ?></span>
                    <div>
                        <h2 id="apertura-titulo">Abre tu turno</h2>
                        <p>Hola, <?= e($usuario->getNombres()) ?>. Elige tu caja, cuenta el sobre del fondo y empieza a vender.</p>
                    </div>
                </div>
                <ol class="pasos" aria-label="Pasos para abrir">
                    <li class="activo"><span>1</span>Elige tu caja</li>
                    <li><span>2</span>Cuenta el fondo</li>
                    <li><span>3</span>Abre el turno</li>
                </ol>
            </header>
            <?php if ($cajasLibres === []): ?>
                <?php
                $vacioTitulo = 'No hay cajas libres ahora';
                $vacioTexto = 'Todas las cajas de la tienda están ocupadas o inactivas. Pide al administrador que libere o active una.';
                require __DIR__ . '/../parciales/vacio.php';
                ?>
            <?php else: ?>
            <form class="formulario apertura-cuerpo" method="post" action="/?ruta=turno_abrir" data-apertura>
                <?= campoCsrf($csrf) ?>
                <div class="apertura-pasos">
                    <fieldset class="paso">
                        <legend><span class="paso-n">1</span>Elige tu caja</legend>
                        <div class="cajas-elegir">
                            <?php foreach ($cajasTienda as $i => $c): ?>
                                <?php
                                $ocupada = $turnoDeCaja[$c->getIdCaja()] ?? null;
                                $libre = $c->estadoAhora($ocupada !== null) === 'libre';
                                $fondoCaja = $fondos[$c->getIdCaja()] ?? 0;
                                ?>
                                <label class="caja-opcion<?= $libre ? '' : ' no-disponible' ?>">
                                    <input type="radio" name="caja" value="<?= $c->getIdCaja() ?>" data-numero="<?= $c->getNumeroCaja() ?>" data-fondo="<?= e(number_format($fondoCaja, 2, '.', '')) ?>"<?= $libre && $c === $cajasLibres[0] ? ' checked' : '' ?><?= $libre ? '' : ' disabled' ?>>
                                    <span class="caja-opcion-cabeza"><?= icono('caja') ?><strong>Caja <?= $c->getNumeroCaja() ?></strong></span>
                                    <span class="caja-opcion-estado <?= $libre ? 'libre' : 'ocupada' ?>">
                                        <?php if ($ocupada !== null): ?>Ocupada · <?= e($nombresEmpleado[$ocupada['turno']->getIdEmpleado()] ?? '-') ?>
                                        <?php elseif (!$libre): ?>Inactiva
                                        <?php else: ?>Libre<?php endif; ?>
                                    </span>
                                    <span class="caja-opcion-fondo">Fondo fijo <strong><?= e(soles($fondoCaja)) ?></strong></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <fieldset class="paso">
                        <legend><span class="paso-n">2</span>Cuenta el sobre del fondo</legend>
                        <p class="ayuda">Anota cuántos billetes y monedas hay: el total se suma solo y te dice si cuadra con el fondo fijo.</p>
                        <?php
                        $conteoId = 'apertura';
                        $conteoCampoTotal = 'monto_inicial';
                        $conteoValor = '';
                        $conteoEsperado = $primerFondo;
                        $conteoRotulo = 'el fondo fijo';
                        $conteoExacto = true;
                        $conteoSinMarco = true;
                        require __DIR__ . '/../parciales/conteo_efectivo.php';
                        ?>
                        <div class="observacion-apertura" data-si-descuadra>
                            <label for="apertura-observacion">Si no cuadra con el fondo fijo, ¿por qué?</label>
                            <input id="apertura-observacion" name="observacion" maxlength="150" placeholder="Ej.: el sobre vino con S/ 2 menos en monedas">
                        </div>
                    </fieldset>
                </div>
                <aside class="apertura-resumen" aria-label="Resumen de la apertura">
                    <h3><span class="paso-n">3</span>Abre el turno</h3>
                    <dl>
                        <dt>Caja</dt><dd data-resumen-caja>Caja <?= (int) $cajasLibres[0]->getNumeroCaja() ?></dd>
                        <dt>Fondo fijo</dt><dd data-resumen-fondo><?= e(soles($primerFondo)) ?></dd>
                        <dt>Contaste</dt><dd data-resumen-contado>—</dd>
                    </dl>
                    <p class="apertura-estado" data-resumen-estado aria-live="polite">Cuenta el fondo para continuar.</p>
                    <button class="acento grande" type="submit"><?= icono('caja') ?>Abrir mi turno</button>
                    <p class="ayuda">Para vender necesitas un turno abierto. Queda registrado con tu nombre y la hora.</p>
                </aside>
            </form>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <?php
        $enGaveta = $miArqueo['esperado'] ?? $miTurno->getMontoInicial();
        $horas = $horasAbierto($miTurno);
        $enPausa = $miTurno->estaEnPausa();
        $numeroMiCaja = $numeroCaja[$miTurno->getIdCaja()] ?? '-';
        $misMovimientos = $movimientos[$miTurno->getIdCajaTurno()] ?? [];
        $ventasTurno = $misVentas ?? ['ventas' => 0, 'efectivo' => 0.0, 'tarjeta' => 0.0, 'digital' => 0.0];
        $pasoTope = Dinero::centimos($enGaveta) > Dinero::centimos($regla['tope']);
        // Retiro de seguridad: con cierre ciego no se dice cuanto (seria mostrar lo esperado); sin el, se sugiere el monto
        $retiroSugerido = $regla['ciego'] ? 0 : MovimientoCaja::retiroSugerido(Dinero::centimos($enGaveta),
            Dinero::centimos($regla['tope']), Dinero::centimos($fondos[$miTurno->getIdCaja()] ?? 0.0));
        ?>
        <header class="turno-barra<?= $enPausa ? ' en-pausa' : '' ?>" aria-label="Estado de tu turno">
            <div class="turno-barra-estado">
                <span class="estado-pastilla"><span class="punto" aria-hidden="true"></span><?= $enPausa ? 'Caja en pausa' : 'Turno activo' ?></span>
                <strong>Caja N.° <?= e((string) $numeroMiCaja) ?></strong>
                <span class="separador" aria-hidden="true">|</span>
                <strong>Turno N.° <?= $miTurno->getIdCajaTurno() ?></strong>
            </div>
            <div class="turno-barra-meta">
                <span><?= icono('usuario') ?><?= e($usuario->nombreCompleto()) ?></span>
                <span title="Desde las <?= e(substr($miTurno->getFechaHoraApertura(), 11, 5)) ?>"><?= icono('reloj') ?>Abierto hace <?= e($tiempoAbierto($horas)) ?></span>
            </div>
            <div class="turno-barra-acciones">
                <?php if ($enPausa): ?>
                    <form method="post" action="/?ruta=turno_pausa">
                        <?= campoCsrf($csrf) ?>
                        <input type="hidden" name="accion" value="reanudar">
                        <button class="acento" type="submit"><?= icono('reanudar') ?>Reanudar caja</button>
                    </form>
                <?php else: ?>
                    <a class="boton acento" href="/?ruta=venta_nueva"><?= icono('venta') ?>Ir a vender</a>
                    <form method="post" action="/?ruta=turno_pausa">
                        <?= campoCsrf($csrf) ?>
                        <input type="hidden" name="accion" value="pausar">
                        <button class="secundario" type="submit" title="Si te alejas: nadie podrá vender con tu turno"><?= icono('pausa') ?>Pausar caja</button>
                    </form>
                <?php endif; ?>
                <a class="boton oscuro" href="#arqueo-cierre"><?= icono('candado') ?>Cerrar caja</a>
            </div>
        </header>

        <?php if ($enPausa): ?>
            <div class="aviso alerta" role="status"><span>Caja en pausa desde las <?= e(substr((string) $miTurno->getEnPausaDesde(), 11, 5)) ?>: nadie puede vender con tu turno hasta que la reanudes.</span></div>
        <?php endif; ?>
        <?php if ($pasoTope): ?>
            <div class="aviso alerta aviso-retiro" role="status">
                <span>
                    <strong>La gaveta pasó el tope de <?= e(soles($regla['tope'])) ?>.</strong>
                    <?php if ($retiroSugerido > 0): ?>
                        Retira <?= e(soles($retiroSugerido / 100)) ?> a la caja fuerte: queda el fondo para dar vuelto.
                    <?php else: ?>
                        Cuenta los billetes grandes y haz un retiro de seguridad a la caja fuerte.
                    <?php endif; ?>
                </span>
                <a class="boton acento chico" href="#movimientos-titulo" data-retiro-seguridad="<?= e($retiroSugerido > 0 ? Dinero::aTexto($retiroSugerido) : '') ?>"><?= icono('retiro') ?><?= e($retiroSugerido > 0 ? 'Retirar ' . soles($retiroSugerido / 100) : 'Registrar retiro') ?></a>
            </div>
        <?php endif; ?>
        <?php if ($horas > $regla['horas']): ?>
            <div class="aviso alerta" role="status"><span>Tu turno lleva más de <?= (int) $regla['horas'] ?> horas abierto. Arquéalo y ciérralo al terminar.</span></div>
        <?php endif; ?>

        <section class="metricas-turno" aria-label="Tu turno en cifras">
            <article class="metrica">
                <span class="metrica-rotulo">Fondo inicial de caja</span>
                <strong class="metrica-valor"><?= e(soles($miTurno->getMontoInicial())) ?></strong>
                <small>Contado al abrir, a las <?= e(substr($miTurno->getFechaHoraApertura(), 11, 5)) ?></small>
            </article>
            <article class="metrica">
                <span class="metrica-rotulo">Ventas del turno <span class="etiqueta neutra"><?= (int) $ventasTurno['ventas'] ?> <?= $ventasTurno['ventas'] === 1 ? 'venta' : 'ventas' ?></span></span>
                <dl class="metrica-filas">
                    <dt>Efectivo</dt>
                    <dd><?php if ($regla['ciego']): ?><span class="sutil"><?= icono('candado') ?>se ve al cerrar</span><?php else: ?><?= e(soles($ventasTurno['efectivo'])) ?><?php endif; ?></dd>
                    <dt>Tarjeta</dt><dd><?= e(soles($ventasTurno['tarjeta'])) ?></dd>
                    <dt>Billetera Sip</dt><dd><?= e(soles($ventasTurno['digital'])) ?></dd>
                </dl>
            </article>
            <article class="metrica metrica-oscura">
                <span class="metrica-rotulo">Efectivo esperado en gaveta</span>
                <?php if ($regla['ciego']): ?>
                    <strong class="metrica-valor velado" aria-label="Oculto">S/ ●●●.●●</strong>
                    <small><?= icono('candado') ?>Oculto por cierre ciego: cuenta sin mirar</small>
                <?php else: ?>
                    <details class="revelar" data-revelar>
                        <summary><span class="metrica-valor velado" aria-hidden="true">S/ ●●●.●●</span><span class="revelar-boton"><?= icono('ver') ?>Ver estimado</span></summary>
                        <strong class="metrica-valor"><?= e(soles($enGaveta)) ?></strong>
                    </details>
                    <small>Se oculta solo a los 10 segundos</small>
                <?php endif; ?>
            </article>
        </section>

        <section class="panel movimientos-turno" aria-labelledby="movimientos-titulo">
            <div class="titulo-seccion">
                <div>
                    <h2 id="movimientos-titulo">Movimientos de efectivo</h2>
                    <p class="sutil">Anota cada vez que sacas billetes para el depósito o un gasto menor, o cuando te traen sencillo.</p>
                </div>
            </div>
            <form class="formulario movimiento-form" method="post" action="/?ruta=caja_movimiento" data-movimiento>
                <?= campoCsrf($csrf) ?>
                <fieldset class="segmentado">
                    <legend class="visualmente-oculto">Tipo de movimiento</legend>
                    <label><input type="radio" name="tipo" value="RETIRO" checked><span><?= icono('retiro') ?>Retiro <small>sale de la gaveta</small></span></label>
                    <label><input type="radio" name="tipo" value="INGRESO"><span><?= icono('ingreso') ?>Ingreso <small>sencillo o cambio</small></span></label>
                </fieldset>
                <div class="movimiento-campos">
                    <div class="movimiento-categoria-campo">
                        <label for="categoria_movimiento">Categoría</label>
                        <select id="categoria_movimiento" name="categoria" required>
                            <?php foreach (MovimientoCaja::CATEGORIAS as $tipoMov => $categoriasMov): ?>
                                <optgroup label="<?= $tipoMov === 'RETIRO' ? 'Retiro' : 'Ingreso' ?>" data-tipo="<?= e($tipoMov) ?>">
                                    <?php foreach ($categoriasMov as $claveCat => $nombreCat): ?>
                                        <option value="<?= e($claveCat) ?>"><?= e($nombreCat) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="monto_movimiento">Monto (S/)</label>
                        <input id="monto_movimiento" name="monto" inputmode="decimal" required placeholder="0.00" autocomplete="off">
                    </div>
                    <div>
                        <label for="comprobante_movimiento">Comprobante <span class="sutil">(opcional)</span></label>
                        <input id="comprobante_movimiento" name="comprobante" maxlength="20" pattern="[A-Za-z0-9][A-Za-z0-9\/\-]{0,19}" placeholder="Ej.: B001-245" autocomplete="off">
                    </div>
                    <div class="movimiento-motivo">
                        <label for="motivo_movimiento">Detalle</label>
                        <input id="motivo_movimiento" name="motivo" maxlength="150" required list="motivos-caja" placeholder="Ej.: depósito al banco, pago de bolsas" autocomplete="off">
                        <datalist id="motivos-caja">
                            <option value="Retiro de seguridad a la caja fuerte">
                            <option value="Depósito parcial al banco">
                            <option value="Compra menor: bolsas">
                            <option value="Pago a proveedor">
                            <option value="Sencillo del banco">
                            <option value="Cambio traído por el administrador">
                        </datalist>
                    </div>
                    <div class="movimiento-boton"><button class="secundario" type="submit"><?= icono('ok') ?>Registrar</button></div>
                </div>
            </form>

            <h3>Historial del turno</h3>
            <div class="tabla">
            <table class="tabla-auditoria">
                <thead><tr><th scope="col">Hora</th><th scope="col">Tipo</th><th scope="col">Detalle</th><th scope="col" class="num">Monto</th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($misMovimientos) as $m): ?>
                    <?php $esRetiro = $m['tipo'] === 'RETIRO'; ?>
                    <tr>
                        <td class="fecha"><?= e(substr($m['fecha_hora'], 11, 5)) ?></td>
                        <td><span class="etiqueta <?= $esRetiro ? 'reponer' : 'ok' ?>"><?= $esRetiro ? 'Retiro' : 'Ingreso' ?></span></td>
                        <td><strong class="movimiento-categoria"><?= e(MovimientoCaja::nombreCategoria($m['tipo'], $m['categoria'])) ?></strong> <?= e($m['motivo']) ?><?php if ($m['comprobante'] !== null): ?> <span class="sutil">· <?= e($m['comprobante']) ?></span><?php endif; ?></td>
                        <td class="num <?= $esRetiro ? 'texto-error' : 'texto-ok' ?>"><?= e(($esRetiro ? '− ' : '+ ') . soles($m['monto'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="fila-apertura">
                        <td class="fecha"><?= e(substr($miTurno->getFechaHoraApertura(), 11, 5)) ?></td>
                        <td><span class="etiqueta neutra">Apertura</span></td>
                        <td>Fondo contado al abrir el turno</td>
                        <td class="num"><?= e(soles($miTurno->getMontoInicial())) ?></td>
                    </tr>
                </tbody>
            </table>
            </div>
            <?php if ($misMovimientos === []): ?><p class="sutil">Aún no hay retiros ni ingresos en este turno.</p><?php endif; ?>
        </section>

        <?php
        $drawerId = 'arqueo-cierre';
        $drawerTitulo = 'Cierre automático · turno ' . $miTurno->getIdCajaTurno();
        $drawerSubtitulo = 'Caja ' . $numeroMiCaja . ' · ' . $usuario->nombreCompleto();
        $drawerCerrar = '#contenido';
        $drawerAbierto = $abrir === 'arqueo-cierre';
        $drawerAncho = true;
        $drawerPantalla = true;
        require __DIR__ . '/../parciales/drawer_abrir.php';
        $cobradoEfectivo = $miArqueo['efectivoCobrado'] ?? 0.0;
        $retirosTurno = $miArqueo['retiros'] ?? 0.0;
        $ingresosTurno = $miArqueo['ingresos'] ?? 0.0;
        $devolucionesTurno = $miArqueo['devoluciones'] ?? 0.0;
        ?>
        <div class="arqueo-intro">
            <span class="arqueo-intro-icono"><?= icono('candado') ?></span>
            <div>
                <strong>El sistema ya hizo las cuentas. Tú solo cuentas el efectivo.</strong>
                <p><?= e($regla['ciego']
                    ? 'Cierre ciego: cuentas sin ver lo esperado. Al confirmar, el sistema compara y te muestra la diferencia en el comprobante de cierre.'
                    : 'Mientras cuentas ves si cuadra con lo esperado. Al confirmar, el sistema cierra el turno y arma el comprobante de cierre.') ?></p>
                <ol class="pasos" data-pasos-cierre>
                    <li class="hecho"><span>1</span>Resumen del sistema</li>
                    <li class="activo"><span>2</span>Cuenta el efectivo</li>
                    <li><span>3</span>Confirma y cierra</li>
                </ol>
            </div>
        </div>
        <?php if ($error !== null && $abrir === 'arqueo-cierre'): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form class="formulario arqueo-form" method="post" action="/?ruta=turno_cerrar" data-cierre
              data-confirmar="Vas a cerrar el turno <?= $miTurno->getIdCajaTurno() ?> declarando {total} en efectivo. Después ya no podrás vender ni corregir este conteo. ¿Cerrar el turno?"
              data-confirmar-boton="Sí, cerrar mi turno">
            <?= campoCsrf($csrf) ?>
            <fieldset class="paso">
                <legend><span class="paso-n">1</span>Lo que calculó el sistema</legend>
                <div class="cierre-resumen">
                    <div class="cierre-dato"><span>Ventas emitidas</span><strong><?= (int) $ventasTurno['ventas'] ?></strong></div>
                    <div class="cierre-dato"><span>Fondo inicial</span><strong><?= e(soles($miTurno->getMontoInicial())) ?></strong></div>
                    <div class="cierre-dato efectivo"><span>Ventas en efectivo</span><strong><?php if ($regla['ciego']): ?><span class="velado" aria-label="Oculto">S/ ●●●.●●</span><?php else: ?><?= e(soles($cobradoEfectivo)) ?><?php endif; ?></strong></div>
                    <div class="cierre-dato"><span>Retiros de caja</span><strong><?= e(($retirosTurno > 0 ? '− ' : '') . soles($retirosTurno)) ?></strong></div>
                    <div class="cierre-dato"><span>Ingresos de caja</span><strong><?= e(($ingresosTurno > 0 ? '+ ' : '') . soles($ingresosTurno)) ?></strong></div>
                    <?php if ($devolucionesTurno > 0): ?>
                        <div class="cierre-dato"><span>Devoluciones</span><strong><?= e('− ' . soles($devolucionesTurno)) ?></strong></div>
                    <?php endif; ?>
                    <div class="cierre-dato voucher"><span>Tarjeta <small>(vouchers)</small></span><strong><?= e(soles($ventasTurno['tarjeta'])) ?></strong></div>
                    <div class="cierre-dato voucher"><span>Billetera Sip <small>(vouchers)</small></span><strong><?= e(soles($ventasTurno['digital'])) ?></strong></div>
                </div>
                <div class="cierre-esperado<?= $regla['ciego'] ? ' oculto' : '' ?>">
                    <span>Efectivo que debe haber en la gaveta</span>
                    <?php if ($regla['ciego']): ?>
                        <strong class="velado" aria-label="Oculto">S/ ●●●.●●</strong>
                        <small><?= icono('candado') ?>Se compara solo al confirmar</small>
                    <?php else: ?>
                        <strong><?= e(soles($enGaveta)) ?></strong>
                        <small>fondo + efectivo + ingresos − retiros − devoluciones</small>
                    <?php endif; ?>
                </div>
                <p class="ayuda">Tarjeta y billetera Sip no entran al arqueo: cuádralas con sus vouchers.</p>
            </fieldset>
            <fieldset class="paso">
                <legend><span class="paso-n">2</span>Cuenta el efectivo de la gaveta</legend>
                <p class="ayuda">Incluye el fondo inicial. Escribe cuántos hay de cada billete y moneda, o solo el total.</p>
                <?php
                $conteoId = 'cierre';
                $conteoCampoTotal = 'monto_cierre';
                $conteoValor = '';
                $conteoEsperado = $regla['ciego'] ? null : $enGaveta;
                $conteoRotulo = 'lo esperado';
                $conteoSinMarco = true;
                $conteoGrande = true;
                require __DIR__ . '/../parciales/conteo_efectivo.php';
                ?>
            </fieldset>
            <fieldset class="paso">
                <legend><span class="paso-n">3</span>Confirma y cierra</legend>
                <label for="cierre-observacion">Observación <span class="sutil">(opcional)</span></label>
                <input id="cierre-observacion" name="observacion" maxlength="150" placeholder="Ej.: un cliente pagó con billete roto">
                <label class="casilla confirmar-conteo"><input id="cierre-confirmo" type="checkbox" name="conteo_completo" value="S" required> Conté todo el efectivo de la gaveta, incluido el fondo inicial</label>
                <div class="botones-formulario arqueo-botones">
                    <a class="boton secundario" href="#contenido">Volver al turno</a>
                    <button class="oscuro grande" type="submit"><?= icono('candado') ?>Confirmar y cerrar turno</button>
                </div>
            </fieldset>
        </form>
        <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
    <?php endif; ?>
<?php endif; ?>

<?php if ($esAdministrador): ?>
    <?php
    $enGavetas = array_sum(array_map(fn(array $f): float => $f['esperado'], $turnoDeCaja));
    $enPausaAhora = count(array_filter($turnoDeCaja, fn(array $f): bool => $f['turno']->estaEnPausa()));
    ?>
    <section class="metricas-turno" aria-label="La caja de la tienda en cifras">
        <article class="metrica">
            <span class="metrica-rotulo">Cajas abiertas</span>
            <strong class="metrica-valor"><?= count($turnoDeCaja) ?> <small>de <?= count($cajasActivas) ?></small></strong>
            <small><?= e($enPausaAhora > 0 ? $enPausaAhora . ' en pausa' : 'ninguna en pausa') ?></small>
        </article>
        <article class="metrica">
            <span class="metrica-rotulo">Efectivo esperado en gavetas</span>
            <strong class="metrica-valor"><?= e(soles($enGavetas)) ?></strong>
            <small>suma de los turnos abiertos</small>
        </article>
        <article class="metrica<?= $porRevisar !== [] ? ' metrica-alerta' : '' ?>">
            <span class="metrica-rotulo">Cierres por revisar</span>
            <strong class="metrica-valor"><?= count($porRevisar) ?></strong>
            <small><?= e($porRevisar === [] ? 'todo al día' : 'pasaron la tolerancia de ' . soles($regla['tolerancia'])) ?></small>
        </article>
    </section>
    <h2>Cajas ahora</h2>
    <div class="cajas-vivo">
        <?php foreach ($cajasTienda as $c): ?>
            <?php
            $fila = $turnoDeCaja[$c->getIdCaja()] ?? null;
            $activa = $c->estaActiva();
            // Libre (activa y sin turno) se ve lista para usar; solo la caja INACTIVA se ve apagada
            $estadoCaja = $c->estadoAhora($fila !== null);
            ?>
            <article class="panel caja-vivo<?= e($estadoCaja === 'ocupada' ? '' : ' ' . $estadoCaja) ?>" data-caja-estado="<?= e($estadoCaja) ?>">
                <header>
                    <strong>Caja <?= $c->getNumeroCaja() ?></strong>
                    <?php if ($fila === null && $activa): ?>
                        <span class="etiqueta info">Libre</span>
                    <?php elseif ($fila === null): ?>
                        <span class="etiqueta neutra">Inactiva</span>
                    <?php elseif ($fila['turno']->estaEnPausa()): ?>
                        <span class="etiqueta alerta">En pausa</span>
                    <?php else: ?>
                        <span class="etiqueta ok">Vendiendo</span>
                    <?php endif; ?>
                </header>
                <?php if ($fila === null): ?>
                    <p class="sutil">Fondo fijo <?= e(soles($fondos[$c->getIdCaja()] ?? 0)) ?></p>
                    <p class="sutil"><?= $activa ? 'Lista para que un cajero abra su turno.' : 'Apagada: no se pueden abrir turnos en ella.' ?></p>
                    <?php if ($puedeCambiarReglas): ?>
                        <form method="post" action="/?ruta=caja_estado">
                            <?= campoCsrf($csrf) ?>
                            <input type="hidden" name="id" value="<?= $c->getIdCaja() ?>">
                            <input type="hidden" name="volver" value="caja">
                            <?php if ($activa): ?>
                                <input type="hidden" name="estado" value="INACTIVA">
                                <button class="secundario chico" type="submit" data-confirmar="¿Desactivar la caja <?= $c->getNumeroCaja() ?>? No se podrán abrir turnos en ella." data-confirmar-boton="Sí, desactivar">Desactivar</button>
                            <?php else: ?>
                                <input type="hidden" name="estado" value="ACTIVA">
                                <button class="acento chico" type="submit"><?= icono('caja') ?>Activar caja</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
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

<?php $historialPlegado = $esCajero && !$esAdministrador; ?>
<?php if ($historialPlegado): ?>
<details class="historial-turnos">
    <summary>Mis turnos anteriores <span class="etiqueta neutra"><?= count($arqueo) ?></span></summary>
<?php else: ?>
<h2>Turnos de la tienda</h2>
<?php endif; ?>
<?php if ($arqueo === []): ?>
    <?php
    $vacioTitulo = 'Todavía no hay turnos';
    $vacioTexto = 'Cada vez que un cajero abre y cierra su caja, el turno aparece aquí con su arqueo.';
    require __DIR__ . '/../parciales/vacio.php';
    ?>
    <?php if ($historialPlegado): ?></details><?php endif; ?>
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
            <td class="acciones-fila"><a class="boton secundario chico" href="#turno-<?= $t->getIdCajaTurno() ?>"><?= icono('ver') ?>Ver</a><?php if (!$t->estaAbierto()): ?> <a class="boton secundario chico" href="/?ruta=turno_cierre&amp;turno=<?= $t->getIdCajaTurno() ?>"><?= icono('imprimir') ?>Cierre</a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($historialPlegado): ?></details><?php endif; ?>

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
            <dt>+ Ingresos de caja</dt><dd><?= e(soles($fila['ingresos'])) ?></dd>
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
                <li><span class="fecha"><?= e(substr($m['fecha_hora'], 11, 5)) ?></span> <?= e($signo . soles($m['monto'])) ?> · <?= e(MovimientoCaja::nombreCategoria($m['tipo'], $m['categoria'])) ?>: <?= e($m['motivo']) ?><?php if ($m['comprobante'] !== null): ?> <span class="sutil">(<?= e($m['comprobante']) ?>)</span><?php endif; ?></li>
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
