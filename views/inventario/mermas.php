<?php
declare(strict_types=1);
/**
 * V: mermas de una tienda. El almacenero registra desde un panel lateral (#nueva-merma).
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
<div class="cabecera">
    <div>
        <h1>Mermas de <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Productos que se pierden: vencidos, dañados o robados. Cada merma baja el stock y queda en el kardex.</p>
    </div>
    <?php if ($puedeRegistrar): ?><div class="acciones"><a class="boton acento" href="#nueva-merma"><?= icono('mermas') ?>Registrar merma</a></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = 'Sin mermas en esta tienda';
    $vacioTexto = 'Buena señal: no se ha registrado mercadería perdida.';
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">#</th><th scope="col">Fecha</th><th scope="col">Motivo</th><th scope="col">Productos</th><th scope="col">Registró</th><th scope="col">Observación</th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $fila): ?>
        <?php $m = $fila['merma']; ?>
        <tr>
            <td><?= $m->getIdMerma() ?></td>
            <td class="fecha"><?= e($m->getFechaHora()) ?></td>
            <td><span class="etiqueta alerta"><?= e($m->getMotivo()) ?></span></td>
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
    </tbody>
</table>
</div>
<?php endif; ?>

<?php if ($puedeRegistrar): ?>
    <?php
    $opciones = array_filter($productos, fn(Producto $p): bool => $p->estaActivo());
    $filasLineas = 4;
    $motivoViejo = is_string($viejo['motivo'] ?? null) ? $viejo['motivo'] : '';
    $drawerId = 'nueva-merma';
    $drawerTitulo = 'Registrar merma';
    $drawerSubtitulo = $tienda->getNombreTienda();
    $drawerCerrar = '/?ruta=mermas';
    $drawerAbierto = $error !== null; // si hubo un error, vuelve abierto con lo escrito
    $drawerAncho = true;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    ?>
    <form class="formulario" method="post" action="/?ruta=merma_registrar">
        <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?= campoCsrf($csrf) ?>
        <label for="motivo">Motivo</label>
        <select id="motivo" name="motivo" required>
            <?php foreach ($motivos as $motivo): ?>
                <option value="<?= e($motivo) ?>" <?= $motivoViejo === $motivo ? 'selected' : '' ?>><?= e($motivo) ?></option>
            <?php endforeach; ?>
        </select>
        <h3>Productos perdidos</h3>
        <?php require __DIR__ . '/../parciales/lineas_producto.php'; ?>
        <label for="observacion">Observación <span class="ayuda">opcional</span></label>
        <input id="observacion" name="observacion" maxlength="200"
               value="<?= e(is_string($viejo['observacion'] ?? null) ? $viejo['observacion'] : '') ?>">
        <div class="botones-formulario">
            <button class="acento" type="submit"><?= icono('mermas') ?>Registrar merma</button>
            <small>Baja el stock de la tienda y no se puede deshacer.</small>
        </div>
    </form>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
