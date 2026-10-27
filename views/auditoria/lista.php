<?php
declare(strict_types=1);
/**
 * V: bitacora de acciones delicadas de la tienda del administrador. Cada fila abre su detalle (#aud-ID).
 * @var Tienda $tienda
 * @var string[] $acciones
 * @var ?string $accion
 * @var array<int, array{id_auditoria: string, fecha_hora: string, accion: string, detalle: ?string, empleado: string, rol: string}> $filas
 * @var Paginacion $paginacion
 * @var string $urlPagina
 */
$titulo = 'Auditoría';
require __DIR__ . '/../layout_inicio.php';
// Acciones que conviene mirar primero
$delicada = fn(string $a): bool => in_array($a, ['VENTA_ANULADA', 'PRECIO_CAMBIADO'], true);
?>
<div class="cabecera">
    <div>
        <h1>Auditoría · <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil"><?= $paginacion->getTotalFilas() ?> acciones. Nadie puede editar ni borrar esta lista.</p>
    </div>
</div>
<div class="aviso info">Aquí queda quién anuló, cambió precios, tocó el personal, las claves, la tienda o las cajas, y cuándo.
    Las ventas, traslados, mermas y conteos tienen su propio historial.</div>

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
    <?php if ($accion !== null): ?><a class="boton secundario" href="/?ruta=auditoria">Ver todas</a><?php endif; ?>
</form>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = 'Todavía no hay acciones registradas' . ($accion !== null ? ' de ese tipo' : '');
    $vacioTexto = 'Cuando alguien anule una venta, cambie un precio o toque el personal, quedará aquí.';
    $vacioAccion = $accion !== null ? ['/?ruta=auditoria', 'Ver todas'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
    <div class="tabla">
    <table>
        <thead>
            <tr><th scope="col">#</th><th scope="col">Fecha</th><th scope="col">Acción</th><th scope="col">Detalle</th><th scope="col">Quién</th><th scope="col"><span class="visualmente-oculto">Ver</span></th></tr>
        </thead>
        <tbody>
        <?php foreach ($filas as $f): ?>
            <tr id="fila-aud-<?= (int) $f['id_auditoria'] ?>">
                <td><?= (int) $f['id_auditoria'] ?></td>
                <td class="fecha"><?= e($f['fecha_hora']) ?></td>
                <td><span class="etiqueta <?= $delicada($f['accion']) ? 'reponer' : 'neutra' ?>"><?= e($f['accion']) ?></span></td>
                <td class="recortado"><?= e($f['detalle'] ?? '') ?></td>
                <td><?= e($f['empleado']) ?> <small class="sutil">(<?= e($f['rol']) ?>)</small></td>
                <td><a class="boton secundario chico" href="#aud-<?= (int) $f['id_auditoria'] ?>"><?= icono('ver') ?>Ver</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php require __DIR__ . '/../parciales/paginacion.php'; ?>

    <?php foreach ($filas as $f): ?>
        <?php
        $drawerId = 'aud-' . (int) $f['id_auditoria'];
        $drawerTitulo = $f['accion'];
        $drawerSubtitulo = 'Registro ' . (int) $f['id_auditoria'] . ' · ' . $f['fecha_hora'];
        $drawerCerrar = '#fila-aud-' . (int) $f['id_auditoria'];
        require __DIR__ . '/../parciales/drawer_abrir.php';
        ?>
        <dl class="datos">
            <dt>Fecha y hora</dt><dd><?= e($f['fecha_hora']) ?></dd>
            <dt>Acción</dt><dd><span class="etiqueta <?= $delicada($f['accion']) ? 'reponer' : 'neutra' ?>"><?= e($f['accion']) ?></span></dd>
            <dt>Quién</dt><dd><?= e($f['empleado']) ?> (<?= e($f['rol']) ?>)</dd>
            <dt>Tienda</dt><dd><?= e($tienda->getNombreTienda()) ?></dd>
            <dt>Detalle</dt><dd class="texto-largo"><?= e($f['detalle'] ?? '-') ?></dd>
        </dl>
        <p class="sutil bloque">El sistema no ofrece ninguna forma de editar ni borrar este registro.</p>
        <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
