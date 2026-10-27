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
<h1>Ajustes de inventario de <?= e($tienda->getNombreTienda()) ?></h1>
<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<?php if ($puedeRegistrar): ?>
    <form class="formulario panel" method="post" action="/?ruta=ajuste_registrar">
        <h2>Registrar conteo físico</h2>
        <p>Escribe solo lo que contaste. Lo que dejes vacío no se toca; si escribes 0, el producto queda en 0.</p>
        <?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>
        <?= campoCsrf($csrf) ?>
        <label for="motivo">Motivo</label>
        <select id="motivo" name="motivo">
            <?php foreach ($motivos as $motivo): ?>
                <option value="<?= e($motivo) ?>" <?= $motivoViejo === $motivo ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $motivo)) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="tabla">
        <table>
            <tr><th>Producto</th><th>En el sistema</th><th>Contado</th></tr>
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
        </table>
        </div>
        <label for="observacion">Observación (opcional)</label>
        <input id="observacion" name="observacion" maxlength="200" size="60"
               value="<?= e(is_string($viejo['observacion'] ?? null) ? $viejo['observacion'] : '') ?>">
        <p><button type="submit">Guardar conteo</button></p>
    </form>
<?php endif; ?>

<h2>Ajustes registrados</h2>
<div class="tabla">
<table>
    <tr><th>#</th><th>Fecha</th><th>Motivo</th><th>Diferencias</th><th>Registró</th><th>Observación</th></tr>
    <?php if ($filas === []): ?>
        <tr><td colspan="6">Todavía no hay ajustes en esta tienda.</td></tr>
    <?php endif; ?>
    <?php foreach ($filas as $fila): ?>
        <?php $a = $fila['ajuste']; ?>
        <tr>
            <td><?= $a->getIdAjuste() ?></td>
            <td><?= e($a->getFechaHora()) ?></td>
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
</table>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
