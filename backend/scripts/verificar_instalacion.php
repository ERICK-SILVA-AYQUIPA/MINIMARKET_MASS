<?php
declare(strict_types=1);

/**
 * Revisa que una instalacion este lista para usarse con datos reales (no cambia nada).
 *   php backend/scripts/verificar_instalacion.php
 * FALLA = hay que corregirlo antes de abrir la tienda. AVISO = conviene revisarlo.
 * No imprime claves ni DNIs. Termina con codigo 1 si hay alguna FALLA.
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la consola.');
}
require __DIR__ . '/../config/Database.php';
require __DIR__ . '/../config/Migraciones.php';
require __DIR__ . '/../config/Aplicacion.php';
require __DIR__ . '/../models/Emisor.php';
require __DIR__ . '/../models/facturacion/FacturacionElectronica.php';
date_default_timezone_set('America/Lima');

$raiz = dirname(__DIR__, 2);
$fallas = 0;
$avisos = 0;

function resultado(string $estado, string $texto): void
{
    global $fallas, $avisos;
    if ($estado === 'FALLA') {
        $fallas++;
    } elseif ($estado === 'AVISO') {
        $avisos++;
    }
    printf("  %-5s %s\n", $estado, $texto);
}

function revisar(bool $cumple, string $bien, string $mal, string $siNo = 'FALLA'): void
{
    resultado($cumple ? 'OK' : $siNo, $cumple ? $bien : $mal);
}

// php.ini: "2M", "512K" o "1G" -> bytes
function bytesIni(string $valor): int
{
    $numero = (int) $valor;
    return match (strtoupper(substr(trim($valor), -1))) {
        'G'     => $numero * 1024 ** 3,
        'M'     => $numero * 1024 ** 2,
        'K'     => $numero * 1024,
        default => $numero,
    };
}

echo "PHP\n";
revisar(PHP_VERSION_ID >= 80300, 'PHP ' . PHP_VERSION, 'PHP ' . PHP_VERSION . ': se necesita 8.3 o más');
foreach (['pdo_mysql', 'mbstring'] as $extension) {
    revisar(extension_loaded($extension), "Extensión $extension", "Falta la extensión $extension: actívala en php.ini");
}
revisar(extension_loaded('gd'), 'Extensión gd (fotos de producto)', 'Falta la extensión gd: sin ella no se pueden subir fotos de producto', 'AVISO');
revisar(is_writable($raiz . '/frontend/public/img/productos'), 'Se pueden guardar fotos en frontend/public/img/productos',
    'PHP no puede escribir en frontend/public/img/productos: dale permiso al usuario del servidor web', 'AVISO');
revisar(!filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN), 'display_errors = Off',
    'display_errors está en On: en el servidor ponlo en Off (php.ini del servidor web)', 'AVISO');
revisar(bytesIni((string) ini_get('upload_max_filesize')) >= 1024 ** 2 && bytesIni((string) ini_get('post_max_size')) >= 2 * 1024 ** 2,
    'Se pueden subir archivos de 1 MB (importar catálogo)', 'upload_max_filesize debe ser 1M o más y post_max_size 2M o más', 'AVISO');

echo "Configuración (.env)\n";
revisar(is_readable($raiz . '/.env') || getenv('DB_PASSWORD') !== false, 'Existe .env', 'No hay .env: copia .env.example y cambia las claves');
try {
    $db = Database::conexion();
} catch (PDOException) {
    resultado('FALLA', 'No se pudo conectar a MySQL con los datos de .env (revisa DB_HOST, DB_PORT, DB_USER y DB_PASSWORD)');
    echo PHP_EOL . "$fallas fallas, $avisos avisos" . PHP_EOL;
    exit(1);
}
foreach (['DB_PASSWORD', 'DB_ROOT_PASSWORD'] as $variable) {
    $clave = (string) getenv($variable);
    $deEjemplo = $clave === '' || str_starts_with($clave, 'cambia_esta_clave');
    revisar(!$deEjemplo && strlen($clave) >= 12, "$variable cambiada y de 12 caracteres o más",
        "$variable es la de ejemplo o es corta: pon una clave larga", $variable === 'DB_ROOT_PASSWORD' && $clave === '' ? 'AVISO' : 'FALLA');
}
$prohibidos = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/frontend/public', FilesystemIterator::SKIP_DOTS)) as $archivo) {
    if (preg_match('/(^\.env|\.sql$|\.bak$|\.log$)/', $archivo->getFilename()) === 1) {
        $prohibidos[] = $archivo->getFilename();
    }
}
revisar($prohibidos === [], 'frontend/public/ no tiene .env, .sql ni logs', 'Sácalos de frontend/public/ (se pueden descargar): ' . implode(', ', $prohibidos));

echo "Base de datos\n";
$usuario = (string) $db->query('SELECT CURRENT_USER()')->fetchColumn();
revisar(!str_starts_with($usuario, 'root@'), 'La aplicación no entra como root', 'La aplicación entra como root: usa el usuario mass_app (docs/despliegue.md)');
$tablas = (int) $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchColumn();
revisar($tablas === 46, 'Las 46 tablas', "Hay $tablas tablas y deben ser 46: falta correr algún script de database/ o php backend/scripts/migrar.php (docs/despliegue.md, sección 8)");
try {
    $estadoMigraciones = (new Migraciones($db))->estado();
    revisar($estadoMigraciones['pendientes'] === [] && $estadoMigraciones['cambiadas'] === [],
        'Migraciones al día (' . count(Migraciones::archivos()) . ')',
        'Faltan migraciones o alguna fue editada (' . implode(', ', array_merge($estadoMigraciones['pendientes'], $estadoMigraciones['cambiadas']))
        . '): corre php backend/scripts/migrar.php');
} catch (PDOException) {
    resultado('FALLA', 'No existe la tabla migracion_aplicada: corre php backend/scripts/migrar.php');
}
$boleta = (int) $db->query("SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = DATABASE() AND routine_name = 'sp_boleta'")->fetchColumn();
revisar($boleta === 1, 'Procedimiento sp_boleta', 'Falta sp_boleta: corre database/boleta.sql', 'AVISO');
$minutos = (int) $db->query('SELECT TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), NOW())')->fetchColumn();
revisar($minutos === -300, 'MySQL tiene la hora de Lima', 'MySQL no está en la hora de Lima: las ventas saldrían con otra fecha (TZ: America/Lima)');

echo "Datos\n";
$conClaveDePrueba = [];
foreach ($db->query("SELECT nombres, clave_hash FROM empleado WHERE estado = 'ACTIVO' AND clave_hash IS NOT NULL")->fetchAll() as $empleado) {
    if (password_verify('Mass2026!', (string) $empleado['clave_hash'])) {
        $conClaveDePrueba[] = (string) $empleado['nombres'];
    }
}
$porCambiar = (int) $db->query("SELECT COUNT(*) FROM empleado WHERE estado = 'ACTIVO' AND debe_cambiar_clave = 'S'")->fetchColumn();
revisar($porCambiar === 0, 'Todos los empleados activos ya eligieron su clave',
    "$porCambiar empleados activos aún no cambian la clave que les puso el administrador (se les pedirá al entrar)", 'AVISO');
revisar($conClaveDePrueba === [], 'Nadie usa la clave de prueba',
    count($conClaveDePrueba) . ' empleados activos siguen con la clave de prueba (' . implode(', ', array_slice($conClaveDePrueba, 0, 5))
    . (count($conClaveDePrueba) > 5 ? '…' : '') . '): que la cambien en "Mi clave"');

echo "Ticket y comprobantes\n";
$emisor = Emisor::desdeEntorno();
revisar($emisor->estaConfigurado(), 'Razón social y RUC de la empresa en el ticket',
    $emisor->getRuc() !== '' && !Emisor::rucValido($emisor->getRuc())
        ? 'EMPRESA_RUC no es un RUC válido (11 dígitos con su dígito verificador): corrígelo en .env'
        : 'Faltan EMPRESA_RAZON_SOCIAL y EMPRESA_RUC en .env: el ticket sale sin RUC', 'AVISO');
$sinDireccion = $db->query("SELECT nombre_tienda FROM tienda WHERE estado = 'ACTIVA' AND (TRIM(direccion) = '' OR direccion LIKE 'Direccion de ejemplo%' OR direccion LIKE 'Dirección de ejemplo%')")->fetchAll(PDO::FETCH_COLUMN);
revisar($sinDireccion === [], 'Cada tienda tiene su dirección real',
    'Tiendas con la dirección de ejemplo (no sale en el ticket): ' . implode(', ', $sinDireccion) . '. Corrígela en Tiendas → Editar', 'AVISO');
$proveedor = FacturacionElectronica::desdeEntorno();
revisar(!FacturacionElectronica::ticketEsInterno($proveedor), 'Las ventas se envían a SUNAT por ' . $proveedor->nombre(),
    'Los tickets salen como TICKET INTERNO, no válidos ante SUNAT. Proveedor: ' . $proveedor->nombre()
    . ' (docs/facturacion_electronica.md; confirmarlo con un contador)', 'AVISO');

echo "Respaldos\n";
// .tar.gz(.gpg) = base + fotos (respaldo.sh / respaldo.ps1); .sql = respaldos antiguos, solo la base
$respaldos = array_merge(...array_map(fn(string $patron): array => glob($raiz . '/respaldos/' . $patron) ?: [],
    ['minimarket_mass_*.tar.gz', 'minimarket_mass_*.tar.gz.gpg', 'minimarket_mass_*.sql']));
$ultimo = $respaldos === [] ? 0 : max(array_map('filemtime', $respaldos));
revisar($ultimo > time() - 2 * 86400, 'Hay un respaldo de las últimas 48 horas',
    'No hay un respaldo reciente: programa el diario con backend/scripts/programar_respaldo', 'AVISO');
revisar(trim((string) getenv('RESPALDO_REMOTO')) !== '', 'Los respaldos se copian fuera del servidor (RESPALDO_REMOTO)',
    'Los respaldos solo quedan en este disco: configura RESPALDO_REMOTO en .env (docs/despliegue.md, sección 10)', 'AVISO');
revisar(strlen((string) getenv('RESPALDO_CLAVE')) >= 12, 'Los respaldos se cifran (RESPALDO_CLAVE)',
    'Los respaldos no se cifran: pon una RESPALDO_CLAVE de 12 caracteres o más en .env', 'AVISO');

echo "Entrega\n";
revisar(Aplicacion::version() !== 'sin versión', 'Versión ' . Aplicacion::version() . ' (archivo VERSION)',
    'El archivo VERSION falta o no tiene el formato 1.2.3: el pie de página no mostrará la versión', 'AVISO');
revisar(Aplicacion::soporteConfigurado(), 'Contacto de soporte: ' . Aplicacion::soporte()['nombre'],
    'Nadie a quien llamar: pon SOPORTE_NOMBRE y SOPORTE_TELEFONO o SOPORTE_CORREO en .env (sale en el pie y en Ayuda)', 'AVISO');

echo PHP_EOL . "$fallas fallas, $avisos avisos" . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
