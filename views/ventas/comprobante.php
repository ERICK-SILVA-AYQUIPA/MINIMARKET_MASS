<?php
declare(strict_types=1);
/**
 * V: boleta o factura completa: el cuerpo (parciales de detalle_venta.php), la revision y la anulacion.
 * @var Venta $venta
 * @var SerieComprobante $serie
 * @var Tienda $tienda
 * @var ?AnulacionVenta $anulacion
 * @var ?Usuario $anuladaPor
 * @var Usuario[] $puedenAnular
 * @var bool $puedeAnular  el usuario actual puede anular esta venta
 * @var bool $detalleCuadra
 * @var bool $pagosCuadran
 */
$numero = $serie->formatear($venta->getNumeroCorrelativo());
$titulo = $numero;
require __DIR__ . '/../layout_inicio.php';
?>
<a class="volver" href="/?ruta=ventas&amp;tienda=<?= $tienda->getIdTienda() ?>"><?= icono('volver') ?>Ventas de <?= e($tienda->getNombreTienda()) ?></a>
<div class="cabecera">
    <div>
        <h1><?= e($serie->nombreComprobante()) ?> <?= e($numero) ?></h1>
        <p class="sutil">Total <?= e(soles($venta->getTotal())) ?> · <?= e($venta->getFechaHora()) ?></p>
    </div>
    <div class="acciones">
        <a class="boton" href="/?ruta=comprobante_imprimir&amp;serie=<?= e($serie->getSerie()) ?>&amp;numero=<?= $venta->getNumeroCorrelativo() ?>"><?= icono('imprimir') ?>Imprimir ticket / PDF</a>
    </div>
</div>

<?php if ($venta->estaAnulada()): ?>
    <div class="aviso alerta">Este comprobante está ANULADO: su stock volvió al kardex y sus pagos no cuentan.</div>
<?php endif; ?>

<div class="rejilla-principal">
<div class="comprobante">
    <?php require __DIR__ . '/detalle_venta.php'; ?>
</div>

<div>
    <section class="panel">
        <h2>Revisión</h2>
        <ul class="lista-revision">
            <li><span class="etiqueta <?= $detalleCuadra ? 'ok' : 'error' ?>"><?= $detalleCuadra ? 'Sí' : 'No' ?></span> Importes del detalle suman el total</li>
            <li><span class="etiqueta <?= $pagosCuadran ? 'ok' : 'error' ?>"><?= $pagosCuadran ? 'Sí' : 'No' ?></span> <?= $venta->estaAnulada() ? 'Todos los pagos están anulados' : 'Pagos válidos suman el total' ?></li>
        </ul>
    </section>

    <section class="panel">
    <?php if ($anulacion !== null): ?>
        <h2>Anulación</h2>
        <dl class="datos">
            <dt>Fecha</dt><dd><?= e($anulacion->getFechaHora()) ?></dd>
            <dt>Motivo</dt><dd><?= e($anulacion->getMotivo()) ?></dd>
            <dt>Anulada por</dt><dd><?= e($anuladaPor?->nombreCompleto() ?? '-') ?> (<?= e($anuladaPor?->getNombreRol() ?? '-') ?>)</dd>
        </dl>
    <?php else: ?>
        <h2>¿Quién puede anularla?</h2>
        <p>Solo un ADMINISTRADOR activo de <?= e($tienda->getNombreTienda()) ?>:
            <?= e(implode(', ', array_map(fn(Usuario $u): string => $u->nombreCompleto(), $puedenAnular)) ?: 'nadie') ?></p>
        <?php if ($puedeAnular): ?>
            <form class="formulario" method="post" action="/?ruta=venta_anular">
                <?= campoCsrf($csrf) ?>
                <input type="hidden" name="serie" value="<?= e($serie->getSerie()) ?>">
                <input type="hidden" name="numero" value="<?= $venta->getNumeroCorrelativo() ?>">
                <label for="motivo">Motivo de la anulación</label>
                <input id="motivo" name="motivo" maxlength="150" required>
                <div class="botones-formulario">
                    <button class="peligro" type="submit">Anular venta</button>
                    <small>Devuelve el stock al kardex y anula sus pagos. No se puede deshacer.</small>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
    </section>
</div>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
