<?php
declare(strict_types=1);
/**
 * V: pantalla de venta del cajero. Izquierda: productos con su foto; derecha: carrito, comprobante y cobro.
 * Sin JavaScript se vende con las filas de producto y cantidad; con JavaScript esas filas se llenan desde la
 * cuadricula y se ven como un carrito (public/js/app.js), pero siguen siendo lo que se envia.
 * El total lo calcula el servidor con el precio de la base.
 * @var ?CajaTurno $turno
 * @var ?int $numeroCaja
 * @var Tienda $tienda
 * @var array<int, array{producto: Producto, stock: ?Stock, foto: array{url: ?string, propia: bool, icono: string, tono: int, categoria: string}}> $productos
 * @var array<int, string> $categorias  las que tienen productos para vender, id => nombre
 * @var array<int, string[]> $ofertas  id_producto => promociones de hoy ("15 % menos", "3 x S/ 10.00")
 * @var Promocion[] $promocionesVenta  promociones de hoy a toda la compra
 * @var int $topeDescuento  centesimas: hasta cuanto puede descontar a mano este cajero
 * @var string $datosPromociones  JSON con las promociones de hoy y el tope, para el total en vivo de app.js
 * @var array $viejo
 * @var ?string $error
 */
$titulo = 'Nueva venta';
require __DIR__ . '/../layout_inicio.php';
$v = fn(string $campo): string => is_string($viejo[$campo] ?? null) ? $viejo[$campo] : '';
$opciones = array_column($productos, 'producto');
$filasLineas = 6;
$conImporte = true;
// Stock de la tienda por producto: public/app.js avisa si la cantidad no alcanza (el servidor lo vuelve a validar)
$stockPorProducto = [];
foreach ($productos as $fila) {
    if ($fila['stock'] !== null) {
        $stockPorProducto[$fila['producto']->getIdProducto()] = $fila['stock']->getCantidadActual();
    }
}
// Medio de pago: los 4 que acepta la tienda. Sin JavaScript se ven todos los campos; con JavaScript, solo los del elegido.
$medios = [
    'EFECTIVO' => ['Efectivo', 'billete'],
    'DEBITO'   => ['Débito', 'tarjeta'],
    'CREDITO'  => ['Crédito', 'tarjeta'],
    'SIP'      => ['Sip', 'qr'],
];
$medioElegido = isset($medios[$v('medio_pago')]) ? $v('medio_pago') : 'EFECTIVO';
$conDescuento = $v('descuento_valor') !== '';
?>
<div class="cabecera venta-cabecera">
    <div>
        <h1>Nueva venta</h1>
        <p class="sutil"><?= e($tienda->getNombreTienda()) ?>
            <?php if ($turno !== null): ?>
                <span class="turno-chip<?= $turno->estaEnPausa() ? ' en-pausa' : '' ?>"><span class="punto" aria-hidden="true"></span>Caja <?= e((string) ($numeroCaja ?? '-')) ?> · Turno <?= $turno->getIdCajaTurno() ?></span>
            <?php endif; ?>
        </p>
    </div>
    <?php if ($turno !== null): ?><div class="acciones"><a class="boton secundario" href="/?ruta=caja"><?= icono('caja') ?>Mi caja</a></div><?php endif; ?>
</div>

