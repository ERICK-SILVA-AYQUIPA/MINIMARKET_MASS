<?php
declare(strict_types=1);
/**
 * V: formulario de venta del cajero. El total lo calcula el servidor con el precio de la base.
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
?>
<h1>Nueva venta · <?= e($tienda->getNombreTienda()) ?></h1>

<?php if ($turno === null): ?>
    <p class="aviso error">No tienes un turno abierto. <a href="/?ruta=caja">Abre tu turno de caja</a> para vender.</p>
<?php else: ?>
    <p>Turno <?= $turno->getIdCajaTurno() ?> abierto desde <?= e($turno->getFechaHoraApertura()) ?>.</p>
    <?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>

    <form class="formulario panel" method="post" action="/?ruta=venta_registrar">
        <?= campoCsrf($csrf) ?>

        <label>Comprobante</label>
        <label style="display:inline; font-weight:400"><input type="radio" name="tipo" value="B" <?= $v('tipo') !== 'F' ? 'checked' : '' ?>> Boleta</label>
        <label style="display:inline; font-weight:400"><input type="radio" name="tipo" value="F" <?= $v('tipo') === 'F' ? 'checked' : '' ?>> Factura</label>

        <label for="documento">DNI o RUC del cliente (vacío = CLIENTE VARIOS; la factura exige RUC)</label>
        <input id="documento" name="documento" inputmode="numeric" maxlength="11" value="<?= e($v('documento')) ?>">

        <h3>Productos</h3>
        <?php require __DIR__ . '/../parciales/lineas_producto.php'; ?>

        <h3>Pago</h3>
        <p>Tarjeta y Sip se cobran por el monto que escribas; el efectivo cubre lo que falte del total.</p>
        <fieldset>
            <legend>Tarjeta (opcional)</legend>
            <label for="tarjeta_monto">Monto</label>
            <input id="tarjeta_monto" name="tarjeta_monto" inputmode="decimal" value="<?= e($v('tarjeta_monto')) ?>">
            <label for="tarjeta_tipo">Tipo</label>
            <select id="tarjeta_tipo" name="tarjeta_tipo">
                <option value="CREDITO" <?= $v('tarjeta_tipo') === 'CREDITO' ? 'selected' : '' ?>>Crédito</option>
                <option value="DEBITO" <?= $v('tarjeta_tipo') === 'DEBITO' ? 'selected' : '' ?>>Débito</option>
            </select>
            <label for="tarjeta_marca">Marca</label>
            <input id="tarjeta_marca" name="tarjeta_marca" maxlength="20" value="<?= e($v('tarjeta_marca')) ?>">
            <label for="tarjeta_ultimos4">Últimos 4 dígitos (nunca el número completo)</label>
            <input id="tarjeta_ultimos4" name="tarjeta_ultimos4" inputmode="numeric" maxlength="4" value="<?= e($v('tarjeta_ultimos4')) ?>">
            <label for="tarjeta_autorizacion">Código de autorización</label>
            <input id="tarjeta_autorizacion" name="tarjeta_autorizacion" maxlength="10" value="<?= e($v('tarjeta_autorizacion')) ?>">
        </fieldset>
        <fieldset>
            <legend>Billetera digital Sip (opcional)</legend>
            <label for="sip_monto">Monto</label>
            <input id="sip_monto" name="sip_monto" inputmode="decimal" value="<?= e($v('sip_monto')) ?>">
            <label for="sip_modalidad">Modalidad</label>
            <select id="sip_modalidad" name="sip_modalidad">
                <option value="QR" <?= $v('sip_modalidad') !== 'TARJETA_SIP' ? 'selected' : '' ?>>QR</option>
                <option value="TARJETA_SIP" <?= $v('sip_modalidad') === 'TARJETA_SIP' ? 'selected' : '' ?>>Tarjeta Sip</option>
            </select>
            <label for="sip_operacion">Código de operación</label>
            <input id="sip_operacion" name="sip_operacion" maxlength="30" value="<?= e($v('sip_operacion')) ?>">
        </fieldset>
        <fieldset>
            <legend>Efectivo</legend>
            <label for="efectivo_recibido">Monto que entrega el cliente</label>
            <input id="efectivo_recibido" name="efectivo_recibido" inputmode="decimal" value="<?= e($v('efectivo_recibido')) ?>">
        </fieldset>

        <p><button type="submit">Registrar venta</button></p>
    </form>

    <h3>Stock en <?= e($tienda->getNombreTienda()) ?></h3>
    <div class="tabla">
    <table>
        <tr><th>Producto</th><th>Precio</th><th>Stock</th></tr>
        <?php foreach ($productos as $fila): ?>
            <?php $p = $fila['producto']; $s = $fila['stock']; ?>
            <tr>
                <td><?= e($p->getNombreProducto()) ?></td>
                <td class="num"><?= e(soles($p->getPrecioVenta())) ?><?= $p->esAlPeso() ? ' /kg' : '' ?></td>
                <td class="num"><?= $s === null ? 'Sin registro' : e(number_format($s->getCantidadActual(), $p->esAlPeso() ? 3 : 0)) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
