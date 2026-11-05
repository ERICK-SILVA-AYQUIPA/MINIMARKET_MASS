<?php
declare(strict_types=1);

/**
 * Punto de entrada: el unico archivo al que entra el navegador.
 * Abre la conexion, revisa quien entro, lee la ruta y llama al metodo del controlador.
 */
// La hora de PHP igual a la de MySQL (TZ America/Lima en docker-compose.yml): "hoy" es el mismo dia en las dos
date_default_timezone_set('America/Lima');

require __DIR__ . '/../../backend/config/Database.php';
require __DIR__ . '/../../backend/config/Sesion.php';
require __DIR__ . '/../../backend/config/Aplicacion.php';
require __DIR__ . '/../../backend/controllers/AuthController.php';
require __DIR__ . '/../../backend/controllers/ProductoController.php';
require __DIR__ . '/../../backend/controllers/VentaController.php';
require __DIR__ . '/../../backend/controllers/TiendaController.php';
require __DIR__ . '/../../backend/controllers/InventarioController.php';
require __DIR__ . '/../../backend/controllers/CajaController.php';
require __DIR__ . '/../../backend/controllers/EmpleadoController.php';
require __DIR__ . '/../../backend/controllers/ClienteController.php';
require __DIR__ . '/../../backend/controllers/ReporteController.php';
require __DIR__ . '/../../backend/controllers/PanelController.php';
require __DIR__ . '/../../backend/controllers/AuditoriaController.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Cabeceras de seguridad: no se carga en un iframe de otro sitio y no se adivinan tipos
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
// Solo se ejecuta JavaScript de archivos de este sitio (public/app.js): un script pegado en la pagina
// o traido de otro sitio no corre, aunque alguien lograra inyectarlo
header("Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");
// Con HTTPS, el navegador recuerda por un año que este sitio solo se abre cifrado
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000');
}

// GET /?ruta=salud: responde si la pagina y la base estan arriba, sin login ni sesion. La usa app.js para avisar
// "Sin conexion con el servidor" y sirve para un monitor externo (docs/plan_sin_internet.md)
if ($metodo === 'GET' && ($_GET['ruta'] ?? null) === 'salud') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    try {
        Database::conexion()->query('SELECT 1');
        $base = 'ok';
    } catch (PDOException $ex) {
        error_log('Minimarket Mass: salud sin MySQL: ' . $ex->getMessage());
        http_response_code(503);
        $base = 'caida';
    }
    echo json_encode(['estado' => $base === 'ok' ? 'ok' : 'error', 'base' => $base, 'version' => Aplicacion::version(),
        'hora' => date('c')], JSON_UNESCAPED_UNICODE);
    exit;
}

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
$reportes   = new ReporteRepository($db);
$empleadoServicio = new EmpleadoServicio($db);

// Quien entro: el empleado de la sesion, si sigue ACTIVO
$idEmpleado = $sesion->idEmpleado();
$usuario = $idEmpleado !== null ? $empleados->buscarPorId($idEmpleado) : null;
if ($usuario !== null && !$usuario->estaActivo()) {
    $sesion->salir();
    $usuario = null;
}
// Si la clave cambio despues de entrar (en otra PC, o la reinicio el administrador), esta sesion ya no vale
$estadoClave = $usuario !== null ? $empleados->estadoClave($usuario->getIdEmpleado()) : null;
if ($usuario !== null && ($estadoClave === null || $sesion->versionClave() !== $estadoClave['versionClave'])) {
    $sesion->cerrarPorClaveCambiada();
    $usuario = null;
}
// Clave puesta por el administrador (o la de prueba): solo puede cambiarla o salir
$debeCambiarClave = $usuario !== null && $estadoClave['debeCambiarClave'];
// El administrador trabaja con la sucursal que eligio en el menu lateral (si aun existe)
$idTiendaElegida = $sesion->tiendaElegida();
if ($usuario !== null && $idTiendaElegida !== null) {
    if ($usuario->puedeElegirTienda() && $tiendas->buscarPorId($idTiendaElegida) !== null) {
        $usuario = $usuario->enTienda($idTiendaElegida);
    } else {
        $sesion->elegirTienda(null);
    }
}

