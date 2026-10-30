<?php
declare(strict_types=1);
/**
 * V: ventas de una tienda. Solo muestra; las reglas estan en Venta.
 * Al tocar un comprobante se abre su detalle en el panel lateral (&ver=B001-12).
 * @var array<int, Tienda> $tiendas
 * @var Tienda $tienda
 * @var string $rutaSelector
 * @var array<int, array{venta: Venta, serie: SerieComprobante, cajero: ?Usuario, cliente: string, pagosCuadran: bool}> $filas
 * @var float $totalEmitido  de todas las paginas, sin anuladas
 * @var array{desde: ?string, hasta: ?string, estado: ?string} $filtro
 * @var Paginacion $paginacion
 * @var string $urlPagina
 * @var ?array<string, mixed> $detalleVenta  datos del comprobante abierto (ver VentaController::datosComprobante)
 */
$titulo = 'Ventas';
require __DIR__ . '/../layout_inicio.php';
$hayFiltros = array_filter($filtro) !== [];
$urlEstaPagina = $urlPagina . '&pagina=' . $paginacion->getPagina();
?>
<div class="cabecera">
    <div>
        <h1>Ventas de <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil"><?= $paginacion->getTotalFilas() ?> comprobantes · total emitido (sin anuladas) <strong><?= e(soles($totalEmitido)) ?></strong></p>
    </div>
    <?php if ($usuarioActual->puedeVender()): ?>
        <div class="acciones"><a class="boton acento" href="/?ruta=venta_nueva"><?= icono('venta') ?>Nueva venta</a></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../parciales/selector_tienda.php'; ?>

<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="ventas">
    <input type="hidden" name="tienda" value="<?= $tienda->getIdTienda() ?>">
    <label>Desde <input type="date" name="desde" value="<?= e($filtro['desde'] ?? '') ?>"></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($filtro['hasta'] ?? '') ?>"></label>
    <label>Estado
        <select name="estado">
            <option value="">Todos</option>
            <?php foreach (['EMITIDA', 'ANULADA'] as $opcion): ?>
                <option value="<?= e($opcion) ?>" <?= $filtro['estado'] === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Filtrar</button>
    <?php if ($hayFiltros): ?><a class="boton secundario" href="/?ruta=ventas&amp;tienda=<?= $tienda->getIdTienda() ?>">Quitar filtros</a><?php endif; ?>
</form>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = $hayFiltros ? 'No hay ventas con esos filtros' : 'Todavía no hay ventas en esta tienda';
    $vacioTexto = $hayFiltros ? 'Prueba con otras fechas o quita los filtros.' : 'Las boletas y facturas aparecen aquí apenas el cajero las emite.';
    $vacioAccion = $hayFiltros ? ['/?ruta=ventas&tienda=' . $tienda->getIdTienda(), 'Quitar filtros'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">Comprobante</th><th scope="col">Fecha y hora</th><th scope="col">Cajero</th><th scope="col">Cliente</th><th scope="col" class="num">Total</th><th scope="col">Estado</th><th scope="col">Pagos</th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $fila): ?>
        <?php
        $v = $fila['venta'];
        $s = $fila['serie'];
        $codigo = $s->getSerie() . '-' . $v->getNumeroCorrelativo();
        ?>
        <tr id="venta-<?= e($codigo) ?>">
            <td class="codigo"><a class="fila-enlace" href="<?= e($urlEstaPagina . '&ver=' . $codigo) ?>"><?= e($s->formatear($v->getNumeroCorrelativo())) ?></a></td>
            <td class="fecha"><?= e(fecha($v->getFechaHora())) ?></td>
            <td><?= e($fila['cajero']?->nombreCompleto() ?? '-') ?></td>
            <td><?= e($fila['cliente']) ?></td>
            <td class="num"><?= e(soles($v->getTotal())) ?></td>
            <td><span class="etiqueta <?= $v->estaAnulada() ? 'anulada' : 'ok' ?>"><?= e($v->getEstado()) ?></span></td>
            <td><?= $fila['pagosCuadran'] ? '<span class="etiqueta ok">Cuadran</span>' : '<span class="etiqueta reponer">No cuadran</span>' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../parciales/paginacion.php'; ?>
<?php endif; ?>

<?php if ($detalleVenta !== null): ?>
    <?php
    extract($detalleVenta);
    $urlComprobante = '/?ruta=comprobante&serie=' . $serie->getSerie() . '&numero=' . $venta->getNumeroCorrelativo();
    $drawerId = 'detalle-venta';
    $drawerTitulo = $serie->nombreComprobante() . ' ' . $serie->formatear($venta->getNumeroCorrelativo());
    $drawerSubtitulo = 'Total ' . soles($venta->getTotal());
    $drawerCerrar = $urlEstaPagina . '#venta-' . $serie->getSerie() . '-' . $venta->getNumeroCorrelativo();
    $drawerAbierto = true;
    $drawerAncho = true;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    require __DIR__ . '/detalle_venta.php';
    ?>
    <div class="acciones bloque">
        <a class="boton" href="<?= e($urlComprobante) ?>"><?= icono('ver') ?><?= $puedeAnular ? 'Abrir comprobante (para anular)' : 'Abrir comprobante completo' ?></a>
        <a class="boton secundario" href="<?= e(str_replace('ruta=comprobante', 'ruta=comprobante_imprimir', $urlComprobante)) ?>"><?= icono('imprimir') ?>Imprimir ticket</a>
    </div>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
