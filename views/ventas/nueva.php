<?php
declare(strict_types=1);
/**
 * V: pantalla de venta del cajero. Izquierda: productos; derecha: comprobante, cliente y cobro.
 * El total lo calcula el servidor con el precio de la base.
 * @var ?CajaTurno $turno
 * @var Tienda $tienda
 * @var array<int, array{producto: Producto, stock: ?Stock}> $productos
 * @var array $viejo
 * @var ?string $error
 */
$titulo = 'Nueva venta';
require __DIR__ . '/../layout_inicio.php';
$v = fn(string $campo): string => is_string($viejo[$campo] ?? null) ? $viejo[$campo] : '';
$opciones = array_column($productos, 'producto');
$filasLineas = 6;
// Los pagos con tarjeta o Sip se muestran abiertos si el cajero ya los habia llenado
$conTarjeta = $v('tarjeta_monto') !== '';
$conSip = $v('sip_monto') !== '';
?>
<div class="cabecera">
    <div>
        <h1>Nueva venta</h1>
        <p class="sutil"><?= e($tienda->getNombreTienda()) ?><?= e($turno !== null ? ' · turno ' . $turno->getIdCajaTurno() . ' abierto desde ' . $turno->getFechaHoraApertura() : '') ?></p>
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

    <form class="formulario caja-pantalla venta" method="post" action="/?ruta=venta_registrar">
        <?= campoCsrf($csrf) ?>
        <section class="panel">
            <h2>Productos</h2>
            <?php require __DIR__ . '/../parciales/lineas_producto.php'; ?>
            <details class="bloque">
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

        <aside class="panel resumen" aria-label="Cobro">
            <h2>Cobro</h2>
            <fieldset class="sin-borde">
                <legend>Comprobante</legend>
                <div class="opciones">
                    <label><input type="radio" name="tipo" value="B" <?= $v('tipo') !== 'F' ? 'checked' : '' ?>> Boleta</label>
                    <label><input type="radio" name="tipo" value="F" <?= $v('tipo') === 'F' ? 'checked' : '' ?>> Factura</label>
                </div>
            </fieldset>

            <label for="documento">DNI o RUC del cliente</label>
            <span class="ayuda">Vacío = CLIENTE VARIOS. La factura exige RUC.</span>
            <input id="documento" name="documento" inputmode="numeric" maxlength="11" autocomplete="off" value="<?= e($v('documento')) ?>">

            <label for="efectivo_recibido">Efectivo que entrega el cliente (S/)</label>
            <span class="ayuda">Cubre lo que falte del total; el vuelto sale en el comprobante.</span>
            <input id="efectivo_recibido" name="efectivo_recibido" inputmode="decimal" value="<?= e($v('efectivo_recibido')) ?>">

            <details class="pago"<?= $conTarjeta ? ' open' : '' ?>>
                <summary>Pagar una parte con tarjeta</summary>
                <label for="tarjeta_monto">Monto</label>
                <input id="tarjeta_monto" name="tarjeta_monto" inputmode="decimal" value="<?= e($v('tarjeta_monto')) ?>">
                <label for="tarjeta_tipo">Tipo</label>
                <select id="tarjeta_tipo" name="tarjeta_tipo">
                    <option value="CREDITO" <?= $v('tarjeta_tipo') === 'CREDITO' ? 'selected' : '' ?>>Crédito</option>
                    <option value="DEBITO" <?= $v('tarjeta_tipo') === 'DEBITO' ? 'selected' : '' ?>>Débito</option>
                </select>
                <label for="tarjeta_marca">Marca</label>
                <input id="tarjeta_marca" name="tarjeta_marca" maxlength="20" value="<?= e($v('tarjeta_marca')) ?>">
                <label for="tarjeta_ultimos4">Últimos 4 dígitos <span class="ayuda">nunca el número completo</span></label>
                <input id="tarjeta_ultimos4" name="tarjeta_ultimos4" inputmode="numeric" maxlength="4" value="<?= e($v('tarjeta_ultimos4')) ?>">
                <label for="tarjeta_autorizacion">Código de autorización</label>
                <input id="tarjeta_autorizacion" name="tarjeta_autorizacion" maxlength="10" value="<?= e($v('tarjeta_autorizacion')) ?>">
            </details>
            <details class="pago"<?= $conSip ? ' open' : '' ?>>
                <summary>Pagar una parte con billetera Sip</summary>
                <label for="sip_monto">Monto</label>
                <input id="sip_monto" name="sip_monto" inputmode="decimal" value="<?= e($v('sip_monto')) ?>">
                <label for="sip_modalidad">Modalidad</label>
                <select id="sip_modalidad" name="sip_modalidad">
                    <option value="QR" <?= $v('sip_modalidad') !== 'TARJETA_SIP' ? 'selected' : '' ?>>QR</option>
                    <option value="TARJETA_SIP" <?= $v('sip_modalidad') === 'TARJETA_SIP' ? 'selected' : '' ?>>Tarjeta Sip</option>
                </select>
                <label for="sip_operacion">Código de operación</label>
                <input id="sip_operacion" name="sip_operacion" maxlength="30" value="<?= e($v('sip_operacion')) ?>">
            </details>

            <div class="botones-formulario">
                <button class="acento grande" type="submit"><?= icono('ok') ?>Registrar venta</button>
                <small>El sistema calcula el total con los precios vigentes y valida el stock.</small>
            </div>
        </aside>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
