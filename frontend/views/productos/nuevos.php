<?php
declare(strict_types=1);
/**
 * V: productos recien agregados al catalogo, de cualquier origen: la app (al escanear un codigo nuevo), el formulario
 * web o una importacion CSV. Origen, quien, cuando, desde que sucursal, stock inicial y de hoy, lo que falta completar
 * y si el administrador ya lo reviso (migracion 022). Cada fila abre su detalle (#nuevo-ID).
 * @var Tienda[] $tiendas
 * @var ?int $idTienda       null = todas las sucursales
 * @var string $periodo      'hoy', '7', '30', '90', 'todo' o 'rango' (fechas propias)
 * @var ?string $desde
 * @var ?string $hasta
 * @var ?string $origen      APP, WEB, CSV o null
 * @var ?string $estado      'pendientes', 'revisados' o null
 * @var string $texto
 * @var bool $hayFiltros
 * @var bool $puedeRevisar   el administrador: marca como revisado y completa los datos
 * @var array{total: int, hoy: int, semana: int, app: int, pendientes: int} $resumen
 * @var array<int, array<string, mixed>> $filas  ver ProductoRepository::paginaNuevos, mas foto y faltantes
 * @var array<string, string|int> $consultaActual  los filtros ya limpios
 * @var array<string, array{0: string, 1: ?int}> $periodos
 * @var array<string, string> $origenes
 * @var int $diasNuevo
 * @var Paginacion $paginacion
 * @var string $urlPagina
 */
$titulo = 'Recién agregados';
require __DIR__ . '/../layout_inicio.php';

// Enlace a la lista con un filtro cambiado (null lo quita); siempre vuelve a la pagina 1
$conFiltro = static function (array $cambios) use ($consultaActual): string {
    $q = array_filter($cambios + $consultaActual, static fn($v): bool => $v !== null);
    return '/?' . http_build_query(['ruta' => 'productos_nuevos'] + $q);
};
$cantidadTexto = static fn(mixed $valor, bool $alPeso): string => $valor === null ? '—'
    : number_format((float) $valor, $alPeso ? 3 : 0);
$claseOrigen = ['APP' => 'info', 'WEB' => 'neutra', 'CSV' => 'neutra'];
$limiteNuevo = (new DateTimeImmutable('today'))->modify('-' . ($diasNuevo - 1) . ' days')->format('Y-m-d');
$pendientesEnPagina = array_filter($filas, static fn(array $f): bool => $f['fecha_revision'] === null);
$tiendaNombre = $idTienda !== null && isset($tiendas[$idTienda]) ? $tiendas[$idTienda]->getNombreTienda() : null;
?>
<div class="cabecera">
    <div>
        <h1>Recién agregados</h1>
        <p class="sutil">
            Productos nuevos del catálogo: los que se registran en la app al escanear un código nuevo, desde el formulario o con un CSV.
            <?php if (count($tiendas) === 1 && $tiendaNombre !== null): ?>Ves los registrados desde <?= e($tiendaNombre) ?>.<?php endif; ?>
        </p>
    </div>
    <?php if ($puedeRevisar): ?>
    <div class="acciones">
        <a class="boton secundario" href="/?ruta=catalogo_importar"><?= icono('descarga') ?>Importar CSV</a>
        <a class="boton acento" href="/?ruta=producto_form"><?= icono('mas') ?>Nuevo producto</a>
    </div>
    <?php endif; ?>
</div>
<?php
$guiaClave = 'productos_nuevos';
$guiaTitulo = 'Cómo se usa esta lista';
$guiaPasos = [
    'Cuando alguien escanea en la app un código que no está en el catálogo y lo registra, el producto aparece aquí al instante, con quién, cuándo y desde qué sucursal.',
    'La etiqueta NUEVO dura ' . $diasNuevo . ' días. «Por completar» avisa lo que le falta en su sucursal: foto, marca, stock mínimo o stock.',
    $puedeRevisar
        ? 'Revisa nombre, categoría y precio. Si algo está mal, usa «Completar datos»; cuando esté bien, márcalo como revisado (uno o varios a la vez).'
        : 'El administrador revisa cada producto nuevo: nombre, categoría y precio. Si ves un error, avísale.',
];
require __DIR__ . '/../parciales/guia.php';
?>

