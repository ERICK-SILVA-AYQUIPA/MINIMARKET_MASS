<?php
declare(strict_types=1);
/**
 * V: reportes de venta de la tienda entre dos fechas (solo ventas EMITIDAS).
 * Cada tabla es una de las consultas 10 a 16 de consultas.sql, filtrada por tienda y fecha.
 * @var Tienda $tienda
 * @var string $desde
 * @var string $hasta
 * @var ?string $error
 * @var array{comprobantes: int, total: float, ticketPromedio: float, anuladas: int} $resumen
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
    echo '<div class="tabla"><table><tr>';
    foreach (array_keys($columnas) as $titulo) {
        echo '<th>' . e($titulo) . '</th>';
    }
    echo '</tr>';
    if ($filas === []) {
        echo '<tr><td colspan="' . count($columnas) . '">Sin ventas en estas fechas.</td></tr>';
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
    echo '</table></div>';
};
?>
<h1>Reportes de <?= e($tienda->getNombreTienda()) ?></h1>
<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="reportes">
    <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>" required></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>" required></label>
    <button type="submit">Ver</button>
</form>
<?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>

<div class="tarjetas">
    <div>Vendido<strong><?= e(soles($resumen['total'])) ?></strong></div>
    <div>Comprobantes<strong><?= $resumen['comprobantes'] ?></strong></div>
    <div>Ticket promedio<strong><?= e(soles($resumen['ticketPromedio'])) ?></strong></div>
    <div>Anuladas<strong><?= $resumen['anuladas'] ?></strong></div>
</div>

<h2>Ventas por día <small><a href="<?= e($csv('dias')) ?>">CSV</a></small></h2>
<?php $tabla($porDia, ['Día' => ['dia', 'texto'], 'Comprobantes' => ['comprobantes', 'numero'], 'Total' => ['total', 'soles']]); ?>

<h2>Productos más vendidos <small><a href="<?= e($csv('productos')) ?>">CSV</a></small></h2>
<?php $tabla($masVendidos, ['Producto' => ['producto', 'texto'], 'Unidad' => ['unidad', 'texto'], 'Cantidad' => ['cantidad', 'numero'], 'Importe' => ['importe', 'soles']]); ?>

<h2>Por categoría</h2>
<?php $tabla($porCategoria, ['Categoría' => ['categoria', 'texto'], 'Importe' => ['importe', 'soles']]); ?>

<h2>Por cajero <small><a href="<?= e($csv('cajeros')) ?>">CSV</a></small></h2>
<?php $tabla($porCajero, ['Cajero' => ['cajero', 'texto'], 'Comprobantes' => ['comprobantes', 'numero'], 'Total' => ['total', 'soles']]); ?>

<h2>Por método de pago <small><a href="<?= e($csv('metodos')) ?>">CSV</a></small></h2>
<?php $tabla($porMetodo, ['Método' => ['metodo', 'texto'], 'Pagos' => ['pagos', 'numero'], 'Monto' => ['monto', 'soles']]); ?>

<h2>Boletas y facturas</h2>
<?php $tabla($porTipo, ['Tipo' => ['tipo', 'texto'], 'Comprobantes' => ['comprobantes', 'numero'], 'Total' => ['total', 'soles']]); ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
