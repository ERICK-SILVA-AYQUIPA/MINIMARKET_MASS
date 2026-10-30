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
    'reponer_csv'          => [200, 403, 200],
    'utilidad'             => [200, 403, 403],
    'reporte_csv&tipo=utilidad' => [200, 403, 403],
    'catalogo_importar'    => [200, 403, 403],
    'catalogo_plantilla'   => [200, 403, 403],
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
revisar('La caja del cajero muestra lo que hay en gaveta y el retiro', $luis->get('ruta=caja') === 200 && $luis->tiene('En gaveta ahora')
    && $luis->tiene('action="/?ruta=caja_movimiento"') && $rosa->get('ruta=caja') === 200 && !$rosa->tiene('caja_movimiento'));
revisar('Una venta de hace más de 7 días ya no ofrece devolución', $luis->get('ruta=comprobante&serie=B001&numero=1') === 200
    && $luis->tiene('ya no acepta devoluciones') && !$luis->tiene('venta_devolver'));
revisar('La utilidad muestra la comparación por tienda y el margen', $rosa->get('ruta=utilidad&desde=2026-01-01') === 200
    && $rosa->tiene('Por tienda') && $rosa->tiene('Mass Cerro Colorado') && $rosa->tiene('Margen'));
revisar('Reponer sugiere cuánto pedir y sale en el menú del almacenero', $carmen->get('ruta=reponer') === 200
    && ($carmen->tiene('Pedir') || $carmen->tiene('Nada por reponer')) && $carmen->get('ruta=inicio') === 200 && $carmen->tiene('ruta=reponer"'));
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
    && $rosa->tiene('data-chips="tarjetas-productos"') && $rosa->tiene('data-guia="catalogo"') && $rosa->tiene('iconos.svg#lacteo'));
revisar('Filtrar por categoría deja solo esa (Lacteos: 12)', $rosa->get('ruta=productos&categoria=2') === 200
    && substr_count($rosa->cuerpo, '<li id="fila-') === 12 && substr_count($rosa->cuerpo, '" data-categoria="2">') === 12);
revisar('La vista en tabla también tiene foto chica', $rosa->get('ruta=productos&vista=tabla') === 200 && $rosa->tiene('id="filas-productos"')
    && $rosa->tiene('class="foto chica'));
revisar('La venta tiene la cuadrícula, el campo del lector y el código en cada opción', $luis->get('ruta=venta_nueva') === 200
    && $luis->tiene('data-escanear') && $luis->tiene('data-agregar="1"') && $luis->tiene('data-codigo="7750000000011"'));
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
revisar('La auditoría está en el menú del administrador', $rosa->get('ruta=inicio') === 200 && $rosa->tiene('ruta=auditoria"'));
revisar('Las pantallas cargan solo /js/app.js y el ticket ninguno', $rosa->get('ruta=inicio') === 200 && $rosa->tiene('<script src="/js/app.js')
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
    [$carmen, 'Carmen', 'caja_movimiento', ['tipo' => 'RETIRO', 'monto' => '1', 'motivo' => 'x']],
    [$carmen, 'Carmen', 'venta_devolver', ['serie' => 'B001', 'numero' => '1', 'producto' => ['1'], 'cantidad' => ['1'], 'motivo' => 'x']],
    [$rosa, 'Rosa', 'venta_devolver', ['serie' => 'B001', 'numero' => '1', 'producto' => ['1'], 'cantidad' => ['1'], 'motivo' => 'x']],
    [$rosa, 'Rosa', 'caja_movimiento', ['tipo' => 'RETIRO', 'monto' => '1', 'motivo' => 'x']],
    [$rosa, 'Rosa', 'venta_registrar', ['tipo' => 'B']],
    [$rosa, 'Rosa', 'traslado_registrar', ['destino' => '2']],
    [$luis, 'Luis', 'ingreso_registrar', ['tipo_documento' => 'FACTURA']],
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
revisar('Retiro con monto inválido: 422', $luis->post('caja_movimiento', ['tipo' => 'RETIRO', 'monto' => 'abc', 'motivo' => 'x']) === 422
    && $luis->tiene('Monto no válido'));
revisar('Retiro mayor que la gaveta: 422', $luis->post('caja_movimiento', ['tipo' => 'RETIRO', 'monto' => '99999', 'motivo' => 'x']) === 422
    && $luis->tiene('en la gaveta'));
revisar('Devolución de una venta vieja: vuelve al comprobante con el error', $luis->post('venta_devolver',
    ['serie' => 'B001', 'numero' => '1', 'producto' => ['1'], 'cantidad' => ['1'], 'motivo' => 'x']) === 303
    && $luis->get('ruta=comprobante&serie=B001&numero=1') === 200 && $luis->tiene('ya no se aceptan devoluciones'));
revisar('Turno con monto inválido: 422', $luis->post('turno_abrir', ['caja' => '1', 'monto_inicial' => '-1']) === 422);

echo "Salir\n";
revisar('Salir cierra la sesión', $carmen->post('salir', []) === 303 && $carmen->get('ruta=inicio') === 303);

echo PHP_EOL . "$ok correctas, $fallas fallas" . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