<section class="tarjetas" aria-label="Resumen">
    <div><span>Agregados</span><strong><?= (int) $resumen['total'] ?></strong><small><?= e($periodo === 'rango' ? 'en esas fechas' : ($periodo === 'todo' ? 'en total' : 'en ' . mb_strtolower($periodos[$periodo][0]))) ?></small></div>
    <div><span>Hoy</span><strong><?= (int) $resumen['hoy'] ?></strong><small>registrados hoy</small></div>
    <div><span>Desde la app</span><strong><?= (int) $resumen['app'] ?></strong><small>al escanear un código nuevo</small></div>
    <div class="<?= $resumen['pendientes'] > 0 ? 'destacada' : '' ?>"><span>Por revisar</span><strong><?= (int) $resumen['pendientes'] ?></strong>
        <small><?php if ($resumen['pendientes'] > 0 && $estado !== 'pendientes'): ?><a href="<?= e($conFiltro(['estado' => 'pendientes'])) ?>">Ver solo esos</a><?php else: ?>falta que el administrador los vea<?php endif; ?></small></div>
</section>

<div class="barra-catalogo">
    <nav class="chips" aria-label="Periodo">
        <?php foreach ($periodos as $clave => [$nombre]): ?>
            <a href="<?= e($conFiltro(['periodo' => (string) $clave, 'desde' => null, 'hasta' => null])) ?>"<?= $periodo === (string) $clave ? ' aria-current="true"' : '' ?>><?= e($nombre) ?></a>
        <?php endforeach; ?>
        <?php if ($periodo === 'rango'): ?><a href="<?= e($conFiltro([])) ?>" aria-current="true"><?= e(fecha($desde) . ' – ' . fecha($hasta)) ?></a><?php endif; ?>
    </nav>
    <div class="alternar" role="group" aria-label="Revisión">
        <a href="<?= e($conFiltro(['estado' => null])) ?>"<?= $estado === null ? ' aria-current="true"' : '' ?>>Todos</a>
        <a href="<?= e($conFiltro(['estado' => 'pendientes'])) ?>"<?= $estado === 'pendientes' ? ' aria-current="true"' : '' ?>>Por revisar</a>
        <a href="<?= e($conFiltro(['estado' => 'revisados'])) ?>"<?= $estado === 'revisados' ? ' aria-current="true"' : '' ?>>Revisados</a>
    </div>
</div>

<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="productos_nuevos">
    <?php if ($periodo !== 'rango'): ?><input type="hidden" name="periodo" value="<?= e($periodo) ?>"><?php endif; ?>
    <?php if ($estado !== null): ?><input type="hidden" name="estado" value="<?= e($estado) ?>"><?php endif; ?>
    <label>Origen
        <select name="origen">
            <option value="">Todos</option>
            <?php foreach ($origenes as $clave => $nombre): ?>
                <option value="<?= e($clave) ?>" <?= $clave === $origen ? 'selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if (count($tiendas) > 1): ?>
    <label>Sucursal
        <select name="tienda">
            <option value="">Todas</option>
            <?php foreach ($tiendas as $t): ?>
                <option value="<?= $t->getIdTienda() ?>" <?= $t->getIdTienda() === $idTienda ? 'selected' : '' ?>><?= e($t->getNombreTienda()) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php endif; ?>
    <label>Desde <input type="date" name="desde" value="<?= e($periodo === 'rango' ? ($desde ?? '') : '') ?>"></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($periodo === 'rango' ? ($hasta ?? '') : '') ?>"></label>
    <label>Producto <input type="search" name="q" value="<?= e($texto) ?>" placeholder="Nombre o código" maxlength="60"></label>
    <button class="secundario" type="submit"><?= icono('buscar') ?>Filtrar</button>
    <?php if ($hayFiltros): ?><a class="boton secundario" href="/?ruta=productos_nuevos">Quitar filtros</a><?php endif; ?>