<?php if ($turno === null): ?>
    <?php
    $vacioTitulo = 'No tienes un turno abierto';
    $vacioTexto = 'Para vender primero abre tu turno de caja con el monto inicial en efectivo.';
    $vacioAccion = ['/?ruta=caja', 'Abrir mi turno de caja'];
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
    <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($turno->estaEnPausa()): ?>
        <div class="aviso alerta" role="status"><span>Tu caja está en pausa: reanúdala en <a href="/?ruta=caja">Mi caja</a> para registrar ventas.</span></div>
    <?php endif; ?>

    <form class="formulario caja-pantalla venta" method="post" action="/?ruta=venta_registrar" data-venta data-promociones="<?= e($datosPromociones) ?>">
        <?= campoCsrf($csrf) ?>
        <section class="panel venta-catalogo">
            <h2 class="visualmente-oculto">Productos</h2>
            <?php
            $guiaClave = 'venta';
            $guiaTitulo = 'Cómo vender rápido';
            $guiaPasos = [
                'Escanea el código de barras (el cursor ya está en el campo) o toca su foto: entra al carrito de la derecha. Tocarlo otra vez suma 1; en el carrito cambias la cantidad o lo quitas.',
                'Para lo que va al peso (papa, plátano…), tócalo y escribe en el carrito los kg que marca la balanza, por ejemplo 1.250.',
                'Debajo del carrito ves el total (ya con las promociones del día) y el vuelto al momento. Elige boleta o factura y el medio de pago: efectivo, débito, crédito o Sip.',
            ];
            require __DIR__ . '/../parciales/guia.php';
            ?>
            <div class="selector-productos" data-selector hidden>
                <div class="selector-barra">
                    <label class="escanear">
                        <svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#codigo"></use></svg>
                        <span class="visualmente-oculto">Código de barras</span>
                        <input type="text" inputmode="numeric" data-escanear placeholder="Escanea o escribe el código y Enter" autocomplete="off">
                    </label>
                    <label class="buscar-producto">
                        <svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#buscar"></use></svg>
                        <span class="visualmente-oculto">Buscar producto</span>
                        <input type="search" data-buscar-producto placeholder="Buscar producto" autocomplete="off">
                    </label>
                </div>
                <div class="chips" data-chips-venta role="group" aria-label="Filtrar por categoría">
                    <button type="button" data-categoria="" aria-pressed="true">Todas</button>
                    <?php foreach ($categorias as $idCategoria => $nombreCategoria): ?>
                        <button type="button" data-categoria="<?= (int) $idCategoria ?>" aria-pressed="false"><?= e($nombreCategoria) ?></button>
                    <?php endforeach; ?>
                </div>
                <ul class="rejilla-venta" data-rejilla-venta aria-label="Productos para agregar">
                    <?php foreach ($productos as $fila): ?>
                        <?php
                        $p = $fila['producto'];
                        $s = $fila['stock'];
                        $foto = $fila['foto'];
                        $fotoClase = '';
                        $hay = $s === null ? 0.0 : $s->getCantidadActual();
                        $hayTexto = number_format($hay, $p->esAlPeso() ? 3 : 0) . ($p->esAlPeso() ? ' kg' : ' und');
                        $claseHay = $hay <= 0 ? 'error' : ($s !== null && $s->necesitaReponer() ? 'alerta' : 'ok');
                        ?>
                        <li data-categoria="<?= (int) $p->getIdCategoria() ?>">
                            <button type="button" class="producto-venta" data-agregar="<?= $p->getIdProducto() ?>"<?= $hay <= 0 ? ' disabled' : '' ?>>
                                <span class="producto-venta-foto">
                                    <?php require __DIR__ . '/../parciales/foto_producto.php'; ?>
                                    <span class="producto-venta-cantidad" data-en-carrito aria-hidden="true"></span>
                                    <?php if (isset($ofertas[$p->getIdProducto()])): ?><span class="producto-venta-oferta"><?= e($ofertas[$p->getIdProducto()][0]) ?></span><?php endif; ?>
                                    <?php if ($hay <= 0): ?><span class="producto-venta-agotado">Agotado</span><?php endif; ?>
                                </span>
                                <span class="producto-venta-nombre"><?= e($p->getNombreProducto()) ?></span>
                                <span class="producto-venta-pie">
                                    <strong><?= e(soles($p->getPrecioVenta())) ?><?= $p->esAlPeso() ? '<small>/kg</small>' : '' ?></strong>
                                    <?php if ($hay > 0): ?><span class="stock-punto <?= e($claseHay) ?>"><?= e($hayTexto) ?></span><?php endif; ?>
                                </span>
                                <span class="visualmente-oculto"><?= e($p->getCodigoBarras() ?? '') ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php require __DIR__ . '/../parciales/lineas_producto.php'; ?>
            <details class="bloque" data-sin-js>
                <summary>Ver precios y stock en <?= e($tienda->getNombreTienda()) ?></summary>
                <div class="tabla">
                <table>
                    <thead><tr><th scope="col">Producto</th><th scope="col" class="num">Precio</th><th scope="col" class="num">Stock</th></tr></thead>
                    <tbody>
                    <?php foreach ($productos as $fila): ?>
                        <?php $p = $fila['producto']; $s = $fila['stock']; ?>
                        <tr>
                            <td><?= e($p->getNombreProducto()) ?></td>
                            <td class="num"><?= e(soles($p->getPrecioVenta())) ?><?= $p->esAlPeso() ? ' /kg' : '' ?></td>
                            <td class="num"><?= $s === null ? 'Sin registro' : e(number_format($s->getCantidadActual(), $p->esAlPeso() ? 3 : 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </details>
        </section>

        <aside class="panel resumen venta-carrito" id="cobro" aria-label="Carrito y cobro" tabindex="-1">
            <div class="carrito-cabeza" data-carrito-cabeza hidden>
                <h2>Carrito <span class="etiqueta neutra" data-carrito-cuenta>0</span></h2>
                <button type="button" class="enlace" data-carrito-vaciar>Vaciar</button>
            </div>
            <ul class="carrito" data-carrito aria-live="polite" aria-label="Productos en la venta" hidden></ul>
            <template data-plantilla-carrito>
                <button type="button" class="carrito-paso" data-boton="menos"><?= icono('menos') ?></button>
                <button type="button" class="carrito-paso" data-boton="mas"><?= icono('mas') ?></button>
                <button type="button" class="carrito-quitar" data-boton="quitar"><?= icono('cerrar') ?></button>
            </template>
            <h2 data-sin-js>Cobro</h2>
            <?php if ($promocionesVenta !== []): ?>
                <p class="promo-venta"><?= icono('oferta') ?><span>Hoy: <?= e(implode(' · ', array_map(fn(Promocion $pr): string => $pr->descripcion(), $promocionesVenta))) ?></span></p>
            <?php endif; ?>
            <div class="total-vivo" data-total-vivo hidden aria-live="polite">
                <div class="total-grande"><span>Total</span><strong data-total>S/ 0.00</strong></div>
                <dl>
                    <dt data-fila-descuento hidden>Subtotal</dt><dd data-fila-descuento data-subtotal hidden>S/ 0.00</dd>
                    <dt data-fila-descuento hidden>Descuentos</dt><dd data-fila-descuento data-descuentos hidden>S/ 0.00</dd>
                    <dt data-rotulo-otros>Tarjeta o Sip</dt><dd data-otros>S/ 0.00</dd>
                    <dt>A pagar en efectivo</dt><dd data-falta>S/ 0.00</dd>
                    <dt>Vuelto</dt><dd data-vuelto>S/ 0.00</dd>
                </dl>
                <ul class="lista-descuentos" data-lista-descuentos hidden></ul>
                <ul class="avisos-vivo" data-avisos></ul>
                <p class="ayuda">Cálculo de ayuda: al registrar, el servidor vuelve a calcular con los precios y promociones de la base.</p>
            </div>
            <fieldset class="sin-borde">
                <legend>Comprobante</legend>
                <div class="opciones">
                    <label><input type="radio" name="tipo" value="B" <?= $v('tipo') !== 'F' ? 'checked' : '' ?>> Boleta</label>
                    <label><input type="radio" name="tipo" value="F" <?= $v('tipo') === 'F' ? 'checked' : '' ?>> Factura</label>
                </div>
            </fieldset>

            <div class="rotulo-con-estado">
                <label for="documento">DNI o RUC del cliente</label>
                <span class="etiqueta neutra" data-cliente aria-live="polite" hidden>Cliente varios</span>
            </div>
            <span class="ayuda">Vacío = CLIENTE VARIOS. La factura exige RUC.</span>
            <input id="documento" name="documento" inputmode="numeric" maxlength="11" autocomplete="off" value="<?= e($v('documento')) ?>">

            <?php if ($topeDescuento > 0): ?>
            <details class="pago" data-descuento<?= $conDescuento ? ' open' : '' ?>>
                <summary>Descuento a toda la compra</summary>
                <p class="ayuda">Hasta el <?= e(Promocion::porcentajeATexto($topeDescuento)) ?>, con su motivo: queda en la auditoría. Las promociones se aplican solas.</p>
                <div class="descuento-valor">
                    <div>
                        <label for="descuento_valor">Descuento</label>
                        <input id="descuento_valor" name="descuento_valor" inputmode="decimal" autocomplete="off" value="<?= e($v('descuento_valor')) ?>">
                    </div>
                    <div>
                        <label for="descuento_unidad">En</label>
                        <select id="descuento_unidad" name="descuento_unidad">
                            <option value="PORCENTAJE" <?= $v('descuento_unidad') !== 'MONTO' ? 'selected' : '' ?>>%</option>
                            <option value="MONTO" <?= $v('descuento_unidad') === 'MONTO' ? 'selected' : '' ?>>S/</option>
                        </select>
                    </div>
                </div>
                <label for="descuento_motivo">Motivo</label>
                <input id="descuento_motivo" name="descuento_motivo" maxlength="150" placeholder="Ej.: producto con empaque dañado" value="<?= e($v('descuento_motivo')) ?>">
            </details>
            <?php endif; ?>

            <fieldset class="sin-borde">
                <legend>Medio de pago</legend>
                <div class="opciones medios-pago" data-medios-pago>
                    <?php foreach ($medios as $valorMedio => [$textoMedio, $iconoMedio]): ?>
                        <label><input type="radio" name="medio_pago" value="<?= e($valorMedio) ?>" <?= $medioElegido === $valorMedio ? 'checked' : '' ?>><?php if ($iconoMedio === 'billete'): ?><?= icono('billete') ?><?php elseif ($iconoMedio === 'qr'): ?><?= icono('qr') ?><?php else: ?><?= icono('tarjeta') ?><?php endif; ?><?= e($textoMedio) ?></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="pago-medio" data-pago="tarjeta">
                <p class="sutil" data-sin-js>Solo para débito o crédito.</p>
                <label for="tarjeta_marca">Marca de la tarjeta</label>
                <input id="tarjeta_marca" name="tarjeta_marca" maxlength="20" list="marcas-tarjeta" autocomplete="off" value="<?= e($v('tarjeta_marca')) ?>">
                <datalist id="marcas-tarjeta"><option value="VISA"><option value="MASTERCARD"><option value="AMEX"><option value="DINERS"></datalist>
                <div class="descuento-valor">
                    <div>
                        <label for="tarjeta_ultimos4">Últimos 4 dígitos</label>
                        <input id="tarjeta_ultimos4" name="tarjeta_ultimos4" inputmode="numeric" maxlength="4" autocomplete="off" value="<?= e($v('tarjeta_ultimos4')) ?>">
                    </div>
                    <div>
                        <label for="tarjeta_autorizacion">Autorización</label>
                        <input id="tarjeta_autorizacion" name="tarjeta_autorizacion" maxlength="10" autocomplete="off" value="<?= e($v('tarjeta_autorizacion')) ?>">
                    </div>
                </div>
                <label for="tarjeta_monto">Monto con tarjeta <span class="ayuda">vacío = todo el total; si es menos, el resto en efectivo</span></label>
                <input id="tarjeta_monto" name="tarjeta_monto" inputmode="decimal" autocomplete="off" value="<?= e($v('tarjeta_monto')) ?>">
            </div>

            <div class="pago-medio" data-pago="sip">
                <p class="sutil" data-sin-js>Solo para Sip.</p>
                <label for="sip_modalidad">Modalidad</label>
                <select id="sip_modalidad" name="sip_modalidad">
                    <option value="QR" <?= $v('sip_modalidad') !== 'TARJETA_SIP' ? 'selected' : '' ?>>QR</option>
                    <option value="TARJETA_SIP" <?= $v('sip_modalidad') === 'TARJETA_SIP' ? 'selected' : '' ?>>Tarjeta Sip</option>
                </select>
                <label for="sip_operacion">Código de operación</label>
                <input id="sip_operacion" name="sip_operacion" maxlength="30" autocomplete="off" value="<?= e($v('sip_operacion')) ?>">
                <label for="sip_monto">Monto con Sip <span class="ayuda">vacío = todo el total; si es menos, el resto en efectivo</span></label>
                <input id="sip_monto" name="sip_monto" inputmode="decimal" autocomplete="off" value="<?= e($v('sip_monto')) ?>">
            </div>

            <div class="pago-medio" data-pago="efectivo">
                <label for="efectivo_recibido">Efectivo que entrega el cliente (S/)</label>
                <span class="ayuda">Cubre lo que falte del total; el vuelto sale en el comprobante.</span>
                <input id="efectivo_recibido" name="efectivo_recibido" inputmode="decimal" value="<?= e($v('efectivo_recibido')) ?>">
                <div class="efectivo-rapido" data-efectivo-rapido role="group" aria-label="Montos rápidos de efectivo" hidden></div>
            </div>

            <div class="botones-formulario">
                <button class="acento grande" type="submit" aria-keyshortcuts="F9"><?= icono('ok') ?>Registrar venta</button>
                <small>El sistema calcula el total con los precios vigentes y valida el stock. <span class="solo-teclado">Atajo: <kbd>F9</kbd>.</span></small>
            </div>
        </aside>
        <!-- Celular: el total siempre a la vista; "Cobrar" baja al carrito y al cobro (app.js la muestra con productos) -->
        <div class="cobro-fijo" data-cobro-fijo hidden>
            <div class="cobro-fijo-total">
                <span data-cobro-fijo-cuenta>0 productos</span>
                <strong data-cobro-fijo-monto>S/ 0.00</strong>
            </div>
            <a class="boton acento" href="#cobro" data-cobro-fijo-ir><?= icono('venta') ?>Cobrar</a>
        </div>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
