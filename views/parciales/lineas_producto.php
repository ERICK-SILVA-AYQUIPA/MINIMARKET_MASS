<?php
declare(strict_types=1);
/**
 * V: filas para elegir producto y cantidad (venta, traslado, merma; el ingreso agrega el costo).
 * @var array<int, Producto> $opciones  productos que se pueden elegir
 * @var array $viejo                     lo que el usuario escribio (si hubo un error)
 * @var int $filasLineas
 * @var bool $conCosto                 true en el ingreso de mercaderia: pide el costo unitario
 */
$conCosto ??= false;
$productosViejos = is_array($viejo['producto'] ?? null) ? $viejo['producto'] : [];
$cantidadesViejas = is_array($viejo['cantidad'] ?? null) ? $viejo['cantidad'] : [];
$costosViejos = is_array($viejo['costo'] ?? null) ? $viejo['costo'] : [];
?>
<div class="tabla lineas">
<table>
    <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">Producto</th>
            <th scope="col">Cantidad <span class="ayuda">unidades, o kg con 3 decimales</span></th>
            <?php if ($conCosto): ?><th scope="col">Costo unitario S/ <span class="ayuda">por unidad o kg</span></th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php for ($i = 0; $i < $filasLineas; $i++): ?>
        <?php $elegido = is_string($productosViejos[$i] ?? null) ? $productosViejos[$i] : ''; ?>
        <tr>
            <td class="sutil"><?= $i + 1 ?></td>
            <td>
                <select name="producto[]" aria-label="Producto <?= $i + 1 ?>">
                    <option value="">— Elegir producto —</option>
                    <?php foreach ($opciones as $p): ?>
                        <option value="<?= $p->getIdProducto() ?>" <?= $elegido === (string) $p->getIdProducto() ? 'selected' : '' ?>>
                            <?= e($p->getNombreProducto()) ?> · <?= e(soles($p->getPrecioVenta())) ?><?= $p->esAlPeso() ? ' /kg' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input name="cantidad[]" inputmode="decimal" size="8" aria-label="Cantidad <?= $i + 1 ?>"
                       value="<?= e(is_string($cantidadesViejas[$i] ?? null) ? $cantidadesViejas[$i] : '') ?>"></td>
            <?php if ($conCosto): ?>
                <td><input name="costo[]" inputmode="decimal" size="8" aria-label="Costo unitario <?= $i + 1 ?>"
                           value="<?= e(is_string($costosViejos[$i] ?? null) ? $costosViejos[$i] : '') ?>"></td>
            <?php endif; ?>
        </tr>
    <?php endfor; ?>
    </tbody>
</table>
</div>
