<?php
declare(strict_types=1);
/**
 * V: traslados de una tienda y formulario para el almacenero.
 * @var array<int, Tienda> $tiendas
 * @var Tienda $tienda
 * @var string $rutaSelector
 * @var array<int, Producto> $productos
 * @var array<int, string> $nombresEmpleado
 * @var bool $puedeRegistrar
 * @var array<int, array{traslado: TrasladoMercaderia, movimientos: MovimientoStock[]}> $filas
 * @var array $viejo
 * @var ?string $error
 */
$titulo = 'Traslados';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Traslados de <?= e($tienda->getNombreTienda()) ?></h1>
<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<?php if ($puedeRegistrar): ?>
    <?php
    $opciones = array_filter($productos, fn(Producto $p): bool => $p->estaActivo());
    $filasLineas = 4;
    $destinoViejo = is_string($viejo['destino'] ?? null) ? $viejo['destino'] : '';
    ?>
    <form class="formulario panel" method="post" action="/?ruta=traslado_registrar">
        <h2>Enviar mercadería desde <?= e($tiendas[$usuarioActual->getIdTienda()]->getNombreTienda()) ?></h2>
        <?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>
        <?= campoCsrf($csrf) ?>
        <label for="destino">Tienda de destino</label>
        <select id="destino" name="destino" required>
            <?php foreach ($tiendas as $id => $t): ?>
                <?php if ($id !== $usuarioActual->getIdTienda() && $t->estaActiva()): ?>
                    <option value="<?= $id ?>" <?= $destinoViejo === (string) $id ? 'selected' : '' ?>><?= e($t->getNombreTienda()) ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <?php require __DIR__ . '/../parciales/lineas_producto.php'; ?>
        <label for="observacion">Observación (opcional)</label>
        <input id="observacion" name="observacion" maxlength="200" size="60"
               value="<?= e(is_string($viejo['observacion'] ?? null) ? $viejo['observacion'] : '') ?>">
        <p><button type="submit">Registrar traslado</button></p>
    </form>
<?php endif; ?>

<div class="tabla">
<table>
    <tr><th>#</th><th>Fecha</th><th>Origen → Destino</th><th>Productos</th><th>Registró</th><th>Observación</th></tr>
    <?php foreach ($filas as $fila): ?>
        <?php $t = $fila['traslado']; ?>
        <tr>
            <td><?= $t->getIdTraslado() ?></td>
            <td><?= e($t->getFechaHora()) ?></td>
            <td><?= e(($tiendas[$t->getIdTiendaOrigen()] ?? null)?->getNombreTienda() ?? '-') ?> → <?= e(($tiendas[$t->getIdTiendaDestino()] ?? null)?->getNombreTienda() ?? '-') ?></td>
            <td>
                <?php foreach ($fila['movimientos'] as $m): ?>
                    <?php $p = $productos[$m->getIdProducto()] ?? null; ?>
                    <?= e(number_format($m->getCantidad(), $p?->esAlPeso() ? 3 : 0)) ?> · <?= e($p?->getNombreProducto() ?? '-') ?><br>
                <?php endforeach; ?>
            </td>
            <td><?= e($nombresEmpleado[$t->getIdEmpleado()] ?? '-') ?></td>
            <td><?= e($t->getObservacion() ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
