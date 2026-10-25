<?php
declare(strict_types=1);

/**
 * Punto de entrada: el unico archivo al que entra el navegador.
 * Lee la ruta y decide a que metodo de que controlador llamar.
 */
require __DIR__ . '/../config/Database.php';
require __DIR__ . '/../controllers/ProductoController.php';
require __DIR__ . '/../controllers/VentaController.php';
require __DIR__ . '/../controllers/TiendaController.php';

try {
    $db = Database::conexion();
} catch (PDOException $ex) {
    // Al navegador no se le muestra el detalle (host, usuario); va al log de la terminal
    error_log('Minimarket Mass: no se pudo conectar a MySQL: ' . $ex->getMessage());
    http_response_code(503);
    $mensaje = 'La base de datos no está disponible. Revisa que el contenedor de MySQL esté corriendo.';
    require __DIR__ . '/../views/errores/500.php';
    exit;
}

// Cada Repository recibe la misma conexion y se comparte entre controladores
$tiendas   = new TiendaRepository($db);
$productos = new ProductoRepository($db);
$ventas    = new VentaRepository($db);
$empleados = new EmpleadoRepository($db);
$clientes  = new ClienteRepository($db);

$productoController = new ProductoController($tiendas, $productos);
$ventaController    = new VentaController($tiendas, $ventas, $empleados, $clientes, $productos);
$tiendaController   = new TiendaController($tiendas, $empleados, $ventas);

// Solo strings: si llega un arreglo (?ruta[]=x) se ignora
$leer = static function (string $clave): ?string {
    $valor = $_GET[$clave] ?? null;
    return is_string($valor) ? $valor : null;
};

$ruta = $leer('ruta') ?? 'productos';

// La pagina se arma en un bufer: si algo falla a mitad, se descarta y se muestra el error limpio
ob_start();
try {
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
    ob_end_flush();
} catch (Throwable $ex) {
    ob_end_clean();
    // Un error inesperado (consulta, dato incoherente) no muestra la traza en el navegador
    error_log('Minimarket Mass: ' . $ex::class . ': ' . $ex->getMessage());
    http_response_code(500);
    $mensaje = 'Ocurrió un error al preparar la página.';
    require __DIR__ . '/../views/errores/500.php';
}