$auth = new AuthController($tiendas, $sesion, $usuario, $empleados, new IntentoLoginRepository($db), $empleadoServicio);
$productoController = new ProductoController($tiendas, $sesion, $usuario, $productos, $catalogo, new ProductoServicio($db));
$ventaController = new VentaController($tiendas, $sesion, $usuario, $ventas, $empleados, $clientes, $productos, $cajas,
    new VentaServicio($db), $catalogo, new OperacionMovilServicio($db));
$tiendaController = new TiendaController($tiendas, $sesion, $usuario, $empleados, $ventas, new TiendaServicio($db));
$ingresos = new IngresoRepository($db);
$inventarioController = new InventarioController($tiendas, $sesion, $usuario, $inventario, $productos, $empleados, $catalogo,
    new InventarioServicio($db), $ingresos);
$cajaController = new CajaController($tiendas, $sesion, $usuario, $cajas, $empleados, new CajaServicio($db));
$empleadoController = new EmpleadoController($tiendas, $sesion, $usuario, $empleados, $empleadoServicio);
$clienteController = new ClienteController($tiendas, $sesion, $usuario, $clientes, $ventas, new ClienteServicio($db));
$reporteController = new ReporteController($tiendas, $sesion, $usuario, $reportes);
$panelController = new PanelController($tiendas, $sesion, $usuario, new PanelRepository($db), $reportes, $inventario, $cajas,
    $productos, $catalogo, $ingresos);
Controller::usarAlertasInventario(fn(int $idTienda): array => $panelController->alertasInventario($idTienda));
$auditoriaController = new AuditoriaController($tiendas, $sesion, $usuario, new AuditoriaRepository($db));

// Solo strings: si llega un arreglo (?ruta[]=x) se ignora
$leer = static function (string $clave): ?string {
    $valor = $_GET[$clave] ?? null;
    return is_string($valor) ? $valor : null;
};

$ruta = $leer('ruta') ?? 'inicio';
Controller::marcarRuta($ruta);
// El modo del menu lateral lo guarda app.js en una cookie por usuario de este navegador
if ($usuario !== null) {
    $cookieMenu = $_COOKIE['menu_' . $usuario->getIdEmpleado()] ?? null;
    Controller::marcarModoMenu(is_string($cookieMenu) ? $cookieMenu : null);
}

// Rutas que cambian datos: solo por POST y con el token CSRF del formulario
$rutasPost = [
    'login', 'salir', 'mi_clave_guardar',
    'venta_registrar', 'venta_anular', 'ingreso_registrar', 'traslado_registrar', 'merma_registrar', 'turno_abrir', 'turno_cerrar',
    'venta_devolver', 'caja_movimiento', 'catalogo_subir',
    'turno_recontar', 'turno_pausa', 'turno_revisar', 'arqueo_sorpresa', 'regla_caja_guardar',
    'producto_guardar', 'producto_estado', 'producto_foto', 'stock_minimo', 'categoria_crear', 'marca_crear',
    'empleado_crear', 'empleado_guardar', 'empleado_clave', 'cliente_documento',
    'ajuste_registrar', 'tienda_guardar', 'tienda_elegir', 'tienda_abrir', 'caja_crear', 'caja_estado', 'distrito_crear',
];

