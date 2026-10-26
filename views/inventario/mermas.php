<?php
declare(strict_types=1);
/**
 * V: mermas de una tienda y formulario para su almacenero.
 * @var array<int, Tienda> $tiendas
 * @var Tienda $tienda
 * @var string $rutaSelector
 * @var array<int, Producto> $productos
 * @var array<int, string> $nombresEmpleado
 * @var string[] $motivos
 * @var bool $puedeRegistrar
 * @var array<int, array{merma: Merma, movimientos: MovimientoStock[]}> $filas
 * @var array $viejo
 * @var ?string $error
 */
$titulo = 'Mermas';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Mermas de <?= e($tienda->getNombreTienda()) ?></h1>
<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<?php if ($puedeRegistrar): ?>
    <?php
    $opciones = array_filter($productos, fn(Producto $p): bool => $p->estaActivo());
    $filasLineas = 4;
    $motivoViejo = is_string($viejo['motivo'] ?? null) ? $viejo['motivo'] : '';
    ?>
    <form class="formulario panel" method="post" action="/?ruta=merma_registrar">
        <h2>Registrar merma</h2>
        <?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>
        <?= campoCsrf($csrf) ?>
        <label for="motivo">Motivo</label>
        <select id="motivo" name="motivo" required>
            <?php foreach ($motivos as $motivo): ?>
                <option value="<?= e($motivo) ?>" <?= $motivoViejo === $motivo ? 'selected' : '' ?>><?= e($motivo) ?></option>
            <?php endforeach; ?>
        </select>
        <?php require __DIR__ . '/../parciales/lineas_producto.php'; ?>
        <label for="observacion">Observación (opcional)</label>
        <input id="observacion" name="observacion" maxlength="200" size="60"
               value="<?= e(is_string($viejo['observacion'] ?? null) ? $viejo['observacion'] : '') ?>">
        <p><button type="submit">Registrar merma</button></p>
    </form>
<?php endif; ?>

<div class="tabla">
<table>
    <tr><th>#</th><th>Fecha</th><th>Motivo</th><th>Productos</th><th>Registró</th><th>Observación</th></tr>
    <?php foreach ($filas as $fila): ?>
        <?php $m = $fila['merma']; ?>
        <tr>
            <td><?= $m->getIdMerma() ?></td>
            <td><?= e($m->getFechaHora()) ?></td>
            <td><?= e($m->getMotivo()) ?></td>
            <td>
                <?php foreach ($fila['movimientos'] as $mov): ?>
                    <?php $p = $productos[$mov->getIdProducto()] ?? null; ?>
                    <?= e(number_format($mov->getCantidad(), $p?->esAlPeso() ? 3 : 0)) ?> · <?= e($p?->getNombreProducto() ?? '-') ?><br>
                <?php endforeach; ?>
            </td>
            <td><?= e($nombresEmpleado[$m->getIdEmpleado()] ?? '-') ?></td>
            <td><?= e($m->getObservacion() ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
