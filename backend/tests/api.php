<?php
declare(strict_types=1);

/**
 * Pruebas de la API /api/v1 que usa la app Android, contra la base de Docker:
 *   php tests/api.php
 *
 * Levanta solo un servidor `php -S` en el puerto 8097 (sirve la pagina web y la API a la vez).
 * OJO: registra ventas, un ingreso, una anulacion y un turno de verdad (como tests/integracion.php).
 * No depende de los numeros exactos de datos.sql: compara el stock antes y despues de cada operacion.
 * Revisa tambien que lo hecho desde la app se vea en la pagina web y al reves.
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la terminal.');
}
date_default_timezone_set('America/Lima');

const BASE = 'http://127.0.0.1:8097';
const CLAVE = 'Mass2026!';

$servidor = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:8097', '-t', __DIR__ . '/../../frontend/public'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $tuberias
);
for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', 8097) === false; $i++) {
    usleep(100_000);
}
register_shutdown_function(static fn() => proc_terminate($servidor));

$ok = 0;
$fallas = 0;
function revisar(string $caso, bool $cumple, string $detalle = ''): void
{
    global $ok, $fallas;
    $cumple ? $ok++ : $fallas++;
    echo ($cumple ? '  OK    ' : '  FALLA ') . $caso . ($cumple || $detalle === '' ? '' : '  -> ' . $detalle) . PHP_EOL;
}

/**
 * La app: JSON con el token en la cabecera Authorization.
 */
final class App
{
    public string $token = '';
    public int $codigo = 0;
    public array $json = [];

    public function entrar(string $dni, string $clave = CLAVE): int
    {
        $this->post('auth/login', ['dni' => $dni, 'clave' => $clave, 'dispositivo' => 'prueba']);
        $this->token = (string) ($this->json['datos']['token'] ?? '');
        return $this->codigo;
    }

    public function get(string $ruta): int
    {
        return $this->pedir('GET', $ruta, null, []);
    }

    public function post(string $ruta, array $datos, ?string $clave = null): int
    {
        return $this->pedir('POST', $ruta, json_encode($datos), $clave === null ? [] : ['Idempotency-Key: ' . $clave]);
    }

    public function crudo(string $metodo, string $ruta, ?string $cuerpo, array $cabeceras): int
    {
        return $this->pedir($metodo, $ruta, $cuerpo, $cabeceras, false);
    }

    /** Un pedido listo para curl_multi (dos a la vez) */
    public function preparar(string $ruta, array $datos, string $clave): CurlHandle
    {
        $c = curl_init(BASE . '/api/v1/' . $ruta);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($datos),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->token, 'Idempotency-Key: ' . $clave],
            CURLOPT_TIMEOUT        => 30,
        ]);
        return $c;
    }

    public function datos(): array
    {
        return is_array($this->json['datos'] ?? null) ? $this->json['datos'] : [];
    }

    public function mensaje(): string
    {
        return (string) ($this->json['error']['mensaje'] ?? '');
    }

    private function pedir(string $metodo, string $ruta, ?string $cuerpo, array $cabeceras, bool $json = true): int
    {
        $c = curl_init(BASE . '/api/v1/' . $ruta);
        if ($json && $cuerpo !== null) {
            $cabeceras[] = 'Content-Type: application/json';
        }
        if ($this->token !== '') {
            $cabeceras[] = 'Authorization: Bearer ' . $this->token;
        }
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($cuerpo !== null) {
            curl_setopt($c, CURLOPT_POSTFIELDS, $cuerpo);
        }
        $respuesta = (string) curl_exec($c);
        $this->codigo = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        curl_close($c);
        $this->json = json_decode($respuesta, true) ?? [];
        return $this->codigo;
    }
}

/**
 * El navegador de la pagina web (cookie de sesion y token CSRF), para cruzar web y app.
 */
final class Web
{
    private string $cookies;
    public int $codigo = 0;
    public string $cuerpo = '';

    public function __construct()
    {
        $this->cookies = tempnam(sys_get_temp_dir(), 'massapi');
    }

    public function __destruct()
    {
        @unlink($this->cookies);
    }

    public function entrar(string $dni): int
    {
        return $this->post('login', ['dni' => $dni, 'clave' => CLAVE], 'ruta=login');
    }

    public function get(string $consulta): int
    {
        return $this->pedir('/?' . $consulta, null);
    }

