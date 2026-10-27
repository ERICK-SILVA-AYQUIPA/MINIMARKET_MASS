<?php
declare(strict_types=1);

/**
 * Pruebas web: piden las paginas como lo haria el navegador (login, cookies, token CSRF)
 * y revisan los codigos HTTP de cada rol, la validacion y las defensas de seguridad.
 *   php tests/web.php
 *
 * Levanta solo un servidor `php -S` en el puerto 8099 y usa la base de Docker.
 * No cambia datos: los formularios que manda son rechazados (403 o 422) a proposito.
 * Se puede correr antes o despues de tests/integracion.php.
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la terminal.');
}
date_default_timezone_set('America/Lima');

const BASE = 'http://127.0.0.1:8099';
const CLAVE = 'Mass2026!';

$servidor = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:8099', '-t', __DIR__ . '/../public'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $tuberias
);
// Espera a que el servidor acepte conexiones (hasta 5 segundos)
for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', 8099) === false; $i++) {
    usleep(100_000);
}
register_shutdown_function(static fn() => proc_terminate($servidor));

$ok = 0;
$fallas = 0;
function revisar(string $caso, bool $cumple): void
{
    global $ok, $fallas;
    $cumple ? $ok++ : $fallas++;
    echo ($cumple ? '  OK    ' : '  FALLA ') . $caso . PHP_EOL;
}

/**
 * Un navegador con su propia cookie de sesion.
 */
final class Navegador
{
    private string $cookies;
    public int $codigo = 0;
    public string $cuerpo = '';
    /** @var array<string, string> */
    public array $cabeceras = [];

    public function __construct()
    {
        $this->cookies = tempnam(sys_get_temp_dir(), 'mass');
    }

    public function __destruct()
    {
        @unlink($this->cookies);
    }

    public function get(string $consulta): int
    {
        return $this->pedir('/?' . $consulta, null);
    }

    /** POST con el token CSRF de la sesion (salvo que se pase otro) */
    public function post(string $ruta, array $datos, bool $conCsrf = true): int
    {
        if ($conCsrf && !array_key_exists('csrf', $datos)) {
            $datos['csrf'] = $this->token();
        }
        return $this->pedir('/?ruta=' . $ruta, http_build_query($datos));
    }

    public function entrar(string $dni, string $clave = CLAVE): int
    {
        $this->get('ruta=login');
        return $this->post('login', ['dni' => $dni, 'clave' => $clave]);
    }

    public function token(): string
    {
        $this->get('ruta=login');
        if (preg_match('/name="csrf" value="([0-9a-f]+)"/', $this->cuerpo, $m) !== 1) {
            // Con sesion, el login redirige: el token se lee del panel
            $this->get('ruta=inicio');
            preg_match('/name="csrf" value="([0-9a-f]+)"/', $this->cuerpo, $m);
        }
        return $m[1] ?? '';
    }

    public function tiene(string $texto): bool
    {
        return str_contains($this->cuerpo, $texto);
    }