// La pagina se arma en un bufer: si algo falla a mitad, se descarta y se muestra el error limpio
ob_start();
try {
    if ($metodo === 'POST') {
        if (!in_array($ruta, $rutasPost, true)) {
            // noEncontrado() pone 404: se vuelve a poner 405 despues de armar la pagina
            header('Allow: GET');
            $productoController->noEncontrado('Esa ruta no acepta formularios.');
            http_response_code(405);
        } elseif (!$sesion->csrfValido(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null)) {
            // Formulario vencido o enviado desde otro sitio
            $productoController->prohibido('El formulario venció. Vuelve a la página y envíalo otra vez.');
        } elseif ($usuario === null && $ruta !== 'login') {
            header('Location: /?ruta=login', true, 303);
        } elseif ($debeCambiarClave && !in_array($ruta, ['mi_clave_guardar', 'salir'], true)) {
            header('Location: /?ruta=mi_clave', true, 303);
        } else {
            match ($ruta) {
                'login'              => $auth->entrar($_POST),
                'salir'              => $auth->salir(),
                'venta_registrar'    => $ventaController->registrar($_POST),
                'venta_anular'       => $ventaController->anular($_POST),
                'venta_devolver'     => $ventaController->devolver($_POST),
                'ingreso_registrar'  => $inventarioController->registrarIngreso($_POST),
                'traslado_registrar' => $inventarioController->registrarTraslado($_POST),
                'merma_registrar'    => $inventarioController->registrarMerma($_POST),
                'turno_abrir'        => $cajaController->abrir($_POST),
                'turno_cerrar'       => $cajaController->cerrar($_POST),
                'caja_movimiento'    => $cajaController->movimiento($_POST),
                'turno_recontar'     => $cajaController->recontar($_POST),
                'turno_pausa'        => $cajaController->pausa($_POST),
                'turno_revisar'      => $cajaController->revisar($_POST),
                'arqueo_sorpresa'    => $cajaController->arqueoSorpresa($_POST),
                'regla_caja_guardar' => $cajaController->guardarReglas($_POST),
                'mi_clave_guardar'   => $auth->cambiarClave($_POST, $debeCambiarClave),
                'producto_guardar'   => $productoController->guardar($_POST),
                'producto_foto'      => $productoController->cambiarFoto($_POST, is_array($_FILES['foto'] ?? null) ? $_FILES['foto'] : null),
                'producto_estado'    => $productoController->cambiarEstado($_POST),
                'stock_minimo'       => $productoController->fijarMinimo($_POST),
                'categoria_crear'    => $productoController->crearCategoria($_POST),
                'marca_crear'        => $productoController->crearMarca($_POST),
                'catalogo_subir'     => $productoController->subirCatalogo($_POST, is_array($_FILES['archivo'] ?? null) ? $_FILES['archivo'] : null),
                'empleado_crear'     => $empleadoController->crear($_POST),
                'empleado_guardar'   => $empleadoController->guardar($_POST),
                'empleado_clave'     => $empleadoController->reiniciarClave($_POST),
                'cliente_documento'  => $clienteController->completarDocumento($_POST),
                'ajuste_registrar'   => $inventarioController->registrarAjuste($_POST),
                'tienda_guardar'     => $tiendaController->guardar($_POST),
                'tienda_elegir'      => $tiendaController->elegir($_POST),
                'tienda_abrir'       => $tiendaController->abrir($_POST),
                'caja_crear'         => $tiendaController->agregarCaja(),
                'caja_estado'        => $tiendaController->estadoCaja($_POST),
                'distrito_crear'     => $tiendaController->crearDistrito($_POST),
            };
        }
    } elseif ($ruta === 'login' && $usuario === null) {
        $auth->formulario();
    } elseif ($ruta === 'login') {
        header('Location: /?ruta=inicio', true, 303);
    } elseif ($usuario === null) {
        // Todo lo demas necesita haber entrado
        header('Location: /?ruta=login', true, 303);
    } elseif ($debeCambiarClave && $ruta !== 'mi_clave') {
        header('Location: /?ruta=mi_clave', true, 303);
    } else {
        switch ($ruta) {
            // La pagina avisa que el empleado sigue usando el teclado o el mouse (app.js): Sesion::idEmpleado()
            // ya renovo la hora de la ultima actividad. No devuelve contenido.
            case 'sesion_activa':
                header('Cache-Control: no-store');
                http_response_code(204);
                break;

            case 'inicio':
                $panelController->inicio();
                break;

            case 'productos':
                $productoController->lista($leer('tienda'), $leer('ver'), $leer('vista'), $leer('categoria'));
                break;

            case 'producto':
                $productoController->detalle($leer('codigo'), $leer('id'), $leer('tienda'));
                break;

            case 'stock':
                $productoController->stockTiendas();
                break;

            case 'stock_csv':
                $productoController->stockCsv();
                break;

            case 'ventas':
                $ventaController->lista($leer('tienda'), $leer('desde'), $leer('hasta'), $leer('estado'), $leer('pagina'), $leer('ver'));
                break;

            case 'comprobante':
                $ventaController->comprobante($leer('serie'), $leer('numero'));
                break;

            case 'comprobante_imprimir':
                $ventaController->comprobante($leer('serie'), $leer('numero'), true);
                break;

            case 'venta_nueva':
                $ventaController->nueva();
                break;

            case 'venta_estado':
                $ventaController->estado($leer('clave'));
                break;

            case 'ayuda':
                $panelController->ayuda();
                break;

            case 'tiendas':
                $tiendaController->lista();
                break;

            case 'kardex':
                $inventarioController->kardex($leer('tienda'), $leer('producto'), $leer('pagina'));
                break;

            case 'kardex_csv':
                $inventarioController->kardexCsv($leer('tienda'), $leer('producto'));
                break;

            case 'traslados':
                $inventarioController->traslados($leer('tienda'));
                break;

            case 'ingresos':
                $inventarioController->ingresos($leer('pagina'));
                break;

            case 'ingresos_csv':
                $inventarioController->ingresosCsv();
                break;

            case 'vencimientos':
                $inventarioController->vencimientos($leer('dias'));
                break;

            case 'mermas':
                $inventarioController->mermas($leer('tienda'));
                break;

            case 'caja':
                $cajaController->panel();
                break;

            case 'turno_cierre':
                $cajaController->comprobanteCierre($leer('turno'));
                break;

            case 'producto_form':
                $productoController->formulario($leer('id'));
                break;

            case 'catalogo_importar':
                $productoController->importar();
                break;

            case 'catalogo_plantilla':
                $productoController->plantilla();
                break;

            case 'catalogo':
                $productoController->catalogo();
                break;

            case 'empleados':
                $empleadoController->lista();
                break;

            case 'empleado':
                $empleadoController->editar($leer('id'));
                break;

            case 'clientes':
                $clienteController->lista($leer('documento'), $leer('pagina'), $leer('ver'));
                break;

            case 'cliente':
                $clienteController->detalle($leer('id'));
                break;

            case 'reportes':
                $reporteController->ventas($leer('desde'), $leer('hasta'), $leer('alcance'));
                break;

            case 'utilidad':
                $reporteController->utilidad($leer('desde'), $leer('hasta'));
                break;

            case 'reponer':
                $panelController->reponer();
                break;

            case 'reponer_csv':
                $panelController->reponerCsv();
                break;

            case 'reporte_csv':
                $reporteController->csv($leer('tipo'), $leer('desde'), $leer('hasta'), $leer('alcance'));
                break;

            case 'reporte_excel':
                $reporteController->excel($leer('desde'), $leer('hasta'), $leer('alcance'));
                break;

            case 'reporte_pdf':
                $reporteController->pdf($leer('desde'), $leer('hasta'), $leer('alcance'));
                break;

            case 'ajustes':
                $inventarioController->ajustes($leer('tienda'));
                break;

            case 'tienda_editar':
                $tiendaController->editar();
                break;

            case 'tienda_nueva':
                $tiendaController->nueva();
                break;

            case 'auditoria':
                $auditoriaController->lista($leer('accion'), $leer('pagina'));
                break;

            case 'mi_clave':
                $auth->formularioClave(null, $debeCambiarClave);
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
