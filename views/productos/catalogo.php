<?php
declare(strict_types=1);
/**
 * V: categorias, marcas y unidades del catalogo, con formularios para el administrador.
 * @var array<int, Categoria> $categorias
 * @var array<int, Marca> $marcas
 * @var array<int, UnidadMedida> $unidades
 * @var bool $puedeEditar
 * @var ?string $error
 */
$titulo = 'Categorías y marcas';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Categorías, marcas y unidades</h1>
<?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>

<div class="tarjetas">
    <div class="panel">
        <h2>Categorías</h2>
        <ul><?php foreach ($categorias as $c): ?><li><?= e($c->getNombreCategoria()) ?></li><?php endforeach; ?></ul>
        <?php if ($puedeEditar): ?>
            <form class="formulario" method="post" action="/?ruta=categoria_crear">
                <?= campoCsrf($csrf) ?>
                <label for="categoria">Nueva categoría</label>
                <input id="categoria" name="nombre" maxlength="50" required>
                <button type="submit">Agregar</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="panel">
        <h2>Marcas</h2>
        <ul><?php foreach ($marcas as $m): ?><li><?= e($m->getNombreMarca()) ?><?= $m->esMarcaPropia() ? ' (propia)' : '' ?></li><?php endforeach; ?></ul>
        <?php if ($puedeEditar): ?>
            <form class="formulario" method="post" action="/?ruta=marca_crear">
                <?= campoCsrf($csrf) ?>
                <label for="marca">Nueva marca</label>
                <input id="marca" name="nombre" maxlength="50" required>
                <label><input type="checkbox" name="propia" value="S"> Es marca propia de Mass</label>
                <button type="submit">Agregar</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="panel">
        <h2>Unidades</h2>
        <ul><?php foreach ($unidades as $u): ?><li><?= e($u->getNombreUnidad()) ?> (<?= e($u->getAbreviatura()) ?>)</li><?php endforeach; ?></ul>
    </div>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
