<?php
declare(strict_types=1);
/**
 * V: traslados de una tienda. El almacenero envia mercaderia desde un panel lateral (#nuevo-traslado).
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
<div class="cabecera">
    <div>
        <h1>Traslados de <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Mercadería que sale de una tienda y entra a otra. Cada traslado mueve el kardex de las dos.</p>
    </div>
    <?php if ($puedeRegistrar): ?><div class="acciones"><a class="boton acento" href="#nuevo-traslado"><?= icono('traslados') ?>Enviar mercadería</a></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = 'Sin traslados en esta tienda';
    $vacioTexto = 'Cuando una tienda envíe mercadería a otra, el envío aparecerá aquí en las dos.';
    $vacioAccion = $puedeRegistrar ? ['#nuevo-traslado', 'Enviar mercadería'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">#</th><th scope="col">Fecha</th><th scope="col">Origen → Destino</th><th scope="col">Productos</th><th scope="col">Registró</th><th scope="col">Observación</th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $fila): ?>
        <?php $t = $fila['traslado']; ?>
        <tr>
            <td><?= $t->getIdTraslado() ?></td>
            <td class="fecha"><?= e(fecha($t->getFechaHora())) ?></td>
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
    </tbody>
</table>
</div>
<?php endif; ?>

<?php if ($puedeRegistrar): ?>
    <?php
    $opciones = array_filter($productos, fn(Producto $p): bool => $p->estaActivo());
    $filasLineas = 4;
    $destinoViejo = is_string($viejo['destino'] ?? null) ? $viejo['destino'] : '';
    $drawerId = 'nuevo-traslado';
    $drawerTitulo = 'Enviar mercadería';
    $drawerSubtitulo = 'Sale de ' . $tiendas[$usuarioActual->getIdTienda()]->getNombreTienda();
    $drawerCerrar = '/?ruta=traslados';
    $drawerAbierto = $error !== null; // si hubo un error, vuelve abierto con lo escrito
    $drawerAncho = true;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    ?>
    <form class="formulario" method="post" action="/?ruta=traslado_registrar" data-confirmar="¿Enviar esta mercadería? El stock sale de tu tienda y entra a la de destino." data-confirmar-boton="Sí, enviar">
        <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?= campoCsrf($csrf) ?>
        <label for="destino">Tienda de destino</label>
        <select id="destino" name="destino" required>
            <?php foreach ($tiendas as $id => $t): ?>
                <?php if ($id !== $usuarioActual->getIdTienda() && $t->estaActiva()): ?>
                    <option value="<?= $id ?>" <?= $destinoViejo === (string) $id ? 'selected' : '' ?>><?= e($t->getNombreTienda()) ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <h3>Productos que salen</h3>
        <?php require __DIR__ . '/../parciales/lineas_producto.php'; ?>
        <label for="observacion">Observación <span class="ayuda">opcional</span></label>
        <input id="observacion" name="observacion" maxlength="200"
               value="<?= e(is_string($viejo['observacion'] ?? null) ? $viejo['observacion'] : '') ?>">
        <div class="botones-formulario">
            <button class="acento" type="submit"><?= icono('traslados') ?>Registrar traslado</button>
            <small>Baja el stock aquí y lo sube en la tienda de destino, en una sola operación.</small>
        </div>
    </form>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
