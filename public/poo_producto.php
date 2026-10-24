<?php
declare(strict_types=1);

// Paso 1: prueba de la clase Producto con dos filas reales de datos.sql
require __DIR__ . '/../models/Producto.php';
require __DIR__ . '/../models/Stock.php';

$yogurt = new Producto(11, 'Yogurt Gloria fresa 1 L', '7750000000097', 7.90, 'N', 'ACTIVO', 2, 1, 1);
$papa   = new Producto(8, 'Papa blanca (por kg)', null, 2.50, 'S', 'ACTIVO', 4, 8, 2);
$stockLecheYanahuara = new Stock(3, 1, 16.000, 20.000); // leche en Yanahuara: debajo del minimo

$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$sino = fn(bool $b): string => $b ? 'Sí' : 'No';
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>POO · Producto</title></head>
<body>
<h1><?= $e($yogurt->getNombreProducto()) ?></h1>
<p>Código de barras: <?= $e($yogurt->getCodigoBarras() ?? '-') ?></p>
<p>Precio de venta: S/ <?= number_format($yogurt->getPrecioVenta(), 2) ?></p>
<p>¿Se vende al peso? <?= $sino($yogurt->esAlPeso()) ?></p>
<p>¿3 unidades es cantidad válida? <?= $sino($yogurt->cantidadValida(3)) ?> · importe S/ <?= number_format($yogurt->importe(3), 2) ?></p>
<p>¿1.5 unidades es cantidad válida? <?= $sino($yogurt->cantidadValida(1.5)) ?></p>

<h1><?= $e($papa->getNombreProducto()) ?></h1>
<p>Código de barras: <?= $e($papa->getCodigoBarras() ?? '(no tiene: producto al peso)') ?></p>
<p>¿Se vende al peso? <?= $sino($papa->esAlPeso()) ?></p>
<p>¿1.250 kg es cantidad válida? <?= $sino($papa->cantidadValida(1.25)) ?> · importe S/ <?= number_format($papa->importe(1.25), 2) ?></p>
<p>¿1.2505 kg es cantidad válida? <?= $sino($papa->cantidadValida(1.2505)) ?> (más de 3 decimales)</p>

<h2>Stock de leche evaporada en Mass Yanahuara</h2>
<p>Actual <?= number_format($stockLecheYanahuara->getCantidadActual(), 3) ?>, mínimo <?= number_format($stockLecheYanahuara->getStockMinimo(), 3) ?></p>
<p>¿Necesita reponer? <?= $sino($stockLecheYanahuara->necesitaReponer()) ?></p>
<p>¿Alcanza para vender 20? <?= $sino($stockLecheYanahuara->hayStock(20)) ?></p>
</body>
</html>
