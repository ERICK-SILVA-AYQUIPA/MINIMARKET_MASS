<?php
declare(strict_types=1);

/**
 * Punto de entrada: el unico archivo al que entra el navegador.
 * Lee la ruta y decide a que metodo de que controlador llamar.
 */
require __DIR__ . '/../controllers/ProductoController.php';
require __DIR__ . '/../controllers/VentaController.php';
require __DIR__ . '/../controllers/TiendaController.php';

// Cada Repository se crea una sola vez y se comparte entre controladores
$tiendas   = new TiendaRepository();
$productos = new ProductoRepository();
$ventas    = new VentaRepository();
$empleados = new EmpleadoRepository();
$clientes  = new ClienteRepository();

$productoController = new ProductoController($tiendas, $productos);
$ventaController    = new VentaController($tiendas, $ventas, $empleados, $clientes, $productos);
$tiendaController   = new TiendaController($tiendas, $empleados, $ventas);

// Solo strings: si llega un arreglo (?ruta[]=x) se ignora
$leer = static function (string $clave): ?string {
    $valor = $_GET[$clave] ?? null;
    return is_string($valor) ? $valor : null;
};

$ruta = $leer('ruta') ?? 'productos';

switch ($ruta) {
    case 'productos':
        $productoController->lista($leer('tienda'));
        break;

    case 'producto':
        $productoController->detalle($leer('codigo'), $leer('id'), $leer('tienda'));
        break;

    case 'stock':
        $productoController->stockTiendas();
        break;

    case 'ventas':
        $ventaController->lista($leer('tienda'));
        break;

    case 'comprobante':
        $ventaController->comprobante($leer('serie'), $leer('numero'));
        break;

    case 'tiendas':
        $tiendaController->lista();
        break;

    default:
        $productoController->noEncontrado('La ruta pedida no existe.');
}