</form>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = $hayFiltros ? 'Ningún producto nuevo con estos filtros' : 'Todavía no se agregaron productos nuevos';
    $vacioTexto = 'Cuando alguien escanee en la app un código que no está en el catálogo y lo registre, o se cree un producto desde el formulario, aparecerá aquí.';
    $vacioAccion = $hayFiltros ? ['/?ruta=productos_nuevos', 'Ver todos los productos nuevos'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
    <?php if ($puedeRevisar && $pendientesEnPagina !== []): ?>
    <form id="revisar-lote" class="barra-lote" method="post" action="/?ruta=productos_revisar">
        <?= campoCsrf($csrf) ?>
        <?php foreach ($consultaActual as $clave => $valor): ?>
            <input type="hidden" name="<?= e((string) $clave) ?>" value="<?= e((string) $valor) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="pagina" value="<?= $paginacion->getPagina() ?>">
        <span><?= icono('info') ?><?= e(count($pendientesEnPagina) === 1 ? '1 producto' : count($pendientesEnPagina) . ' productos') ?> de esta página por revisar. Marca los que ya revisaste.</span>
        <button class="secundario chico" type="submit"><?= icono('ok') ?>Marcar seleccionados como revisados</button>
    </form>
    <?php endif; ?>

    <div class="tabla">
    <table class="tabla-nuevos">
        <caption class="visualmente-oculto">Productos recién agregados, del más reciente al más antiguo</caption>
        <thead>
            <tr>
                <?php if ($puedeRevisar): ?><th scope="col"><span class="visualmente-oculto">Elegir</span></th><?php endif; ?>
                <th scope="col">Producto</th><th scope="col">Origen</th><th scope="col">Registrado</th>
                <th scope="col">Sucursal</th><th scope="col" class="num">Stock</th><th scope="col">Estado</th>
                <th scope="col"><span class="visualmente-oculto">Acciones</span></th>
            </tr>
        </thead>
        <tbody id="filas-productos-nuevos">
        <?php foreach ($filas as $f): ?>
            <?php
            $id = (int) $f['id_producto'];
            $alPeso = $f['se_vende_por_peso'] === 'S';
            $esNuevo = substr((string) $f['fecha_registro'], 0, 10) >= $limiteNuevo;
            $pendiente = $f['fecha_revision'] === null;
            $foto = $f['foto'];
            $fotoClase = 'chica';
            ?>
            <tr id="fila-nuevo-<?= $id ?>"<?= $pendiente ? ' class="pendiente"' : '' ?>>
                <?php if ($puedeRevisar): ?>
                <td><?php if ($pendiente): ?><input type="checkbox" name="ids[]" value="<?= $id ?>" form="revisar-lote" aria-label="Elegir <?= e($f['nombre_producto']) ?>"><?php endif; ?></td>
                <?php endif; ?>
                <td>
                    <div class="con-foto">
                        <?php require __DIR__ . '/../parciales/foto_producto.php'; ?>
                        <div>
                            <a class="fila-enlace" href="#nuevo-<?= $id ?>"><?= e($f['nombre_producto']) ?></a>
                            <?php if ($esNuevo): ?><span class="etiqueta nuevo">NUEVO</span><?php endif; ?>
                            <?php if ($f['estado'] !== 'ACTIVO'): ?><span class="etiqueta neutra"><?= e($f['estado']) ?></span><?php endif; ?>
                            <br><small class="sutil"><?= e($f['categoria'] . ' · ' . $f['marca'] . ' · ' . soles((float) $f['precio_venta']) . ($alPeso ? ' /kg' : '')) ?></small>
                            <br><small class="sutil mono"><?= e($f['codigo_barras'] ?? '(al peso)') ?></small>
                        </div>
                    </div>
                </td>
                <td><span class="etiqueta <?= e($claseOrigen[$f['origen']] ?? 'neutra') ?>"><?= e($origenes[$f['origen']] ?? (string) $f['origen']) ?></span></td>
                <td><time datetime="<?= e(str_replace(' ', 'T', (string) $f['fecha_registro'])) ?>" title="<?= e(fecha($f['fecha_registro'])) ?>"><?= e(hace($f['fecha_registro'])) ?></time>
                    <br><small class="sutil"><?= e($f['empleado']) ?> (<?= e(mb_strtolower((string) $f['rol'])) ?>)</small></td>
                <td data-etiqueta="Sucursal"><?= e($f['tienda']) ?></td>
                <td class="num" data-etiqueta="Stock hoy"><?= e($cantidadTexto($f['stock'], $alPeso)) ?>
                    <?php if ($f['stock_inicial'] !== null): ?><br><small class="sutil">inicial <?= e($cantidadTexto($f['stock_inicial'], $alPeso)) ?></small><?php endif; ?></td>
                <td>
                    <?php if ($pendiente): ?>
                        <span class="etiqueta alerta">Por revisar</span>
                    <?php else: ?>
                        <span class="etiqueta ok" title="<?= e('Revisado ' . fecha($f['fecha_revision']) . ' por ' . $f['revisor']) ?>">Revisado</span>
                    <?php endif; ?>
                    <?php if ($f['faltantes'] !== []): ?>
                        <br><small class="faltantes"><?= e('Por completar: ' . mb_strtolower(implode(', ', $f['faltantes']))) ?></small>
                    <?php endif; ?>
                </td>
                <td class="acciones-fila">
                    <a class="boton secundario chico" href="#nuevo-<?= $id ?>"><?= icono('ver') ?>Ver</a>
                    <?php if ($puedeRevisar && $pendiente): ?>
                    <form method="post" action="/?ruta=productos_revisar">
                        <?= campoCsrf($csrf) ?>
                        <?php foreach ($consultaActual as $clave => $valor): ?>
                            <input type="hidden" name="<?= e((string) $clave) ?>" value="<?= e((string) $valor) ?>">
                        <?php endforeach; ?>
                        <input type="hidden" name="pagina" value="<?= $paginacion->getPagina() ?>">
                        <input type="hidden" name="ids[]" value="<?= $id ?>">
                        <button class="chico" type="submit" title="Nombre, categoría y precio están bien"><?= icono('ok') ?>Revisado</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php require __DIR__ . '/../parciales/paginacion.php'; ?>
    <p class="sutil">NUEVO = registrado en los últimos <?= (int) $diasNuevo ?> días. El stock es el de hoy en la sucursal desde la que se registró; «inicial» es lo que se contó al registrarlo en la app.</p>

    <?php foreach ($filas as $f): ?>
        <?php
        $id = (int) $f['id_producto'];
        $alPeso = $f['se_vende_por_peso'] === 'S';
        $pendiente = $f['fecha_revision'] === null;
        $drawerId = 'nuevo-' . $id;
        $drawerTitulo = $f['nombre_producto'];
        $drawerSubtitulo = ($origenes[$f['origen']] ?? (string) $f['origen']) . ' · ' . hace($f['fecha_registro']);
        $drawerCerrar = '#fila-nuevo-' . $id;
        require __DIR__ . '/../parciales/drawer_abrir.php';
        $foto = $f['foto'];
        $fotoClase = 'grande';
        ?>
        <div class="bloque"><?php require __DIR__ . '/../parciales/foto_producto.php'; ?></div>
        <?php if ($f['faltantes'] !== []): ?>
            <div class="aviso alerta">Por completar en <?= e($f['tienda']) ?>: <?= e(mb_strtolower(implode(', ', $f['faltantes']))) ?>.</div>
        <?php endif; ?>
        <dl class="datos">
            <dt>Código de barras</dt><dd class="mono"><?= e($f['codigo_barras'] ?? '(al peso)') ?></dd>
            <dt>Precio</dt><dd><?= e(soles((float) $f['precio_venta']) . ($alPeso ? ' /kg' : '')) ?></dd>
            <dt>Categoría</dt><dd><?= e($f['categoria']) ?></dd>
            <dt>Marca</dt><dd><?= e($f['marca']) ?></dd>
            <dt>Unidad</dt><dd><?= e($f['unidad']) ?></dd>
            <dt>Estado</dt><dd><span class="etiqueta <?= $f['estado'] === 'ACTIVO' ? 'ok' : 'neutra' ?>"><?= e($f['estado']) ?></span></dd>
            <dt>Origen</dt><dd><?= e($origenes[$f['origen']] ?? (string) $f['origen']) ?></dd>
            <dt>Registró</dt><dd><?= e($f['empleado']) ?> (<?= e(mb_strtolower((string) $f['rol'])) ?>)</dd>
            <dt>Desde la sucursal</dt><dd><?= e($f['tienda']) ?></dd>
            <dt>Fecha y hora</dt><dd><?= e(fecha($f['fecha_registro'])) ?></dd>
            <?php if ($f['stock_inicial'] !== null): ?><dt>Stock inicial</dt><dd><?= e($cantidadTexto($f['stock_inicial'], $alPeso) . ' ' . $f['unidad']) ?></dd><?php endif; ?>
            <dt>Stock hoy ahí</dt><dd><?= e($cantidadTexto($f['stock'], $alPeso) . ' ' . $f['unidad']) ?> <small class="sutil">(mínimo <?= e($cantidadTexto($f['stock_minimo'], $alPeso)) ?>)</small></dd>
            <dt>Revisión</dt><dd><?= $pendiente ? '<span class="etiqueta alerta">Por revisar</span>' : e('Revisado ' . fecha($f['fecha_revision']) . ' por ' . $f['revisor']) ?></dd>
        </dl>
        <div class="bloque acciones">
            <a class="boton secundario" href="/?ruta=producto&amp;id=<?= $id ?>&amp;tienda=<?= (int) $f['id_tienda'] ?>"><?= icono('catalogo') ?>Abrir la ficha</a>
            <?php if ($puedeRevisar): ?>
                <a class="boton secundario" href="/?ruta=producto_form&amp;id=<?= $id ?>"><?= icono('editar') ?>Completar datos</a>
                <?php if (isset($f['faltantes']['foto'])): ?><a class="boton secundario" href="/?ruta=productos&amp;ver=<?= $id ?>"><?= icono('foto') ?>Subir foto</a><?php endif; ?>
                <?php if ($pendiente): ?>
                <form method="post" action="/?ruta=productos_revisar">
                    <?= campoCsrf($csrf) ?>
                    <?php foreach ($consultaActual as $clave => $valor): ?>
                        <input type="hidden" name="<?= e((string) $clave) ?>" value="<?= e((string) $valor) ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="pagina" value="<?= $paginacion->getPagina() ?>">
                    <input type="hidden" name="ids[]" value="<?= $id ?>">
                    <button class="acento" type="submit"><?= icono('ok') ?>Marcar como revisado</button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
