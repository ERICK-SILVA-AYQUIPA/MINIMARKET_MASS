<?php
declare(strict_types=1);

/**
 * Revision automatica del codigo (sin base de datos ni servidor):
 *   php tests/seguridad.php
 *
 * 1. CSRF: cada formulario POST lleva el token, apunta a una ruta de $rutasPost y esa ruta tiene su metodo.
 * 2. Escapado: todo lo que una vista imprime con <?= pasa por e() o es un numero o un texto fijo.
 * 3. SQL: ningun dato de afuera se pega al SQL; solo fragmentos fijos del codigo y enteros.
 * 4. Reglas generales: strict_types, sin propiedades public, sin $_GET/$_POST fuera de public/index.php.
 * 5. JavaScript: solo /app.js, sin scripts pegados ni onclick=, sin innerHTML ni eval, y la CSP en script-src 'self'.
 *
 * Si alguien agrega una vista o una consulta que no cumple, esta prueba falla y dice el archivo y la linea.
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la terminal.');
}

$raiz = realpath(__DIR__ . '/../..');
$ok = 0;
$fallas = 0;
function revisar(string $caso, bool $cumple, array $detalle = []): void
{
    global $ok, $fallas;
    $cumple ? $ok++ : $fallas++;
    echo ($cumple ? '  OK    ' : '  FALLA ') . $caso . PHP_EOL;
    foreach (array_slice($detalle, 0, 15) as $linea) {
        echo '          · ' . $linea . PHP_EOL;
    }
}

/** @return string[] rutas relativas de los .php de una carpeta (recursivo) */
function archivosPhp(string $raiz, string $carpeta): array
{
    $lista = [];
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/' . $carpeta, FilesystemIterator::SKIP_DOTS));
    foreach ($iterador as $archivo) {
        if ($archivo->getExtension() === 'php') {
            $lista[] = substr($archivo->getPathname(), strlen($raiz) + 1);
        }
    }
    sort($lista);
    return $lista;
}

function lineaDe(string $texto, int $posicion): int
{
    return substr_count($texto, "\n", 0, $posicion) + 1;
}

$vistas = archivosPhp($raiz, 'frontend/views');
$index = file_get_contents($raiz . '/frontend/public/index.php');

echo "1. Formularios y CSRF\n";
preg_match('/\$rutasPost = \[(.*?)\];/s', $index, $m);
preg_match_all("/'([a-z_]+)'/", $m[1] ?? '', $rutas);
$rutasPost = $rutas[1];
revisar('index.php define las rutas POST (' . count($rutasPost) . ')', count($rutasPost) > 0);

// Cada ruta POST tiene su linea en el match, y el token se revisa ANTES del match
preg_match('/match \(\$ruta\) \{(.*?)\};/s', $index, $m);
preg_match_all("/'([a-z_]+)'\s*=>/", $m[1] ?? '', $enMatch);
revisar('Cada ruta POST llega a un método del controlador', array_diff($rutasPost, $enMatch[1]) === [] && array_diff($enMatch[1], $rutasPost) === [],
    array_merge(array_diff($rutasPost, $enMatch[1]), array_diff($enMatch[1], $rutasPost)));
$posCsrf = strpos($index, '$sesion->csrfValido(');
$posMatch = strpos($index, 'match ($ruta)');
revisar('El token CSRF se valida antes de ejecutar cualquier POST', $posCsrf !== false && $posMatch !== false && $posCsrf < $posMatch);

