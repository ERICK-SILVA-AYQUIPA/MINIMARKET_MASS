<?php
declare(strict_types=1);
/**
 * V: filas para elegir producto y cantidad (venta, traslado, merma).
 * @var array<int, Producto> $opciones  productos que se pueden elegir
 * @var array $viejo                     lo que el usuario escribio (si hubo un error)
 * @var int $filasLineas
 */
$productosViejos = is_array($viejo['producto'] ?? null) ? $viejo['producto'] : [];
$cantidadesViejas = is_array($viejo['cantidad'] ?? null) ? $viejo['cantidad'] : [];
?>
<table>
    <tr><th>Producto</th><th>Cantidad (unidades, o kg con hasta 3 decimales)</th></tr>
    <?php for ($i = 0; $i < $filasLineas; $i++): ?>
        <?php $elegido = is_string($productosViejos[$i] ?? null) ? $productosViejos[$i] : ''; ?>
        <tr>
            <td>
                <select name="producto[]" aria-label="Producto <?= $i + 1 ?>">
                    <option value="">—</option>
                    <?php foreach ($opciones as $p): ?>
                        <option value="<?= $p->getIdProducto() ?>" <?= $elegido === (string) $p->getIdProducto() ? 'selected' : '' ?>>
                            <?= e($p->getNombreProducto()) ?> · <?= e(soles($p->getPrecioVenta())) ?><?= $p->esAlPeso() ? ' /kg' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input name="cantidad[]" inputmode="decimal" size="8" aria-label="Cantidad <?= $i + 1 ?>"
                       value="<?= e(is_string($cantidadesViejas[$i] ?? null) ? $cantidadesViejas[$i] : '') ?>"></td>
        </tr>
    <?php endfor; ?>
</table>