    // El token CSRF se lee de la pagina (el login antes de entrar, el inicio despues)
    public function post(string $ruta, array $datos, string $pagina = 'ruta=inicio'): int
    {
        $this->get($pagina);
        preg_match('/name="csrf" value="([0-9a-f]+)"/', $this->cuerpo, $m);
        $datos['csrf'] = $m[1] ?? '';
        return $this->pedir('/?ruta=' . $ruta, http_build_query($datos));
    }

    private function pedir(string $url, ?string $post): int
    {
        $c = curl_init(BASE . $url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE     => $this->cookies,
            CURLOPT_COOKIEJAR      => $this->cookies,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($post !== null) {
            curl_setopt($c, CURLOPT_POSTFIELDS, $post);
        }
        $this->cuerpo = (string) curl_exec($c);
        $this->codigo = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        curl_close($c);
        return $this->codigo;
    }
}

// Stock de un producto en la tienda del usuario, como texto "180.000"
function stockApp(App $app, int $idProducto): string
{
    $app->get('productos/' . $idProducto);
    return (string) ($app->datos()['stock'] ?? '?');
}

// Stock que muestra la ficha del producto en la pagina web: "180 (mínimo 20)"
function stockWeb(Web $web, int $idProducto, int $idTienda): ?int
{
    $web->get('ruta=producto&id=' . $idProducto . '&tienda=' . $idTienda);
    return preg_match('/([0-9][0-9,]*) \(mínimo/u', $web->cuerpo, $m) === 1 ? (int) str_replace(',', '', $m[1]) : null;
}

function nuevaClave(): string
{
    return bin2hex(random_bytes(16));
}

$luis = new App();    // CAJERO Cayma, turno abierto
$carmen = new App();  // ALMACENERO Cayma
$rosa = new App();    // ADMINISTRADOR Cayma
$miguel = new App();  // CAJERO Yanahuara, sin turno

echo "Salud y login\n";
$anonimo = new App();
revisar('GET /salud responde sin token', $anonimo->get('salud') === 200 && ($anonimo->json['ok'] ?? false) === true);
revisar('Ruta desconocida: 404 en JSON', $anonimo->get('no_existe') === 404 && ($anonimo->json['error']['codigo'] ?? '') === 'RUTA_DESCONOCIDA');
revisar('Método equivocado: 405', $anonimo->crudo('DELETE', 'productos', null, []) === 405);
revisar('Sin token: 401', $anonimo->get('productos') === 401);
$falso = new App();
$falso->token = str_repeat('a', 64);
revisar('Token inventado: 401', $falso->get('productos') === 401);
revisar('Clave errada: 401 con mensaje genérico', $anonimo->entrar('70000002', 'otra') === 401 && $anonimo->mensaje() === 'DNI o clave incorrectos.');
revisar('POST sin application/json: 415', $anonimo->crudo('POST', 'auth/login', 'dni=1', ['Content-Type: application/x-www-form-urlencoded']) === 415);
revisar('JSON roto: 400', $anonimo->crudo('POST', 'auth/login', '{dni:', ['Content-Type: application/json']) === 400);
revisar('Login del cajero con la clave de la web', $luis->entrar('70000002') === 200 && strlen($luis->token) === 64);
revisar('La respuesta del login no trae el DNI completo', ($luis->json['datos']['usuario']['dni'] ?? '') === '*****002');
$carmen->entrar('70000003');
$rosa->entrar('70000001');
$miguel->entrar('70000008');

echo "Permisos por rol\n";
$luis->get('auth/yo');
$permisosLuis = $luis->datos()['permisos'] ?? [];
revisar('Cajero: vende y no recibe mercadería', ($permisosLuis['vender'] ?? null) === true && ($permisosLuis['registrar_ingreso'] ?? null) === false);
$carmen->get('auth/yo');
$permisosCarmen = $carmen->datos()['permisos'] ?? [];
revisar('Almacenero: recibe mercadería y no vende', ($permisosCarmen['registrar_ingreso'] ?? null) === true && ($permisosCarmen['vender'] ?? null) === false);
revisar('Almacenero no puede registrar ventas (403)',
    $carmen->post('ventas', ['tipo_comprobante' => 'B', 'lineas' => [['id_producto' => '1', 'cantidad' => '1']], 'pago' => ['metodo' => 'EFECTIVO', 'efectivo_recibido' => '100']], nuevaClave()) === 403);
revisar('Cajero no puede registrar ingresos (403)', $luis->post('ingresos', ['documento' => [], 'lineas' => [['id_producto' => '1', 'cantidad' => '1']]], nuevaClave()) === 403);
revisar('Cajero no ve los ingresos con costos (403)', $luis->get('ingresos') === 403);

echo "Escáner y consulta de productos\n";
revisar('Código registrado: devuelve el producto con precio y stock',
    $luis->get('productos/codigo/7750000000011') === 200 && ($luis->datos()['id_producto'] ?? 0) === 1
    && preg_match('/^[0-9]+\.[0-9]{2}$/', (string) ($luis->datos()['precio_venta'] ?? '')) === 1 && isset($luis->datos()['stock']));
revisar('Código no registrado: 404 con mensaje claro', $luis->get('productos/codigo/7799999999999') === 404
    && str_contains($luis->mensaje(), 'no está registrado'));
revisar('Code 128 con letras: 404, no inventa nada', $luis->get('productos/codigo/ABC-123') === 404);
revisar('Búsqueda por nombre', $luis->get('productos?q=leche') === 200 && count($luis->datos()['productos'] ?? []) > 0
    && array_filter($luis->datos()['productos'], fn(array $p): bool => stripos($p['nombre'], 'leche') === false) === []);
revisar('Búsqueda por parte del código', $luis->get('productos?q=775000000001') === 200 && in_array(1, array_column($luis->datos()['productos'] ?? [], 'id_producto'), true));
revisar('Un % escrito no es comodín', $luis->get('productos?q=' . rawurlencode('%')) === 200 && ($luis->datos()['total'] ?? -1) === 0);
$luis->get('productos?filtro=bajo');
$bajos = $luis->datos()['productos'] ?? [];
revisar('Filtro stock bajo: todos están en su mínimo o debajo', array_filter($bajos, fn(array $p): bool => $p['stock_bajo'] !== true) === []);
$luis->get('productos?filtro=agotados');
revisar('Filtro agotados: todos con stock 0', array_filter($luis->datos()['productos'] ?? [], fn(array $p): bool => $p['agotado'] !== true) === []);
revisar('Ficha del producto con sus movimientos', $luis->get('productos/1') === 200 && is_array($luis->datos()['movimientos'] ?? null));

echo "Venta desde la app\n";
$web = new Web();
$web->entrar('70000001');   // Rosa mira la pagina web
$antes = stockApp($luis, 1);
$antesWeb = stockWeb($web, 1, 1);
revisar('La web y la app muestran el mismo stock', $antesWeb !== null && (int) $antes === $antesWeb, "app $antes, web " . var_export($antesWeb, true));
$luis->get('productos/1');
$precio = (string) ($luis->datos()['precio_venta'] ?? '0');
$total = number_format(2 * (float) $precio, 2, '.', '');
$venta = ['tipo_comprobante' => 'B', 'documento' => '', 'total_esperado' => $total,
    'lineas' => [['id_producto' => '1', 'cantidad' => '2']], 'pago' => ['metodo' => 'EFECTIVO', 'efectivo_recibido' => '50.00']];

revisar('Sin Idempotency-Key: 422', $luis->post('ventas', $venta) === 422 && str_contains($luis->mensaje(), 'Idempotency-Key'));
revisar('Cantidad con decimales en producto por unidad: 422',
    $luis->post('ventas', ['lineas' => [['id_producto' => '1', 'cantidad' => '1.5']]] + $venta, nuevaClave()) === 422);
revisar('Cantidad 0: 422', $luis->post('ventas', ['lineas' => [['id_producto' => '1', 'cantidad' => '0']]] + $venta, nuevaClave()) === 422);
revisar('Cantidad negativa: 422', $luis->post('ventas', ['lineas' => [['id_producto' => '1', 'cantidad' => '-2']]] + $venta, nuevaClave()) === 422);
revisar('Stock insuficiente: el servidor la rechaza (422)',
    $luis->post('ventas', ['lineas' => [['id_producto' => '1', 'cantidad' => '9999999']], 'total_esperado' => '',
        'pago' => ['metodo' => 'EFECTIVO', 'efectivo_recibido' => '99999999.99']] + $venta, nuevaClave()) === 422
    && str_contains($luis->mensaje(), 'Stock insuficiente'), $luis->mensaje());
revisar('Precio cambiado desde que se armó el carrito: 409', $luis->post('ventas', ['total_esperado' => '0.10'] + $venta, nuevaClave()) === 409);
revisar('Efectivo que no alcanza: 422', $luis->post('ventas', ['pago' => ['metodo' => 'EFECTIVO', 'efectivo_recibido' => '0.50']] + $venta, nuevaClave()) === 422);
revisar('Método de pago inventado: 422', $luis->post('ventas', ['pago' => ['metodo' => 'YAPE']] + $venta, nuevaClave()) === 422);
revisar('Nada de lo rechazado movió el stock', stockApp($luis, 1) === $antes);

$clave = nuevaClave();
$codigo = $luis->post('ventas', $venta, $clave);
$registrada = $luis->datos();
revisar('Venta registrada: 201 con comprobante', $codigo === 201 && preg_match('/^B[0-9]{3}-[0-9]{8}$/', (string) ($registrada['comprobante'] ?? '')) === 1, $luis->mensaje());
revisar('El total lo calculó el servidor', ($registrada['total'] ?? '') === $total);
$efectivo = $registrada['pagos'][0] ?? [];
revisar('Efectivo con su vuelto', ($efectivo['metodo'] ?? '') === 'EFECTIVO' && ($efectivo['vuelto'] ?? '') === number_format(50 - (float) $total, 2, '.', ''));
$despues = stockApp($luis, 1);
revisar('El stock bajó 2', (float) $despues === (float) $antes - 2, "antes $antes, después $despues");
$luis->get('productos/1');
$ultimo = $luis->datos()['movimientos'][0] ?? [];
revisar('La salida quedó en el kardex como venta', ($ultimo['tipo'] ?? '') === 'VENTA' && ($ultimo['origen'] ?? '') === 'Venta ' . ($registrada['id_venta'] ?? 0));

revisar('Reintento con la misma clave: 200 y la misma venta', $luis->post('ventas', $venta, $clave) === 200
    && ($luis->json['repetida'] ?? false) === true && ($luis->datos()['comprobante'] ?? '') === ($registrada['comprobante'] ?? 'x'));
revisar('El reintento no volvió a bajar el stock', stockApp($luis, 1) === $despues);

// Dos pedidos iguales a la vez (doble toque o reintento mientras el primero sigue en curso)
$clave = nuevaClave();
$multi = curl_multi_init();
$pedidos = [$luis->preparar('ventas', $venta, $clave), $luis->preparar('ventas', $venta, $clave)];
foreach ($pedidos as $p) {
    curl_multi_add_handle($multi, $p);
}
do {
    curl_multi_exec($multi, $corriendo);
    curl_multi_select($multi);
} while ($corriendo > 0);
$codigos = [];
$comprobantes = [];
foreach ($pedidos as $p) {
    $codigos[] = (int) curl_getinfo($p, CURLINFO_RESPONSE_CODE);
    $comprobantes[] = json_decode((string) curl_multi_getcontent($p), true)['datos']['comprobante'] ?? null;
    curl_multi_remove_handle($multi, $p);
}
sort($codigos);
revisar('Dos pedidos a la vez con la misma clave: una sola venta', $codigos === [200, 201] && $comprobantes[0] !== null && $comprobantes[0] === $comprobantes[1],
    implode(',', $codigos));
revisar('Y el stock bajó solo una vez', (float) stockApp($luis, 1) === (float) $despues - 2);
$despues = stockApp($luis, 1);

echo "La web ve lo que hizo la app\n";
$numero = (int) substr((string) $registrada['comprobante'], 5);
revisar('El comprobante de la app se abre en la web', $web->get('ruta=comprobante&serie=' . substr((string) $registrada['comprobante'], 0, 4) . '&numero=' . $numero) === 200
    && str_contains($web->cuerpo, 'Leche evaporada Gloria entera'));
revisar('La ficha web muestra el stock nuevo', stockWeb($web, 1, 1) === (int) $despues, 'web ' . var_export(stockWeb($web, 1, 1), true) . ", app $despues");

echo "La app ve lo que hizo la web\n";
$webLuis = new Web();
$webLuis->entrar('70000002');
$webLuis->post('venta_registrar', ['tipo' => 'B', 'documento' => '', 'producto' => ['1'], 'cantidad' => ['3'], 'efectivo_recibido' => '100']);
revisar('Venta registrada en la web (303 al comprobante)', $webLuis->codigo === 303);
revisar('La app ve 3 unidades menos', (float) stockApp($luis, 1) === (float) $despues - 3);
$luis->get('ventas');
$historial = $luis->datos()['ventas'] ?? [];
revisar('El historial de la app trae la venta de la web primero', ($historial[0]['productos'] ?? 0) === 1 && ($historial[0]['estado'] ?? '') === 'EMITIDA'
    && ($historial[0]['comprobante'] ?? '') !== ($registrada['comprobante'] ?? ''));

echo "Historial y detalle\n";
revisar('Detalle de la venta de la app', $luis->get('ventas/' . $registrada['id_venta']) === 200
    && ($luis->datos()['lineas'][0]['cantidad'] ?? '') === '2.000' && ($luis->datos()['cajero'] ?? '') === 'Luis Caceres Pinto');
revisar('Buscar por número de comprobante', $luis->get('ventas?comprobante=' . $registrada['comprobante']) === 200
    && ($luis->datos()['total'] ?? 0) === 1);
revisar('Filtro por fecha de hoy', $luis->get('ventas?desde=' . date('Y-m-d') . '&hasta=' . date('Y-m-d')) === 200 && ($luis->datos()['total'] ?? 0) >= 3);
revisar('Ventas de otra tienda: 403', $miguel->get('ventas/' . $registrada['id_venta']) === 403);
revisar('El cajero no puede anular (403)', $luis->post('ventas/' . $registrada['id_venta'] . '/anular', ['motivo' => 'prueba']) === 403);

echo "Anulación con el proceso del administrador\n";
$antesAnular = stockApp($rosa, 1);
revisar('El administrador anula con motivo', $rosa->post('ventas/' . $registrada['id_venta'] . '/anular', ['motivo' => 'Prueba de la app']) === 200
    && ($rosa->datos()['estado'] ?? '') === 'ANULADA' && ($rosa->datos()['anulacion']['motivo'] ?? '') === 'Prueba de la app', $rosa->mensaje());
revisar('El stock volvió', (float) stockApp($rosa, 1) === (float) $antesAnular + 2);
revisar('No se anula dos veces (422)', $rosa->post('ventas/' . $registrada['id_venta'] . '/anular', ['motivo' => 'otra']) === 422);
revisar('La venta sigue en el historial (no se borra)', $rosa->get('ventas/' . $registrada['id_venta']) === 200);

echo "Otros métodos de pago\n";
$luis->get('productos/2');
$precio2 = (string) ($luis->datos()['precio_venta'] ?? '0');
$tarjeta = ['tipo_comprobante' => 'B', 'total_esperado' => $precio2, 'lineas' => [['id_producto' => '2', 'cantidad' => '1']],
    'pago' => ['metodo' => 'TARJETA', 'tarjeta_tipo' => 'DEBITO', 'tarjeta_marca' => 'VISA', 'tarjeta_ultimos4' => '4242', 'tarjeta_autorizacion' => 'A1B2C3']];
revisar('Tarjeta: se registra el método y el voucher', $luis->post('ventas', $tarjeta, nuevaClave()) === 201
    && ($luis->datos()['pagos'][0]['metodo'] ?? '') === 'TARJETA', $luis->mensaje());
revisar('Tarjeta sin últimos 4 dígitos: 422', $luis->post('ventas', ['pago' => ['tarjeta_ultimos4' => ''] + $tarjeta['pago']] + $tarjeta, nuevaClave()) === 422);
$sip = ['pago' => ['metodo' => 'SIP', 'sip_modalidad' => 'QR', 'sip_operacion' => 'OP-' . bin2hex(random_bytes(4))]] + $tarjeta;
revisar('Sip con código de operación', $luis->post('ventas', $sip, nuevaClave()) === 201 && ($luis->datos()['pagos'][0]['metodo'] ?? '') === 'BILLETERA DIGITAL SIP', $luis->mensaje());
revisar('Mismo código Sip en otra venta: 422', $luis->post('ventas', $sip, nuevaClave()) === 422);

echo "Caja\n";
$miguel->get('caja');
$cajasMiguel = $miguel->datos()['cajas'] ?? [];
if (($miguel->datos()['turno'] ?? null) !== null) {
    // Solo en una base ya usada por una corrida anterior de esta prueba
    echo "  --    Miguel ya tiene turno abierto: se omiten las pruebas de abrir turno\n";
} else {
    revisar('Cajero sin turno: la app lo sabe', $miguel->codigo === 200 && array_key_exists('turno', $miguel->datos()));
    revisar('Vender sin turno: 422 con el mensaje de la web', $miguel->post('ventas', $venta, nuevaClave()) === 422 && str_contains($miguel->mensaje(), 'turno'));
    $cajaActiva = array_values(array_filter($cajasMiguel, fn(array $c): bool => $c['estado'] === 'ACTIVA'))[0]['id_caja'] ?? 0;
    revisar('Abrir turno desde la app', $miguel->post('caja/turno', ['id_caja' => (string) $cajaActiva, 'monto_inicial' => '50.00']) === 201
        && ($miguel->datos()['turno']['monto_inicial'] ?? '') === '50.00', $miguel->mensaje());
    revisar('No se abren dos turnos (422)', $miguel->post('caja/turno', ['id_caja' => (string) $cajaActiva, 'monto_inicial' => '50.00']) === 422);
}

echo "Entrada de mercadería\n";
$antes = stockApp($carmen, 1);
$numeroDoc = 'T' . random_int(100, 999) . '-' . random_int(10000, 99999);
$ingreso = ['documento' => ['tipo' => 'GUIA', 'numero' => $numeroDoc, 'fecha' => date('Y-m-d'), 'ruc' => '20100070970',
    'razon_social' => 'Proveedor de prueba S.A.C.', 'observacion' => 'Desde la app'],
    'lineas' => [['id_producto' => '1', 'cantidad' => '10', 'costo' => '3.20', 'vence' => '']]];
revisar('Costo no válido: 422', $carmen->post('ingresos', ['lineas' => [['id_producto' => '1', 'cantidad' => '10', 'costo' => 'abc']]] + $ingreso, nuevaClave()) === 422);
revisar('RUC del emisor no válido: 422', $carmen->post('ingresos', ['documento' => ['ruc' => '123'] + $ingreso['documento']] + $ingreso, nuevaClave()) === 422);
$clave = nuevaClave();
revisar('Ingreso registrado: 201', $carmen->post('ingresos', $ingreso, $clave) === 201, $carmen->mensaje());
$recibido = $carmen->datos();
revisar('La respuesta trae el documento y el stock nuevo', ($recibido['numero_documento'] ?? '') === $numeroDoc
    && (float) ($recibido['lineas'][0]['stock_actual'] ?? -1) === (float) $antes + 10);
revisar('El stock subió 10 una sola vez', (float) stockApp($carmen, 1) === (float) $antes + 10);
$carmen->get('productos/1');
revisar('Quedó en el kardex como ingreso, no como venta ni ajuste', ($carmen->datos()['movimientos'][0]['tipo'] ?? '') === 'INGRESO DE MERCADERIA');
revisar('Reintento con la misma clave: 200 sin sumar otra vez', $carmen->post('ingresos', $ingreso, $clave) === 200
    && (float) stockApp($carmen, 1) === (float) $antes + 10);
revisar('El mismo documento con otra clave: 422 (no se recibe dos veces)', $carmen->post('ingresos', $ingreso, nuevaClave()) === 422
    && (float) stockApp($carmen, 1) === (float) $antes + 10);
revisar('El ingreso aparece en la lista', $carmen->get('ingresos') === 200 && ($carmen->datos()['ingresos'][0]['id_ingreso'] ?? 0) === ($recibido['id_ingreso'] ?? -1));
revisar('La web ve el stock nuevo', stockWeb($web, 1, 1) === (int) stockApp($carmen, 1));
$web->get('ruta=ingresos');
revisar('La web lista el ingreso hecho desde la app', str_contains($web->cuerpo, $numeroDoc));

echo "Cerrar sesión\n";
revisar('Logout', $luis->post('auth/logout', []) === 200);
revisar('El token ya no sirve (401)', $luis->get('productos') === 401);

echo "Base\n";
$db = (function (): PDO {
    require_once __DIR__ . '/../config/Database.php';
    return Database::conexion();
})();
revisar('Los tokens se guardan como SHA-256, no en claro', (int) $db->query("SELECT COUNT(*) FROM api_token WHERE CHAR_LENGTH(token_hash) <> 64")->fetchColumn() === 0);
revisar('Cada operación de la app guardó su respuesta', (int) $db->query('SELECT COUNT(*) FROM api_operacion WHERE respuesta IS NULL')->fetchColumn() === 0);
revisar('Ningún stock negativo', (int) $db->query('SELECT COUNT(*) FROM stock WHERE cantidad_actual < 0')->fetchColumn() === 0);

echo PHP_EOL . "$ok correctas, $fallas fallas" . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
