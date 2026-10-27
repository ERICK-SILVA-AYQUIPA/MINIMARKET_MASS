<?php
declare(strict_types=1);
/**
 * V: crear o editar un producto. Con $producto edita; sin el, crea.
 * @var ?Producto $producto
 * @var array<int, Categoria> $categorias
 * @var array<int, Marca> $marcas
 * @var array<int, UnidadMedida> $unidades
 * @var array $viejo  lo que se escribio, si hubo un error
 * @var ?string $error
 */
$titulo = $producto === null ? 'Nuevo producto' : 'Editar producto';
require __DIR__ . '/../layout_inicio.php';

// Lo escrito antes del error manda; si no, los datos guardados del producto
$valor = static function (string $campo, string $guardado) use ($viejo): string {
    return is_string($viejo[$campo] ?? null) ? $viejo[$campo] : $guardado;
};
$porPeso = $producto?->esAlPeso() ?? ($valor('por_peso', 'N') === 'S');
?>
<a class="volver" href="<?= e($producto !== null ? '/?ruta=producto&id=' . $producto->getIdProducto() : '/?ruta=productos') ?>"><?= icono('volver') ?><?= $producto !== null ? 'Volver al producto' : 'Volver al catálogo' ?></a>
<div class="cabecera">
    <div>
        <h1><?= e($titulo) ?></h1>
        <p class="sutil"><?= $producto === null ? 'Se crea con stock 0 en todas las tiendas; luego entra con un ingreso de mercadería.' : e($producto->getNombreProducto()) ?></p>
    </div>
</div>

<form class="formulario panel" method="post" action="/?ruta=producto_guardar">
    <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= campoCsrf($csrf) ?>
    <input type="hidden" name="id" value="<?= $producto?->getIdProducto() ?? '' ?>">

    <label for="nombre">Nombre</label>
    <input id="nombre" name="nombre" maxlength="100" size="50" required value="<?= e($valor('nombre', $producto?->getNombreProducto() ?? '')) ?>">

    <label for="por_peso">Se vende</label>
    <?php if ($producto === null): ?>
        <select id="por_peso" name="por_peso">
            <option value="N" <?= $porPeso ? '' : 'selected' ?>>Por unidad (necesita código de barras)</option>
            <option value="S" <?= $porPeso ? 'selected' : '' ?>>Al peso, en kg</option>
        </select>
    <?php else: ?>
        <p id="por_peso"><strong><?= $porPeso ? 'Al peso, en kg' : 'Por unidad' ?></strong> <span class="ayuda">No se cambia: el stock y las ventas pasadas dependen de esto.</span></p>
    <?php endif; ?>

    <label for="codigo">Código de barras <span class="ayuda">8 a 14 dígitos; vacío solo si se vende al peso</span></label>
    <input id="codigo" name="codigo" inputmode="numeric" pattern="[0-9]{8,14}" maxlength="14"
           value="<?= e($valor('codigo', $producto?->getCodigoBarras() ?? '')) ?>">

    <label for="precio">Precio de venta (S/)</label>
    <input id="precio" name="precio" inputmode="decimal" size="10" required
           value="<?= e($valor('precio', $producto !== null ? number_format($producto->getPrecioVenta(), 2, '.', '') : '')) ?>">

    <?php
    $listas = [
        'categoria' => ['Categoría', $categorias, fn(Categoria $c): string => $c->getNombreCategoria(), $producto?->getIdCategoria()],
        'marca'     => ['Marca', $marcas, fn(Marca $m): string => $m->getNombreMarca(), $producto?->getIdMarca()],
        'unidad'    => ['Unidad', $unidades, fn(UnidadMedida $u): string => $u->getNombreUnidad() . ' (' . $u->getAbreviatura() . ')', $producto?->getIdUnidad()],
    ];
    ?>
    <?php foreach ($listas as $campo => [$etiqueta, $opciones, $nombre, $guardado]): ?>
        <?php $elegido = $valor($campo, (string) ($guardado ?? '')); ?>
        <label for="<?= e($campo) ?>"><?= e($etiqueta) ?></label>
        <select id="<?= e($campo) ?>" name="<?= e($campo) ?>" required>
            <option value="">Elige…</option>
            <?php foreach ($opciones as $id => $opcion): ?>
                <option value="<?= $id ?>" <?= (string) $id === $elegido ? 'selected' : '' ?>><?= e($nombre($opcion)) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endforeach; ?>

    <div class="botones-formulario">
        <button class="acento" type="submit"><?= $producto === null ? 'Crear producto' : 'Guardar cambios' ?></button>
        <?php if ($producto !== null): ?>
            <small>Cambiar el precio no cambia las ventas pasadas: cada venta guarda el precio con que se vendió.</small>
        <?php endif; ?>
    </div>
</form>
<?php require __DIR__ . '/../layout_fin.php'; ?>