$sinToken = [];
$rutaDesconocida = [];
$formularios = 0;
foreach ($vistas as $vista) {
    $texto = file_get_contents($raiz . '/' . $vista);
    preg_match_all('/<form\b[^>]*>.*?<\/form>/s', $texto, $bloques, PREG_OFFSET_CAPTURE);
    foreach ($bloques[0] as [$bloque, $posicion]) {
        $etiqueta = substr($bloque, 0, strpos($bloque, '>') + 1);
        if (stripos($etiqueta, 'method="post"') === false) {
            continue;
        }
        $formularios++;
        $donde = $vista . ':' . lineaDe($texto, $posicion);
        if (!str_contains($bloque, 'campoCsrf(')) {
            $sinToken[] = $donde;
        }
        if (preg_match('/action="\/\?ruta=([a-z_]+)"/', $etiqueta, $accion) !== 1 || !in_array($accion[1], $rutasPost, true)) {
            $rutaDesconocida[] = $donde . ' ' . ($accion[1] ?? '(sin action)');
        }
    }
}
revisar("Los $formularios formularios POST llevan el token CSRF", $sinToken === [], $sinToken);
revisar('Cada formulario POST apunta a una ruta POST conocida', $rutaDesconocida === [], $rutaDesconocida);
revisar('No hay formularios GET que cambien datos (todo cambio va por POST)',
    !preg_match('/<form\b(?![^>]*method="post")[^>]*action="\/\?ruta=(' . implode('|', $rutasPost) . ')"/', implode("\n", array_map(
        fn(string $v): string => file_get_contents($raiz . '/' . $v), $vistas))));

echo "2. Escapado de lo que se imprime\n";
// Formas seguras de <?= ... : e(), el token, enteros y textos fijos del codigo
$seguras = [
    '/^e\(.*\)$/s',
    '/^campoCsrf\(\$csrf\)$/',
    // icono('nombre') con un nombre fijo; icono() escapa el nombre con e()
    "/^icono\\('[a-z]+'\\)$/",
    '/^\(int\) /',
    '/^count\(\$\w+\)$/',
    '/^\$\w+->(get(Id|Numero|Ultimo|Pagina|TotalFilas)\w*|totalPaginas)\(\)$/',
    '/^\$(id|i|alto|porReponer|totalPorReponer)( [+-] (\d+|count\(\$\w+\)))?$/',
    "/^\\$\\w+\\['(comprobantes|anuladas)'\\]$/",
    // condicion ? 'texto fijo' : 'texto fijo' (o e(...))
    "/^.+ \\? '[^'$]*' : ('[^'$]*'|e\\(.*\\))$/s",
    // un numero o un texto fijo si no hay: $numeroCaja[...] ?? '-', $caja?->getNumeroCaja() ?? '-'
    "/^\\$\\w+(\\[[^\\]]+\\]|\\?->get(Id|Numero)\\w*\\(\\)) \\?\\? '[^'$]*'$/",
];
$sinEscapar = [];
$impresiones = 0;
foreach ($vistas as $vista) {
    $texto = file_get_contents($raiz . '/' . $vista);
    preg_match_all('/<\?=\s*(.*?)\s*\?>/s', $texto, $salidas, PREG_OFFSET_CAPTURE);
    foreach ($salidas[1] as [$expresion, $posicion]) {
        $impresiones++;
        $segura = false;
        foreach ($seguras as $patron) {
            if (preg_match($patron, $expresion) === 1) {
                $segura = true;
                break;
            }
        }
        if (!$segura) {
            $sinEscapar[] = $vista . ':' . lineaDe($texto, $posicion) . '  <?= ' . $expresion . ' ?>';
        }
    }
    // echo dentro de una vista: solo con e() o textos fijos
    foreach (explode("\n", $texto) as $n => $linea) {
        if (preg_match('/\becho\s+(.*);/', $linea, $eco) === 1 && preg_match('/\$/', preg_replace('/e\([^()]*(\([^()]*\))?[^()]*\)|count\(\$\w+\)/', '', $eco[1])) === 1
            && !preg_match('/^match /', $eco[1])) {
            $sinEscapar[] = $vista . ':' . ($n + 1) . '  echo ' . trim($eco[1]);
        }
    }
}
revisar("Las $impresiones salidas <?= de las vistas están escapadas o son números", $sinEscapar === [], $sinEscapar);
$layout = file_get_contents($raiz . '/frontend/views/parciales/funciones.php');
revisar('e() usa htmlspecialchars con ENT_QUOTES y UTF-8, e icono() escapa su nombre', str_contains($layout, "htmlspecialchars(\$texto, ENT_QUOTES, 'UTF-8')")
    && str_contains($layout, "'<svg class=\"icono\" aria-hidden=\"true\" focusable=\"false\"><use href=\"/iconos.svg#' . e(\$nombre) . '\"></use></svg>'"));

