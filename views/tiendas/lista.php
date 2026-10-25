<?php
declare(strict_types=1);
/**
 * V: las 3 tiendas con su distrito, cajas, series y personal.
 * @var array<int, array{tienda: Tienda, distrito: string, cajas: Caja[], series: SerieComprobante[], empleados: Usuario[]}> $filas
 */
$titulo = 'Tiendas';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Tiendas de Arequipa</h1>

<?php foreach ($filas as $fila): ?>
    <?php $t = $fila['tienda']; ?>
    <section class="comprobante">
        <h2><?= e($t->getNombreTienda()) ?>
            <span class="etiqueta <?= $t->estaActiva() ? 'ok' : 'anulada' ?>"><?= e($t->getEstado()) ?></span></h2>
        <p>Distrito: <?= e($fila['distrito']) ?> · <?= e($t->getDireccion()) ?> · Teléfono: <?= e($t->getTelefono() ?? '(no registrado)') ?></p>
        <p>Cajas:
            <?php foreach ($fila['cajas'] as $c): ?>
                Caja <?= $c->getNumeroCaja() ?> (<?= e($c->getEstado()) ?>)
            <?php endforeach; ?>
        </p>
        <p>Series:
            <?php foreach ($fila['series'] as $s): ?>
                <?= e($s->getSerie()) ?> <?= e($s->esFactura() ? 'factura' : 'boleta') ?>, último <?= $s->getUltimoNumero() ?> ·
            <?php endforeach; ?>
        </p>
        <table>
            <tr><th>Empleado</th><th>DNI</th><th>Rol</th><th>Vende</th><th>Anula ventas de su tienda</th><th>Traslados y mermas</th></tr>
            <?php foreach ($fila['empleados'] as $u): ?>
                <tr>
                    <td><?= e($u->nombreCompleto()) ?></td>
                    <td><?= e($u->dniOculto()) ?></td>
                    <td><?= e($u->getNombreRol()) ?></td>
                    <td><?= $u->puedeVender() ? 'Sí' : 'No' ?></td>
                    <td><?= $u->puedeAnularVenta($t->getIdTienda()) ? 'Sí' : 'No' ?></td>
                    <td><?= $u->puedeMoverMercaderia() ? 'Sí' : 'No' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>
    <br>
<?php endforeach; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