    private function pedir(string $url, ?string $post): int
    {
        $c = curl_init(BASE . $url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_COOKIEFILE     => $this->cookies,
            CURLOPT_COOKIEJAR      => $this->cookies,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($post !== null) {
            curl_setopt($c, CURLOPT_POSTFIELDS, $post);
        }
        $respuesta = (string) curl_exec($c);
        $this->codigo = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        $largo = (int) curl_getinfo($c, CURLINFO_HEADER_SIZE);
        curl_close($c);

        $this->cabeceras = [];
        foreach (explode("\r\n", substr($respuesta, 0, $largo)) as $linea) {
            if (str_contains($linea, ':')) {
                [$nombre, $valor] = explode(':', $linea, 2);
                $this->cabeceras[strtolower(trim($nombre))] = trim($valor);
            }
        }
        $this->cuerpo = substr($respuesta, $largo);
        return $this->codigo;
    }
}

echo "Sin sesión\n";
$anonimo = new Navegador();
revisar('/ lleva al login (303)', $anonimo->get('') === 303 && str_contains($anonimo->cabeceras['location'] ?? '', 'ruta=login'));
revisar('Cualquier pantalla lleva al login', $anonimo->get('ruta=reportes') === 303);
revisar('El login responde 200', $anonimo->get('ruta=login') === 200);
$csp = $anonimo->cabeceras['content-security-policy'] ?? '';
revisar('Cabecera CSP: solo scripts del propio sitio, nada inline', str_contains($csp, "script-src 'self';")
    && !preg_match("/script-src[^;]*('unsafe-inline'|'unsafe-eval'|https?:)/", $csp) && str_contains($csp, "object-src 'none'"));
revisar('Cabeceras X-Frame-Options DENY y nosniff', ($anonimo->cabeceras['x-frame-options'] ?? '') === 'DENY'
    && ($anonimo->cabeceras['x-content-type-options'] ?? '') === 'nosniff');
$sinCookie = new Navegador();
$sinCookie->get('ruta=login');
$cookie = strtolower($sinCookie->cabeceras['set-cookie'] ?? '');
revisar('Cookie de sesión httponly y SameSite=Lax', str_contains($cookie, 'httponly') && str_contains($cookie, 'samesite=lax'));
revisar('Login sin token CSRF: 403', $anonimo->post('login', ['dni' => '70000001', 'clave' => CLAVE], false) === 403);
revisar('Login con token falso: 403', $anonimo->post('login', ['dni' => '70000001', 'clave' => CLAVE, 'csrf' => str_repeat('0', 64)]) === 403);
revisar('Clave errada: 401 con mensaje genérico', $anonimo->entrar('70000001', 'otra-clave1') === 401 && $anonimo->tiene('DNI o clave incorrectos.'));
revisar('DNI inexistente: el mismo 401 y el mismo mensaje', $anonimo->entrar('79999990', 'otra-clave1') === 401 && $anonimo->tiene('DNI o clave incorrectos.'));
revisar('El DNI escrito vuelve escapado', $anonimo->post('login', ['dni' => '"><b>x', 'clave' => 'x']) === 401
    && !$anonimo->tiene('"><b>x') && $anonimo->tiene('&quot;&gt;&lt;b&gt;x'));

// Bloqueo por DNI: a lo mas 5 fallos seguidos y el siguiente intento responde 429
$codigos = [];
for ($i = 0; $i < 6; $i++) {
    $codigos[] = $anonimo->entrar('79999991', 'otra-clave1');
}
revisar('Muchas claves erradas para un DNI: 429', in_array(429, $codigos, true));

echo "Errores y entrada rara\n";
$rosa = new Navegador();
revisar('Rosa entra y va al panel de inicio', $rosa->entrar('70000001') === 303 && str_contains($rosa->cabeceras['location'] ?? '', 'ruta=inicio'));
revisar('Panel de Rosa (200) con indicadores', $rosa->get('ruta=inicio') === 200 && $rosa->tiene('Vendido hoy') && $rosa->tiene('Ventas de los últimos 14 días'));
revisar('Ruta inexistente: 404', $rosa->get('ruta=xyz') === 404);
revisar('<script> en la ruta no vuelve en la página', $rosa->get('ruta=%3Cscript%3Ealert(1)%3C/script%3E') === 404 && !$rosa->tiene('<script>alert'));
revisar('<script> en la tienda se ignora', $rosa->get('ruta=productos&tienda=%3Cscript%3E') === 200 && !$rosa->tiene('<script>'));
revisar('?ruta[]=x no rompe la página', $rosa->get('ruta[]=x') === 200);
revisar('Comprobante con serie inválida: 404', $rosa->get('ruta=comprobante&serie=X%27%20OR%201=1--&numero=1') === 404);
revisar('Comprobante que no existe: 404', $rosa->get('ruta=comprobante&serie=B001&numero=99999') === 404);
revisar('Producto con id gigante: 404', $rosa->get('ruta=producto&id=99999999999999999999') === 404);
revisar('Página fuera de rango se corrige', $rosa->get('ruta=ventas&pagina=99999') === 200);
revisar('Fecha imposible se ignora', $rosa->get('ruta=ventas&desde=2026-02-30') === 200);
revisar('POST a una ruta de lectura: 405 con Allow', $rosa->post('productos', []) === 405 && ($rosa->cabeceras['allow'] ?? '') === 'GET');
revisar('POST sin token con sesión: 403', $rosa->post('categoria_crear', ['nombre' => 'X'], false) === 403);

echo "Pantallas por rol (GET)\n";
$luis = new Navegador();
$carmen = new Navegador();
revisar('Luis (cajero) y Carmen (almacenero) entran', $luis->entrar('70000002') === 303 && $carmen->entrar('70000003') === 303);
$todas = ['inicio', 'productos', 'stock', 'ventas', 'kardex', 'traslados', 'mermas', 'tiendas', 'mi_clave',
    'comprobante&serie=B001&numero=1', 'comprobante_imprimir&serie=F001&numero=1', 'producto&id=8', 'catalogo',
    'stock_csv', 'kardex_csv&tienda=1'];
$matriz = [
    // ruta                => [Rosa, Luis, Carmen]
    'caja'                 => [200, 200, 403],
    'venta_nueva'          => [403, 200, 403],
    'clientes'             => [200, 200, 403],
    'cliente&id=1'         => [200, 200, 403],
    'ajustes'              => [200, 200, 200],
    'reportes'             => [200, 403, 403],
    'reporte_csv'          => [200, 403, 403],
    'empleados'            => [200, 403, 403],
    'empleado&id=3'        => [200, 403, 403],
    'empleado&id=4'        => [404, 403, 403],
    'producto_form'        => [200, 403, 403],
    'producto_form&id=8'   => [200, 403, 403],
    'tienda_editar'        => [200, 403, 403],
    'tienda_nueva'         => [200, 403, 403],
    'auditoria'            => [200, 403, 403],
    'auditoria&accion=X'   => [200, 403, 403],
    'ingresos'             => [200, 403, 200],
    'ingresos_csv'         => [200, 403, 200],
];
foreach ($todas as $ruta) {
    $matriz[$ruta] = [200, 200, 200];
}
foreach ($matriz as $ruta => [$r, $l, $c]) {
    $obtenido = [$rosa->get('ruta=' . $ruta), $luis->get('ruta=' . $ruta), $carmen->get('ruta=' . $ruta)];
    revisar(sprintf('%-20s Rosa %d · Luis %d · Carmen %d', $ruta, $r, $l, $c), $obtenido === [$r, $l, $c]);
}
revisar('La hoja de conteo solo se muestra a quien puede contar', $carmen->get('ruta=ajustes') === 200 && $carmen->tiene('ajuste_registrar')
    && $luis->get('ruta=ajustes') === 200 && !$luis->tiene('ajuste_registrar'));
revisar('El menú del cajero no ofrece Reportes ni Personal', $luis->get('ruta=inicio') === 200
    && !$luis->tiene('ruta=reportes"') && !$luis->tiene('ruta=empleados"'));
revisar('El ticket para imprimir es una página suelta de 80 mm', $luis->get('ruta=comprobante_imprimir&serie=B001&numero=1') === 200
    && $luis->tiene('size: 80mm') && $luis->tiene('TIENDA MASS') && !$luis->tiene('Menú principal'));
revisar('El ticket de una venta anulada lo dice', $luis->get('ruta=comprobante_imprimir&serie=B001&numero=2') === 200 && $luis->tiene('>ANULADA<'));
revisar('Ticket de un comprobante que no existe: 404', $luis->get('ruta=comprobante_imprimir&serie=B001&numero=99999') === 404);
revisar('El stock y el kardex se descargan en CSV', $carmen->get('ruta=stock_csv') === 200 && str_starts_with($carmen->cabeceras['content-type'] ?? '', 'text/csv')
    && $carmen->get('ruta=kardex_csv&tienda=2&producto=1') === 200 && str_contains($carmen->cuerpo, 'stock_nuevo'));
revisar('Solo el almacenero ve el formulario de ingreso; el administrador lo revisa', $carmen->get('ruta=ingresos') === 200
    && $carmen->tiene('ingreso_registrar') && $rosa->get('ruta=ingresos') === 200 && !$rosa->tiene('ingreso_registrar'));
revisar('Ingresos está en el menú del almacenero y del administrador, no del cajero', $carmen->get('ruta=inicio') === 200
    && $carmen->tiene('ruta=ingresos"') && $rosa->get('ruta=inicio') === 200 && $rosa->tiene('ruta=ingresos"')
    && $luis->get('ruta=inicio') === 200 && !$luis->tiene('ruta=ingresos"'));
revisar('Los ingresos se descargan en CSV con sus costos', $rosa->get('ruta=ingresos_csv') === 200
    && str_starts_with($rosa->cabeceras['content-type'] ?? '', 'text/csv') && str_contains($rosa->cuerpo, 'costo_unitario'));
revisar('La auditoría está en el menú del administrador', $rosa->get('ruta=inicio') === 200 && $rosa->tiene('ruta=auditoria"'));
revisar('Las pantallas cargan solo /app.js y el ticket ninguno', $rosa->get('ruta=inicio') === 200 && $rosa->tiene('<script src="/app.js')
    && substr_count($rosa->cuerpo, '<script') === 1 && $luis->get('ruta=comprobante_imprimir&serie=B001&numero=1') === 200 && !$luis->tiene('<script'));
revisar('La ficha del producto se abre en el panel lateral', $rosa->get('ruta=productos&ver=8') === 200
    && $rosa->tiene('id="ficha-producto"') && $rosa->tiene('drawer abierto'));
revisar('Un panel pedido con un id que no existe no se abre', $rosa->get('ruta=productos&ver=99999') === 200 && !$rosa->tiene('drawer abierto')
    && $rosa->get('ruta=ventas&ver=%3Cscript%3E') === 200 && !$rosa->tiene('drawer abierto') && !$rosa->tiene('<script>'));
revisar('El detalle de una venta y la ficha del cliente se abren en su panel', $luis->get('ruta=ventas&ver=B001-1') === 200
    && $luis->tiene('id="detalle-venta"') && $rosa->get('ruta=clientes&ver=1') === 200 && $rosa->tiene('id="ficha-cliente"'));
revisar('El panel del cliente sigue cerrado para el almacenero', $carmen->get('ruta=clientes&ver=1') === 403);
revisar('El kardex y la auditoría traen el detalle de cada fila', $rosa->get('ruta=kardex') === 200 && $rosa->tiene('href="#mov-')
    && $rosa->get('ruta=auditoria') === 200 && ($rosa->tiene('href="#aud-') || $rosa->tiene('Todavía no hay acciones registradas')));
revisar('El CSV sale como texto CSV con BOM', $rosa->get('ruta=reporte_csv&tipo=productos') === 200
    && str_starts_with($rosa->cabeceras['content-type'] ?? '', 'text/csv') && str_starts_with($rosa->cuerpo, "\xEF\xBB\xBF"));

echo "Formularios de otro rol (POST): 403 sin tocar la base\n";
$prohibidos = [
    [$luis, 'Luis', 'producto_guardar', ['nombre' => 'X', 'precio' => '1']],
    [$luis, 'Luis', 'producto_estado', ['id' => '1', 'estado' => 'DESCONTINUADO']],
    [$luis, 'Luis', 'stock_minimo', ['id' => '1', 'minimo' => '5']],
    [$luis, 'Luis', 'categoria_crear', ['nombre' => 'Prueba']],
    [$luis, 'Luis', 'marca_crear', ['nombre' => 'Prueba']],
    [$luis, 'Luis', 'empleado_crear', ['nombres' => 'A']],
    [$luis, 'Luis', 'empleado_guardar', ['id' => '3']],
    [$luis, 'Luis', 'empleado_clave', ['id' => '3', 'clave' => 'Clave2026x']],
    [$luis, 'Luis', 'venta_anular', ['serie' => 'B001', 'numero' => '1', 'motivo' => 'prueba']],
    [$luis, 'Luis', 'ingreso_registrar', ['tipo_documento' => 'FACTURA']],
    [$luis, 'Luis', 'traslado_registrar', ['destino' => '2']],
    [$luis, 'Luis', 'merma_registrar', ['motivo' => 'VENCIDO']],
    [$luis, 'Luis', 'ajuste_registrar', ['motivo' => 'CONTEO_FISICO']],
    [$luis, 'Luis', 'tienda_guardar', ['nombre' => 'X']],
    [$luis, 'Luis', 'tienda_abrir', ['nombre' => 'X']],
    [$luis, 'Luis', 'caja_crear', []],
    [$luis, 'Luis', 'caja_estado', ['id' => '1', 'estado' => 'INACTIVA']],
    [$luis, 'Luis', 'distrito_crear', ['nombre' => 'Prueba']],
    [$carmen, 'Carmen', 'venta_registrar', ['tipo' => 'B']],
    [$carmen, 'Carmen', 'turno_abrir', ['caja' => '1', 'monto_inicial' => '100']],
    [$carmen, 'Carmen', 'turno_cerrar', ['monto_cierre' => '100']],
    [$carmen, 'Carmen', 'cliente_documento', ['id' => '1', 'dni' => '12345678']],
    [$rosa, 'Rosa', 'venta_registrar', ['tipo' => 'B']],
    [$rosa, 'Rosa', 'traslado_registrar', ['destino' => '2']],
    [$rosa, 'Rosa', 'ingreso_registrar', ['tipo_documento' => 'FACTURA']],
];
foreach ($prohibidos as [$quien, $nombre, $ruta, $datos]) {
    revisar("$nombre → $ruta: 403", $quien->post($ruta, $datos) === 403);
}
$jorge = new Navegador();
revisar('Jorge (admin de Cerro Colorado) no anula una venta de Cayma: 403', $jorge->entrar('70000004') === 303
    && $jorge->post('venta_anular', ['serie' => 'B001', 'numero' => '1', 'motivo' => 'prueba']) === 403);

echo "Validación: 422 con el formulario y el error, sin guardar\n";
revisar('Producto sin datos: 422', $rosa->post('producto_guardar', ['nombre' => '']) === 422 && $rosa->tiene('obligatorio'));
revisar('Lo escrito vuelve escapado', $rosa->post('producto_guardar', ['nombre' => '<b>negrita</b>', 'precio' => 'abc']) === 422
    && !$rosa->tiene('<b>negrita</b>') && $rosa->tiene('&lt;b&gt;negrita'));
revisar('Venta sin productos: 422', $luis->post('venta_registrar', ['tipo' => 'B']) === 422);
revisar('Venta con tipo inventado: 422', $luis->post('venta_registrar', ['tipo' => 'Z']) === 422);
revisar('Traslado a la misma tienda: 422', $carmen->post('traslado_registrar', ['destino' => '1', 'producto' => ['1'], 'cantidad' => ['1']]) === 422);
revisar('Traslado con cantidad negativa: 422', $carmen->post('traslado_registrar', ['destino' => '2', 'producto' => ['1'], 'cantidad' => ['-5']]) === 422);
revisar('Merma con motivo inventado: 422', $carmen->post('merma_registrar', ['motivo' => 'PERDIDO', 'producto' => ['1'], 'cantidad' => ['1']]) === 422);
revisar('Ingreso con RUC corto: 422 y vuelve lo escrito', $carmen->post('ingreso_registrar', ['tipo_documento' => 'FACTURA',
    'numero_documento' => 'F001-1', 'fecha_documento' => date('Y-m-d'), 'ruc_emisor' => '123', 'razon_social' => '<b>Emisor</b>',
    'producto' => ['1'], 'cantidad' => ['1'], 'costo' => ['3.60']]) === 422 && $carmen->tiene('11 dígitos') && $carmen->tiene('&lt;b&gt;Emisor'));
revisar('Ingreso sin costo: 422', $carmen->post('ingreso_registrar', ['tipo_documento' => 'GUIA', 'numero_documento' => 'T001-1',
    'fecha_documento' => date('Y-m-d'), 'ruc_emisor' => '20100055237', 'razon_social' => 'Emisor',
    'producto' => ['1'], 'cantidad' => ['1'], 'costo' => ['']]) === 422 && $carmen->tiene('costo unitario no válido'));
revisar('Conteo con texto: 422', $carmen->post('ajuste_registrar', ['motivo' => 'CONTEO_FISICO', 'producto' => ['1'], 'cantidad' => ['muchos']]) === 422);
revisar('Empleado con DNI de 7 dígitos: 422', $rosa->post('empleado_crear',
    ['nombres' => 'Ana', 'apellidos' => 'Ruiz', 'dni' => '1234567', 'rol' => '2', 'clave' => 'Clave2026x']) === 422);
revisar('Empleado con clave débil: 422', $rosa->post('empleado_crear',
    ['nombres' => 'Ana', 'apellidos' => 'Ruiz', 'dni' => '12345678', 'rol' => '2', 'clave' => '123']) === 422);
revisar('Tienda con teléfono con letras: 422', $rosa->post('tienda_guardar',
    ['nombre' => 'Mass Cayma', 'direccion' => 'Av. 1', 'telefono' => 'abc', 'distrito' => '1', 'estado' => 'ACTIVA']) === 422);
revisar('Mi clave con la actual errada: 422', $rosa->post('mi_clave_guardar', ['actual' => 'otra', 'nueva' => 'Nueva2026x', 'repetida' => 'Nueva2026x']) === 422);
revisar('Turno con monto inválido: 422', $luis->post('turno_abrir', ['caja' => '1', 'monto_inicial' => '-1']) === 422);

echo "Salir\n";
revisar('Salir cierra la sesión', $carmen->post('salir', []) === 303 && $carmen->get('ruta=inicio') === 303);

echo PHP_EOL . "$ok correctas, $fallas fallas" . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
