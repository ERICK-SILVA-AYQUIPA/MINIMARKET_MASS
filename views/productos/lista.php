<?php
declare(strict_types=1);
/**
 * V: tabla HTML del catalogo. Solo muestra; no calcula reglas ni busca datos.
 * Al tocar un producto se abre su ficha en el panel lateral (&ver=ID) sin salir de la lista.
 * @var array<int, array{producto: Producto, stock: ?Stock, marca: string, foto: array{url: ?string, icono: string, tono: int, categoria: string}}> $filas
 * @var array<int, Tienda> $tiendas
 * @var Tienda $tienda
 * @var string $rutaSelector
 * @var bool $puedeEditar
 * @var string $vista                     'tarjetas' o 'tabla'
 * @var array<int, string> $categorias    id => nombre
 * @var ?int $categoriaElegida
 * @var array<int, int> $porCategoria     id de categoria => productos
 * @var ?array<string, mixed> $ficha  datos de la ficha abierta (ver ProductoController::datosFicha)
 */
$titulo = 'Catálogo';
require __DIR__ . '/../layout_inicio.php';
$porReponer = count(array_filter($filas, static fn (array $f): bool => $f['stock']?->necesitaReponer() === true));
?>
<div class="cabecera">
    <div>
        <h1>Catálogo de productos</h1>
        <p class="sutil"><?= count($filas) ?> productos · <?= $porReponer ?> por reponer en <?= e($tienda->getNombreTienda()) ?></p>
    </div>
    <div class="acciones">
        <a class="boton secundario" href="/?ruta=stock"><?= icono('stock') ?>Stock de las 3 tiendas</a>
        <?php if ($puedeEditar): ?>
            <a class="boton secundario" href="/?ruta=catalogo"><?= icono('categorias') ?>Categorías y marcas</a>
            <a class="boton secundario" href="/?ruta=catalogo_importar"><?= icono('descarga') ?>Importar CSV</a>
            <a class="boton acento" href="/?ruta=producto_form"><?= icono('mas') ?>Nuevo producto</a>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<?php
$guiaClave = 'catalogo';
$guiaTitulo = 'Cómo se usa el catálogo';
$guiaPasos = [
    'Elige una categoría o escribe en Buscar (nombre o código de barras): la lista se filtra al momento.',
    'Toca un producto para abrir su ficha a la derecha: precio, stock en la tienda, mínimo y kardex.',
    'La etiqueta REPONER sale cuando el stock llegó a su mínimo. Cambia de tienda con las pestañas de arriba.',
];
if ($puedeEditar) {
    $guiaPasos[] = 'Como administrador, desde la ficha puedes editar el producto y subir su foto. Sin foto, se dibuja el icono de su categoría.';
}
require __DIR__ . '/../parciales/guia.php';

// Enlaces que conservan la tienda, la vista y la categoria elegidas
$base = '/?ruta=productos&tienda=' . $tienda->getIdTienda();
$conVista = $vista === 'tabla' ? '&vista=tabla' : '';
$conCategoria = $categoriaElegida !== null ? '&categoria=' . $categoriaElegida : '';
$contenedor = $vista === 'tabla' ? 'filas-productos' : 'tarjetas-productos';
$totalProductos = array_sum($porCategoria);
?>
<div class="barra-catalogo">
    <nav class="chips" aria-label="Filtrar por categoría" data-chips="<?= e($contenedor) ?>">
        <a href="<?= e($base . $conVista) ?>" data-categoria=""<?= $categoriaElegida === null ? ' aria-current="true"' : '' ?>>Todas <span><?= (int) $totalProductos ?></span></a>
        <?php foreach ($categorias as $idCategoria => $nombreCategoria): ?>
            <?php if (($porCategoria[$idCategoria] ?? 0) === 0) { continue; } ?>
            <a href="<?= e($base . $conVista . '&categoria=' . $idCategoria) ?>" data-categoria="<?= (int) $idCategoria ?>"<?= $categoriaElegida === $idCategoria ? ' aria-current="true"' : '' ?>><?= e($nombreCategoria) ?> <span><?= (int) $porCategoria[$idCategoria] ?></span></a>
        <?php endforeach; ?>
    </nav>
    <div class="alternar" role="group" aria-label="Cómo ver el catálogo">
        <a href="<?= e($base . $conCategoria) ?>"<?= $vista === 'tarjetas' ? ' aria-current="true"' : '' ?>><?= icono('cuadricula') ?>Tarjetas</a>
        <a href="<?= e($base . $conCategoria . '&vista=tabla') ?>"<?= $vista === 'tabla' ? ' aria-current="true"' : '' ?>><?= icono('lista') ?>Tabla</a>
    </div>
