<?php
declare(strict_types=1);
/**
 * V: datos de un cliente, sus datos de contacto (nombre, dirección, teléfono), el formulario para completar su documento y sus compras.
 * La usan la pagina del cliente y el panel lateral de la lista.
 * @var Cliente $cliente
 * @var array<int, array{venta: Venta, serie: SerieComprobante, tienda: ?Tienda}> $compras
 * @var ?string $error
 * @var ?string $csrf
 */
$error ??= null;
$totalCompras = array_sum(array_map(static fn (array $f): float => $f['venta']->estaAnulada() ? 0.0 : $f['venta']->getTotal(), $compras));
?>
<dl class="datos">
    <dt>Nombre</dt><dd><?= e($cliente->getNombre() ?? '-') ?></dd>
    <dt>DNI</dt><dd><?= e($cliente->getDni() ?? '-') ?></dd>
    <dt>RUC</dt><dd><?= e($cliente->getRuc() ?? '-') ?><?= $cliente->puedeRecibirFactura() ? '' : ' (sin RUC no recibe factura)' ?></dd>
    <dt>Dirección</dt><dd><?= e($cliente->getDireccion() ?? '-') ?></dd>
    <dt>Teléfono</dt><dd><?= e($cliente->getTelefono() ?? '-') ?></dd>
    <dt>Compras</dt><dd><?= count($compras) ?> comprobantes · <?= e(soles($totalCompras)) ?> sin contar anuladas</dd>
</dl>

<form class="formulario bloque" method="post" action="/?ruta=cliente_contacto" autocomplete="off">
    <h3>Datos de contacto</h3>
    <?= campoCsrf($csrf) ?>
    <input type="hidden" name="id" value="<?= $cliente->getIdCliente() ?>">
    <label for="nombre-<?= $cliente->getIdCliente() ?>">Nombre</label>
    <input id="nombre-<?= $cliente->getIdCliente() ?>" name="nombre" maxlength="100" value="<?= e($cliente->getNombre() ?? '') ?>">
    <label for="direccion-<?= $cliente->getIdCliente() ?>">Dirección</label>
    <input id="direccion-<?= $cliente->getIdCliente() ?>" name="direccion" maxlength="120" value="<?= e($cliente->getDireccion() ?? '') ?>">
    <label for="telefono-<?= $cliente->getIdCliente() ?>">Teléfono <span class="ayuda">celular de 9 dígitos o fijo de 6 a 9</span></label>
    <input id="telefono-<?= $cliente->getIdCliente() ?>" name="telefono" type="tel" inputmode="numeric" maxlength="11" value="<?= e($cliente->getTelefono() ?? '') ?>">
    <div class="botones-formulario"><button type="submit">Guardar datos</button></div>
</form>

<?php if ($cliente->getDni() === null || $cliente->getRuc() === null): ?>
<form class="formulario bloque" method="post" action="/?ruta=cliente_documento">
    <h3>Completar documento</h3>
    <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= campoCsrf($csrf) ?>
    <input type="hidden" name="id" value="<?= $cliente->getIdCliente() ?>">
    <?php if ($cliente->getDni() === null): ?>
        <label for="dni-<?= $cliente->getIdCliente() ?>">DNI <span class="ayuda">8 dígitos</span></label>
        <input id="dni-<?= $cliente->getIdCliente() ?>" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8">
    <?php endif; ?>
    <?php if ($cliente->getRuc() === null): ?>
        <label for="ruc-<?= $cliente->getIdCliente() ?>">RUC <span class="ayuda">11 dígitos, empieza con 10 o 20</span></label>
        <input id="ruc-<?= $cliente->getIdCliente() ?>" name="ruc" inputmode="numeric" pattern="[0-9]{11}" maxlength="11">
    <?php endif; ?>
    <div class="botones-formulario">
        <button type="submit">Guardar</button>
        <small>Un documento ya registrado no se cambia: sale impreso en sus comprobantes.</small>
    </div>
</form>
<?php endif; ?>

<h3 class="bloque">Compras</h3>
<?php if ($compras === []): ?>
    <?php
    $vacioTitulo = 'Sin compras todavía';
    $vacioTexto = 'Las compras aparecen aquí cuando el cajero pone su DNI o RUC en una venta.';
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">Comprobante</th><th scope="col">Fecha</th><th scope="col">Tienda</th><th scope="col" class="num">Total</th><th scope="col">Estado</th></tr>
    </thead>
    <tbody>
    <?php foreach ($compras as $fila): ?>
        <?php
        $v = $fila['venta'];
        $s = $fila['serie'];
        $enlace = '/?ruta=comprobante&serie=' . urlencode($s->getSerie()) . '&numero=' . $v->getNumeroCorrelativo();
        ?>
        <tr>
            <td class="codigo"><a class="fila-enlace" href="<?= e($enlace) ?>"><?= e($s->formatear($v->getNumeroCorrelativo())) ?></a></td>
            <td class="fecha"><?= e(fecha($v->getFechaHora())) ?></td>
            <td><?= e($fila['tienda']?->getNombreTienda() ?? '-') ?></td>
            <td class="num"><?= e(soles($v->getTotal())) ?></td>
            <td><span class="etiqueta <?= $v->estaAnulada() ? 'anulada' : 'ok' ?>"><?= e($v->getEstado()) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
