<?php
declare(strict_types=1);
/**
 * V: subir el catalogo en un CSV (de Excel). Primero se revisa sin guardar nada; despues se importa.
 * @var string[] $columnas
 * @var int $maximoFilas
 * @var ?array{creados: int, actualizados: int, sinCambios: int, precios: int, categorias: string[], marcas: string[], errores: string[], soloRevisar: bool} $resultado
 * @var ?string $error
 * @var bool $soloRevisar
 */
$titulo = 'Importar catálogo';
require __DIR__ . '/../layout_inicio.php';
$nuevasEtiquetas = $resultado === null ? 0 : count($resultado['categorias']) + count($resultado['marcas']);
$revisado = $resultado !== null && $resultado['soloRevisar'] && $resultado['errores'] === [];
?>
<a class="volver" href="/?ruta=productos"><?= icono('volver') ?>Volver al catálogo</a>
<div class="cabecera">
    <div>
        <h1>Importar catálogo desde CSV</h1>
        <p class="sutil">Crea los productos nuevos y actualiza nombre, categoría, marca y precio de los que ya existen.</p>
    </div>
    <div class="acciones">
        <a class="boton secundario" href="/?ruta=catalogo_plantilla"><?= icono('descarga') ?>Descargar el catálogo actual</a>
    </div>
</div>
<?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>

<?php if ($resultado !== null): ?>
    <section class="panel" aria-labelledby="titulo-resultado">
        <?php if ($resultado['errores'] !== []): ?>
            <h2 id="titulo-resultado">El archivo tiene errores</h2>
            <div class="aviso error" role="alert"><span>No se guardó nada. Corrige estas líneas en Excel y vuelve a subirlo.</span></div>
            <ul class="lista-errores">
                <?php foreach ($resultado['errores'] as $mensaje): ?><li><?= e($mensaje) ?></li><?php endforeach; ?>
            </ul>
        <?php else: ?>
            <h2 id="titulo-resultado">Revisión del archivo</h2>
            <div class="aviso ok"><span>El archivo está bien. Todavía no se guardó nada: elige «Importar» y súbelo otra vez para aplicar los cambios.</span></div>
        <?php endif; ?>
        <section class="tarjetas" aria-label="Lo que cambiaría">
            <div><span>Productos nuevos</span><strong><?= (int) $resultado['creados'] ?></strong><small>con stock 0 en las tiendas</small></div>
            <div><span>Actualizados</span><strong><?= (int) $resultado['actualizados'] ?></strong><small><?= (int) $resultado['precios'] ?> con precio nuevo</small></div>
            <div><span>Sin cambios</span><strong><?= (int) $resultado['sinCambios'] ?></strong><small>ya estaban iguales</small></div>
            <div><span>Categorías y marcas nuevas</span><strong><?= (int) $nuevasEtiquetas ?></strong><small>se crean solas</small></div>
        </section>
        <?php if ($resultado['categorias'] !== [] || $resultado['marcas'] !== []): ?>
            <ul class="lista-chips">
                <?php foreach ($resultado['categorias'] as $nombre): ?><li>Categoría: <?= e($nombre) ?></li><?php endforeach; ?>
                <?php foreach ($resultado['marcas'] as $nombre): ?><li>Marca: <?= e($nombre) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="rejilla">
    <section class="panel" aria-labelledby="titulo-subir">
        <h2 id="titulo-subir">Subir el archivo</h2>
        <form class="formulario" method="post" action="/?ruta=catalogo_subir" enctype="multipart/form-data">
            <?= campoCsrf($csrf) ?>
            <label for="archivo">Archivo CSV (hasta 1 MB y <?= (int) $maximoFilas ?> productos)</label>
            <input id="archivo" name="archivo" type="file" accept=".csv,.txt,text/csv" required>
            <fieldset class="opciones">
                <legend>Qué hacer</legend>
                <label class="casilla"><input type="radio" name="modo" value="revisar"<?= $revisado ? '' : ' checked' ?>> Solo revisar (no guarda nada)</label>
                <label class="casilla"><input type="radio" name="modo" value="importar"<?= $revisado ? ' checked' : '' ?>> Importar y guardar los cambios</label>
            </fieldset>
            <div class="botones-formulario"><button class="acento" type="submit"><?= icono('ingresos') ?>Subir</button></div>
        </form>
    </section>

    <section class="panel" aria-labelledby="titulo-formato">
        <h2 id="titulo-formato">Cómo armar el archivo</h2>
        <ol class="pasos-ayuda">
            <li>Descarga el catálogo actual y ábrelo en Excel.</li>
            <li>Agrega una fila por producto o cambia los precios.</li>
            <li>Guárdalo como «CSV (delimitado por comas)» y súbelo aquí.</li>
        </ol>
        <p class="sutil">Columnas, en este orden (la primera fila es el encabezado):</p>
        <ul class="lista-chips"><?php foreach ($columnas as $columna): ?><li><?= e($columna) ?></li><?php endforeach; ?></ul>
        <p class="sutil">Si el código de barras ya existe se actualiza ese producto; si va vacío (productos a granel) se busca por nombre.
            La unidad es la abreviatura (und, kg…), el precio va con punto decimal y al_peso es S o N.
            El stock no se cambia aquí: entra con «Recibir mercadería».</p>
    </section>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
