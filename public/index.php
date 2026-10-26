<?php
declare(strict_types=1);

/**
 * Punto de entrada: el unico archivo al que entra el navegador.
 * Abre la conexion, revisa quien entro, lee la ruta y llama al metodo del controlador.
 */
require __DIR__ . '/../config/Database.php';
require __DIR__ . '/../config/Sesion.php';
require __DIR__ . '/../controllers/AuthController.php';
require __DIR__ . '/../controllers/ProductoController.php';
require __DIR__ . '/../controllers/VentaController.php';
require __DIR__ . '/../controllers/TiendaController.php';
require __DIR__ . '/../controllers/InventarioController.php';
require __DIR__ . '/../controllers/CajaController.php';

// Cabeceras de seguridad: no se carga en un iframe de otro sitio y no se adivinan tipos
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

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

$sesion = new Sesion();
$sesion->iniciar();

// Cada Repository recibe la misma conexion y se comparte entre controladores
$tiendas    = new TiendaRepository($db);
$productos  = new ProductoRepository($db);
$ventas     = new VentaRepository($db);
$empleados  = new EmpleadoRepository($db);
$clientes   = new ClienteRepository($db);
$catalogo   = new CatalogoRepository($db);
$inventario = new InventarioRepository($db);
$cajas      = new CajaRepository($db);

// Quien entro: el empleado de la sesion, si sigue ACTIVO
$idEmpleado = $sesion->idEmpleado();
$usuario = $idEmpleado !== null ? $empleados->buscarPorId($idEmpleado) : null;
if ($usuario !== null && !$usuario->estaActivo()) {
    $sesion->salir();
    $usuario = null;
}

$auth = new AuthController($tiendas, $sesion, $usuario, $empleados);
$productoController = new ProductoController($tiendas, $sesion, $usuario, $productos, $catalogo);
$ventaController = new VentaController($tiendas, $sesion, $usuario, $ventas, $empleados, $clientes, $productos, $cajas,
    new VentaServicio($db));
$tiendaController = new TiendaController($tiendas, $sesion, $usuario, $empleados, $ventas);
$inventarioController = new InventarioController($tiendas, $sesion, $usuario, $inventario, $productos, $empleados, $catalogo,
    new InventarioServicio($db));
$cajaController = new CajaController($tiendas, $sesion, $usuario, $cajas, $empleados, new CajaServicio($db));

// Solo strings: si llega un arreglo (?ruta[]=x) se ignora
$leer = static function (string $clave): ?string {
    $valor = $_GET[$clave] ?? null;
    return is_string($valor) ? $valor : null;
};

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ruta = $leer('ruta') ?? 'productos';

// Rutas que cambian datos: solo por POST y con el token CSRF del formulario
$rutasPost = ['login', 'salir', 'venta_registrar', 'venta_anular', 'traslado_registrar', 'merma_registrar', 'turno_abrir', 'turno_cerrar'];

// La pagina se arma en un bufer: si algo falla a mitad, se descarta y se muestra el error limpio
ob_start();
try {
    if ($metodo === 'POST') {
        if (!in_array($ruta, $rutasPost, true)) {
            http_response_code(405);
            $productoController->noEncontrado('Esa ruta no acepta formularios.');
        } elseif (!$sesion->csrfValido(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) {
            // Formulario vencido o enviado desde otro sitio
            $productoController->prohibido('El formulario venció. Vuelve a la página y envíalo otra vez.');
        } elseif ($usuario === null && $ruta !== 'login') {
            header('Location: /?ruta=login', true, 303);
        } else {
            match ($ruta) {
                'login'              => $auth->entrar($_POST),
                'salir'              => $auth->salir(),
                'venta_registrar'    => $ventaController->registrar($_POST),
                'venta_anular'       => $ventaController->anular($_POST),
                'traslado_registrar' => $inventarioController->registrarTraslado($_POST),
                'merma_registrar'    => $inventarioController->registrarMerma($_POST),
                'turno_abrir'        => $cajaController->abrir($_POST),
                'turno_cerrar'       => $cajaController->cerrar($_POST),
            };
        }
    } elseif ($ruta === 'login' && $usuario === null) {
        $auth->formulario();
    } elseif ($ruta === 'login') {
        header('Location: /?ruta=productos', true, 303);
    } elseif ($usuario === null) {
        // Todo lo demas necesita haber entrado
        header('Location: /?ruta=login', true, 303);
    } else {
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

            case 'venta_nueva':
                $ventaController->nueva();
                break;

            case 'tiendas':
                $tiendaController->lista();
                break;

            case 'kardex':
                $inventarioController->kardex($leer('tienda'), $leer('producto'));
                break;

            case 'traslados':
                $inventarioController->traslados($leer('tienda'));
                break;

            case 'mermas':
                $inventarioController->mermas($leer('tienda'));
                break;

            case 'caja':
                $cajaController->panel();
                break;

            default:
                $productoController->noEncontrado('La ruta pedida no existe.');
        }
    }
    ob_end_flush();
} catch (Throwable $ex) {
    ob_end_clean();
    // Un error inesperado (consulta, dato incoherente) no muestra la traza en el navegador
    error_log('Minimarket Mass: ' . $ex::class . ': ' . $ex->getMessage() . ' en ' . $ex->getFile() . ':' . $ex->getLine());
    http_response_code(500);
    $mensaje = 'Ocurrió un error al preparar la página.';
    require __DIR__ . '/../views/errores/500.php';
}
