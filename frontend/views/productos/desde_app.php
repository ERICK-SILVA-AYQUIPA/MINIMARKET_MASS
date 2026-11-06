<?php
declare(strict_types=1);
/**
 * V: productos que se registraron desde la app Android al escanear un codigo que no estaba en el catalogo.
 * Quien, cuando y desde que sucursal (columnas de la migracion 018). Cada fila abre su detalle (#app-ID).
 * @var Tienda[] $tiendas
 * @var ?int $idTienda      null = todas las sucursales
 * @var ?string $desde
 * @var ?string $hasta
 * @var string $texto
 * @var bool $hayFiltros
 * @var array{total: int, semana: int, personas: int} $resumen
 * @var array<int, array{id_producto: int, nombre_producto: string, codigo_barras: ?string, precio_venta: string,
 *   estado: string, fecha_registro: string, categoria: string, marca: string, unidad: string, empleado: string,
 *   rol: string, id_tienda: int, tienda: string, stock: ?string}> $filas
 * @var Paginacion $paginacion
 * @var string $urlPagina
 */
$titulo = 'Productos desde la app';
require __DIR__ . '/../layout_inicio.php';
// Stock de hoy en la sucursal desde la que se registro: por unidad (la app no registra productos al peso)
$stockTexto = fn(?string $s): string => $s === null ? '-' : (string) (int) round((float) $s);
?>
<div class="cabecera">
    <div>
        <h1>Productos añadidos desde la app</h1>
        <p class="sutil">Los que alguien registró escaneando un código de barras nuevo con el celular. El catálogo es de toda la cadena.</p>
    </div>
</div>
<?php
$guiaClave = 'productos_app';
$guiaTitulo = 'Para qué sirve esta lista';
$guiaPasos = [
    'Cuando la app escanea un código que no está en el catálogo, el empleado puede registrarlo en el momento. Aquí queda quién, cuándo y desde qué sucursal.',
    'Revisa el nombre, la categoría y el precio de cada uno: si algo está mal, ábrelo y corrígelo en su ficha.',
    'El stock que se muestra es el de hoy en la sucursal desde la que se registró.',
];
require __DIR__ . '/../parciales/guia.php';
?>

<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="productos_app">
    <label>Sucursal
        <select name="tienda">
            <option value="">Todas</option>
            <?php foreach ($tiendas as $t): ?>
                <option value="<?= $t->getIdTienda() ?>" <?= $t->getIdTienda() === $idTienda ? 'selected' : '' ?>><?= e($t->getNombreTienda()) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Desde <input type="date" name="desde" value="<?= e($desde ?? '') ?>"></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($hasta ?? '') ?>"></label>
    <label>Producto <input type="search" name="q" value="<?= e($texto) ?>" placeholder="Nombre o código" maxlength="60"></label>
    <button class="secundario" type="submit"><?= icono('buscar') ?>Filtrar</button>
    <?php if ($hayFiltros): ?><a class="boton secundario" href="/?ruta=productos_app">Quitar filtros</a><?php endif; ?>
</form>

<section class="tarjetas" aria-label="Resumen">
    <div><span>Registrados</span><strong><?= (int) $resumen['total'] ?></strong><small><?= $hayFiltros ? 'con estos filtros' : 'desde la app, en total' ?></small></div>
    <div><span>Últimos 7 días</span><strong><?= (int) $resumen['semana'] ?></strong><small>productos nuevos</small></div>
    <div><span>Personas</span><strong><?= (int) $resumen['personas'] ?></strong><small>los registraron</small></div>
</section>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = $hayFiltros ? 'Ningún producto con estos filtros' : 'Todavía nadie registró productos desde la app';
    $vacioTexto = 'Cuando alguien escanee con la app un código que no está en el catálogo y lo registre, aparecerá aquí.';
    $vacioAccion = $hayFiltros ? ['/?ruta=productos_app', 'Quitar filtros'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
    <div class="tabla">
    <table>
        <thead>
            <tr><th scope="col">Registrado</th><th scope="col">Producto</th><th scope="col">Código</th><th scope="col" class="num">Precio</th>
                <th scope="col">Quién</th><th scope="col">Sucursal</th><th scope="col" class="num">Stock hoy</th><th scope="col"><span class="visualmente-oculto">Ver</span></th></tr>
        </thead>
        <tbody id="filas-productos-app">
        <?php foreach ($filas as $f): ?>
            <?php $id = (int) $f['id_producto']; ?>
            <tr id="fila-app-<?= $id ?>">
                <td class="fecha"><?= e(fecha($f['fecha_registro'])) ?></td>
                <td><?= e($f['nombre_producto']) ?> <small class="sutil"><?= e($f['categoria'] . ' · ' . $f['marca']) ?></small>
                    <?php if ($f['estado'] !== 'ACTIVO'): ?><span class="etiqueta neutra"><?= e($f['estado']) ?></span><?php endif; ?></td>
                <td><?= e($f['codigo_barras'] ?? '-') ?></td>
                <td class="num"><?= e(soles((float) $f['precio_venta'])) ?></td>
                <td><?= e($f['empleado']) ?> <small class="sutil">(<?= e($f['rol']) ?>)</small></td>
                <td><?= e($f['tienda']) ?></td>
                <td class="num"><?= e($stockTexto($f['stock'])) ?></td>
                <td><a class="boton secundario chico" href="#app-<?= $id ?>"><?= icono('ver') ?>Ver</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php require __DIR__ . '/../parciales/paginacion.php'; ?>

    <?php foreach ($filas as $f): ?>
        <?php
        $id = (int) $f['id_producto'];
        $drawerId = 'app-' . $id;
        $drawerTitulo = $f['nombre_producto'];
        $drawerSubtitulo = 'Registrado desde la app · ' . fecha($f['fecha_registro']);
        $drawerCerrar = '#fila-app-' . $id;
        require __DIR__ . '/../parciales/drawer_abrir.php';
        ?>
        <dl class="datos">
            <dt>Código de barras</dt><dd><?= e($f['codigo_barras'] ?? '-') ?></dd>
            <dt>Precio</dt><dd><?= e(soles((float) $f['precio_venta'])) ?></dd>
            <dt>Categoría</dt><dd><?= e($f['categoria']) ?></dd>
            <dt>Marca</dt><dd><?= e($f['marca']) ?></dd>
            <dt>Unidad</dt><dd><?= e($f['unidad']) ?></dd>
            <dt>Estado</dt><dd><span class="etiqueta <?= $f['estado'] === 'ACTIVO' ? 'ok' : 'neutra' ?>"><?= e($f['estado']) ?></span></dd>
            <dt>Registró</dt><dd><?= e($f['empleado']) ?> (<?= e($f['rol']) ?>)</dd>
            <dt>Desde la sucursal</dt><dd><?= e($f['tienda']) ?></dd>
            <dt>Fecha y hora</dt><dd><?= e(fecha($f['fecha_registro'])) ?></dd>
            <dt>Stock hoy ahí</dt><dd><?= e($stockTexto($f['stock']) . ' ' . $f['unidad']) ?></dd>
        </dl>
        <p class="bloque"><a class="boton" href="/?ruta=producto&amp;id=<?= $id ?>&amp;tienda=<?= (int) $f['id_tienda'] ?>"><?= icono('catalogo') ?>Abrir la ficha del producto</a></p>
        <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