</div>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = 'Todavía no hay productos';
    $vacioTexto = 'Cuando el administrador registre un producto aparecerá aquí con su stock en cada tienda.';
    $vacioAccion = $puedeEditar ? ['/?ruta=producto_form', 'Registrar el primero'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<?php
$buscadorTabla = $contenedor;
$buscadorTexto = 'Buscar por nombre, marca o código de barras';
require __DIR__ . '/../parciales/buscador.php';
?>
<?php if ($vista === 'tarjetas'): ?>
<ul class="rejilla-productos" id="tarjetas-productos" aria-label="Productos y su stock en <?= e($tienda->getNombreTienda()) ?>">
    <?php foreach ($filas as $fila): ?>
        <?php
        $p = $fila['producto'];
        $s = $fila['stock'];
        $foto = $fila['foto'];
        $enlace = $base . $conVista . $conCategoria . '&ver=' . $p->getIdProducto();
        $stockTexto = $s === null ? 'Sin registro' : number_format($s->getCantidadActual(), $p->esAlPeso() ? 3 : 0) . ($p->esAlPeso() ? ' kg' : ' und');
        $claseStock = $s === null ? 'neutra' : ($s->getCantidadActual() <= 0 ? 'error' : ($s->necesitaReponer() ? 'alerta' : 'ok'));
        ?>
        <li id="fila-<?= $p->getIdProducto() ?>" data-categoria="<?= (int) $p->getIdCategoria() ?>">
            <a class="tarjeta-producto<?= $p->estaActivo() ? '' : ' inactivo' ?>" href="<?= e($enlace) ?>">
                <?php require __DIR__ . '/../parciales/foto_producto.php'; ?>
                <span class="tarjeta-categoria"><?= e($foto['categoria']) ?></span>
                <strong class="tarjeta-nombre"><?= e($p->getNombreProducto()) ?></strong>
                <span class="tarjeta-marca"><?= e($fila['marca']) ?></span>
                <span class="tarjeta-pie">
                    <span class="tarjeta-precio"><?= e(soles($p->getPrecioVenta())) ?><?= $p->esAlPeso() ? '<small> /kg</small>' : '' ?></span>
                    <span class="etiqueta <?= e($claseStock) ?>"><?= e($p->estaActivo() ? $stockTexto : 'DESCONTINUADO') ?></span>
                </span>
                <span class="visualmente-oculto"><?= e($p->getCodigoBarras() ?? '') ?></span>
                <span class="tarjeta-ver" aria-hidden="true">Ver ficha</span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<p class="sutil">Verde: hay stock · Amarillo: llegó a su mínimo (REPONER) · Rojo: sin stock. Toca un producto para ver su ficha.</p>
<?php else: ?>
<div class="tabla">
<table>
    <caption class="visualmente-oculto">Productos y su stock en <?= e($tienda->getNombreTienda()) ?></caption>
    <thead>
        <tr>
            <th scope="col">Producto</th>
            <th scope="col">Código de barras</th>
            <th scope="col" class="num">Precio</th>
            <th scope="col" class="num">Stock en <?= e($tienda->getNombreTienda()) ?></th>
            <th scope="col">Estado</th>
        </tr>
    </thead>
    <tbody id="filas-productos">
    <?php foreach ($filas as $fila): ?>
        <?php
        $p = $fila['producto'];
        $s = $fila['stock'];
        $foto = $fila['foto'];
        $fotoClase = 'chica';
        $enlace = $base . $conVista . $conCategoria . '&ver=' . $p->getIdProducto();
        ?>
        <tr id="fila-<?= $p->getIdProducto() ?>" data-categoria="<?= (int) $p->getIdCategoria() ?>">
            <td>
                <div class="con-foto">
                    <?php require __DIR__ . '/../parciales/foto_producto.php'; ?>
                    <div>
                        <a class="fila-enlace" href="<?= e($enlace) ?>"><?= e($p->getNombreProducto()) ?></a>
                        <br><small><?= $p->esAlPeso() ? 'Se vende por kg' : 'Se vende por unidad' ?></small>
                    </div>
                </div>
            </td>
            <td><?= e($p->getCodigoBarras() ?? '(al peso)') ?></td>
            <td class="num"><?= e(soles($p->getPrecioVenta())) ?><?= $p->esAlPeso() ? ' /kg' : '' ?></td>
            <td class="num">
                <?php if ($s === null): ?>
                    <span class="sutil">Sin registro</span>
                <?php else: ?>
                    <?= e(number_format($s->getCantidadActual(), $p->esAlPeso() ? 3 : 0)) ?>
                    <?php if ($s->necesitaReponer()): ?>
                        <span class="etiqueta reponer">REPONER</span>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
            <td><span class="etiqueta <?= $p->estaActivo() ? 'ok' : 'neutra' ?>"><?= e($p->getEstado()) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="sutil">REPONER = la cantidad actual llegó al stock mínimo de la tienda. Toca un producto para ver su ficha.</p>
<?php endif; ?>
<?php endif; ?>

<?php if ($ficha !== null): ?>
    <?php
    extract($ficha);
    $drawerId = 'ficha-producto';
    $drawerTitulo = $producto->getNombreProducto();
    $drawerSubtitulo = 'Producto ' . $producto->getIdProducto() . ' · ' . $tienda->getNombreTienda();
    $drawerCerrar = $base . $conVista . $conCategoria . '#fila-' . $producto->getIdProducto();
    $drawerAbierto = true;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    require __DIR__ . '/ficha.php';
    ?>
    <p class="bloque"><a href="/?ruta=producto&amp;id=<?= $producto->getIdProducto() ?>&amp;tienda=<?= $tienda->getIdTienda() ?>">Abrir en su propia página</a></p>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
