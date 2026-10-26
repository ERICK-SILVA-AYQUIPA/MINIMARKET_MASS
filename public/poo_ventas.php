<?php
declare(strict_types=1);

// Prueba de Venta, pagos (herencia de PagoVenta) y Cliente con casos normales y limite
require __DIR__ . '/../config/Database.php';
require __DIR__ . '/../models/VentaRepository.php';
require __DIR__ . '/../models/Cliente.php';

$repo = new VentaRepository(Database::conexion());
$venta2 = $repo->buscarPorComprobante('F001', 1);   // factura con pago mixto: tarjeta + efectivo
$pagos2 = $repo->pagosDeVenta($venta2->getIdVenta());

// Caso limite: la misma venta con un pago de menos ya no cuadra
$pagosIncompletos = array_slice($pagos2, 0, 1);

// Casos que el diseno no acepta: cada uno debe lanzar una excepcion
$intentos = [
    'Cliente sin DNI ni RUC' => fn() => new Cliente(9, null, null),
    'Vuelto igual a lo recibido' => fn() => new PagoEfectivo(99, 5.00, '2026-10-19 10:00:00', 'VALIDO', 1, 10.00, 10.00),
    'Tarjeta con 5 digitos' => fn() => new PagoTarjeta(99, 5.00, '2026-10-19 10:00:00', 'VALIDO', 1, 'CREDITO', 'VISA', '12345', 'X1'),
    'Importe mal calculado' => fn() => new DetalleVenta(99, 2.0, 4.50, 9.10, 1, 1),
    'Serie Z001' => fn() => new SerieComprobante(99, 'Z001', 0, 1, 1),
];

$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$sino = fn(bool $b): string => $b ? 'Sí' : 'No';
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>POO · Ventas</title></head>
<body>
<h1>Venta F001-00000001</h1>
<p>Total: S/ <?= number_format($venta2->getTotal(), 2) ?></p>
<ul>
<?php foreach ($pagos2 as $pago): ?>
    <li><?= $e($pago->getNombreMetodo()) ?> S/ <?= number_format($pago->getMontoPagado(), 2) ?> · <?= $e($pago->detalle()) ?></li>
<?php endforeach; ?>
</ul>
<p>¿Los pagos suman el total? <?= $sino($venta2->pagosCuadran($pagos2)) ?></p>
<p>¿Y con un solo pago? <?= $sino($venta2->pagosCuadran($pagosIncompletos)) ?></p>

<h2>Datos que el modelo rechaza</h2>
<ul>
<?php foreach ($intentos as $caso => $crear): ?>
    <?php
    try {
        $crear();
        $resultado = 'Aceptado (error: debía rechazarse)';
    } catch (InvalidArgumentException $ex) {
        $resultado = 'Rechazado: ' . $ex->getMessage();
    }
    ?>
    <li><?= $e($caso) ?> → <?= $e($resultado) ?></li>
<?php endforeach; ?>
</ul>
</body>
</html>