echo "3. SQL con consultas preparadas\n";
// Variables que pueden ir dentro del texto SQL: fragmentos que arma el codigo, nunca datos del usuario
$permitidas = ['$this', '$donde', '$condicion', '$columna', '$tabla', '$desde', '$sql'];
$sqlArmado = [];
$consultas = 0;
foreach (array_merge(archivosPhp($raiz, 'backend/models'), archivosPhp($raiz, 'backend/config')) as $archivo) {
    $tokens = token_get_all(file_get_contents($raiz . '/' . $archivo));
    $total = count($tokens);
    for ($i = 0; $i < $total; $i++) {
        $t = $tokens[$i];
        if (!is_array($t) || $t[0] !== T_STRING || !in_array($t[1], ['prepare', 'query'], true)) {
            continue;
        }
        $anterior = $tokens[$i - 1] ?? null;
        if (!is_array($anterior) || $anterior[0] !== T_OBJECT_OPERATOR) {
            continue;
        }
        $consultas++;
        // Recorre el argumento hasta cerrar el parentesis
        $nivel = 0;
        $dentroDeFuncion = 0;
        $previo = null;
        for ($j = $i + 1; $j < $total; $j++) {
            $tok = $tokens[$j];
            if ($tok === '(') {
                $nivel++;
                if (is_array($previo) && $previo[0] === T_STRING && in_array(strtolower($previo[1]), ['max', 'min'], true)) {
                    $dentroDeFuncion = $nivel;
                }
            } elseif ($tok === ')') {
                if ($nivel === $dentroDeFuncion) {
                    $dentroDeFuncion = 0;
                }
                if (--$nivel === 0) {
                    break;
                }
            } elseif (is_array($tok) && $tok[0] === T_VARIABLE) {
                $casteado = is_array($previo) && $previo[0] === T_INT_CAST;
                if (!in_array($tok[1], $permitidas, true) && !$casteado && $dentroDeFuncion === 0) {
                    $sqlArmado[] = $archivo . ':' . $tok[2] . '  ' . $tok[1];
                }
            }
            if (!is_array($tok) || $tok[0] !== T_WHITESPACE) {
                $previo = $tok;
            }
        }
    }
}
revisar("Las $consultas llamadas a prepare()/query() no pegan datos de afuera al SQL", $sqlArmado === [], $sqlArmado);
$database = file_get_contents($raiz . '/backend/config/Database.php');
revisar('PDO con consultas preparadas reales y excepciones', str_contains($database, 'PDO::ATTR_EMULATE_PREPARES   => false')
    && str_contains($database, 'PDO::ERRMODE_EXCEPTION'));
$sqlFuera = [];
foreach (array_merge(archivosPhp($raiz, 'backend/controllers'), $vistas) as $archivo) {
    if (preg_match('/->(prepare|query|exec)\(|\b(SELECT|INSERT|UPDATE|DELETE)\s+[A-Za-z*]/', file_get_contents($raiz . '/' . $archivo)) === 1) {
        $sqlFuera[] = $archivo;
    }
}
revisar('Controladores y vistas no tienen SQL (solo los modelos)', $sqlFuera === [], $sqlFuera);

echo "4. Reglas generales del código\n";
$todos = array_merge(archivosPhp($raiz, 'backend/config'), archivosPhp($raiz, 'backend/models'), archivosPhp($raiz, 'backend/controllers'),
    $vistas, archivosPhp($raiz, 'frontend/public'), archivosPhp($raiz, 'backend/tests'));
