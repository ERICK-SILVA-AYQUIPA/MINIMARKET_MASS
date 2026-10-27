<?php
declare(strict_types=1);
/**
 * V: un cliente, sus compras en todas las tiendas y el formulario para completar su documento.
 * @var Cliente $cliente
 * @var array<int, array{venta: Venta, serie: SerieComprobante, tienda: ?Tienda}> $compras
 * @var ?string $error
 */
$titulo = 'Cliente';
require __DIR__ . '/../layout_inicio.php';
?>
<p><a href="/?ruta=clientes">← Volver a clientes</a></p>
<h1>Cliente <?= $cliente->getIdCliente() ?></h1>
<table>
    <tr><th>DNI</th><td><?= e($cliente->getDni() ?? '-') ?></td></tr>
    <tr><th>RUC</th><td><?= e($cliente->getRuc() ?? '-') ?><?= $cliente->puedeRecibirFactura() ? '' : ' (sin RUC no recibe factura)' ?></td></tr>
</table>

<?php if ($cliente->getDni() === null || $cliente->getRuc() === null): ?>
<form class="formulario panel" method="post" action="/?ruta=cliente_documento">
    <h2>Completar documento</h2>
    <?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>
    <?= campoCsrf($csrf) ?>
    <input type="hidden" name="id" value="<?= $cliente->getIdCliente() ?>">
    <?php if ($cliente->getDni() === null): ?>
        <label for="dni">DNI</label>
        <input id="dni" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8">
    <?php endif; ?>
    <?php if ($cliente->getRuc() === null): ?>
        <label for="ruc">RUC</label>
        <input id="ruc" name="ruc" inputmode="numeric" pattern="[0-9]{11}" maxlength="11">
    <?php endif; ?>
    <p><button type="submit">Guardar</button></p>
    <p>Un documento ya registrado no se cambia: sale impreso en sus comprobantes.</p>
</form>
<?php endif; ?>

<h2>Compras</h2>
<div class="tabla">
<table>
    <tr><th>Comprobante</th><th>Fecha</th><th>Tienda</th><th>Total</th><th>Estado</th></tr>
    <?php foreach ($compras as $fila): ?>
        <?php
        $v = $fila['venta'];
        $s = $fila['serie'];
        $enlace = '/?ruta=comprobante&serie=' . urlencode($s->getSerie()) . '&numero=' . $v->getNumeroCorrelativo();
        ?>
        <tr>
            <td><a href="<?= e($enlace) ?>"><?= e($s->formatear($v->getNumeroCorrelativo())) ?></a></td>
            <td><?= e($v->getFechaHora()) ?></td>
            <td><?= e($fila['tienda']?->getNombreTienda() ?? '-') ?></td>
            <td class="num"><?= e(soles($v->getTotal())) ?></td>
            <td><span class="etiqueta <?= $v->estaAnulada() ? 'anulada' : 'ok' ?>"><?= e($v->getEstado()) ?></span></td>
        </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
