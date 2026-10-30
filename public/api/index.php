<?php
declare(strict_types=1);

/**
 * Punto de entrada de la API REST /api/v1 que usa la app Android (android-app/).
 * Comparte la base, los modelos y los servicios con la pagina web (public/index.php): no hay otra base ni otra logica.
 *
 * - Responde siempre JSON: {"ok": true, "datos": ...} o {"ok": false, "error": {"codigo": "...", "mensaje": "..."}}.
 * - Entra con DNI y clave (POST /api/v1/auth/login) y luego manda "Authorization: Bearer <token>".
 *   No usa cookies, por eso no necesita el token CSRF de los formularios.
 * - Las ventas y los ingresos piden la cabecera Idempotency-Key: un reintento no los repite.
 * Con `php -S localhost:8000 -t public`, /api/v1/... llega a este archivo. Apache y Nginx: deploy/.
 */
date_default_timezone_set('America/Lima');

require __DIR__ . '/../../config/Database.php';
require __DIR__ . '/../../models/EmpleadoRepository.php';
require __DIR__ . '/../../models/IntentoLoginRepository.php';
require __DIR__ . '/../../models/TokenApiRepository.php';
require __DIR__ . '/../../models/TiendaRepository.php';
require __DIR__ . '/../../models/ConsultaMovilRepository.php';
require __DIR__ . '/../../models/VentaRepository.php';
require __DIR__ . '/../../models/CajaRepository.php';
require __DIR__ . '/../../controllers/api/AuthApiController.php';
require __DIR__ . '/../../controllers/api/ProductoApiController.php';
require __DIR__ . '/../../controllers/api/VentaApiController.php';
require __DIR__ . '/../../controllers/api/IngresoApiController.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
// Precios, stock y ventas cambian a cada rato: nadie en el camino guarda una copia
header('Cache-Control: no-store');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000');
}

$responder = static function (int $codigo, array $cuerpo): void {
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
};
$fallar = static function (int $codigo, string $clave, string $mensaje) use ($responder): void {
    $responder($codigo, ['ok' => false, 'error' => ['codigo' => $clave, 'mensaje' => $mensaje]]);
};

// La ruta despues de /api/: "v1/productos/codigo/7750000000011"
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$camino = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$posicion = strpos($camino, '/api/');
$ruta = $posicion === false ? '' : trim(substr($camino, $posicion + 5), '/');

$leer = static function (string $clave): ?string {
    $valor = $_GET[$clave] ?? null;
    return is_string($valor) ? $valor : null;
};
$cabecera = static function (string $nombre): string {
    $valor = $_SERVER[$nombre] ?? $_SERVER['REDIRECT_' . $nombre] ?? '';
    return is_string($valor) ? trim($valor) : '';
};

// El cuerpo de un POST: JSON de hasta 256 KB
$datos = [];
if ($metodo === 'POST') {
    if (!str_starts_with(strtolower($cabecera('CONTENT_TYPE') ?: $cabecera('HTTP_CONTENT_TYPE')), 'application/json')) {
        $fallar(415, 'TIPO_NO_SOPORTADO', 'Envía el cuerpo como application/json.');
        exit;
    }
    $crudo = (string) file_get_contents('php://input', false, null, 0, 262_145);
    if (strlen($crudo) > 262_144) {
        $fallar(413, 'MUY_GRANDE', 'El pedido es demasiado grande.');
        exit;
    }
    $decodificado = $crudo === '' ? [] : json_decode($crudo, true, 16);
    if (!is_array($decodificado) || ($decodificado !== [] && array_is_list($decodificado))) {
        $fallar(400, 'JSON_INVALIDO', 'El cuerpo no es un objeto JSON válido.');
        exit;
    }
    $datos = $decodificado;
}

try {
    $db = Database::conexion();
} catch (PDOException $ex) {
    // El detalle (host, usuario) va al log del servidor, nunca al celular
    error_log('Minimarket Mass API: no se pudo conectar a MySQL: ' . $ex->getMessage());
    $fallar(503, 'SIN_BASE', 'El servidor no puede conectarse a la base de datos.');
    exit;
}

// Quien llama: el empleado del token, si sigue ACTIVO
$token = preg_match('/^Bearer\s+(\S+)$/i', $cabecera('HTTP_AUTHORIZATION'), $m) === 1 ? $m[1] : '';
$tokens = new TokenApiRepository($db);
$empleados = new EmpleadoRepository($db);
$usuario = null;
if ($token !== '') {
    $idEmpleado = $tokens->empleadoDe($token);
    $usuario = $idEmpleado !== null ? $empleados->buscarPorId($idEmpleado) : null;
    if ($usuario !== null && !$usuario->estaActivo()) {
        $usuario = null;
    }
}

$tiendas = new TiendaRepository($db);
$consultas = new ConsultaMovilRepository($db);
$operaciones = new OperacionMovilServicio($db);
$auth = new AuthApiController($usuario, $empleados, new IntentoLoginRepository($db), $tokens, $tiendas);
$productos = new ProductoApiController($usuario, $consultas);
$ventas = new VentaApiController($usuario, $consultas, new VentaRepository($db), new CajaRepository($db), $tiendas,
    new VentaServicio($db), new CajaServicio($db), $operaciones);
