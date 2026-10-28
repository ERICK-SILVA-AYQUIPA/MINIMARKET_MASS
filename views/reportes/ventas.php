<?php
declare(strict_types=1);
/**
 * V: reportes de venta de la tienda entre dos fechas (solo ventas EMITIDAS).
 * Cada tabla es una de las consultas 10 a 16 de consultas.sql, filtrada por tienda y fecha.
 * @var Tienda $tienda
 * @var string $desde
 * @var string $hasta
 * @var ?string $error
 * @var array{comprobantes: int, total: float, devuelto: float, neto: float, ticketPromedio: float, anuladas: int} $resumen
 * @var array<int, array<string, string>> $porDia
 * @var array<int, array<string, string>> $porTipo
 * @var array<int, array<string, string>> $masVendidos
 * @var array<int, array<string, string>> $porCategoria
 * @var array<int, array<string, string>> $porCajero
 * @var array<int, array<string, string>> $porMetodo
 */
$titulo = 'Reportes';
require __DIR__ . '/../layout_inicio.php';

$csv = static fn(string $tipo): string => '/?' . http_build_query(['ruta' => 'reporte_csv', 'tipo' => $tipo, 'desde' => $desde, 'hasta' => $hasta]);

// Dibuja una tabla de reporte: [titulo de columna => [clave de la fila, formato]]
$tabla = static function (array $filas, array $columnas): void {
    echo '<div class="tabla"><table><thead><tr>';
    foreach ($columnas as $titulo => [, $formato]) {
        echo '<th scope="col" class="' . e($formato === 'texto' ? '' : 'num') . '">' . e($titulo) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if ($filas === []) {
        echo '<tr><td class="sutil centro" colspan="' . count($columnas) . '">Sin ventas en estas fechas.</td></tr>';
    }
    foreach ($filas as $fila) {
        echo '<tr>';
        foreach ($columnas as [$clave, $formato]) {
            $v = (string) $fila[$clave];
            echo match ($formato) {
                'soles'    => '<td class="num">' . e(soles((float) $v)) . '</td>',
                'numero'   => '<td class="num">' . e(rtrim(rtrim(number_format((float) $v, 3), '0'), '.')) . '</td>',
                default    => '<td>' . e($v) . '</td>',
            };
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
};
?>
<div class="cabecera">
    <div>
        <h1>Reportes de <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Solo ventas EMITIDAS entre <?= e($desde) ?> y <?= e($hasta) ?>. Cada tabla se descarga en CSV para Excel.</p>
    </div>
    <div class="acciones"><a class="boton secundario" href="/?ruta=utilidad&amp;desde=<?= e($desde) ?>&amp;hasta=<?= e($hasta) ?>"><?= icono('utilidad') ?>Ver utilidad</a></div>
</div>
<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="reportes">
    <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>" required></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>" required></label>
    <button type="submit">Ver</button>
</form>
<?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>

<section class="tarjetas" aria-label="Resumen del periodo">
    <div><span>Vendido</span><strong><?= e(soles($resumen['total'])) ?></strong></div>
    <div><span>Devuelto</span><strong><?= e(soles($resumen['devuelto'])) ?></strong><small>neto <?= e(soles($resumen['neto'])) ?></small></div>
    <div><span>Comprobantes</span><strong><?= (int) $resumen['comprobantes'] ?></strong></div>
    <div><span>Ticket promedio</span><strong><?= e(soles($resumen['ticketPromedio'])) ?></strong></div>
    <div><span>Anuladas</span><strong><?= (int) $resumen['anuladas'] ?></strong></div>
</section>
<div class="rejilla">

<section class="panel">
    <div class="titulo-seccion"><h2>Ventas por día</h2><a class="boton secundario chico" href="<?= e($csv('dias')) ?>"><?= icono('descarga') ?>CSV</a></div>
    <?php $tabla($porDia, ['Día' => ['dia', 'texto'], 'Comprobantes' => ['comprobantes', 'numero'], 'Total' => ['total', 'soles']]); ?>
</section>
<section class="panel">
    <div class="titulo-seccion"><h2>Productos más vendidos</h2><a class="boton secundario chico" href="<?= e($csv('productos')) ?>"><?= icono('descarga') ?>CSV</a></div>
    <?php $tabla($masVendidos, ['Producto' => ['producto', 'texto'], 'Unidad' => ['unidad', 'texto'], 'Cantidad' => ['cantidad', 'numero'], 'Importe' => ['importe', 'soles']]); ?>
</section>
<section class="panel">
    <div class="titulo-seccion"><h2>Por cajero</h2><a class="boton secundario chico" href="<?= e($csv('cajeros')) ?>"><?= icono('descarga') ?>CSV</a></div>
    <?php $tabla($porCajero, ['Cajero' => ['cajero', 'texto'], 'Comprobantes' => ['comprobantes', 'numero'], 'Total' => ['total', 'soles']]); ?>
</section>
<section class="panel">
    <div class="titulo-seccion"><h2>Por método de pago</h2><a class="boton secundario chico" href="<?= e($csv('metodos')) ?>"><?= icono('descarga') ?>CSV</a></div>
    <?php $tabla($porMetodo, ['Método' => ['metodo', 'texto'], 'Pagos' => ['pagos', 'numero'], 'Monto' => ['monto', 'soles']]); ?>
</section>
<section class="panel">
    <div class="titulo-seccion"><h2>Por categoría</h2></div>
    <?php $tabla($porCategoria, ['Categoría' => ['categoria', 'texto'], 'Importe' => ['importe', 'soles']]); ?>
</section>
<section class="panel">
    <div class="titulo-seccion"><h2>Boletas y facturas</h2></div>
    <?php $tabla($porTipo, ['Tipo' => ['tipo', 'texto'], 'Comprobantes' => ['comprobantes', 'numero'], 'Total' => ['total', 'soles']]); ?>
</section>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
