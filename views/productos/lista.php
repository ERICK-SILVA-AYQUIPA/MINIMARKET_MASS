<?php
declare(strict_types=1);
/**
 * V: tabla HTML del catalogo. Solo muestra; no calcula reglas ni busca datos.
 * Al tocar un producto se abre su ficha en el panel lateral (&ver=ID) sin salir de la lista.
 * @var array<int, array{producto: Producto, stock: ?Stock}> $filas
 * @var array<int, Tienda> $tiendas
 * @var Tienda $tienda
 * @var string $rutaSelector
 * @var bool $puedeEditar
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

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = 'Todavía no hay productos';
    $vacioTexto = 'Cuando el administrador registre un producto aparecerá aquí con su stock en cada tienda.';
    $vacioAccion = $puedeEditar ? ['/?ruta=producto_form', 'Registrar el primero'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<?php
$buscadorTabla = 'filas-productos';
$buscadorTexto = 'Buscar por nombre o código de barras';
require __DIR__ . '/../parciales/buscador.php';
?>
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
        $enlace = '/?ruta=productos&tienda=' . $tienda->getIdTienda() . '&ver=' . $p->getIdProducto();
        ?>
        <tr id="fila-<?= $p->getIdProducto() ?>">
            <td>
                <a class="fila-enlace" href="<?= e($enlace) ?>"><?= e($p->getNombreProducto()) ?></a>
                <br><small><?= $p->esAlPeso() ? 'Se vende por kg' : 'Se vende por unidad' ?></small>
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

<?php if ($ficha !== null): ?>
    <?php
    extract($ficha);
    $drawerId = 'ficha-producto';
    $drawerTitulo = $producto->getNombreProducto();
    $drawerSubtitulo = 'Producto ' . $producto->getIdProducto() . ' · ' . $tienda->getNombreTienda();
    $drawerCerrar = '/?ruta=productos&tienda=' . $tienda->getIdTienda() . '#fila-' . $producto->getIdProducto();
    $drawerAbierto = true;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    require __DIR__ . '/ficha.php';
    ?>
    <p class="bloque"><a href="/?ruta=producto&amp;id=<?= $producto->getIdProducto() ?>&amp;tienda=<?= $tienda->getIdTienda() ?>">Abrir en su propia página</a></p>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