$ingresos = new IngresoApiController($usuario, $consultas, new InventarioServicio($db), $operaciones);
$clave = $cabecera('HTTP_IDEMPOTENCY_KEY');

/*
 * Rutas: [metodo, patron, necesita token, accion]. Lo que el patron captura ($p[1]) es un id o un codigo.
 * Una operacion con Idempotency-Key devuelve {respuesta, repetida}: 201 la primera vez, 200 si es repetida.
 */
$rutas = [
    ['GET',  '#^v1/salud$#',                    false, fn(array $p): array => ['servicio' => 'Minimarket Mass API', 'version' => 1, 'hora' => date('Y-m-d H:i:s')]],
    ['POST', '#^v1/auth/login$#',               false, fn(array $p): array => $auth->entrar($datos, (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'))],
    ['POST', '#^v1/auth/logout$#',              true,  fn(array $p): array => $auth->salir($token)],
    ['GET',  '#^v1/auth/yo$#',                  true,  fn(array $p): array => $auth->yo()],
    ['GET',  '#^v1/productos$#',                true,  fn(array $p): array => $productos->lista($leer('q'), $leer('filtro'), $leer('pagina'))],
    ['GET',  '#^v1/productos/codigo/([^/]{1,40})$#', true, fn(array $p): array => $productos->porCodigo(rawurldecode($p[1]))],
    ['GET',  '#^v1/productos/([0-9]{1,9})$#',   true,  fn(array $p): array => $productos->detalle($p[1])],
    ['GET',  '#^v1/movimientos$#',              true,  fn(array $p): array => $productos->movimientos($leer('producto'), $leer('pagina'))],
    ['GET',  '#^v1/caja$#',                     true,  fn(array $p): array => $ventas->caja()],
    ['POST', '#^v1/caja/turno$#',               true,  fn(array $p): array => $ventas->abrirTurno($datos)],
    ['GET',  '#^v1/ventas$#',                   true,  fn(array $p): array => $ventas->lista($leer('desde'), $leer('hasta'), $leer('estado'), $leer('comprobante'), $leer('pagina'))],
    ['POST', '#^v1/ventas$#',                   true,  fn(array $p): array => $ventas->registrar($datos, $clave)],
    ['GET',  '#^v1/ventas/([0-9]{1,9})$#',      true,  fn(array $p): array => $ventas->detalle($p[1])],
    ['POST', '#^v1/ventas/([0-9]{1,9})/anular$#', true, fn(array $p): array => $ventas->anular($p[1], $datos)],
    ['GET',  '#^v1/ingresos$#',                 true,  fn(array $p): array => $ingresos->lista($leer('pagina'))],
    ['POST', '#^v1/ingresos$#',                 true,  fn(array $p): array => $ingresos->registrar($datos, $clave)],
    ['GET',  '#^v1/ingresos/([0-9]{1,9})$#',    true,  fn(array $p): array => $ingresos->detalle($p[1])],
];
$conClave = ['v1/ventas', 'v1/ingresos'];

try {
    $encontrada = null;
    $permitidos = [];
    foreach ($rutas as [$verbo, $patron, $conToken, $accion]) {
        if (preg_match($patron, $ruta, $partes) === 1) {
            $permitidos[] = $verbo;
            if ($verbo === $metodo) {
                $encontrada = [$conToken, $accion, $partes];
                break;
            }
        }
    }
    if ($encontrada === null) {
        if ($permitidos !== []) {
            header('Allow: ' . implode(', ', $permitidos));
            $fallar(405, 'METODO_NO_PERMITIDO', 'Esa ruta no acepta ' . $metodo . '.');
        } else {
            $fallar(404, 'RUTA_DESCONOCIDA', 'La ruta pedida no existe.');
        }
        exit;
    }
    [$conToken, $accion, $partes] = $encontrada;
    if ($conToken && $usuario === null) {
        header('WWW-Authenticate: Bearer');
        $fallar(401, 'NO_AUTENTICADO', 'Tu sesión venció o no es válida. Inicia sesión otra vez.');
        exit;
    }

    $resultado = $accion($partes);
    if ($metodo === 'POST' && in_array($ruta, $conClave, true)) {
        $responder($resultado['repetida'] ? 200 : 201, ['ok' => true, 'repetida' => $resultado['repetida'], 'datos' => $resultado['respuesta']]);
    } else {
        $responder($ruta === 'v1/caja/turno' ? 201 : 200, ['ok' => true, 'datos' => $resultado]);
    }
} catch (ErrorApi $ex) {
    $fallar($ex->getHttp(), $ex->getClave(), $ex->getMessage());
} catch (ReglaNegocioException $ex) {
    // Stock insuficiente, turno cerrado, cantidad no valida...: el mismo mensaje que ve la pagina web
    $fallar(422, 'REGLA_NEGOCIO', $ex->getMessage());
} catch (Throwable $ex) {
    // Un error inesperado no muestra la traza ni el SQL: va al log del servidor
    error_log('Minimarket Mass API: ' . $ex::class . ': ' . $ex->getMessage() . ' en ' . $ex->getFile() . ':' . $ex->getLine());
    $fallar(500, 'ERROR_INTERNO', 'Ocurrió un error en el servidor. Intenta otra vez.');
}