$sinStrict = [];
$publicas = [];
$entradaCruda = [];
foreach ($todos as $archivo) {
    $texto = file_get_contents($raiz . '/' . $archivo);
    if (!str_contains($texto, 'declare(strict_types=1);') && trim($texto) !== '' && str_starts_with(ltrim($texto), '<?php')) {
        $sinStrict[] = $archivo;
    }
    if (preg_match('/^\s*public\s+(?!function|const|static function|readonly function)(\??[\w|]+\s+)?\$\w+/m', $texto) === 1
        && !str_starts_with($archivo, 'backend/tests/')) {
        $publicas[] = $archivo;
    }
    // Las dos entradas leen la peticion: la pagina web (public/index.php) y la API de la app (public/api/index.php)
    if (!in_array($archivo, ['frontend/public/index.php', 'frontend/public/api/index.php'], true) && !str_starts_with($archivo, 'backend/tests/') && !str_starts_with($archivo, 'frontend/public/poo_')
        && preg_match('/\$_(GET|POST|REQUEST|COOKIE)\b/', $texto) === 1) {
        $entradaCruda[] = $archivo;
    }
}
revisar('Todos los archivos PHP tienen declare(strict_types=1)', $sinStrict === [], $sinStrict);
revisar('Ninguna clase tiene propiedades public', $publicas === [], $publicas);
revisar('$_GET y $_POST solo se leen en public/index.php y public/api/index.php', $entradaCruda === [], $entradaCruda);
revisar('.env está en .gitignore', in_array('.env', array_map('trim', file($raiz . '/.gitignore')), true));
$claves = [];
foreach (array_merge(archivosPhp($raiz, 'backend/config'), archivosPhp($raiz, 'backend/models'), archivosPhp($raiz, 'backend/controllers')) as $archivo) {
    if (preg_match('/(password|clave)\s*=\s*[\'"][^\'"]{4,}[\'"]/i', file_get_contents($raiz . '/' . $archivo)) === 1) {
        $claves[] = $archivo;
    }
}
revisar('Sin claves escritas en el código', $claves === [], $claves);

echo "5. JavaScript\n";
// Las vistas solo cargan frontend/public/js/app.js: nada de <script> pegado, onclick= ni enlaces javascript:
$scriptsSueltos = [];
foreach ($vistas as $archivo) {
    $texto = file_get_contents($raiz . '/' . $archivo);
    preg_match_all('/<script\b[^>]*>/i', $texto, $etiquetas, PREG_OFFSET_CAPTURE);
    foreach ($etiquetas[0] as [$etiqueta, $posicion]) {
        if (preg_match('#^<script src="/js/app\.js(\?v=\d+)?" defer>$#', $etiqueta) !== 1) {
            $scriptsSueltos[] = $archivo . ':' . lineaDe($texto, $posicion) . '  ' . $etiqueta;
        }
    }
    if (preg_match_all('/\s+on[a-z]+\s*=\s*["\'<]|(href|src|action)\s*=\s*["\']?\s*javascript:/i', $texto, $eventos, PREG_OFFSET_CAPTURE) > 0) {
        foreach ($eventos[0] as [$trozo, $posicion]) {
            $scriptsSueltos[] = $archivo . ':' . lineaDe($texto, $posicion) . '  ' . trim($trozo);
        }
    }
}
revisar('Las vistas solo cargan /js/app.js (sin scripts pegados, onclick= ni javascript:)', $scriptsSueltos === [], $scriptsSueltos);

// app.js nunca convierte datos en HTML ni ejecuta texto, y no llama a otros sitios
$js = file_get_contents($raiz . '/frontend/public/js/app.js');
$peligrosos = [];
foreach (['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'eval(', 'new Function', 'setTimeout(\'', 'fetch(', 'XMLHttpRequest', 'http://', 'https://'] as $patron) {
    if (str_contains($js, $patron)) {
        $peligrosos[] = 'frontend/public/js/app.js:' . lineaDe($js, strpos($js, $patron)) . '  ' . $patron;
    }
}
revisar('app.js escribe con textContent, sin eval ni llamadas a otros sitios', $peligrosos === [], $peligrosos);
revisar('La CSP solo permite scripts de este sitio', preg_match("/script-src 'self';/", $index) === 1
    && preg_match("/script-src[^;]*('unsafe-inline'|'unsafe-eval'|https?:)/", $index) === 0);

echo PHP_EOL . "$ok correctas, $fallas fallas" . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
