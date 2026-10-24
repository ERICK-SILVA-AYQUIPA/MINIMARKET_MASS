<?php
declare(strict_types=1);

/**
 * Punto de entrada: el unico archivo al que entra el navegador.
 * Lee la ruta y decide a que metodo del controlador llamar.
 */
require __DIR__ . '/../controllers/ProductoController.php';

$controller = new ProductoController(new ProductoRepository());

// Solo strings: si llega un arreglo (?ruta[]=x) se ignora
$leer = static function (string $clave): ?string {
    $valor = $_GET[$clave] ?? null;
    return is_string($valor) ? $valor : null;
};

$ruta = $leer('ruta') ?? 'productos';

switch ($ruta) {
    case 'productos':
        $controller->lista($leer('tienda'));
        break;

    case 'producto':
        $controller->detalle($leer('codigo'), $leer('id'), $leer('tienda'));
        break;

    default:
        $controller->noEncontrado('La ruta pedida no existe.');
}
