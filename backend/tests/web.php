<?php
declare(strict_types=1);

/**
 * Pruebas web: piden las paginas como lo haria el navegador (login, cookies, token CSRF)
 * y revisan los codigos HTTP de cada rol, la validacion y las defensas de seguridad.
 *   php tests/web.php
 *
 * Levanta solo un servidor `php -S` en el puerto 8099 y usa la base de Docker.
 * No cambia datos: los formularios que manda son rechazados (403 o 422) a proposito. Lo unico que toca
 * es debe_cambiar_clave de los 9 empleados de ejemplo, despues de probar que la clave de prueba obliga a cambiarla.
 * Se puede correr antes o despues de tests/integracion.php.
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la terminal.');
}
date_default_timezone_set('America/Lima');

const BASE = 'http://127.0.0.1:8099';
const CLAVE = 'Mass2026!';

// Datos de la empresa para la cabecera del ticket (RUC de prueba con digito verificador valido)
putenv('EMPRESA_RAZON_SOCIAL=MINIMARKET DE PRUEBA S.A.C.');
putenv('EMPRESA_RUC=20600000005');
putenv('EMPRESA_DIRECCION_FISCAL=Calle de Prueba 123, Arequipa');
putenv('TICKET_ANCHO_MM=80');
putenv('FACTURACION_PROVEEDOR=ninguno');

$servidor = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:8099', '-t', __DIR__ . '/../../frontend/public'],
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

    /** POST como lo envia app.js (fetch con Accept: application/json): la respuesta es JSON */
    public function postJson(string $ruta, array $datos): int
    {
        $datos['csrf'] ??= $this->token();
        $this->aceptarJson = true;
        try {
            return $this->pedir('/?ruta=' . $ruta, http_build_query($datos));
        } finally {
            $this->aceptarJson = false;
        }
    }

    private bool $aceptarJson = false;

    /** POST multipart con un archivo (como el formulario de importar catalogo) */
    public function subir(string $ruta, array $datos, string $campo, string $archivo, string $nombre): int
    {
        $datos['csrf'] = $this->token();
        $datos[$campo] = new CURLFile($archivo, 'text/csv', $nombre);
        return $this->pedir('/?ruta=' . $ruta, $datos);
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
            // Con sesion, el login redirige: el token se lee del panel (o de Mi clave, si aun debe cambiarla)
            $this->get('ruta=inicio');
            if ($this->codigo === 303) {
                $this->get('ruta=mi_clave');
            }
            preg_match('/name="csrf" value="([0-9a-f]+)"/', $this->cuerpo, $m);
        }
        return $m[1] ?? '';
    }

    public function tiene(string $texto): bool
    {
        return str_contains($this->cuerpo, $texto);
    }

    private function pedir(string $url, string|array|null $post): int
    {
        $c = curl_init(BASE . $url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_COOKIEFILE     => $this->cookies,
            CURLOPT_COOKIEJAR      => $this->cookies,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $this->aceptarJson ? ['Accept: application/json'] : [],
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
revisar('En la misma PC (localhost) el login no avisa de conexión sin cifrar', !$anonimo->tiene('Conexión sin cifrar'));
$desdeOtraPc = curl_init(BASE . '/?ruta=login');
curl_setopt_array($desdeOtraPc, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Host: 192.168.1.20:8000'], CURLOPT_TIMEOUT => 20]);
revisar('Desde otra PC por http:// el login avisa que la clave viaja sin cifrar', str_contains((string) curl_exec($desdeOtraPc), 'Conexión sin cifrar'));
curl_close($desdeOtraPc);
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

echo "Clave de prueba: primero se cambia\n";
// Con debe_cambiar_clave = 'S' (clave reiniciada en Personal) solo se puede ir a Mi clave o salir.
// En la base de ejemplo la migracion 025 lo deja en 'N': aqui se marca a proposito para probarlo y despues se quita.
$marcarClaveDePrueba = static function (string $debe): void {
    require_once __DIR__ . '/../config/Database.php';
    Database::conexion()->prepare("UPDATE empleado SET debe_cambiar_clave = :debe WHERE dni BETWEEN '70000001' AND '70000009'")
        ->execute(['debe' => $debe]);
};
$marcarClaveDePrueba('S');
$nuevo = new Navegador();
revisar('Con la clave de prueba, el login lleva a Mi clave', $nuevo->entrar('70000009') === 303
    && str_contains($nuevo->cabeceras['location'] ?? '', 'ruta=mi_clave'));
revisar('Mi clave avisa que es obligatorio cambiarla', $nuevo->get('ruta=mi_clave') === 200 && $nuevo->tiene('para seguir usando el sistema'));
revisar('No acepta la clave de prueba como clave nueva (422)', $nuevo->post('mi_clave_guardar', ['actual' => CLAVE, 'nueva' => 'MASS2026!', 'repetida' => 'MASS2026!']) === 422
    && $nuevo->tiene('No puedes usar la clave de prueba'));
revisar('Otra pantalla vuelve a Mi clave (303)', $nuevo->get('ruta=productos') === 303
    && str_contains($nuevo->cabeceras['location'] ?? '', 'ruta=mi_clave'));
revisar('Un formulario de otra ruta tampoco pasa (303 a Mi clave)', $nuevo->post('stock_minimo', ['id_producto' => '1', 'minimo' => '5']) === 303
    && str_contains($nuevo->cabeceras['location'] ?? '', 'ruta=mi_clave'));
revisar('Salir sí se puede', $nuevo->post('salir', []) === 303 && $nuevo->get('ruta=inicio') === 303
    && str_contains($nuevo->cabeceras['location'] ?? '', 'ruta=login'));
// El resto de las pruebas usa a los empleados de ejemplo como si ya hubieran cambiado la clave
$marcarClaveDePrueba('N');

echo "Errores y entrada rara\n";
$rosa = new Navegador();
revisar('Rosa entra y va al panel de inicio', $rosa->entrar('70000001') === 303 && str_contains($rosa->cabeceras['location'] ?? '', 'ruta=inicio'));
revisar('Panel de Rosa (200) con indicadores', $rosa->get('ruta=inicio') === 200 && $rosa->tiene('Vendido hoy') && $rosa->tiene('Ventas de los últimos 14 días'));
revisar('La página le dice a app.js tras cuántos minutos sin uso se cierra la sesión', $rosa->tiene('data-inactividad="30"'));
revisar('Seguir conectado (sesion_activa): 204 sin contenido', $rosa->get('ruta=sesion_activa&t=1') === 204 && $rosa->cuerpo === '');
revisar('...y sin sesión lleva al login', $anonimo->get('ruta=sesion_activa') === 303
    && str_contains($anonimo->cabeceras['location'] ?? '', 'ruta=login'));
revisar('El login no lleva el contador de inactividad', $anonimo->get('ruta=login') === 200 && !$anonimo->tiene('data-inactividad'));
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
    'stock_csv', 'kardex_csv&tienda=1', 'productos&vista=tabla', 'productos&categoria=2', 'productos&categoria=999&vista=x'];
$matriz = [
    // ruta                => [Rosa, Luis, Carmen]
    'caja'                 => [200, 200, 403],
    'venta_nueva'          => [403, 200, 403],
    'clientes'             => [200, 200, 403],
    'cliente&id=1'         => [200, 200, 403],
    'ajustes'              => [200, 200, 200],
    'reportes'             => [200, 403, 403],
    'reporte_csv'          => [200, 403, 403],
    'reportes&alcance=cadena' => [200, 403, 403],
    'reporte_csv&tipo=horas&alcance=cadena' => [200, 403, 403],
    'reporte_excel'        => [200, 403, 403],
    'reporte_pdf&alcance=cadena' => [200, 403, 403],
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
    'vencimientos'         => [200, 403, 200],
    'vencimientos&dias=7'  => [200, 403, 200],
    'reponer'              => [200, 403, 200],
    'productos_app'        => [301, 301, 301],
    'productos_nuevos'     => [200, 200, 200],
    'productos_nuevos&tienda=1&desde=2026-01-01&hasta=2026-02-30&q=%25' => [200, 200, 200],
    'productos_nuevos&tienda=2&periodo=todo&origen=APP&estado=pendientes' => [200, 403, 403],
    'productos_nuevos&tienda=x&pagina=-1&periodo=%3Cx%3E&origen=X&estado=x' => [200, 403, 403],
    'productos_nuevos&q[]=x&origen[]=APP&periodo=hoy' => [200, 200, 200],
    'reponer_csv'          => [200, 403, 200],
    'utilidad'             => [200, 403, 403],
    'estadisticas'         => [200, 403, 403],
    'estadisticas&alcance=cadena&desde=2026-01-01' => [200, 403, 403],
    'estadisticas&desde=%3Cx%3E&hasta=2026-02-30&alcance=x' => [200, 403, 403],
    'reporte_csv&tipo=utilidad' => [200, 403, 403],
    'catalogo_importar'    => [200, 403, 403],
    'catalogo_plantilla'   => [200, 403, 403],
    'proveedores'          => [200, 403, 200],
    'ordenes'              => [200, 403, 200],
    'ordenes&estado=ABIERTAS' => [200, 403, 200],
    'ordenes&estado=%3Cx%3E' => [200, 403, 200],
    'ordenes&nueva=1&proveedor=2' => [200, 403, 200],
    'ordenes&desde=reponer' => [200, 403, 200],
    'orden_imprimir&id=999' => [404, 403, 404],
    'orden_imprimir&id=abc' => [404, 403, 404],
    'ingresos&orden=999'   => [200, 403, 200],
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
revisar('El ticket lleva razón social, RUC y dirección fiscal de .env, no la dirección de ejemplo', $luis->tiene('MINIMARKET DE PRUEBA S.A.C.')
    && $luis->tiene('RUC 20600000005') && $luis->tiene('Calle de Prueba 123, Arequipa') && !$luis->tiene('Direccion de ejemplo')
    && !$luis->tiene('Faltan la razón social'));
revisar('El ticket separa op. gravada, IGV 18% y total', $luis->tiene('OP. GRAVADA') && $luis->tiene('IGV 18%') && $luis->tiene('>TOTAL<'));
revisar('Sin proveedor OSE/PSE el ticket dice que es interno y no válido ante SUNAT', $luis->tiene('TICKET DE VENTA INTERNO')
    && $luis->tiene('NO VÁLIDO COMO COMPROBANTE DE PAGO') && !$luis->tiene('BOLETA DE VENTA'));
revisar('Ticket de 58 mm para la ticketera chica (imprime 48 mm)', $luis->get('ruta=comprobante_imprimir&serie=B001&numero=1&ancho=58') === 200
    && $luis->tiene('size: 58mm') && $luis->tiene('width: 48mm') && $luis->tiene('data-ancho="58"'));
revisar('Un ancho que no existe usa el de .env (80 mm)', $luis->get('ruta=comprobante_imprimir&serie=B001&numero=1&ancho=999') === 200 && $luis->tiene('size: 80mm'));
revisar('El comprobante tiene el botón de imprimir en ticketera y muestra el IGV', $luis->get('ruta=comprobante&serie=B001&numero=1') === 200
    && $luis->tiene('ruta=comprobante_imprimir&amp;serie=B001&amp;numero=1&amp;auto=1') && $luis->tiene('Op. gravada') && $luis->tiene('Ticket interno'));
revisar('El ticket de una venta anulada lo dice', $luis->get('ruta=comprobante_imprimir&serie=B001&numero=2') === 200 && $luis->tiene('>ANULADA<'));
revisar('Ticket de un comprobante que no existe: 404', $luis->get('ruta=comprobante_imprimir&serie=B001&numero=99999') === 404);
revisar('El stock y el kardex se descargan en CSV', $carmen->get('ruta=stock_csv') === 200 && str_starts_with($carmen->cabeceras['content-type'] ?? '', 'text/csv')
    && $carmen->get('ruta=kardex_csv&tienda=1&producto=1') === 200 && str_contains($carmen->cuerpo, 'stock_nuevo'));
revisar('Etiquetas QR: la hoja de todo el catálogo trae un QR con el código, nombre y precio de cada producto (sin los al peso)',
    $luis->get('ruta=etiquetas') === 200 && $luis->tiene('<svg') && $luis->tiene('7750000000011') && $luis->tiene('S/ 4.50')
    && $luis->tiene('Sin QR (se venden al peso') && $luis->tiene('size: A4'));
revisar('Etiquetas QR de una categoría o de un producto con copias; un producto que no existe: 404',
    $rosa->get('ruta=etiquetas&categoria=2') === 200 && $rosa->tiene('7750000000011') && !$rosa->tiene('7750000000028')
    && $rosa->get('ruta=etiquetas&id=1&copias=3') === 200 && substr_count($rosa->cuerpo, '<svg') === 3
    && $rosa->get('ruta=etiquetas&id=99999') === 404);
revisar('La ficha muestra el QR del producto y el enlace para imprimir su etiqueta', $rosa->get('ruta=producto&id=1') === 200
    && $rosa->tiene('ficha-qr') && $rosa->tiene('ruta=etiquetas&amp;id=1'));
revisar('Nuevo producto: un código que ya existe avisa de cuál es; solo el administrador consulta',
    $rosa->get('ruta=producto_sugerencia&codigo=7750000000011') === 200 && str_contains($rosa->cuerpo, '"existe"')
    && $rosa->get('ruta=producto_sugerencia&codigo=abc') === 200 && str_contains($rosa->cuerpo, '"encontrado":false')
    && $luis->get('ruta=producto_sugerencia&codigo=7750000000011') === 403);
revisar('El almacenero y el administrador ven el formulario de ingreso', $carmen->get('ruta=ingresos') === 200
    && $carmen->tiene('ingreso_registrar') && $rosa->get('ruta=ingresos') === 200 && $rosa->tiene('ingreso_registrar'));
revisar('Ingresos está en el menú del almacenero y del administrador, no del cajero', $carmen->get('ruta=inicio') === 200
    && $carmen->tiene('ruta=ingresos"') && $rosa->get('ruta=inicio') === 200 && $rosa->tiene('ruta=ingresos"')
    && $luis->get('ruta=inicio') === 200 && !$luis->tiene('ruta=ingresos"'));
revisar('Los ingresos se descargan en CSV con sus costos', $rosa->get('ruta=ingresos_csv') === 200
    && str_starts_with($rosa->cabeceras['content-type'] ?? '', 'text/csv') && str_contains($rosa->cuerpo, 'costo_unitario'));
revisar('El ingreso pide la fecha de vencimiento de cada lote', $carmen->get('ruta=ingresos') === 200 && $carmen->tiene('name="vence[]"'));
revisar('Por vencer muestra la leche vencida; solo el almacenero ve el botón de baja', $carmen->get('ruta=vencimientos') === 200
    && $carmen->tiene('Vencido hace 1 día') && $carmen->tiene('Dar de baja lo vencido')
    && $rosa->get('ruta=vencimientos') === 200 && $rosa->tiene('Vencido hace 1 día') && !$rosa->tiene('Dar de baja lo vencido'));
revisar('El inicio avisa lo que vence esta semana, no al cajero', $carmen->get('ruta=inicio') === 200 && $carmen->tiene('Vencen esta semana')
    && $carmen->tiene('ruta=vencimientos"') && $luis->get('ruta=inicio') === 200 && !$luis->tiene('Vencen esta semana'));
revisar('La caja del cajero oculta lo esperado (cierre ciego) y ofrece el retiro', $luis->get('ruta=caja') === 200 && $luis->tiene('cierre ciego')
    && !$luis->tiene('En gaveta ahora') && $luis->tiene('data-conteo')
    && $luis->tiene('action="/?ruta=caja_movimiento"') && $rosa->get('ruta=caja') === 200 && !$rosa->tiene('caja_movimiento'));
revisar('El turno del cajero: barra de estado, movimientos y el cierre solo dentro del arqueo ciego', $luis->get('ruta=caja') === 200
    && $luis->tiene('class="turno-barra') && $luis->tiene('Movimientos de efectivo') && $luis->tiene('href="#arqueo-cierre"')
    && substr_count($luis->cuerpo, 'action="/?ruta=turno_cerrar"') === 1
    && strpos($luis->cuerpo, 'id="arqueo-cierre"') < strpos($luis->cuerpo, 'action="/?ruta=turno_cerrar"')
    && $luis->tiene('data-confirmar="Vas a cerrar el turno') && !$luis->tiene('Ver estimado'));
revisar('Cierre ciego: primero se cuenta, luego tarjeta y Sip, y no hay cuadre en la página que deje adivinar lo esperado', $luis->get('ruta=caja') === 200
    && $luis->tiene('data-cierre') && $luis->tiene('data-modo="ciego"') && $luis->tiene('Confirma y cierra')
    && strpos($luis->cuerpo, 'data-paso-cierre="conteo"') < strpos($luis->cuerpo, 'data-paso-cierre="vouchers"')
    && strpos($luis->cuerpo, 'data-paso-cierre="vouchers"') < strpos($luis->cuerpo, 'data-paso-cierre="confirmar"')
    && !$luis->tiene('data-paso-cierre="cuadre"') && !$luis->tiene('class="cuadre"') && !$luis->tiene('Ventas en efectivo'));
revisar('El total contado no se exige si hay billetes: el servidor los suma (sin JavaScript también se cierra)', $luis->get('ruta=caja') === 200
    && preg_match('/<input id="cierre-total"[^>]*>/', $luis->cuerpo, $totalCierre) === 1 && !str_contains($totalCierre[0], 'required')
    && $luis->tiene('data-conteo-modo'));
revisar('El comprobante de cierre lo arma el sistema: ventas por pago, esperado, contado y diferencia', $rosa->get('ruta=turno_cierre&turno=1') === 200
    && $rosa->tiene('CIERRE DE CAJA') && $rosa->tiene('= Efectivo esperado') && $rosa->tiene('Efectivo contado')
    && $rosa->tiene('class="cierre-resultado') && $rosa->tiene('data-imprimir') && !$rosa->tiene('?>')
    && $rosa->tiene('class="cuadre"') && $rosa->tiene('Efectivo esperado en la gaveta') && $rosa->tiene('contado − esperado'));
revisar('Una venta en efectivo de un turno ya cerrado no ofrece anularla (descuadraría el arqueo)', $rosa->get('ruta=comprobante&serie=B001&numero=1') === 200
    && !$rosa->tiene('action="/?ruta=venta_anular"') && $rosa->tiene('anularla descuadraría ese arqueo'));
revisar('Un turno abierto o inventado no tiene comprobante de cierre: 404', $rosa->get('ruta=turno_cierre&turno=5') === 404
    && $rosa->get('ruta=turno_cierre&turno=abc') === 404 && $rosa->get('ruta=turno_cierre') === 404);
revisar('El almacenero no ve cierres de caja: 403', $carmen->get('ruta=turno_cierre&turno=1') === 403);
revisar('El historial de turnos enlaza el comprobante de cierre', $rosa->get('ruta=caja') === 200 && $rosa->tiene('ruta=turno_cierre&amp;turno=1"'));
revisar('Una venta de hace más de 7 días ya no ofrece devolución', $luis->get('ruta=comprobante&serie=B001&numero=1') === 200
    && $luis->tiene('ya no acepta devoluciones') && !$luis->tiene('venta_devolver'));
revisar('La utilidad muestra la comparación por tienda y el margen', $rosa->get('ruta=utilidad&desde=2026-01-01') === 200
    && $rosa->tiene('Por tienda') && $rosa->tiene('Mass Cerro Colorado') && $rosa->tiene('Margen'));
revisar('Reponer sugiere cuánto pedir y sale en el menú del almacenero', $carmen->get('ruta=reponer') === 200
    && ($carmen->tiene('Pedir') || $carmen->tiene('Nada por reponer')) && $carmen->get('ruta=inicio') === 200 && $carmen->tiene('ruta=reponer"'));
revisar('Reportes: comparado con el mes anterior, gráficos de productos, hora y cajero, y botones Excel y PDF',
    $rosa->get('ruta=reportes&desde=2026-01-01') === 200 && $rosa->tiene('Comparado con el mes anterior') && $rosa->tiene('Ventas por hora')
    && $rosa->tiene('aria-label="Importe de los productos más vendidos"') && $rosa->tiene('aria-label="Total vendido por cajero"')
    && $rosa->tiene('ruta=reporte_excel') && $rosa->tiene('ruta=reporte_pdf') && $rosa->tiene('Todas las tiendas') && !$rosa->tiene('<h2>Por tienda</h2>'));
revisar('El consolidado suma las 3 tiendas y las compara; sus descargas siguen en la cadena',
    $rosa->get('ruta=reportes&alcance=cadena&desde=2026-01-01') === 200 && $rosa->tiene('Reportes de toda la cadena')
    && $rosa->tiene('<h2>Por tienda</h2>') && $rosa->tiene('Mass Cerro Colorado') && $rosa->tiene('3 tiendas')
    && $rosa->tiene('ruta=reporte_excel&amp;desde=2026-01-01&amp;hasta=' . date('Y-m-d') . '&amp;alcance=cadena'));
revisar('Un alcance inventado se queda en su tienda', $rosa->get('ruta=reportes&alcance=%3Cx%3E') === 200 && !$rosa->tiene('<h2>Por tienda</h2>'));
revisar('El Excel se descarga como .xlsx con una hoja por tabla',
    $rosa->get('ruta=reporte_excel&alcance=cadena&desde=2026-01-01') === 200
    && str_starts_with($rosa->cabeceras['content-type'] ?? '', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    && str_contains($rosa->cabeceras['content-disposition'] ?? '', 'reporte_ventas_cadena_2026-01-01_') && str_starts_with($rosa->cuerpo, 'PK')
    && str_contains($rosa->cuerpo, 'name="Por tienda"') && str_contains($rosa->cuerpo, 'name="Ventas por hora"') && str_contains($rosa->cuerpo, 'Mass Cerro Colorado'));
revisar('El PDF se descarga como application/pdf con el consolidado',
    $rosa->get('ruta=reporte_pdf&alcance=cadena&desde=2026-01-01') === 200 && ($rosa->cabeceras['content-type'] ?? '') === 'application/pdf'
    && str_starts_with($rosa->cuerpo, '%PDF-') && str_contains($rosa->cuerpo, '(Por tienda)') && str_contains($rosa->cuerpo, '(Mass Cerro Colorado)'));
revisar('El CSV por hora sale con su encabezado', $rosa->get('ruta=reporte_csv&tipo=horas&desde=2026-01-01') === 200 && str_contains($rosa->cuerpo, 'hora,comprobantes,total'));
revisar('Estadísticas: opiniones, mapa de calor día x hora y producto x día, lo que más y menos se vende y lo que no se vendió',
    $rosa->get('ruta=estadisticas&desde=2026-01-01') === 200 && $rosa->tiene('Qué nos dicen los números') && $rosa->tiene('Hora pico')
    && $rosa->tiene('Mapa de calor: día y hora') && $rosa->tiene('productos estrella por día') && $rosa->tiene('class="calor-casilla')
    && $rosa->tiene('Lo que más se vende') && $rosa->tiene('No se vendió nada') && $rosa->tiene('ruta=estadisticas"'));
revisar('Estadísticas de toda la cadena', $rosa->get('ruta=estadisticas&alcance=cadena&desde=2026-01-01') === 200
    && $rosa->tiene('Estadísticas de toda la cadena') && $rosa->tiene('3 tiendas'));
revisar('El CSV de utilidad trae el margen', $rosa->get('ruta=reporte_csv&tipo=utilidad&desde=2026-01-01') === 200 && str_contains($rosa->cuerpo, 'margen_%'));
revisar('El catálogo ofrece Importar CSV solo al administrador', $rosa->get('ruta=productos') === 200 && $rosa->tiene('ruta=catalogo_importar"')
    && $carmen->get('ruta=productos') === 200 && !$carmen->tiene('catalogo_importar'));
revisar('La plantilla es el catálogo actual en CSV con las columnas para importar', $rosa->get('ruta=catalogo_plantilla') === 200
    && str_starts_with($rosa->cabeceras['content-type'] ?? '', 'text/csv')
    && str_contains($rosa->cuerpo, 'codigo_barras,nombre,categoria,marca,unidad,precio_venta,al_peso') && str_contains($rosa->cuerpo, 'Leche'));
$plantilla = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($plantilla, $rosa->cuerpo);
revisar('Subir la plantilla tal cual: se revisa sin errores ni cambios', $rosa->subir('catalogo_subir', ['modo' => 'revisar'], 'archivo', $plantilla, 'catalogo.csv') === 200
    && $rosa->tiene('Revisión del archivo') && $rosa->tiene('Productos nuevos</span><strong>0<'));
file_put_contents($plantilla, "codigo_barras;nombre\n1;x\n");
revisar('Un CSV con otras columnas: 422 y dice cuáles van', $rosa->subir('catalogo_subir', ['modo' => 'importar'], 'archivo', $plantilla, 'malo.csv') === 422
    && $rosa->tiene('La primera fila debe ser'));
revisar('Un archivo que no es .csv: 422', $rosa->subir('catalogo_subir', [], 'archivo', $plantilla, 'foto.png') === 422);
@unlink($plantilla);
revisar('El catálogo sale en tarjetas con chips de categoría y guía', $rosa->get('ruta=productos') === 200 && $rosa->tiene('id="tarjetas-productos"')
    && $rosa->tiene('data-chips="tarjetas-productos"') && $rosa->tiene('data-guia="catalogo"') && $rosa->tiene('src="/img/catalogo/1.jpg?v='));
revisar('Filtrar por categoría deja solo esa (Lácteos: 12)', $rosa->get('ruta=productos&categoria=2') === 200
    && substr_count($rosa->cuerpo, '<li id="fila-') === 12 && substr_count($rosa->cuerpo, '" data-categoria="2">') === 12);
revisar('La vista en tabla también tiene foto chica', $rosa->get('ruta=productos&vista=tabla') === 200 && $rosa->tiene('id="filas-productos"')
    && $rosa->tiene('class="foto chica'));
revisar('La venta tiene la cuadrícula, el campo del lector y el código en cada opción', $luis->get('ruta=venta_nueva') === 200
    && $luis->tiene('data-escanear') && $luis->tiene('data-agregar="1"') && $luis->tiene('data-codigo="7750000000011"'));
revisar('La venta muestra la foto real de cada producto y el carrito lateral', $luis->get('ruta=venta_nueva') === 200
    && $luis->tiene('src="/img/catalogo/1.jpg?v=') && $luis->tiene('data-carrito') && $luis->tiene('data-plantilla-carrito')
    && $luis->tiene('iconos.svg#'));
$fotoPrueba = tempnam(sys_get_temp_dir(), 'foto');
$lienzo = imagecreatetruecolor(300, 200);
imagepng($lienzo, $fotoPrueba);
revisar('Rosa sube la foto de la leche y la ficha la muestra', $rosa->subir('producto_foto', ['id' => '1'], 'foto', $fotoPrueba, 'leche.png') === 303
    && $rosa->get('ruta=productos&ver=1') === 200 && $rosa->tiene('src="/img/productos/1.jpg?v=') && $rosa->tiene('Quitar foto'));
revisar('La foto se sirve como imagen', $rosa->get('') !== 0 && (function () use ($rosa): bool {
    $c = curl_init(BASE . '/img/productos/1.jpg');
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false]);
    $cuerpo = (string) curl_exec($c);
    $tipo = (string) curl_getinfo($c, CURLINFO_CONTENT_TYPE);
    curl_close($c);
    return str_starts_with($tipo, 'image/jpeg') && str_starts_with($cuerpo, "\xFF\xD8");
})());
file_put_contents($fotoPrueba, '<?php echo "hola"; ?>');
revisar('Un archivo que no es imagen no se acepta como foto', $rosa->subir('producto_foto', ['id' => '2'], 'foto', $fotoPrueba, 'foto.png') === 303
    && $rosa->get('ruta=productos&ver=2') === 200 && $rosa->tiene('Sube una foto JPG, PNG o WebP') && !$rosa->tiene('src="/img/productos/2.jpg'));
revisar('El cajero y el almacenero no cambian fotos', $luis->subir('producto_foto', ['id' => '1'], 'foto', $fotoPrueba, 'f.png') === 403
    && $carmen->post('producto_foto', ['id' => '1', 'quitar' => '1']) === 403 && $carmen->get('ruta=productos&ver=1') === 200 && !$carmen->tiene('producto_foto'));
revisar('Rosa quita la foto y vuelve el icono de la categoría', $rosa->post('producto_foto', ['id' => '1', 'quitar' => '1']) === 303
    && $rosa->get('ruta=productos&ver=1') === 200 && !$rosa->tiene('/img/productos/1.jpg') && $rosa->tiene('Subir una foto'));
@unlink($fotoPrueba);
revisar('Subir sin archivo: 422', $rosa->post('catalogo_subir', ['modo' => 'revisar']) === 422 && $rosa->tiene('Elige el archivo CSV'));
revisar('El cajero y el almacenero no suben el catálogo', $luis->post('catalogo_subir', []) === 403 && $carmen->post('catalogo_subir', []) === 403);
revisar('Inventario va en tres opciones para el almacenero: Productos, Movimientos y Compras', $carmen->get('ruta=inicio') === 200
    && preg_match('#<div class="grupo">Inventario</div>(.*?)(?:<div class="grupo">|</nav>)#s', $carmen->cuerpo, $m) === 1 && substr_count($m[1], '<a ') === 3
    && str_contains($m[1], '>Productos<') && str_contains($m[1], '>Movimientos<') && str_contains($m[1], '>Compras<'));
revisar('El cajero no ve Compras: solo Productos y Movimientos', $luis->get('ruta=inicio') === 200
    && preg_match('#<div class="grupo">Inventario</div>(.*?)(?:<div class="grupo">|</nav>)#s', $luis->cuerpo, $m) === 1 && substr_count($m[1], '<a ') === 2
    && !str_contains($m[1], '>Compras<'));
revisar('Compras abre con las pestañas Órdenes de compra y Proveedores', $carmen->get('ruta=proveedores') === 200
    && $carmen->tiene('<a href="/?ruta=proveedores" aria-current="page">') && $carmen->tiene('<a href="/?ruta=ordenes">')
    && $carmen->tiene('<a href="/?ruta=ordenes" class="activo" aria-current="page" data-pestanas="Compras">'));
revisar('Los proveedores de la migración se ven; solo el administrador los edita', $carmen->get('ruta=proveedores') === 200
    && $carmen->tiene('Distribuidora Arequipa Sur S.A.C.') && !$carmen->tiene('proveedor_guardar')
    && $rosa->get('ruta=proveedores') === 200 && $rosa->tiene('action="/?ruta=proveedor_guardar"') && $rosa->tiene('action="/?ruta=proveedor_estado"'));
revisar('Reponer ofrece armar la orden y pedir al último proveedor', $carmen->get('ruta=reponer') === 200
    && $carmen->tiene('Armar orden de compra') && $carmen->tiene('ruta=ordenes&amp;desde=reponer&amp;proveedor=') && $carmen->tiene('Último proveedor'));
revisar('Desde Reponer la orden nueva abre llena con lo sugerido', $carmen->get('ruta=ordenes&desde=reponer') === 200
    && $carmen->tiene('class="drawer abierto ancho" id="nueva-orden"') && $carmen->tiene('action="/?ruta=orden_registrar"')
    && $carmen->tiene('producto(s) de Reponer'));
revisar('La orden nueva no abre sola sin pedirla', $carmen->get('ruta=ordenes') === 200 && $carmen->tiene('class="drawer ancho" id="nueva-orden"'));
revisar('Recibir una orden que no existe avisa y abre el ingreso normal', $carmen->get('ruta=ingresos&orden=999') === 200
    && $carmen->tiene('ya no espera mercadería') && !$carmen->tiene('name="orden"'));
revisar('El ingreso sugiere los RUC de los proveedores', $carmen->get('ruta=ingresos') === 200 && $carmen->tiene('<datalist id="rucs-proveedores">')
    && $carmen->tiene('<option value="20456789123">'));
revisar('Mermas sale como pestaña de Movimientos y queda marcada', $carmen->get('ruta=mermas') === 200
    && $carmen->tiene('<a href="/?ruta=mermas" aria-current="page">') && $carmen->tiene('<a href="/?ruta=ajustes">')
    && $carmen->tiene('<a href="/?ruta=ingresos" class="activo" aria-current="page" data-pestanas="Movimientos">'));
revisar('El cajero ve Movimientos sin Ingresos ni Conteo', $luis->get('ruta=kardex') === 200
    && $luis->tiene('<a href="/?ruta=traslados" class="activo" aria-current="page" data-pestanas="Movimientos">') && !$luis->tiene('ruta=ingresos"') && !$luis->tiene('ruta=ajustes"'));
revisar('La ficha de un producto marca Productos y la pestaña Catálogo', $rosa->get('ruta=producto&id=1') === 200
    && $rosa->tiene('<a href="/?ruta=productos" class="activo" aria-current="page" data-pestanas="Productos">') && $rosa->tiene('<a href="/?ruta=productos" aria-current="page">'));
revisar('Productos avisa con números lo que vence y lo que hay que reponer; el cajero no los ve', $carmen->get('ruta=stock') === 200
    && $carmen->tiene('class="menu-cuenta"') && $carmen->tiene('class="pestana-cuenta reponer"') && $carmen->tiene('class="pestanas-rotulo"')
    && $luis->get('ruta=stock') === 200 && !$luis->tiene('menu-cuenta') && !$luis->tiene('pestana-cuenta'));
revisar('"Recién agregados" es una pestaña de Productos para los tres roles',
    $carmen->get('ruta=productos_nuevos') === 200 && $carmen->tiene('<a href="/?ruta=productos_nuevos" aria-current="page">')
    && ($carmen->tiene('href="#nuevo-') || $carmen->tiene('Todavía no se agregaron productos nuevos'))
    && $rosa->get('ruta=stock') === 200 && $rosa->tiene('ruta=productos_nuevos"') && $luis->get('ruta=stock') === 200 && $luis->tiene('ruta=productos_nuevos"'));
revisar('La pestaña de antes ("Desde la app") lleva a Recién agregados con el origen App y sin copiar texto raro',
    $carmen->get('ruta=productos_app&desde=2026-01-01&q=%3Cx%3E&pagina=2') === 301
    && str_starts_with($carmen->cabeceras['location'] ?? '', '/?ruta=productos_nuevos&origen=APP&periodo=todo&desde=2026-01-01&q=%3Cx%3E'));
revisar('Solo el administrador tiene los botones para marcar como revisado',
    $rosa->get('ruta=productos_nuevos&periodo=todo') === 200 && $rosa->tiene('Marcar seleccionados') === $rosa->tiene('<span class="etiqueta alerta">Por revisar</span>')
    && $rosa->post('productos_revisar', ['ids' => ['x'], 'periodo' => '7']) === 303
    && str_starts_with($rosa->cabeceras['location'] ?? '', '/?ruta=productos_nuevos&periodo=7')
    && $luis->get('ruta=productos_nuevos&periodo=todo') === 200 && !$luis->tiene('ruta=productos_revisar') && !$luis->tiene('Completar datos'));
revisar('Recién agregados no repite en la página lo que se busca sin escaparlo', $rosa->get('ruta=productos_nuevos&q=%3Cscript%3E') === 200
    && !$rosa->tiene('<script>') && $rosa->tiene('&lt;script&gt;'));
revisar('La auditoría está en el menú del administrador', $rosa->get('ruta=inicio') === 200 && $rosa->tiene('ruta=auditoria"'));
revisar('Las pantallas cargan solo /js/app.js y el ticket solo /js/ticket.js', $rosa->get('ruta=inicio') === 200 && $rosa->tiene('<script src="/js/app.js')
    && substr_count($rosa->cuerpo, '<script') === 1 && $luis->get('ruta=comprobante_imprimir&serie=B001&numero=1') === 200
    && substr_count($luis->cuerpo, '<script') === 1 && $luis->tiene('<script src="/js/ticket.js" defer></script>'));
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

echo "Promociones y medios de pago\n";
revisar('Nueva venta ofrece los 4 medios de pago: efectivo, débito, crédito y Sip', $luis->get('ruta=venta_nueva') === 200
    && $luis->tiene('name="medio_pago" value="EFECTIVO"') && $luis->tiene('name="medio_pago" value="DEBITO"')
    && $luis->tiene('name="medio_pago" value="CREDITO"') && $luis->tiene('name="medio_pago" value="SIP"')
    && $luis->tiene('data-promociones=') && $luis->tiene('name="descuento_motivo"'));
revisar('El administrador ve y crea promociones', $rosa->get('ruta=promociones') === 200 && $rosa->tiene('id="nueva-promocion"')
    && $rosa->tiene('action="/?ruta=promocion_crear"'));
revisar('El cajero las ve, sin el formulario', $luis->get('ruta=promociones') === 200 && !$luis->tiene('id="nueva-promocion"'));
revisar('El almacenero no: 403', $carmen->get('ruta=promociones') === 403);
revisar('Promoción sin nombre: 422 con el formulario abierto', $rosa->post('promocion_crear', ['tipo' => 'VENTA', 'porcentaje' => '5']) === 422
    && $rosa->tiene('nueva-promocion'));

echo "Formularios de otro rol (POST): 403 sin tocar la base\n";
$prohibidos = [
    [$luis, 'Luis', 'producto_guardar', ['nombre' => 'X', 'precio' => '1']],
    [$luis, 'Luis', 'producto_estado', ['id' => '1', 'estado' => 'DESCONTINUADO']],
    [$luis, 'Luis', 'productos_revisar', ['ids' => ['1']]],
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
    [$carmen, 'Carmen', 'productos_revisar', ['ids' => ['1']]],
    [$carmen, 'Carmen', 'turno_abrir', ['caja' => '1', 'monto_inicial' => '100']],
    [$carmen, 'Carmen', 'turno_cerrar', ['monto_cierre' => '100']],
    [$carmen, 'Carmen', 'turno_pausa', ['accion' => 'pausar']],
    [$carmen, 'Carmen', 'turno_recontar', ['monto_cierre' => '100']],
    [$luis, 'Luis', 'turno_revisar', ['turno' => '1', 'decision' => 'APROBAR', 'comentario' => 'x']],
    [$luis, 'Luis', 'arqueo_sorpresa', ['turno' => '5', 'monto' => '100']],
    [$luis, 'Luis', 'regla_caja_guardar', ['tolerancia' => '1', 'tope' => '500', 'horas' => '10']],
    [$carmen, 'Carmen', 'regla_caja_guardar', ['tolerancia' => '1', 'tope' => '500', 'horas' => '10']],
    [$carmen, 'Carmen', 'cliente_documento', ['id' => '1', 'dni' => '12345678']],
    [$carmen, 'Carmen', 'cliente_crear', ['nombre' => 'X', 'dni' => '12345670']],
    [$carmen, 'Carmen', 'cliente_contacto', ['id' => '1', 'nombre' => 'X']],
    [$carmen, 'Carmen', 'caja_movimiento', ['tipo' => 'RETIRO', 'monto' => '1', 'motivo' => 'x']],
    [$carmen, 'Carmen', 'venta_devolver', ['serie' => 'B001', 'numero' => '1', 'producto' => ['1'], 'cantidad' => ['1'], 'motivo' => 'x']],
    [$rosa, 'Rosa', 'venta_devolver', ['serie' => 'B001', 'numero' => '1', 'producto' => ['1'], 'cantidad' => ['1'], 'motivo' => 'x']],
    [$rosa, 'Rosa', 'caja_movimiento', ['tipo' => 'RETIRO', 'monto' => '1', 'motivo' => 'x']],
    [$rosa, 'Rosa', 'venta_registrar', ['tipo' => 'B']],
    [$rosa, 'Rosa', 'traslado_registrar', ['destino' => '2']],
    [$luis, 'Luis', 'ingreso_registrar', ['tipo_documento' => 'FACTURA']],
    [$luis, 'Luis', 'promocion_crear', ['nombre' => 'X', 'tipo' => 'VENTA', 'porcentaje' => '50']],
    [$luis, 'Luis', 'promocion_estado', ['id_promocion' => '1', 'estado' => 'PAUSADA']],
    [$carmen, 'Carmen', 'promocion_crear', ['nombre' => 'X', 'tipo' => 'VENTA', 'porcentaje' => '50']],
    [$luis, 'Luis', 'orden_registrar', ['proveedor' => '1']],
    [$luis, 'Luis', 'proveedor_guardar', ['ruc' => '20512345678', 'razon_social' => 'X']],
    [$carmen, 'Carmen', 'proveedor_guardar', ['ruc' => '20512345678', 'razon_social' => 'X']],
    [$carmen, 'Carmen', 'proveedor_estado', ['id' => '1', 'estado' => 'INACTIVO']],
    [$carmen, 'Carmen', 'orden_cancelar', ['id' => '1', 'motivo' => 'x']],
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
revisar('Proveedor con RUC corto: 422 y vuelve lo escrito', $rosa->post('proveedor_guardar', ['ruc' => '123', 'razon_social' => '<b>Prov</b>']) === 422
    && $rosa->tiene('11 dígitos') && $rosa->tiene('&lt;b&gt;Prov') && $rosa->tiene('class="drawer abierto" id="nuevo-proveedor"'));
revisar('Corregir un proveedor que no existe: 422', $rosa->post('proveedor_guardar', ['id' => '999', 'razon_social' => 'X']) === 422
    && $rosa->tiene('no existe'));
revisar('Orden sin proveedor: 422 y el formulario vuelve abierto', $carmen->post('orden_registrar', ['proveedor' => '',
    'producto' => ['1'], 'cantidad' => ['10'], 'costo' => ['3.50']]) === 422 && $carmen->tiene('Elige el proveedor')
    && $carmen->tiene('class="drawer abierto ancho" id="nueva-orden"'));
revisar('Cancelar una orden que no existe: 422', $rosa->post('orden_cancelar', ['id' => '999', 'motivo' => 'x']) === 422
    && $rosa->tiene('no es de tu tienda'));
revisar('Recibir contra una orden que no existe: 422', $carmen->post('ingreso_registrar', ['tipo_documento' => 'GUIA', 'numero_documento' => 'T001-1',
    'fecha_documento' => date('Y-m-d'), 'orden' => '999', 'producto' => ['1'], 'cantidad' => ['1'], 'costo' => ['3.60']]) === 422
    && $carmen->tiene('no es de tu tienda'));
revisar('Conteo con texto: 422', $carmen->post('ajuste_registrar', ['motivo' => 'CONTEO_FISICO', 'producto' => ['1'], 'cantidad' => ['muchos']]) === 422);
revisar('Empleado con DNI de 7 dígitos: 422', $rosa->post('empleado_crear',
    ['nombres' => 'Ana', 'apellidos' => 'Ruiz', 'dni' => '1234567', 'rol' => '2', 'clave' => 'Clave2026x']) === 422);
revisar('Empleado con clave débil: 422', $rosa->post('empleado_crear',
    ['nombres' => 'Ana', 'apellidos' => 'Ruiz', 'dni' => '12345678', 'rol' => '2', 'clave' => '123']) === 422);
revisar('Cliente sin DNI ni RUC: 422 y el formulario vuelve abierto', $luis->post('cliente_crear', ['nombre' => 'Pepe', 'dni' => '', 'ruc' => '']) === 422
    && $luis->tiene('class="drawer abierto" id="nuevo-cliente"') && $luis->tiene('value="Pepe"'));
revisar('Cliente con teléfono con letras: 422', $rosa->post('cliente_crear', ['nombre' => 'Pepe', 'dni' => '12345670', 'telefono' => 'abc']) === 422);
revisar('Editar empleado con DNI de 7 dígitos: 422', $rosa->post('empleado_guardar',
    ['id' => '3', 'nombres' => 'Carmen', 'apellidos' => 'Ccori Flores', 'dni' => '7000003', 'rol' => '3', 'estado' => 'ACTIVO']) === 422);
revisar('Tienda con teléfono con letras: 422', $rosa->post('tienda_guardar',
    ['nombre' => 'Mass Cayma', 'direccion' => 'Av. 1', 'telefono' => 'abc', 'distrito' => '1', 'estado' => 'ACTIVA']) === 422);
revisar('Mi clave con la actual errada: 422', $rosa->post('mi_clave_guardar', ['actual' => 'otra', 'nueva' => 'Nueva2026x', 'repetida' => 'Nueva2026x']) === 422);
revisar('Retiro con monto inválido: 422', $luis->post('caja_movimiento', ['tipo' => 'RETIRO', 'monto' => 'abc', 'motivo' => 'x']) === 422
    && $luis->tiene('Monto no válido'));
revisar('Retiro mayor que la gaveta: 422', $luis->post('caja_movimiento', ['tipo' => 'RETIRO', 'monto' => '99999', 'motivo' => 'x']) === 422
    && $luis->tiene('en la gaveta'));
revisar('Devolución de una venta vieja: vuelve al comprobante con el error', $luis->post('venta_devolver',
    ['serie' => 'B001', 'numero' => '1', 'producto' => ['1'], 'cantidad' => ['1'], 'motivo' => 'x']) === 303
    && $luis->get('ruta=comprobante&serie=B001&numero=1') === 200 && $luis->tiene('ya no se aceptan devoluciones'));
revisar('Turno con monto inválido: 422', $luis->post('turno_abrir', ['caja' => '1', 'monto_inicial' => '-1']) === 422);
revisar('Conteo con un billete de S/ 3: 422 y el arqueo vuelve abierto con el error', $luis->post('turno_cerrar', ['monto_cierre' => '', 'conteo' => ['3.00' => '1']]) === 422
    && $luis->tiene('data-conteo') && $luis->tiene('class="drawer abierto ancho pantalla" id="arqueo-cierre"'));
revisar('Reconteo sin turno en reconteo: 422', $luis->post('turno_recontar', ['monto_cierre' => '100']) === 422);
revisar('Revisar un turno que no está observado: 422', $rosa->post('turno_revisar', ['turno' => '1', 'decision' => 'APROBAR', 'comentario' => 'Visto']) === 422);
revisar('Arqueo sorpresa con monto inválido: 422', $rosa->post('arqueo_sorpresa', ['turno' => '5', 'monto' => 'abc']) === 422);
revisar('Reglas con turnos de 30 horas: 422', $rosa->post('regla_caja_guardar', ['tolerancia' => '1.00', 'tope' => '500.00', 'horas' => '30']) === 422);
revisar('El administrador ve las cajas en vivo, el arqueo sorpresa y las reglas', $rosa->get('ruta=caja') === 200
    && $rosa->tiene('Cajas ahora') && $rosa->tiene('#sorpresa-5') && $rosa->tiene('id="reglas-caja"'));
// La caja 2 de Cayma esta activa y sin turno: se ve libre (no apagada como una inactiva) y la caja 1, de Luis, ocupada
revisar('La caja libre se ve libre, no inactiva, y se puede desactivar desde la caja', $rosa->get('ruta=caja') === 200
    && $rosa->tiene('<article class="panel caja-vivo libre" data-caja-estado="libre">')
    && $rosa->tiene('<span class="etiqueta info">Libre</span>') && !$rosa->tiene('data-caja-estado="inactiva"')
    && $rosa->tiene('<article class="panel caja-vivo" data-caja-estado="ocupada">')
    && $rosa->tiene('name="volver" value="caja"') && $rosa->tiene('Lista para que un cajero abra su turno.'));
revisar('Las cajas abiertas se cuentan sobre las activas', $rosa->tiene('1 <small>de 2</small>'));

echo "Sucursal del administrador\n";
// El administrador elige en el menu lateral la sucursal con la que trabaja; su ficha no cambia (solo la sesion)
$jefa = new Navegador();
$jefa->entrar('70000001');
revisar('El administrador ve el selector de sucursal con todas las tiendas, Cayma marcada como la suya',
    $jefa->get('ruta=inicio') === 200 && $jefa->tiene('action="/?ruta=tienda_elegir"')
    && $jefa->tiene('<option value="1" selected>Mass Cayma · la tuya</option>')
    && $jefa->tiene('>Mass Cerro Colorado</option>') && $jefa->tiene('>Mass Yanahuara</option>'));
revisar('El selector vuelve a la misma opción del menu (Kardex -> Movimientos)',
    $jefa->get('ruta=kardex') === 200 && $jefa->tiene('name="volver" value="ingresos"'));
revisar('Desde la ficha de un producto vuelve a la lista de productos, no a la ficha',
    $jefa->get('ruta=producto&id=1') === 200 && $jefa->tiene('name="volver" value="productos"'));
revisar('El cajero y el almacenero no tienen selector: ven solo su tienda',
    $luis->get('ruta=inicio') === 200 && !$luis->tiene('tienda_elegir') && $luis->tiene('<span class="marca-tienda">Mass Cayma</span>')
    && $carmen->get('ruta=inicio') === 200 && !$carmen->tiene('tienda_elegir'));
revisar('El cajero no puede cambiar de sucursal por POST: 403',
    $luis->post('tienda_elegir', ['tienda' => '2', 'volver' => 'inicio']) === 403);
revisar('Cambiar de sucursal sin token: 403', $jefa->post('tienda_elegir', ['tienda' => '2'], false) === 403
    && $jefa->get('ruta=inicio') === 200 && $jefa->tiene('<option value="1" selected>'));
revisar('Sucursal que no existe: 404', $jefa->post('tienda_elegir', ['tienda' => '999', 'volver' => 'inicio']) === 404);
revisar('Sucursal con texto: 404', $jefa->post('tienda_elegir', ['tienda' => 'abc', 'volver' => 'inicio']) === 404);
revisar('Elegir Cerro Colorado vuelve a la opción pedida con un aviso',
    $jefa->post('tienda_elegir', ['tienda' => '2', 'volver' => 'empleados']) === 303
    && str_ends_with($jefa->cabeceras['location'] ?? '', '/?ruta=empleados')
    && $jefa->get('ruta=empleados') === 200 && $jefa->tiene('Ahora trabajas en Mass Cerro Colorado.'));
revisar('El personal que ve ahora es el de Cerro Colorado (Jorge, Ana, Pedro), no el de Cayma',
    $jefa->tiene('Jorge') && $jefa->tiene('Pedro') && !$jefa->tiene('Cáceres'));
revisar('El menu marca Cerro Colorado y sigue diciendo cuál es la suya',
    $jefa->tiene('<option value="2" selected>Mass Cerro Colorado</option>') && $jefa->tiene('<option value="1">Mass Cayma · la tuya</option>'));
revisar('Las ventas, el stock y los reportes se abren en Cerro Colorado',
    $jefa->get('ruta=ventas') === 200 && $jefa->tiene('B002-') && !$jefa->tiene('B001-')
    && $jefa->get('ruta=reportes') === 200 && $jefa->get('ruta=caja') === 200 && $jefa->get('ruta=tienda_editar') === 200
    && $jefa->tiene('value="Mass Cerro Colorado"'));
revisar('Con otra sucursal elegida igual se puede pedir otra tienda en la URL',
    $jefa->get('ruta=ventas&tienda=1') === 200 && $jefa->tiene('B001-'));
revisar('Un volver con una URL de otro sitio se ignora: va al inicio',
    $jefa->post('tienda_elegir', ['tienda' => '3', 'volver' => '//otro.sitio']) === 303
    && str_ends_with($jefa->cabeceras['location'] ?? '', '/?ruta=inicio')
    && $jefa->get('ruta=inicio') === 200 && $jefa->tiene('<option value="3" selected>Mass Yanahuara</option>'));
revisar('Volver a su tienda la deja como al entrar',
    $jefa->post('tienda_elegir', ['tienda' => '1', 'volver' => 'inicio']) === 303
    && $jefa->get('ruta=empleados') === 200 && $jefa->tiene('Cáceres') && !$jefa->tiene('Pedro'));
$jefa->post('tienda_elegir', ['tienda' => '2', 'volver' => 'inicio']);
revisar('Salir y volver a entrar empieza otra vez en su propia tienda',
    $jefa->post('salir', []) === 303 && $jefa->entrar('70000001') === 303
    && $jefa->get('ruta=inicio') === 200 && $jefa->tiene('<option value="1" selected>'));

echo "Versión, soporte y sin internet\n";
$version = trim((string) file_get_contents(__DIR__ . '/../../VERSION'));
revisar('El login muestra la versión en el pie', $anonimo->get('ruta=login') === 200 && $anonimo->tiene('v' . $version) && $anonimo->tiene('Soporte:'));
revisar('Sin sesión no hay enlace a Ayuda', !$anonimo->tiene('ruta=ayuda'));
revisar('Ayuda sin sesión lleva al login', $anonimo->get('ruta=ayuda') === 303);
revisar('?ruta=salud responde JSON sin login, con la versión y la base arriba', $anonimo->get('ruta=salud') === 200
    && str_starts_with($anonimo->cabeceras['content-type'] ?? '', 'application/json')
    && (json_decode($anonimo->cuerpo, true)['version'] ?? '') === $version && (json_decode($anonimo->cuerpo, true)['base'] ?? '') === 'ok'
    && !isset($anonimo->cabeceras['set-cookie']));
foreach ([[$luis, 'Luis (cajero)', 'Empezar el día'], [$carmen, 'Carmen (almacenero)', 'Recibir mercadería'], [$rosa, 'Rosa (administradora)', 'Revisar cajas']] as [$quien, $nombre, $suManual]) {
    revisar("Ayuda de $nombre: versión, soporte, sin internet y su manual", $quien->get('ruta=ayuda') === 200
        && $quien->tiene('versión <strong>' . $version) && $quien->tiene('Si se cae el internet') && $quien->tiene($suManual));
}
revisar('El cajero no ve el manual del administrador', $luis->get('ruta=ayuda') === 200 && !$luis->tiene('Revisar cajas'));
revisar('Cada página lleva el enlace a Ayuda en el pie', $carmen->get('ruta=productos') === 200 && $carmen->tiene('href="/?ruta=ayuda"'));
revisar('Nueva venta trae una clave única para no registrar dos veces', $luis->get('ruta=venta_nueva') === 200
    && preg_match('/name="clave_operacion" value="([0-9a-f]{32})"/', $luis->cuerpo, $claveA) === 1
    && $luis->get('ruta=venta_nueva') === 200 && preg_match('/name="clave_operacion" value="([0-9a-f]{32})"/', $luis->cuerpo, $claveB) === 1
    && $claveA[1] !== $claveB[1]);
revisar('Venta con error: vuelve con la misma clave', $luis->post('venta_registrar', ['tipo' => 'B', 'clave_operacion' => str_repeat('a', 32)]) === 422
    && $luis->tiene('value="' . str_repeat('a', 32) . '"'));
revisar('venta_estado de una clave que no se usó: no registrada', $luis->get('ruta=venta_estado&clave=' . str_repeat('b', 32)) === 200
    && json_decode($luis->cuerpo, true) === ['registrada' => false]);
revisar('venta_estado con una clave rara: no registrada (no rompe)', $luis->get('ruta=venta_estado&clave=%27%20OR%201=1') === 200
    && json_decode($luis->cuerpo, true) === ['registrada' => false]);
revisar('venta_estado es solo del cajero (403)', $carmen->get('ruta=venta_estado&clave=' . str_repeat('b', 32)) === 403);

echo "Cada empleado solo en su sucursal\n";
// El cajero y el almacenero trabajan en su tienda: no ven pestañas de otras ni pueden pedirlas por la URL
$miguel = new Navegador();
revisar('Miguel (cajero de Yanahuara) entra', $miguel->entrar('70000008') === 303);
revisar('El catálogo del cajero no tiene pestañas de tiendas ni el botón de stock de todas',
    $miguel->get('ruta=productos') === 200 && $miguel->tiene('por reponer en Mass Yanahuara')
    && !$miguel->tiene('aria-label="Elegir tienda"') && !$miguel->tiene('Stock de las')
    && !$miguel->tiene('Mass Cayma') && !$miguel->tiene('Mass Cerro Colorado'));
revisar('El cajero pide otra tienda en la URL: 403, sin datos de esa tienda',
    $miguel->get('ruta=productos&tienda=1') === 403 && $miguel->tiene('Solo puedes ver los datos de tu tienda') && !$miguel->tiene('Mass Cayma')
    && $luis->get('ruta=producto&id=8&tienda=2') === 403 && !$luis->tiene('Mass Cerro Colorado')
    && $luis->get('ruta=stock_csv&tienda=3') === 403 && !str_contains($luis->cuerpo, 'Mass Yanahuara'));
revisar('Con su propia tienda (o sin tienda) en la URL, todo sigue igual',
    $miguel->get('ruta=productos&tienda=3') === 200 && $miguel->tiene('por reponer en Mass Yanahuara')
    && $miguel->get('ruta=productos&tienda=03') === 200 && $miguel->tiene('por reponer en Mass Yanahuara')
    && $miguel->get('ruta=productos&tienda=') === 200 && $miguel->tiene('por reponer en Mass Yanahuara')
    && $miguel->get('ruta=productos&tienda=3x') === 403);
revisar('Las ventas de otra tienda no se ven: pedirlas es 403 y la lista solo trae su serie',
    $luis->get('ruta=ventas&tienda=2') === 403
    && $luis->get('ruta=ventas') === 200 && $luis->tiene('B001-') && !$luis->tiene('B002-') && !$luis->tiene('aria-label="Elegir tienda"'));
revisar('La ficha de un cliente solo muestra al cajero las compras de su tienda (el administrador ve las de la cadena)',
    $luis->get('ruta=cliente&id=2') === 200 && $luis->tiene('Mass Cayma') && !$luis->tiene('Mass Yanahuara') && !$luis->tiene('B003-')
    && $rosa->get('ruta=cliente&id=2') === 200 && $rosa->tiene('Mass Cayma') && $rosa->tiene('Mass Yanahuara'));
revisar('Recién agregados: el cajero y el almacenero solo ven su sucursal y no tienen el filtro de tiendas',
    $carmen->get('ruta=productos_nuevos') === 200 && !$carmen->tiene('<select name="tienda">')
    && $luis->get('ruta=productos_nuevos&periodo=todo') === 200 && !$luis->tiene('<select name="tienda">')
    && $miguel->get('ruta=productos_nuevos&tienda=1') === 403
    && $rosa->get('ruta=productos_nuevos') === 200 && $rosa->tiene('<select name="tienda">'));
revisar('Un comprobante de otra tienda no existe para el cajero (página, ticket y panel): 404',
    $rosa->get('ruta=comprobante&serie=B002&numero=1') === 200
    && $luis->get('ruta=comprobante&serie=B002&numero=1') === 404
    && $luis->get('ruta=comprobante_imprimir&serie=B002&numero=1') === 404
    && $luis->get('ruta=ventas&ver=B002-1') === 200 && !$luis->tiene('B002-1'));
revisar('El stock del cajero solo tiene la columna de su tienda (pantalla y CSV)',
    $luis->get('ruta=stock') === 200 && $luis->tiene('Mass Cayma') && !$luis->tiene('Mass Cerro Colorado') && !$luis->tiene('Mass Yanahuara')
    && $luis->get('ruta=stock_csv') === 200 && str_contains($luis->cuerpo, 'Mass Cayma') && !str_contains($luis->cuerpo, 'Mass Yanahuara'));
revisar('El almacenero no abre el kardex, las mermas ni el conteo de otra tienda',
    $carmen->get('ruta=kardex&tienda=2') === 403 && !$carmen->tiene('Mass Cerro Colorado')
    && $carmen->get('ruta=kardex_csv&tienda=2') === 403 && $carmen->get('ruta=mermas&tienda=3') === 403
    && $carmen->get('ruta=ajustes&tienda=2') === 403 && $carmen->get('ruta=traslados&tienda=2') === 403
    && $carmen->get('ruta=kardex&tienda=1') === 200 && !$carmen->tiene('aria-label="Elegir tienda"'));
revisar('El cajero no tiene Tiendas en el menú y la lista solo muestra la suya (sin el personal de otras)',
    $miguel->get('ruta=inicio') === 200 && !$miguel->tiene('ruta=tiendas"')
    && $miguel->get('ruta=tiendas') === 200 && $miguel->tiene('Mass Yanahuara') && !$miguel->tiene('Mass Cayma') && !$miguel->tiene('Caceres'));
revisar('El administrador sí ve las pestañas, el stock de todas y la lista de tiendas',
    $rosa->get('ruta=productos') === 200 && $rosa->tiene('aria-label="Elegir tienda"') && $rosa->tiene('Stock de las')
    && $rosa->get('ruta=productos&tienda=3') === 200 && $rosa->tiene('por reponer en Mass Yanahuara')
    && $rosa->get('ruta=stock') === 200 && $rosa->tiene('Mass Yanahuara')
    && $rosa->get('ruta=inicio') === 200 && $rosa->tiene('ruta=tiendas"'));

echo "Asistente (sin ANTHROPIC_API_KEY en el .env de las pruebas)\n";
revisar('La página del asistente abre y dice que falta la clave', $rosa->get('ruta=asistente') === 200 && $rosa->tiene('aún no está configurado')
    && !$rosa->tiene('data-asistente-form'));
revisar('Sin clave el menú no ofrece el asistente ni el botón flotante', $luis->get('ruta=inicio') === 200
    && !$luis->tiene('ruta=asistente"') && !$luis->tiene('asistente-flotante'));
revisar('Preguntar sin clave responde JSON con el aviso (sin llamar a internet)', $luis->postJson('asistente_preguntar', ['pregunta' => '¿cuánto vendimos?']) === 200
    && str_starts_with($luis->cabeceras['content-type'] ?? '', 'application/json')
    && ($r = json_decode($luis->cuerpo, true)) !== null && $r['ok'] === false && str_contains($r['respuesta'], 'ANTHROPIC_API_KEY'));
revisar('Sin JavaScript vuelve al chat con el aviso', $luis->post('asistente_preguntar', ['pregunta' => 'hola']) === 303
    && str_contains($luis->cabeceras['location'] ?? '', 'ruta=asistente') && $luis->get('ruta=asistente') === 200 && $luis->tiene('ANTHROPIC_API_KEY'));
revisar('Preguntar sin el token CSRF: 403', $luis->postJson('asistente_preguntar', ['pregunta' => 'hola', 'csrf' => 'x']) === 403);
revisar('Preguntar por GET no existe (es POST)', $luis->get('ruta=asistente_preguntar') === 404);
revisar('Borrar la conversación', $luis->post('asistente_borrar', []) === 303);

echo "Salir\n";
revisar('Salir cierra la sesión', $carmen->post('salir', []) === 303 && $carmen->get('ruta=inicio') === 303);

echo PHP_EOL . "$ok correctas, $fallas fallas" . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
