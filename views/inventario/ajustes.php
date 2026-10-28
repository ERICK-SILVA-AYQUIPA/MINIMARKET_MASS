<?php
declare(strict_types=1);
/**
 * V: hoja de conteo fisico de una tienda y los ajustes ya hechos.
 * @var array<int, Tienda> $tiendas
 * @var Tienda $tienda
 * @var string $rutaSelector
 * @var array<int, Producto> $productos
 * @var array<int, ?Stock> $stocks       lo que dice el sistema de cada producto
 * @var array<int, string> $nombresEmpleado
 * @var array<int, TipoMovimiento> $tipos
 * @var string[] $motivos
 * @var bool $puedeRegistrar
 * @var array<int, array{ajuste: AjusteInventario, movimientos: MovimientoStock[]}> $filas
 * @var array $viejo
 * @var ?string $error
 */
$titulo = 'Ajustes de inventario';
require __DIR__ . '/../layout_inicio.php';
$cantidadesViejas = is_array($viejo['cantidad'] ?? null) ? $viejo['cantidad'] : [];
$motivoViejo = is_string($viejo['motivo'] ?? null) ? $viejo['motivo'] : 'CONTEO_FISICO';
?>
<div class="cabecera">
    <div>
        <h1>Conteo físico de <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Si lo contado no coincide con el sistema, el ajuste corrige el stock y deja la diferencia en el kardex.</p>
    </div>
</div>
<?php
$guiaClave = 'conteo';
$guiaTitulo = 'Cómo hacer el conteo físico';
$guiaPasos = [
    'Cuenta lo que hay en el anaquel y escribe la cantidad real de cada producto.',
    'El sistema compara con lo que dice el stock y registra la diferencia como ajuste de entrada o de salida en el kardex.',
];
require __DIR__ . '/../parciales/guia.php';
?>
<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<?php if ($puedeRegistrar): ?>
    <form class="formulario panel" method="post" action="/?ruta=ajuste_registrar" data-confirmar="¿Guardar el conteo? Lo contado reemplaza al stock del sistema." data-confirmar-boton="Sí, guardar">
        <h2>Hoja de conteo</h2>
        <div class="aviso info">Escribe solo lo que contaste. Lo que dejes vacío no se toca; si escribes 0, el producto queda en 0.</div>
        <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?= campoCsrf($csrf) ?>
        <label for="motivo">Motivo</label>
        <select id="motivo" name="motivo">
            <?php foreach ($motivos as $motivo): ?>
                <option value="<?= e($motivo) ?>" <?= $motivoViejo === $motivo ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $motivo)) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="tabla">
        <table>
            <thead><tr><th scope="col">Producto</th><th scope="col" class="num">En el sistema</th><th scope="col">Contado</th></tr></thead>
            <tbody>
            <?php $i = 0; ?>
            <?php foreach ($productos as $id => $p): ?>
                <?php $decimales = $p->esAlPeso() ? 3 : 0; ?>
                <tr>
                    <td><?= e($p->getNombreProducto()) ?><?= $p->estaActivo() ? '' : ' (descontinuado)' ?></td>
                    <td class="num"><?= e(number_format($stocks[$id]?->getCantidadActual() ?? 0, $decimales)) ?> <?= $p->esAlPeso() ? 'kg' : 'und' ?></td>
                    <td>
                        <input type="hidden" name="producto[<?= $i ?>]" value="<?= $id ?>">
                        <input name="cantidad[<?= $i ?>]" inputmode="decimal" size="8" aria-label="Contado de <?= e($p->getNombreProducto()) ?>"
                               value="<?= e(is_string($cantidadesViejas[$i] ?? null) ? $cantidadesViejas[$i] : '') ?>">
                    </td>
                </tr>
                <?php $i++; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <label for="observacion">Observación <span class="ayuda">opcional</span></label>
        <input id="observacion" name="observacion" maxlength="200"
               value="<?= e(is_string($viejo['observacion'] ?? null) ? $viejo['observacion'] : '') ?>">
        <div class="botones-formulario">
            <button class="acento" type="submit"><?= icono('conteo') ?>Guardar conteo</button>
            <small>Solo se ajustan los productos cuyo conteo difiere del sistema.</small>
        </div>
    </form>
<?php else: ?>
    <div class="aviso info">El conteo lo registran el almacenero y el administrador de la tienda. Aquí puedes ver los ajustes ya hechos.</div>
<?php endif; ?>

<h2>Ajustes registrados</h2>
<div class="tabla">
<table>
    <thead><tr><th scope="col">#</th><th scope="col">Fecha</th><th scope="col">Motivo</th><th scope="col">Diferencias</th><th scope="col">Registró</th><th scope="col">Observación</th></tr></thead>
    <tbody>
    <?php if ($filas === []): ?>
        <tr><td colspan="6" class="sutil centro">Todavía no hay ajustes en esta tienda.</td></tr>
    <?php endif; ?>
    <?php foreach ($filas as $fila): ?>
        <?php $a = $fila['ajuste']; ?>
        <tr>
            <td><?= $a->getIdAjuste() ?></td>
            <td class="fecha"><?= e(fecha($a->getFechaHora())) ?></td>
            <td><?= e(str_replace('_', ' ', $a->getMotivo())) ?></td>
            <td>
                <?php foreach ($fila['movimientos'] as $mov): ?>
                    <?php
                    $p = $productos[$mov->getIdProducto()] ?? null;
                    $entra = ($tipos[$mov->getIdTipoMovimiento()] ?? null)?->esEntrada() ?? false;
                    ?>
                    <?= $entra ? '+' : '−' ?><?= e(number_format($mov->getCantidad(), $p?->esAlPeso() ? 3 : 0)) ?>
                    · <?= e($p?->getNombreProducto() ?? '-') ?> (queda <?= e(number_format($mov->getStockNuevo(), $p?->esAlPeso() ? 3 : 0)) ?>)<br>
                <?php endforeach; ?>
            </td>
            <td><?= e($nombresEmpleado[$a->getIdEmpleado()] ?? '-') ?></td>
            <td><?= e($a->getObservacion() ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
