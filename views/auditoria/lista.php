<?php
declare(strict_types=1);
/**
 * V: bitacora de acciones delicadas de la tienda del administrador.
 * @var Tienda $tienda
 * @var string[] $acciones
 * @var ?string $accion
 * @var array<int, array{id_auditoria: string, fecha_hora: string, accion: string, detalle: ?string, empleado: string, rol: string}> $filas
 * @var Paginacion $paginacion
 * @var string $urlPagina
 */
$titulo = 'Auditoría';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Auditoría · <?= e($tienda->getNombreTienda()) ?></h1>
<p class="sutil">Quién anuló, cambió precios, tocó el personal, las claves, la tienda o las cajas, y cuándo. Nadie puede editar ni borrar esta lista.
   Las ventas, traslados, mermas y conteos tienen su propio historial.</p>

<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="auditoria">
    <label>Acción
        <select name="accion">
            <option value="">Todas</option>
            <?php foreach ($acciones as $opcion): ?>
                <option value="<?= e($opcion) ?>" <?= $opcion === $accion ? 'selected' : '' ?>><?= e($opcion) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Filtrar</button>
</form>

<?php if ($filas === []): ?>
    <p>Todavía no hay acciones registradas<?= $accion !== null ? ' de ese tipo' : '' ?>.</p>
<?php else: ?>
    <div class="tabla">
    <table>
        <tr><th>#</th><th>Fecha</th><th>Acción</th><th>Detalle</th><th>Quién</th></tr>
        <?php foreach ($filas as $f): ?>
            <tr>
                <td><?= (int) $f['id_auditoria'] ?></td>
                <td><?= e($f['fecha_hora']) ?></td>
                <td><span class="etiqueta <?= $f['accion'] === 'VENTA_ANULADA' || $f['accion'] === 'PRECIO_CAMBIADO' ? 'reponer' : 'anulada' ?>"><?= e($f['accion']) ?></span></td>
                <td><?= e($f['detalle'] ?? '') ?></td>
                <td><?= e($f['empleado']) ?> <small class="sutil">(<?= e($f['rol']) ?>)</small></td>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <p class="sutil"><?= $paginacion->getTotalFilas() ?> acciones.</p>
    <?php require __DIR__ . '/../parciales/paginacion.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
