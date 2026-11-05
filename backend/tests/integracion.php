<?php
declare(strict_types=1);

/**
 * Pruebas de integracion contra la base MySQL de Docker.
 *   php tests/integracion.php
 *
 * OJO: escribe ventas, traslados, mermas, ajustes, turnos, productos, empleados y tiendas de verdad. Para volver a los datos de datos.sql:
 *   docker compose down -v ; docker compose up -d
 *
 * Al final corre las consultas 20 a 31 de consultas.sql: cada una debe devolver 0 filas.
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la terminal.');
}
date_default_timezone_set('America/Lima');

require __DIR__ . '/../config/Database.php';
require __DIR__ . '/../models/EmpleadoRepository.php';
require __DIR__ . '/../models/servicios/VentaServicio.php';
require __DIR__ . '/../models/servicios/InventarioServicio.php';
require __DIR__ . '/../models/servicios/CajaServicio.php';
require __DIR__ . '/../models/servicios/ProductoServicio.php';
require __DIR__ . '/../models/servicios/EmpleadoServicio.php';
require __DIR__ . '/../models/servicios/ClienteServicio.php';
require __DIR__ . '/../models/servicios/TiendaServicio.php';
require __DIR__ . '/../models/ReporteRepository.php';
require __DIR__ . '/../models/IntentoLoginRepository.php';
require __DIR__ . '/../models/TokenApiRepository.php';
require __DIR__ . '/../config/Migraciones.php';
require __DIR__ . '/../models/Paginacion.php';
require_once __DIR__ . '/../models/ProductoRepository.php';
require __DIR__ . '/../models/IngresoRepository.php';
require_once __DIR__ . '/../models/CajaRepository.php';
require __DIR__ . '/../models/VentaRepository.php';
require __DIR__ . '/../models/Vencimiento.php';
require __DIR__ . '/../models/InventarioRepository.php';
require __DIR__ . '/../models/PanelRepository.php';
require __DIR__ . '/../models/Reposicion.php';
require __DIR__ . '/../models/CatalogoCsv.php';
require_once __DIR__ . '/../models/FotoProducto.php';

$db = Database::conexion();

// Los casos esperan los datos de datos.sql tal cual (por ejemplo, que B001 vaya en el 4)
if ((int) $db->query("SELECT ultimo_numero FROM serie_comprobante WHERE serie = 'B001'")->fetchColumn() !== 4) {
    fwrite(STDERR, "La base ya tiene cambios. Reiniciala: docker compose down -v ; docker compose up -d\n");
    exit(2);
}

$empleados = new EmpleadoRepository($db);
$ventas = new VentaServicio($db);
$inventario = new InventarioServicio($db);
$caja = new CajaServicio($db);
$catalogo = new ProductoServicio($db);
$personal = new EmpleadoServicio($db);
$clientesServicio = new ClienteServicio($db);

$rosa = $empleados->buscarPorId(1);    // ADMINISTRADOR Cayma
$luis = $empleados->buscarPorId(2);    // CAJERO Cayma, turno 5 abierto
$carmen = $empleados->buscarPorId(3);  // ALMACENERO Cayma
$jorge = $empleados->buscarPorId(4);   // ADMINISTRADOR Cerro Colorado
$miguel = $empleados->buscarPorId(8);  // CAJERO Yanahuara, sin turno abierto

$ok = 0;
$fallas = 0;
function revisar(string $caso, bool $cumple): void
{
    global $ok, $fallas;
    $cumple ? $ok++ : $fallas++;
    echo ($cumple ? '  OK    ' : '  FALLA ') . $caso . PHP_EOL;
}
function rechaza(string $caso, callable $operacion): void
{
    try {
        $operacion();
        revisar($caso . ' (no la rechazó)', false);
    } catch (ReglaNegocioException $ex) {
        revisar($caso . ' -> ' . $ex->getMessage(), true);
    }
}
function valor(PDO $db, string $sql, array $parametros = []): string
{
    $consulta = $db->prepare($sql);
    $consulta->execute($parametros);
    return (string) $consulta->fetchColumn();
}
$stock = fn(int $tienda, int $producto): string => valor($db,
    'SELECT cantidad_actual FROM stock WHERE id_tienda = ? AND id_producto = ?', [$tienda, $producto]);
$ventasTotales = fn(): string => valor($db, 'SELECT COUNT(*) FROM venta');

echo "Tildes y utf8mb4\n";
revisar('La base, la conexión y todas las columnas de texto usan utf8mb4',
    valor($db, 'SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()') === 'utf8mb4'
    && valor($db, 'SELECT @@character_set_connection') === 'utf8mb4'
    && valor($db, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                   AND CHARACTER_SET_NAME IS NOT NULL AND CHARACTER_SET_NAME <> 'utf8mb4'") === '0');
revisar('Los datos de ejemplo llevan tildes y eñes (migración 015)',
    valor($db, 'SELECT nombre_categoria FROM categoria WHERE id_categoria = 2') === 'Lácteos'
    && valor($db, 'SELECT nombre_marca FROM marca WHERE id_marca = 2') === 'Costeño'
    && valor($db, "SELECT CONCAT(nombres, ' ', apellidos) FROM empleado WHERE id_empleado = 7") === 'Lucía Zegarra Paredes'
    && valor($db, "SELECT CONCAT(nombres, ' ', apellidos) FROM empleado WHERE id_empleado = 8") === 'Miguel Núñez Cuadros'
    && valor($db, 'SELECT direccion FROM tienda WHERE id_tienda = 1') === 'Dirección de ejemplo, Cayma'
    && valor($db, 'SELECT nombre_producto FROM producto WHERE id_producto = 10') === 'Azúcar rubia Costeño 1 kg');
revisar('...guardadas como UTF-8 de verdad, no como texto mal convertido ("NÃºÃ±ez")',
    valor($db, 'SELECT HEX(apellidos) FROM empleado WHERE id_empleado = 8') === strtoupper(bin2hex('Núñez Cuadros'))
    && valor($db, 'SELECT CHAR_LENGTH(apellidos) FROM empleado WHERE id_empleado = 8') === '13');
revisar('No queda ningún texto de ejemplo sin tilde',
    valor($db, "SELECT (SELECT COUNT(*) FROM producto WHERE nombre_producto COLLATE utf8mb4_bin
                        REGEXP 'Costeno|Atun |Azucar|Platano|Bolivar|Cafe |Nescafe|tallarin|Frejol|Lejia|Jabon|Panales|higienic|clasic|[Ll]imon')
                  + (SELECT COUNT(*) FROM categoria WHERE nombre_categoria COLLATE utf8mb4_bin = 'Lacteos')
                  + (SELECT COUNT(*) FROM tienda WHERE direccion COLLATE utf8mb4_bin LIKE 'Direccion%')
                  + (SELECT COUNT(*) FROM empleado WHERE CONCAT(nombres, ' ', apellidos) COLLATE utf8mb4_bin
                        REGEXP 'Lucia|Nunez|Sofia|Caceres|Huaman|Avila')
                  + (SELECT COUNT(*) FROM tipo_movimiento WHERE nombre_movimiento COLLATE utf8mb4_bin REGEXP 'ANULACION|DEVOLUCION|MERCADERIA')") === '0');
revisar('Buscar sin tilde encuentra el texto con tilde ("azucar" -> "Azúcar")',
    valor($db, "SELECT COUNT(*) FROM producto WHERE nombre_producto LIKE '%azucar%'") === '3'
    && valor($db, "SELECT id_categoria FROM categoria WHERE nombre_categoria = 'lacteos'") === '2');
$cambiadas = 0;
foreach (Migraciones::sentencias((string) file_get_contents(__DIR__ . '/../database/migraciones/015_tildes_en_datos.sql')) as $sentencia) {
    $cambiadas += (int) $db->exec($sentencia);
}
revisar('Correr la migración 015 otra vez no cambia nada', $cambiadas === 0);

echo "Sucursal elegida por el administrador\n";
$rosaEnCerro = $rosa->enTienda(2);
revisar('Solo el administrador activo puede elegir sucursal',
    $rosa->puedeElegirTienda() && !$luis->puedeElegirTienda() && !$carmen->puedeElegirTienda());
revisar('En la sucursal elegida maneja esa tienda y deja la suya',
    $rosaEnCerro->getIdTienda() === 2 && $rosaEnCerro->puedeAdministrarTienda(2) && !$rosaEnCerro->puedeAdministrarTienda(1)
    && $rosaEnCerro->puedeVerArqueo(2) && $rosaEnCerro->puedeAnularVenta(2) && $rosaEnCerro->puedeAjustarStock(2));
revisar('Su ficha no cambia: la tienda propia sigue siendo Cayma (y elegir dos veces no la pierde)',
    $rosaEnCerro->getIdTiendaPropia() === 1 && $rosaEnCerro->enTienda(3)->getIdTiendaPropia() === 1
    && $rosa->getIdTienda() === 1 && $rosa->getIdTiendaPropia() === 1 && $rosaEnCerro instanceof Administrador);

echo "Ventas\n";
$antes = $stock(1, 8);
$r = $ventas->registrar($luis, 'B', '', [
    ['id_producto' => '8', 'cantidad' => '1.250'],
    ['id_producto' => '1', 'cantidad' => '2'],
], ['efectivo_recibido' => '20']);
revisar('Boleta en efectivo: ' . $r['serie'] . '-' . $r['numero'], $r['serie'] === 'B001' && $r['numero'] === 5);
revisar('Descuenta 1.250 kg de papa en Cayma', $stock(1, 8) === number_format((float) $antes - 1.25, 3, '.', ''));
revisar('Total = 1.25 x 2.50 + 2 x 4.50 = 12.13', valor($db, 'SELECT total FROM venta WHERE id_serie = 1 AND numero_correlativo = 5') === '12.13');
revisar('Vuelto 7.87', valor($db, 'SELECT e.vuelto FROM efectivo e JOIN pago_venta p ON p.id_pago = e.id_pago JOIN venta v ON v.id_venta = p.id_venta WHERE v.id_serie = 1 AND v.numero_correlativo = 5') === '7.87');

$r = $ventas->registrar($luis, 'F', '20123456789', [['id_producto' => '4', 'cantidad' => '3']], [
    'tarjeta_monto' => '20', 'tarjeta_tipo' => 'DEBITO', 'tarjeta_marca' => 'Visa', 'tarjeta_ultimos4' => '9876', 'tarjeta_autorizacion' => 'Z9',
    'sip_monto' => '5.50', 'sip_modalidad' => 'QR', 'sip_operacion' => 'SIP-PRUEBA-0100',
    'efectivo_recibido' => '10',
]);
revisar('Factura con pago mixto tarjeta + Sip + efectivo: ' . $r['serie'] . '-' . $r['numero'], $r['serie'] === 'F001' && $r['numero'] === 4);

$r = $ventas->registrar($luis, 'B', '12345678', [['id_producto' => '11', 'cantidad' => '1']], ['efectivo_recibido' => '7.90']);
revisar('Boleta con DNI nuevo crea el cliente', valor($db, 'SELECT COUNT(*) FROM cliente WHERE dni = ?', ['12345678']) === '1');

$n = $ventasTotales();
$papa = $stock(1, 8);
rechaza('Stock insuficiente', fn() => $ventas->registrar($luis, 'B', '', [['id_producto' => '8', 'cantidad' => '9999']], ['efectivo_recibido' => '99999']));
revisar('...y no guardó nada (rollback)', $ventasTotales() === $n && $stock(1, 8) === $papa);
rechaza('Factura sin RUC', fn() => $ventas->registrar($luis, 'F', '45678912', [['id_producto' => '1', 'cantidad' => '1']], ['efectivo_recibido' => '10']));
rechaza('Cantidad decimal en producto por unidad', fn() => $ventas->registrar($luis, 'B', '', [['id_producto' => '1', 'cantidad' => '1.5']], ['efectivo_recibido' => '10']));
rechaza('Efectivo que no alcanza', fn() => $ventas->registrar($luis, 'B', '', [['id_producto' => '1', 'cantidad' => '1']], ['efectivo_recibido' => '1']));
rechaza('Tarjeta que pasa el total', fn() => $ventas->registrar($luis, 'B', '', [['id_producto' => '1', 'cantidad' => '1']], [
    'tarjeta_monto' => '50', 'tarjeta_tipo' => 'CREDITO', 'tarjeta_marca' => 'VISA', 'tarjeta_ultimos4' => '1111', 'tarjeta_autorizacion' => 'A1']));
rechaza('Voucher de tarjeta repetido (misma tarjeta y autorización de otra venta)', fn() => $ventas->registrar($luis, 'B', '', [['id_producto' => '1', 'cantidad' => '1']], [
    'tarjeta_monto' => '1', 'tarjeta_tipo' => 'DEBITO', 'tarjeta_marca' => 'VISA', 'tarjeta_ultimos4' => '9876', 'tarjeta_autorizacion' => 'z9',
    'efectivo_recibido' => '10']));
rechaza('Código Sip repetido', fn() => $ventas->registrar($luis, 'B', '', [['id_producto' => '1', 'cantidad' => '1']], [
    'sip_monto' => '4.50', 'sip_modalidad' => 'QR', 'sip_operacion' => 'SIP-EJEMPLO-0001']));
rechaza('Cajero sin turno abierto', fn() => $ventas->registrar($miguel, 'B', '', [['id_producto' => '1', 'cantidad' => '1']], ['efectivo_recibido' => '10']));
rechaza('Un administrador no vende', fn() => $ventas->registrar($rosa, 'B', '', [['id_producto' => '1', 'cantidad' => '1']], ['efectivo_recibido' => '10']));
revisar('Ningún rechazo guardó una venta', $ventasTotales() === $n);

echo "Anulación\n";
$idVenta = (int) valor($db, 'SELECT id_venta FROM venta WHERE id_serie = 1 AND numero_correlativo = 5');
rechaza('Administrador de otra tienda', fn() => $ventas->anular($jorge, $idVenta, 'Prueba'));
rechaza('Un cajero no anula', fn() => $ventas->anular($luis, $idVenta, 'Prueba'));
$papa = $stock(1, 8);
$ventas->anular($rosa, $idVenta, 'Prueba de anulación');
revisar('Venta ANULADA y pagos ANULADOS', valor($db, 'SELECT estado FROM venta WHERE id_venta = ?', [$idVenta]) === 'ANULADA'
    && valor($db, "SELECT COUNT(*) FROM pago_venta WHERE id_venta = ? AND estado = 'VALIDO'", [$idVenta]) === '0');
revisar('Devuelve 1.250 kg de papa', $stock(1, 8) === number_format((float) $papa + 1.25, 3, '.', ''));
rechaza('No se anula dos veces', fn() => $ventas->anular($rosa, $idVenta, 'Otra vez'));
// Cuadre de caja: el efectivo de un turno ya cerrado no puede salir de su arqueo
$cerradaEfectivo = (int) valor($db, "SELECT id_venta FROM venta WHERE id_serie = 1 AND numero_correlativo = 1");
rechaza('No se anula una venta en efectivo de un turno ya cerrado (descuadraría el arqueo)', fn() => $ventas->anular($rosa, $cerradaEfectivo, 'Prueba'));
revisar('...y la venta sigue EMITIDA con su pago', valor($db, 'SELECT estado FROM venta WHERE id_venta = ?', [$cerradaEfectivo]) === 'EMITIDA'
    && valor($db, "SELECT COUNT(*) FROM pago_venta WHERE id_venta = ? AND estado = 'VALIDO'", [$cerradaEfectivo]) === '1');

echo "Traslado y merma\n";
$origen = $stock(1, 2);
$destino = $stock(2, 2);
$id = $inventario->trasladar($carmen, 2, [['id_producto' => '2', 'cantidad' => '10']], 'Reposición de arroz');
revisar("Traslado $id Cayma -> Cerro Colorado", $stock(1, 2) === number_format((float) $origen - 10, 3, '.', '')
    && $stock(2, 2) === number_format((float) $destino + 10, 3, '.', ''));
rechaza('Traslado a la misma tienda', fn() => $inventario->trasladar($carmen, 1, [['id_producto' => '2', 'cantidad' => '1']], null));
rechaza('Un cajero no traslada', fn() => $inventario->trasladar($luis, 2, [['id_producto' => '2', 'cantidad' => '1']], null));
$leche = $stock(1, 1);
$id = $inventario->registrarMerma($carmen, 'VENCIDO', 'Prueba', [['id_producto' => '1', 'cantidad' => '2']]);
revisar("Merma $id descuenta 2 leches", $stock(1, 1) === number_format((float) $leche - 2, 3, '.', ''));
rechaza('Motivo de merma no válido', fn() => $inventario->registrarMerma($carmen, 'PERDIDO', null, [['id_producto' => '1', 'cantidad' => '1']]));

echo "Ingreso de mercadería\n";
$ingresosAntes = (int) valor($db, 'SELECT COUNT(*) FROM ingreso_mercaderia');   // operaciones.sql trae 3 de ejemplo
$documento = ['tipo' => 'FACTURA', 'numero' => 'f001-00012345', 'fecha' => date('Y-m-d'), 'ruc' => '20100055237',
    'razon_social' => 'Distribuidora de Prueba S.A.C.', 'observacion' => 'Pedido semanal'];
$lecheAntes = $stock(1, 1);
$papaAntes = $stock(1, 8);
$idIngreso = $inventario->registrarIngreso($carmen, $documento, [
    ['id_producto' => '1', 'cantidad' => '24', 'costo' => '3.60'],
    ['id_producto' => '8', 'cantidad' => '10.5', 'costo' => '1.95', 'vence' => date('Y-m-d', strtotime('+5 days'))],
    ['id_producto' => '', 'cantidad' => '', 'costo' => ''],
]);
revisar("Ingreso $idIngreso suma 24 leches y 10.5 kg de papa", $stock(1, 1) === number_format((float) $lecheAntes + 24, 3, '.', '')
    && $stock(1, 8) === number_format((float) $papaAntes + 10.5, 3, '.', ''));
revisar('...con el número en mayúsculas y total = 24 x 3.60 + 10.5 x 1.95 = 106.88', valor($db,
    'SELECT CONCAT(numero_documento, \'/\', total) FROM ingreso_mercaderia WHERE id_ingreso = ?', [$idIngreso]) === 'F001-00012345/106.88');
revisar('...el kardex anota 2 entradas INGRESO con el documento', valor($db,
    'SELECT COUNT(*) FROM movimiento_stock WHERE id_ingreso = ? AND id_tipo_movimiento = 8 AND observacion = ?',
    [$idIngreso, 'FACTURA F001-00012345']) === '2');
revisar('...y la auditoría el ingreso', (int) valor($db,
    "SELECT COUNT(*) FROM auditoria WHERE accion = 'INGRESO_REGISTRADO' AND detalle LIKE ?", ["Ingreso $idIngreso: FACTURA F001-00012345%S/ 106.88"]) === 1);
rechaza('El mismo documento dos veces', fn() => $inventario->registrarIngreso($carmen, $documento,
    [['id_producto' => '1', 'cantidad' => '1', 'costo' => '3.60']]));
rechaza('Un cajero no registra ingresos', fn() => $inventario->registrarIngreso($luis, ['numero' => 'F001-1'] + $documento,
    [['id_producto' => '1', 'cantidad' => '1', 'costo' => '3.60']]));
rechaza('RUC de 10 dígitos', fn() => $inventario->registrarIngreso($carmen, ['ruc' => '2010005523', 'numero' => 'F001-3'] + $documento,
    [['id_producto' => '1', 'cantidad' => '1', 'costo' => '3.60']]));
rechaza('Fecha del documento futura', fn() => $inventario->registrarIngreso($carmen,
    ['fecha' => date('Y-m-d', strtotime('+1 day')), 'numero' => 'F001-4'] + $documento, [['id_producto' => '1', 'cantidad' => '1', 'costo' => '3.60']]));
rechaza('Número sin serie', fn() => $inventario->registrarIngreso($carmen, ['numero' => '12345'] + $documento,
    [['id_producto' => '1', 'cantidad' => '1', 'costo' => '3.60']]));
rechaza('Costo con 3 decimales', fn() => $inventario->registrarIngreso($carmen, ['numero' => 'F001-5'] + $documento,
    [['id_producto' => '1', 'cantidad' => '1', 'costo' => '3.605']]));
rechaza('Un producto en dos líneas', fn() => $inventario->registrarIngreso($carmen, ['numero' => 'F001-6'] + $documento,
    [['id_producto' => '1', 'cantidad' => '1', 'costo' => '3.60'], ['id_producto' => '1', 'cantidad' => '2', 'costo' => '3.50']]));
rechaza('Media leche (se vende por unidad)', fn() => $inventario->registrarIngreso($carmen, ['numero' => 'F001-7'] + $documento,
    [['id_producto' => '1', 'cantidad' => '0.5', 'costo' => '3.60']]));
rechaza('Un lote que ya venció', fn() => $inventario->registrarIngreso($carmen, ['numero' => 'F001-8'] + $documento,
    [['id_producto' => '1', 'cantidad' => '1', 'costo' => '3.60', 'vence' => date('Y-m-d', strtotime('-1 day'))]]));
rechaza('Fecha de vencimiento que no existe', fn() => $inventario->registrarIngreso($carmen, ['numero' => 'F001-9'] + $documento,
    [['id_producto' => '1', 'cantidad' => '1', 'costo' => '3.60', 'vence' => '2027-02-30']]));
revisar('...los rechazados no dejaron ingreso ni stock', (int) valor($db, 'SELECT COUNT(*) FROM ingreso_mercaderia') === $ingresosAntes + 1
    && $stock(1, 1) === number_format((float) $lecheAntes + 24, 3, '.', ''));
$ingresosRepo = new IngresoRepository($db);
$detalle = $ingresosRepo->detalle($idIngreso);
revisar('IngresoRepository devuelve la cabecera y su detalle', $ingresosRepo->contar(1) === (int) valor($db, 'SELECT COUNT(*) FROM ingreso_mercaderia WHERE id_tienda = 1')
    && $ingresosRepo->pagina(1, 25, 0)[0]->documento() === 'FACTURA F001-00012345'
    && count($detalle) === 2 && $detalle[1]->getCostoTotal() === 20.48
    && $detalle[0]->getFechaVencimiento() === null && $detalle[1]->getFechaVencimiento() === date('Y-m-d', strtotime('+5 days')));

$fideosAntes = $stock(1, 2);
$idIngresoAdmin = $inventario->registrarIngreso($rosa, ['numero' => 'F001-2'] + $documento, [['id_producto' => '2', 'cantidad' => '2', 'costo' => '2.10']]);
revisar('El administrador también recibe mercadería de su tienda', $idIngresoAdmin > 0
    && $stock(1, 2) === number_format((float) $fideosAntes + 2, 3, '.', ''));

echo "Por vencer\n";
$lotes = $ingresosRepo->lotesConVencimiento(1);
$porVencer = [];
foreach ($lotes as $idProducto => $datos) {
    foreach (Vencimiento::porVencer(Vencimiento::repartirStock($datos['lotes'], $datos['stock']), date('Y-m-d'), 30) as $lote) {
        $porVencer[] = $idProducto . '/' . $lote['id_ingreso'] . '/' . $lote['dias'];
    }
}
revisar('Leche, plátano, yogurt y la papa recién recibida tienen lotes con fecha', array_diff([1, 8, 9, 11], array_keys($lotes)) === []);
revisar('Por vencer en 30 días: leche vencida, plátano, yogurt (dos lotes) y la papa: ' . implode(' ', $porVencer),
    in_array('1/1/-1', $porVencer, true) && in_array('9/3/2', $porVencer, true) && in_array('11/2/4', $porVencer, true)
    && in_array('11/3/25', $porVencer, true) && in_array("8/$idIngreso/5", $porVencer, true) && count($porVencer) === 5);

echo "Caja\n";
rechaza('Abrir con un turno ya abierto', fn() => $caja->abrirTurno($luis, 2, '100'));
rechaza('Abrir una caja de otra tienda', fn() => $caja->abrirTurno($miguel, 1, '100'));
$idTurno = $caja->abrirTurno($miguel, 6, '150.00');
revisar("Miguel abre el turno $idTurno en la caja 2 de Yanahuara", valor($db, 'SELECT estado FROM caja_turno WHERE id_caja_turno = ?', [$idTurno]) === 'ABIERTO');
$ventas->registrar($miguel, 'B', '', [['id_producto' => '3', 'cantidad' => '2']], ['efectivo_recibido' => '20']);
$caja->cerrarTurno($miguel, '163.00');
revisar('Cierra con 150 + 13 cobrados = 163.00', valor($db, 'SELECT monto_cierre FROM caja_turno WHERE id_caja_turno = ?', [$idTurno]) === '163.00');
rechaza('Cerrar sin turno abierto', fn() => $caja->cerrarTurno($miguel, '0'));
$arqueo = fn(int $turno, string $tipo): string => valor($db,
    'SELECT CONCAT(monto_contado, \'/\', monto_esperado) FROM arqueo_caja WHERE id_caja_turno = ? AND tipo = ?', [$turno, $tipo]);
revisar('Guarda el esperado 163.00 y la diferencia 0.00 (caja_apertura.sql)',
    valor($db, 'SELECT CONCAT(monto_esperado, \'/\', diferencia) FROM caja_turno WHERE id_caja_turno = ?', [$idTurno]) === '163.00/0.00');
revisar('Arqueo de apertura: contó 150.00 con fondo fijo 100.00', $arqueo($idTurno, 'APERTURA') === '150.00/100.00');
revisar('Arqueo de cierre: contó 163.00 y se esperaba 163.00', $arqueo($idTurno, 'CIERRE') === '163.00/163.00');
$idTurno2 = $caja->abrirTurno($miguel, 6, '100.00');
$caja->cerrarTurno($miguel, '90.50');
revisar('Un faltante queda negativo: 90.50 - 100.00 = -9.50',
    valor($db, 'SELECT diferencia FROM caja_turno WHERE id_caja_turno = ?', [$idTurno2]) === '-9.50');
$lucia = $empleados->buscarPorId(7);  // ADMINISTRADOR Yanahuara
$estadoTurno = fn(int $turno): string => valor($db, 'SELECT estado FROM caja_turno WHERE id_caja_turno = ?', [$turno]);
revisar('Faltan 9.50 y la tolerancia es 1.00: el cierre queda OBSERVADO', $estadoTurno($idTurno2) === 'OBSERVADO');
revisar('El cierre observado quedó en la auditoría', (int) valor($db, "SELECT COUNT(*) FROM auditoria WHERE accion = 'CAJA_CIERRE'") >= 1);
rechaza('Con un cierre observado no abre otro turno', fn() => $caja->abrirTurno($miguel, 6, '100.00'));
rechaza('El cajero no revisa su propio cierre', fn() => $caja->revisarCierre($miguel, $idTurno2, 'APROBAR', 'Visto'));
rechaza('Un administrador de otra tienda no lo revisa', fn() => $caja->revisarCierre($rosa, $idTurno2, 'APROBAR', 'Visto'));
rechaza('Revisar sin comentario', fn() => $caja->revisarCierre($lucia, $idTurno2, 'RECONTEO', ''));
rechaza('Una decisión que no existe', fn() => $caja->revisarCierre($lucia, $idTurno2, 'BORRAR', 'Prueba'));
// Si Miguel ya no esta activo, no puede volver a contar: el reconteo dejaria el turno sin cerrar nunca
$db->exec("UPDATE empleado SET estado = 'INACTIVO' WHERE id_empleado = 8");
rechaza('Con el cajero inactivo no se pide reconteo (solo aprobar)', fn() => $caja->revisarCierre($lucia, $idTurno2, 'RECONTEO', 'Cuenta otra vez'));
$db->exec("UPDATE empleado SET estado = 'ACTIVO' WHERE id_empleado = 8");
$caja->revisarCierre($lucia, $idTurno2, 'RECONTEO', 'Cuenta de nuevo las monedas');
revisar('Lucía pide reconteo: queda RECONTEO con quién y cuándo lo revisó', $estadoTurno($idTurno2) === 'RECONTEO'
    && valor($db, 'SELECT id_empleado_revisa FROM caja_turno WHERE id_caja_turno = ?', [$idTurno2]) === '7');
rechaza('En reconteo tampoco abre otro turno', fn() => $caja->abrirTurno($miguel, 6, '100.00'));
rechaza('No se desactiva a un cajero que debe volver a contar', fn() => $personal->editar($lucia, 8, 'Miguel', 'Núñez Cuadros', 2, 'INACTIVO'));
rechaza('...ni se le cambia el rol', fn() => $personal->editar($lucia, 8, 'Miguel', 'Núñez Cuadros', 3, 'ACTIVO'));
rechaza('Un conteo con una denominación que no existe', fn() => $caja->recontarTurno($miguel, '', ['3.00' => '1']));
rechaza('Un conteo con cantidad negativa', fn() => $caja->recontarTurno($miguel, '', ['100.00' => '-1']));
// El total escrito no manda: el servidor suma el desglose (1 x 50 + 2 x 20 + 1 x 10 = 100.00)
$resultado = $caja->recontarTurno($miguel, '1.00', ['50.00' => '1', '20.00' => '2', '10.00' => '1', '0.50' => '']);
revisar('El reconteo suma los billetes (100.00) y el turno cuadra: CERRADO con diferencia 0.00', $resultado['estado'] === 'CERRADO'
    && valor($db, 'SELECT CONCAT(estado, \'/\', monto_cierre, \'/\', diferencia) FROM caja_turno WHERE id_caja_turno = ?', [$idTurno2]) === 'CERRADO/100.00/0.00');
revisar('El desglose del reconteo quedó en arqueo_detalle (3 filas)', (int) valor($db,
    'SELECT COUNT(*) FROM arqueo_detalle d JOIN arqueo_caja a ON a.id_arqueo = d.id_arqueo WHERE a.id_caja_turno = ?', [$idTurno2]) === 3);
revisar('El reconteo quedó en la auditoría', (int) valor($db, "SELECT COUNT(*) FROM auditoria WHERE accion IN ('CAJA_RECONTEO_PEDIDO', 'CAJA_RECONTEO')") === 2);

// Apertura con conteo por billetes contra el fondo fijo de la caja
rechaza('Abrir con un monto que no cuadra con el fondo y sin motivo (web)', fn() => $caja->abrirTurno($miguel, 6, '80.00', [], null, true));
$idTurno3 = $caja->abrirTurno($miguel, 6, '', ['100.00' => '1'], null, true);
revisar('Abre contando un billete de 100 (cuadra con el fondo fijo)', $arqueo($idTurno3, 'APERTURA') === '100.00/100.00'
    && valor($db, 'SELECT monto_inicial FROM caja_turno WHERE id_caja_turno = ?', [$idTurno3]) === '100.00');
// Pausa: nadie vende con el turno hasta reanudar
$caja->pausarTurno($miguel, true);
rechaza('Con la caja en pausa no se vende', fn() => $ventas->registrar($miguel, 'B', '', [['id_producto' => '3', 'cantidad' => '1']], ['efectivo_recibido' => '10']));
$caja->pausarTurno($miguel, false);
revisar('Al reanudar en_pausa_desde vuelve a NULL', valor($db, 'SELECT COALESCE(en_pausa_desde, \'-\') FROM caja_turno WHERE id_caja_turno = ?', [$idTurno3]) === '-');
// Arqueo sorpresa del administrador
rechaza('El cajero no se hace arqueo sorpresa', fn() => $caja->arqueoSorpresa($miguel, $idTurno3, '100.00'));
rechaza('Rosa no hace arqueo en otra tienda', fn() => $caja->arqueoSorpresa($rosa, $idTurno3, '100.00'));
$sorpresa = $caja->arqueoSorpresa($lucia, $idTurno3, '99.00', [], 'Revisión de la tarde');
revisar('Arqueo sorpresa: contó 99.00 frente a 100.00 esperado', $sorpresa['diferencia'] === -100 && $arqueo($idTurno3, 'PARCIAL') === '99.00/100.00');
$cierre3 = $caja->cerrarTurno($miguel, '100.50');
revisar('Sobran 0.50, dentro de la tolerancia: CERRADO', $cierre3['estado'] === 'CERRADO' && $estadoTurno($idTurno3) === 'CERRADO');
// Reglas de la tienda y fondo fijo
rechaza('El cajero no cambia las reglas', fn() => $caja->guardarReglas($miguel, '1.00', '500.00', true, '10', []));
rechaza('Tolerancia mayor a 100', fn() => $caja->guardarReglas($lucia, '150.00', '500.00', true, '10', []));
rechaza('Turnos de 30 horas', fn() => $caja->guardarReglas($lucia, '1.00', '500.00', true, '30', []));
rechaza('Fondo de una caja de otra tienda', fn() => $caja->guardarReglas($lucia, '1.00', '500.00', true, '10', [1 => '200.00']));
$caja->guardarReglas($lucia, '2.50', '800.00', false, '12', [6 => '120.00']);
revisar('Lucía guarda tolerancia 2.50, tope 800, cierre a la vista, 12 h y fondo 120 en la caja 6',
    valor($db, 'SELECT CONCAT(tolerancia, \'/\', tope_gaveta, \'/\', cierre_ciego, \'/\', horas_maximas) FROM regla_caja WHERE id_tienda = 3') === '2.50/800.00/N/12'
    && valor($db, 'SELECT fondo_fijo FROM caja WHERE id_caja = 6') === '120.00');
$caja->guardarReglas($lucia, '1.00', '500.00', true, '10', [6 => '100.00']);
$rechazaBase = function (string $caso, string $sql) use ($db): void {
    try {
        $db->exec($sql);
        revisar($caso . ' (la base no la rechazó)', false);
    } catch (PDOException $ex) {
        revisar($caso . ' -> la base la rechaza', true);
    }
};
$rechazaBase('Segundo turno ABIERTO en la caja 1 de Cayma', "INSERT INTO caja_turno (monto_inicial, estado, id_caja, id_empleado) VALUES (100, 'ABIERTO', 1, 8)");
$rechazaBase('Diferencia que no es cierre - esperado', "UPDATE caja_turno SET diferencia = 5 WHERE id_caja_turno = $idTurno");
$rechazaBase('Arqueo de un tipo que no existe', "INSERT INTO arqueo_caja (tipo, monto_contado, monto_esperado, id_caja_turno, id_empleado) VALUES ('OTRO', 1, 1, $idTurno, 8)");
$rechazaBase('Billete de 30 soles en el desglose', "INSERT INTO arqueo_detalle (id_arqueo, denominacion, cantidad) VALUES (1, 30.00, 1)");

echo "Devoluciones\n";
// Venta B001-7 de Luis: 1 yogurt (7.90). Y una nueva con 3 leches y 2 kg de plátano para devolver parte.
$r = $ventas->registrar($luis, 'B', '', [['id_producto' => '1', 'cantidad' => '3'], ['id_producto' => '9', 'cantidad' => '2']], ['efectivo_recibido' => '50']);
$idVentaDev = (int) valor($db, 'SELECT id_venta FROM venta WHERE id_serie = 1 AND numero_correlativo = ?', [$r['numero']]);
$precioPlatano = valor($db, 'SELECT precio_unitario FROM detalle_venta WHERE id_venta = ? AND id_producto = 9', [$idVentaDev]);
$lecheAntes = $stock(1, 1);
$platanoAntes = $stock(1, 9);
rechaza('Un administrador no registra devoluciones', fn() => $ventas->devolver($rosa, $idVentaDev, [['id_producto' => '1', 'cantidad' => '1']], 'Prueba'));
rechaza('Un cajero de otra tienda tampoco', fn() => $ventas->devolver($miguel, $idVentaDev, [['id_producto' => '1', 'cantidad' => '1']], 'Prueba'));
rechaza('Sin motivo', fn() => $ventas->devolver($luis, $idVentaDev, [['id_producto' => '1', 'cantidad' => '1']], ' '));
rechaza('Más de lo vendido', fn() => $ventas->devolver($luis, $idVentaDev, [['id_producto' => '1', 'cantidad' => '4']], 'Prueba'));
rechaza('Un producto que no estaba en la venta', fn() => $ventas->devolver($luis, $idVentaDev, [['id_producto' => '2', 'cantidad' => '1']], 'Prueba'));
rechaza('Media leche', fn() => $ventas->devolver($luis, $idVentaDev, [['id_producto' => '1', 'cantidad' => '0.5']], 'Prueba'));
rechaza('Nada que devolver', fn() => $ventas->devolver($luis, $idVentaDev, [['id_producto' => '1', 'cantidad' => '']], 'Prueba'));
$devuelto = $ventas->devolver($luis, $idVentaDev, [['id_producto' => '1', 'cantidad' => '1'], ['id_producto' => '9', 'cantidad' => '0.500'],
    ['id_producto' => '', 'cantidad' => '']], 'Leche abollada y plátano maduro');
$esperado = 450 + Cantidad::importe(500, Dinero::deTexto($precioPlatano));
revisar('Devuelve 1 leche y 0.5 kg de plátano al precio de la venta: S/ ' . Dinero::aTexto($devuelto), $devuelto === $esperado);
revisar('...el stock vuelve con el kardex DEVOLUCION DE VENTA', $stock(1, 1) === number_format((float) $lecheAntes + 1, 3, '.', '')
    && $stock(1, 9) === number_format((float) $platanoAntes + 0.5, 3, '.', '')
    && valor($db, 'SELECT COUNT(*) FROM movimiento_stock WHERE id_devolucion IS NOT NULL AND id_tipo_movimiento = 9 AND observacion LIKE ?',
        ['Devolución de B001-%']) === '2');
$kardexLeche = (new InventarioRepository($db))->kardex(1, 1, 1);
revisar('El kardex lee el movimiento con su origen: ' . $kardexLeche[0]->origen(), str_starts_with($kardexLeche[0]->origen(), 'Devolución '));
rechaza('Otra vez 3 leches: solo quedan 2', fn() => $ventas->devolver($luis, $idVentaDev, [['id_producto' => '1', 'cantidad' => '3']], 'Prueba'));
$ventas->devolver($luis, $idVentaDev, [['id_producto' => '1', 'cantidad' => '2']], 'Cambió de opinión');
$ventasRepo = new VentaRepository($db);
revisar('VentaRepository lista las 2 devoluciones y lo devuelto por producto', count($ventasRepo->devolucionesDeVenta($idVentaDev)) === 2
    && $ventasRepo->cantidadesDevueltas($idVentaDev) === [1 => 3000, 9 => 500]);
rechaza('Con devoluciones ya no se anula', fn() => $ventas->anular($rosa, $idVentaDev, 'Prueba'));
$idAnulada = (int) valor($db, "SELECT id_venta FROM venta WHERE estado = 'ANULADA' LIMIT 1");
rechaza('Una venta anulada no se devuelve', fn() => $ventas->devolver($luis, $idAnulada, [['id_producto' => '1', 'cantidad' => '1']], 'Prueba'));
$idVieja = (int) valor($db, "SELECT v.id_venta FROM venta v JOIN serie_comprobante s ON s.id_serie = v.id_serie
    WHERE s.id_tienda = 1 AND v.estado = 'EMITIDA' AND v.fecha_hora < NOW() - INTERVAL 7 DAY LIMIT 1");
if ($idVieja > 0) {
    rechaza('Una venta de hace más de 7 días', fn() => $ventas->devolver($luis, $idVieja, [['id_producto' => '1', 'cantidad' => '1']], 'Prueba'));
}
revisar('Las devoluciones quedaron en la auditoría', (int) valor($db, "SELECT COUNT(*) FROM auditoria WHERE accion = 'DEVOLUCION_REGISTRADA'") === 2);

echo "Retiros e ingresos de caja\n";
$cajasRepo = new CajaRepository($db);
$turnoLuis = $cajasRepo->turnoAbiertoDe(2)->getIdCajaTurno();
$fila = fn(): array => array_values(array_filter($cajasRepo->arqueoDeTienda(1), fn(array $f): bool => $f['turno']->getIdCajaTurno() === $turnoLuis))[0];
$esperadoAntes = $fila()['esperado'];
rechaza('Un almacenero no saca efectivo', fn() => $caja->registrarMovimiento($carmen, 'RETIRO', '10', 'Prueba'));
rechaza('Tipo que no existe', fn() => $caja->registrarMovimiento($luis, 'PRESTAMO', '10', 'Prueba'));
rechaza('Monto 0', fn() => $caja->registrarMovimiento($luis, 'RETIRO', '0', 'Prueba'));
rechaza('Sin motivo', fn() => $caja->registrarMovimiento($luis, 'RETIRO', '10', ''));
rechaza('Retirar más de lo que hay en la gaveta', fn() => $caja->registrarMovimiento($luis, 'RETIRO', '99999', 'Prueba'));
rechaza('Categoría de ingreso en un retiro', fn() => $caja->registrarMovimiento($luis, 'RETIRO', '10', 'Prueba', 'SENCILLO'));
rechaza('Categoría que no existe', fn() => $caja->registrarMovimiento($luis, 'INGRESO', '10', 'Prueba', 'PRESTAMO'));
rechaza('Comprobante con caracteres raros', fn() => $caja->registrarMovimiento($luis, 'RETIRO', '10', 'Prueba', 'GASTO', "B001'--"));
rechaza('Comprobante de más de 20 caracteres', fn() => $caja->registrarMovimiento($luis, 'RETIRO', '10', 'Prueba', 'GASTO', str_repeat('9', 21)));
$caja->registrarMovimiento($luis, 'RETIRO', '50.00', 'Depósito al banco', 'DEPOSITO', 'dep-00123');
$caja->registrarMovimiento($luis, 'INGRESO', '20.00', 'Sencillo del almacén', 'SENCILLO');
$movsLuis = $cajasRepo->movimientosDeTurno($turnoLuis);
revisar('El arqueo resta el retiro y suma el ingreso: esperado baja 30.00', round($esperadoAntes - $fila()['esperado'], 2) === 30.0
    && $fila()['retiros'] === 50.0 && $fila()['ingresos'] === 20.0 && count($movsLuis) === 2);
revisar('Cada movimiento guarda su categoría y su comprobante (en mayúsculas)', $movsLuis[0]['categoria'] === 'DEPOSITO'
    && $movsLuis[0]['comprobante'] === 'DEP-00123' && $movsLuis[1]['categoria'] === 'SENCILLO' && $movsLuis[1]['comprobante'] === null);
$errorCheck = '';
try {
    $db->exec("INSERT INTO movimiento_caja (tipo, categoria, monto, motivo, id_caja_turno, id_empleado) VALUES ('INGRESO', 'PROVEEDOR', 1, 'x', $turnoLuis, 2)");
} catch (PDOException $ex) {
    $errorCheck = $ex->getMessage();
}
revisar('La base rechaza un ingreso con categoría de retiro (ck_movimiento_caja_categoria)', str_contains($errorCheck, 'ck_movimiento_caja_categoria'));
revisar('...y el arqueo ya restaba las devoluciones', $fila()['devoluciones'] === round(($devuelto + 900) / 100, 2));
revisar('Retiro e ingreso quedaron en la auditoría', (int) valor($db,
    "SELECT COUNT(*) FROM auditoria WHERE accion IN ('CAJA_RETIRO', 'CAJA_INGRESO')") === 2);
rechaza('Miguel sin turno no mueve efectivo', fn() => $caja->registrarMovimiento($miguel, 'INGRESO', '10', 'Prueba'));

echo "Catálogo y stock mínimo\n";
$producto = [
    'nombre' => 'Galletas Soda Mass 6 pack', 'codigo' => '7750000000103', 'precio' => '3.50', 'por_peso' => 'N',
    'categoria' => '1', 'marca' => '1', 'unidad' => '1',
];
$idProducto = $catalogo->crear($rosa, $producto);
revisar("Rosa crea el producto $idProducto con stock 0 en las 3 tiendas",
    valor($db, 'SELECT COUNT(*) FROM stock WHERE id_producto = ? AND cantidad_actual = 0', [$idProducto]) === '3');
rechaza('Código de barras repetido', fn() => $catalogo->crear($rosa, ['codigo' => '7750000000011'] + $producto));
rechaza('Producto por unidad sin código', fn() => $catalogo->crear($rosa, ['codigo' => ''] + $producto));
rechaza('Producto al peso que no usa kg', fn() => $catalogo->crear($rosa, ['por_peso' => 'S', 'codigo' => ''] + $producto));
rechaza('Precio 0', fn() => $catalogo->crear($rosa, ['precio' => '0', 'codigo' => '7750000000110'] + $producto));
rechaza('Un cajero no cambia el catálogo', fn() => $catalogo->crear($luis, ['codigo' => '7750000000127'] + $producto));
rechaza('El almacenero no usa el formulario del catálogo de la web', fn() => $catalogo->crear($carmen, ['codigo' => '7750000000134'] + $producto));
$escaneado = ['nombre' => 'Chifles Inka Chips 150 g', 'codigo' => '7750000000141', 'precio' => '5.20', 'categoria' => '1',
    'marca' => '', 'marca_nombre' => 'Marca Escaneada Prueba', 'unidad' => '1'];
$idEscaneado = $catalogo->crearAlEscanear($carmen, $escaneado);
revisar("Carmen registra al escanear el producto $idEscaneado, con stock 0 en las 3 tiendas",
    valor($db, 'SELECT COUNT(*) FROM stock WHERE id_producto = ? AND cantidad_actual = 0', [$idEscaneado]) === '3');
revisar('...con la marca nueva que escribió y siempre por unidad', valor($db,
    'SELECT CONCAT(m.nombre_marca, \'/\', p.se_vende_por_peso) FROM producto p JOIN marca m ON m.id_marca = p.id_marca WHERE p.id_producto = ?',
    [$idEscaneado]) === 'Marca Escaneada Prueba/N');
revisar('...y la auditoría dice quién y que fue desde la app', (int) valor($db,
    "SELECT COUNT(*) FROM auditoria WHERE accion = 'PRODUCTO_CREADO' AND id_empleado = 3 AND detalle LIKE ?", ["$idEscaneado %(app)"]) === 1);
revisar('...y el producto guarda que vino de la app, quién y desde qué sucursal', valor($db,
    'SELECT CONCAT(origen, \'/\', id_empleado_registro, \'/\', id_tienda_registro, \'/\', fecha_registro IS NOT NULL) FROM producto WHERE id_producto = ?',
    [$idEscaneado]) === 'APP/3/1/1'
    && valor($db, 'SELECT CONCAT(origen, \'/\', id_empleado_registro) FROM producto WHERE id_producto = ?', [$idProducto]) === 'WEB/1');
$productosRepo = new ProductoRepository($db);
$desdeApp = $productosRepo->paginaDesdeApp(null, null, null, '', 50, 0);
revisar('La pantalla "Desde la app" lo lista primero, con Carmen y su sucursal', (int) ($desdeApp[0]['id_producto'] ?? 0) === $idEscaneado
    && $desdeApp[0]['empleado'] === valor($db, "SELECT CONCAT(nombres, ' ', apellidos) FROM empleado WHERE id_empleado = 3")
    && (int) $desdeApp[0]['id_tienda'] === 1 && $desdeApp[0]['stock'] === '0.000'
    && !in_array($idProducto, array_map('intval', array_column($desdeApp, 'id_producto')), true));
revisar('...y se filtra por sucursal, fecha y texto (% se busca como letra)',
    $productosRepo->contarDesdeApp(2, null, null, '') === 0 && $productosRepo->contarDesdeApp(1, date('Y-m-d'), date('Y-m-d'), 'chifles inka') === 1
    && $productosRepo->contarDesdeApp(null, null, date('Y-m-d', strtotime('-1 day')), '') === 0
    && $productosRepo->contarDesdeApp(null, null, null, '%') === 0 && $productosRepo->contarDesdeApp(null, null, null, '7750000000141') === 1
    && $productosRepo->resumenDesdeApp(null, null, null, '')['semana'] === $productosRepo->contarDesdeApp(null, null, null, ''));
$otraMarca = (int) valor($db, 'SELECT COUNT(*) FROM marca');
$catalogo->crearAlEscanear($rosa, ['codigo' => '7750000000158', 'nombre' => 'Chifles salados 80 g', 'marca_nombre' => 'marca escaneada prueba'] + $escaneado);
revisar('Una marca escrita que ya existe (sin importar mayúsculas) no se repite', (int) valor($db, 'SELECT COUNT(*) FROM marca') === $otraMarca);
rechaza('Al escanear, el código es obligatorio', fn() => $catalogo->crearAlEscanear($carmen, ['codigo' => ''] + $escaneado));
rechaza('Al escanear, un código que ya existe', fn() => $catalogo->crearAlEscanear($carmen, ['codigo' => '7750000000011'] + $escaneado));
rechaza('Al escanear, no se usa la unidad kg', fn() => $catalogo->crearAlEscanear($carmen, ['codigo' => '7750000000165',
    'unidad' => valor($db, "SELECT id_unidad FROM unidad_medida WHERE abreviatura = 'kg'")] + $escaneado));
$idDelCajero = $catalogo->crearAlEscanear($luis, ['codigo' => '7750000000172', 'nombre' => 'Caramelos de prueba', 'stock_inicial' => '6'] + $escaneado);
revisar('El cajero también lo registra, con lo que hay en su tienda', $stock(1, $idDelCajero) === '6.000'
    && $stock(2, $idDelCajero) === '0.000');
revisar('...y ese stock inicial queda en el kardex como ajuste con su nombre', valor($db,
    'SELECT CONCAT(m.id_tipo_movimiento, \'/\', a.motivo, \'/\', m.id_empleado) FROM movimiento_stock m
       JOIN ajuste_inventario a ON a.id_ajuste = m.id_ajuste WHERE m.id_producto = ?', [$idDelCajero]) === '6/CONTEO_FISICO/2');
rechaza('Stock inicial con decimales', fn() => $catalogo->crearAlEscanear($luis, ['codigo' => '7750000000189', 'stock_inicial' => '1.5'] + $escaneado));
rechaza('Un empleado inactivo no registra productos', fn() => $catalogo->crearAlEscanear(
    new Cajero(99, 'Ex', 'Cajero', '70000097', 'INACTIVO', 1), ['codigo' => '7750000000196'] + $escaneado));
$catalogo->editar($rosa, 1, [
    'nombre' => 'Leche evaporada Gloria entera 400 g', 'codigo' => '7750000000011', 'precio' => '4.80',
    'categoria' => '2', 'marca' => '1', 'unidad' => '1',
]);
revisar('Nuevo precio de la leche 4.80', valor($db, 'SELECT precio_venta FROM producto WHERE id_producto = 1') === '4.80');
revisar('...y la venta B001-1 conserva su precio', valor($db,
    'SELECT COUNT(*) FROM detalle_venta d JOIN venta v ON v.id_venta = d.id_venta WHERE v.id_serie = 1 AND v.numero_correlativo = 1 AND d.precio_unitario = 4.80') === '0');
$catalogo->cambiarEstado($rosa, $idProducto, 'DESCONTINUADO');
rechaza('No se vende un producto descontinuado', fn() => $ventas->registrar($luis, 'B', '', [['id_producto' => (string) $idProducto, 'cantidad' => '1']], ['efectivo_recibido' => '10']));
$catalogo->fijarStockMinimo($rosa, 8, '12.5');
revisar('Rosa fija el mínimo de papa en Cayma: 12.500 kg', valor($db, 'SELECT stock_minimo FROM stock WHERE id_tienda = 1 AND id_producto = 8') === '12.500');
rechaza('Mínimo con decimales en producto por unidad', fn() => $catalogo->fijarStockMinimo($rosa, 1, '2.5'));
rechaza('Un almacenero no fija mínimos', fn() => $catalogo->fijarStockMinimo($carmen, 1, '5'));
$catalogo->fijarStockMinimo($rosa, $idProducto, '3');
$catalogo->fijarStockMinimo($rosa, $idProducto, '0.000');
revisar('Mínimo "0.000" se acepta como sin mínimo', valor($db, 'SELECT stock_minimo FROM stock WHERE id_tienda = 1 AND id_producto = ?', [$idProducto]) === '0.000');
$catalogo->crearCategoria($rosa, 'Panadería');
rechaza('Categoría repetida', fn() => $catalogo->crearCategoria($rosa, 'Panadería'));

echo "Importar catálogo en CSV\n";
$productosAntes = (int) valor($db, 'SELECT COUNT(*) FROM producto');
$auditoriaAntes = (int) valor($db, 'SELECT COUNT(*) FROM auditoria');
$inicial = CatalogoCsv::leer((string) file_get_contents(__DIR__ . '/../database/catalogo_inicial.csv'));
$revision = $catalogo->importarCatalogo($rosa, $inicial['filas'], true);
revisar("El catálogo de ejemplo (" . count($inicial['filas']) . " filas) se revisa sin errores ni productos nuevos",
    $inicial['errores'] === [] && $revision['errores'] === [] && $revision['creados'] === 0 && $revision['sinCambios'] >= count($inicial['filas']) - 2);
// La fila 2 escribe "Lacteos" sin tilde, como un CSV viejo: igual encuentra "Lácteos" y no crea otra categoria
$filasCsv = [
    2 => ['codigo_barras' => '7750000000011', 'nombre' => 'Leche evaporada Gloria entera 400 g', 'categoria' => 'Lacteos', 'marca' => 'Gloria', 'unidad' => 'und', 'precio_venta' => '4.90', 'al_peso' => 'N'],
    3 => ['codigo_barras' => '7751234000017', 'nombre' => 'Comida para perro Ricocan adulto 1 kg', 'categoria' => 'Mascotas', 'marca' => 'Ricocan', 'unidad' => 'UND', 'precio_venta' => '9.90', 'al_peso' => 'no'],
    4 => ['codigo_barras' => '', 'nombre' => 'Camote amarillo', 'categoria' => 'Frutas y verduras', 'marca' => 'Sin marca', 'unidad' => 'kg', 'precio_venta' => '2.80', 'al_peso' => 'S'],
];
$revision = $catalogo->importarCatalogo($rosa, $filasCsv, true);
revisar('Solo revisar: cuenta 2 nuevos y 1 precio, pero no guarda nada', $revision['creados'] === 2 && $revision['precios'] === 1
    && $revision['categorias'] === ['Mascotas'] && $revision['marcas'] === ['Ricocan']
    && (int) valor($db, 'SELECT COUNT(*) FROM producto') === $productosAntes && (int) valor($db, 'SELECT COUNT(*) FROM auditoria') === $auditoriaAntes
    && valor($db, "SELECT COUNT(*) FROM categoria WHERE nombre_categoria = 'Mascotas'") === '0');
$conError = $filasCsv + [5 => ['unidad' => 'caja'] + $filasCsv[3], 6 => ['precio_venta' => '5.00'] + $filasCsv[2]];
$revision = $catalogo->importarCatalogo($rosa, $conError, false);
revisar('Un archivo con errores no guarda nada y dice la línea', count($revision['errores']) === 2 && str_starts_with($revision['errores'][0], 'Línea 5:')
    && str_contains($revision['errores'][1], 'ya está en la línea 2')
    && (int) valor($db, 'SELECT COUNT(*) FROM producto') === $productosAntes);
$importado = $catalogo->importarCatalogo($rosa, $filasCsv, false);
revisar('Importar: 2 productos nuevos con stock 0 en las 3 tiendas', $importado['errores'] === [] && $importado['creados'] === 2
    && valor($db, "SELECT COUNT(*) FROM stock s JOIN producto p ON p.id_producto = s.id_producto
                   WHERE p.nombre_producto IN ('Comida para perro Ricocan adulto 1 kg', 'Camote amarillo') AND s.cantidad_actual = 0") === '6'
    && valor($db, "SELECT se_vende_por_peso FROM producto WHERE nombre_producto = 'Camote amarillo' AND codigo_barras IS NULL") === 'S');
revisar('...el precio de la leche pasa a 4.90 y queda en la auditoría', valor($db, 'SELECT precio_venta FROM producto WHERE id_producto = 1') === '4.90'
    && valor($db, "SELECT COUNT(*) FROM auditoria WHERE accion = 'PRECIO_CAMBIADO' AND detalle LIKE '%(CSV)'") === '1'
    && valor($db, "SELECT COUNT(*) FROM auditoria WHERE accion = 'CATALOGO_IMPORTADO'") === '1');
$otraVez = $catalogo->importarCatalogo($rosa, $filasCsv, false);
revisar('Subir el mismo archivo otra vez no duplica nada', $otraVez['creados'] === 0 && $otraVez['sinCambios'] === 3);
revisar('...y "Camote" a granel se encuentra por nombre', (int) valor($db, "SELECT COUNT(*) FROM producto WHERE nombre_producto = 'Camote amarillo'") === 1);
rechaza('Un almacenero no importa el catálogo', fn() => $catalogo->importarCatalogo($carmen, $filasCsv, true));

echo "Fotos de producto\n";
$fotosPrueba = new FotoProducto(sys_get_temp_dir() . '/mass_fotos_integracion_' . getmypid());
$lienzo = imagecreatetruecolor(50, 50);
ob_start();
imagepng($lienzo);
$png = (string) ob_get_clean();
$catalogo->cambiarFoto($rosa, 3, $png, $fotosPrueba);
revisar('Rosa sube la foto de la gaseosa y queda en la auditoría', $fotosPrueba->url(3) !== null
    && valor($db, "SELECT COUNT(*) FROM auditoria WHERE accion = 'PRODUCTO_FOTO' AND detalle LIKE '3 %foto nueva'") === '1');
rechaza('Un cajero no cambia fotos', fn() => $catalogo->cambiarFoto($luis, 3, null, $fotosPrueba));
rechaza('Foto de un producto que no existe', fn() => $catalogo->cambiarFoto($rosa, 99999, $png, $fotosPrueba));
rechaza('Una foto que no es imagen', fn() => $catalogo->cambiarFoto($rosa, 4, 'no soy una foto', $fotosPrueba));
$catalogo->cambiarFoto($rosa, 3, null, $fotosPrueba);
revisar('Quitar la foto la borra y también queda en la auditoría', $fotosPrueba->url(3) === null
    && valor($db, "SELECT COUNT(*) FROM auditoria WHERE accion = 'PRODUCTO_FOTO' AND detalle LIKE '3 %'") === '2');
rechaza('Quitar una foto que no hay', fn() => $catalogo->cambiarFoto($rosa, 3, null, $fotosPrueba));
@rmdir(sys_get_temp_dir() . '/mass_fotos_integracion_' . getmypid());

echo "Personal y claves\n";
$idNuevo = $personal->crear($rosa, 'Diana', 'Torres Lazo', '70000010', 2, 'Caja2026x');
revisar("Rosa registra a la cajera $idNuevo en Cayma", valor($db, 'SELECT id_tienda FROM empleado WHERE id_empleado = ?', [$idNuevo]) === '1');
revisar('...con la clave en hash, no en texto', password_verify('Caja2026x', valor($db, 'SELECT clave_hash FROM empleado WHERE id_empleado = ?', [$idNuevo])));
revisar('...y la cambia en su primer ingreso (la clave la eligió Rosa)', valor($db, 'SELECT debe_cambiar_clave FROM empleado WHERE id_empleado = ?', [$idNuevo]) === 'S');
rechaza('DNI repetido', fn() => $personal->crear($rosa, 'Otra', 'Persona', '70000002', 2, 'Caja2026x'));
rechaza('Clave sin números', fn() => $personal->crear($rosa, 'Otra', 'Persona', '70000011', 2, 'solamenteletras'));
rechaza('Jorge no edita personal de Cayma', fn() => $personal->editar($jorge, 2, 'Luis', 'Cáceres Pinto', 2, 'INACTIVO'));
rechaza('Rosa no se desactiva a sí misma', fn() => $personal->editar($rosa, 1, 'Rosa', 'Quispe Huamán', 1, 'INACTIVO'));
rechaza('No se desactiva a un cajero con turno abierto', fn() => $personal->editar($rosa, 2, 'Luis', 'Cáceres Pinto', 2, 'INACTIVO'));
$personal->editar($rosa, $idNuevo, 'Diana', 'Torres Lazo', 3, 'INACTIVO');
revisar('Diana pasa a almacenera INACTIVA', valor($db, 'SELECT CONCAT(id_rol, estado) FROM empleado WHERE id_empleado = ?', [$idNuevo]) === '3INACTIVO');
rechaza('Luis no se queda con la clave de prueba', fn() => $personal->cambiarMiClave($luis, 'Mass2026!', 'mass2026!', 'mass2026!'));
rechaza('...ni con una que lleva su DNI', fn() => $personal->cambiarMiClave($luis, 'Mass2026!', 'Luis70000002', 'Luis70000002'));
rechaza('...ni con una de las más usadas', fn() => $personal->cambiarMiClave($luis, 'Mass2026!', 'Password123', 'Password123'));
$personal->cambiarMiClave($luis, 'Mass2026!', 'NuevaClave1', 'NuevaClave1');
revisar('Luis cambia su clave', password_verify('NuevaClave1', valor($db, 'SELECT clave_hash FROM empleado WHERE id_empleado = 2')));
rechaza('Cambiar clave con la actual errada', fn() => $personal->cambiarMiClave($luis, 'Mass2026!', 'OtraClave2', 'OtraClave2'));
revisar('...y ya no tiene que cambiarla', valor($db, 'SELECT debe_cambiar_clave FROM empleado WHERE id_empleado = 2') === 'N');
$tokens = new TokenApiRepository($db);
$tokenDeLuis = $tokens->crear(2, 'prueba')['token'];
$versionAntes = (int) valor($db, 'SELECT version_clave FROM empleado WHERE id_empleado = 2');
$versionNueva = $personal->reiniciarClave($rosa, 2, 'Mass2026!');
revisar('Rosa le devuelve una clave a Luis', password_verify('Mass2026!', valor($db, 'SELECT clave_hash FROM empleado WHERE id_empleado = 2')));
revisar('...sube la versión de la clave (cierra sus sesiones web)', $versionNueva === $versionAntes + 1
    && valor($db, 'SELECT version_clave FROM empleado WHERE id_empleado = 2') === (string) $versionNueva);
revisar('...revoca sus tokens de la app', $tokens->empleadoDe($tokenDeLuis) === null
    && valor($db, "SELECT COUNT(*) FROM api_token WHERE id_empleado = 2 AND revocado = 'N'") === '0');
revisar('...y Luis tendrá que cambiarla al entrar', valor($db, 'SELECT debe_cambiar_clave FROM empleado WHERE id_empleado = 2') === 'S');
$tokenDeJorge = $tokens->crear(4, 'prueba')['token'];
revisar('Los tokens de otros empleados siguen valiendo', $tokens->empleadoDe($tokenDeJorge) === 4);
rechaza('Rosa no se pone a sí misma la clave de prueba', fn() => $personal->reiniciarClave($rosa, 1, 'Mass2026!'));
$personal->reiniciarClave($rosa, 1, 'Rosa-Cayma7');
revisar('Si Rosa se reinicia su propia clave, no se le obliga a cambiarla', valor($db, 'SELECT debe_cambiar_clave FROM empleado WHERE id_empleado = 1') === 'N');
// api.php corre despues y entra con la clave de prueba: se le devuelve a Rosa
$db->prepare('UPDATE empleado SET clave_hash = ? WHERE id_empleado = 1')->execute([Clave::hash('Mass2026!')]);

echo "Desbloqueo al reiniciar la clave\n";
$db->exec("INSERT INTO intento_login (dni, ip, fecha_hora, exito) VALUES "
    . implode(', ', array_fill(0, IntentoLoginRepository::MAXIMO_POR_DNI, "('70000002', '10.7.7.7', NOW(), 'N')")));
$intentosLuis = new IntentoLoginRepository($db);
revisar('Luis queda bloqueado tras 5 claves erradas', $intentosLuis->estaBloqueado('70000002', '10.7.7.8'));
$personal->reiniciarClave($rosa, 2, 'Temporal2026x');
revisar('...y Rosa lo desbloquea al ponerle una clave nueva', !$intentosLuis->estaBloqueado('70000002', '10.7.7.8'));
$personal->reiniciarClave($rosa, 2, 'Mass2026!');
rechaza('Jorge no reinicia la clave de alguien de Cayma', fn() => $personal->reiniciarClave($jorge, 2, 'Mass2026!'));

echo "Limpieza de intentos y tokens\n";
$db->exec("INSERT INTO intento_login (dni, ip, fecha_hora, exito) VALUES ('70000006', '10.9.9.9', NOW() - INTERVAL 31 DAY, 'N'), ('70000006', '10.9.9.9', NOW() - INTERVAL 29 DAY, 'N')");
$db->exec("INSERT INTO api_token (token_hash, id_empleado, fecha_creacion, ultimo_uso, fecha_expira)
           VALUES (REPEAT('e', 64), 4, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 1 DAY)");
$intentosLimpieza = new IntentoLoginRepository($db);
revisar('Se borran los intentos de login de más de 30 días', $intentosLimpieza->borrarViejos() >= 1
    && valor($db, "SELECT COUNT(*) FROM intento_login WHERE ip = '10.9.9.9'") === '1');
revisar('Se borran los tokens vencidos', $tokens->borrarVencidos() >= 1
    && valor($db, "SELECT COUNT(*) FROM api_token WHERE token_hash = REPEAT('e', 64)") === '0');
revisar('...y no los vigentes', $tokens->empleadoDe($tokenDeJorge) === 4);
$tokens->revocar($tokenDeJorge);

echo "Migraciones\n";
$migraciones = new Migraciones($db);
$estado = $migraciones->estado();
revisar('La base tiene todas las migraciones del repositorio, sin cambios', $estado === ['pendientes' => [], 'cambiadas' => [], 'desconocidas' => []]);
revisar('Correr migrar otra vez no hace nada', $migraciones->aplicarPendientes(static function (string $linea): void {}) === []);
// Una copia de las migraciones con la 013 editada: migrar se niega a seguir
$copia = sys_get_temp_dir() . '/mass_migraciones_' . getmypid();
@mkdir($copia . '/migraciones', 0700, true);
copy(__DIR__ . '/../database/migracion_aplicada.sql', $copia . '/migracion_aplicada.sql');
foreach (Migraciones::archivos() as $nombre => $ruta) {
    file_put_contents($copia . '/migraciones/' . $nombre, file_get_contents($ruta) . "\n-- editada despues de aplicarse\n");
}
$editada = new Migraciones($db, $copia);
revisar('Una migración aplicada y luego editada se detecta', $editada->estado()['cambiadas'] === array_keys(Migraciones::archivos()));
try {
    $editada->aplicarPendientes(static function (string $linea): void {});
    revisar('...y migrar no sigue (no la rechazó)', false);
} catch (RuntimeException $ex) {
    revisar('...y migrar no sigue: ' . $ex->getMessage(), str_contains($ex->getMessage(), 'migracion nueva'));
}
array_map('unlink', glob($copia . '/migraciones/*') ?: []);
@rmdir($copia . '/migraciones');
@unlink($copia . '/migracion_aplicada.sql');
@rmdir($copia);

echo "Clientes\n";
$idCliente = (int) valor($db, 'SELECT id_cliente FROM cliente WHERE dni = ?', ['12345678']);
$clientesServicio->completarDocumento($luis, $idCliente, '', '10123456781');
revisar('Se agrega el RUC al cliente 12345678', valor($db, 'SELECT ruc FROM cliente WHERE id_cliente = ?', [$idCliente]) === '10123456781');
rechaza('No se cambia un documento ya registrado', fn() => $clientesServicio->completarDocumento($luis, $idCliente, '87654321', ''));
rechaza('Un almacenero no edita clientes', fn() => $clientesServicio->completarDocumento($carmen, $idCliente, '', '10999999991'));

echo "Ingreso, reportes y páginas\n";
$intentos = new IntentoLoginRepository($db);
for ($i = 0; $i < IntentoLoginRepository::MAXIMO_POR_DNI; $i++) {
    $intentos->registrar('70000005', '10.0.0.9', false);
}
revisar('5 claves erradas bloquean ese DNI', $intentos->estaBloqueado('70000005', '10.0.0.8'));
revisar('...pero no a otro DNI', !$intentos->estaBloqueado('70000004', '10.0.0.8'));
$reportes = new ReporteRepository($db);
$resumen = $reportes->resumen(1, '2026-10-17', '2026-10-19');
revisar('Cayma vendió S/ 323.00 del 17 al 19/10 (como el paso 7)', abs($resumen['total'] - 323.00) < 0.001);
revisar('Más vendido en Cayma tiene importe', ($reportes->masVendidos(1, '2026-01-01', '2026-12-31')[0]['importe'] ?? '0') > 0);
$hoy = date('Y-m-d');
$resumenHoy = $reportes->resumen(1, $hoy, $hoy);
revisar('El resumen de hoy resta las devoluciones: devuelto S/ ' . number_format($resumenHoy['devuelto'], 2),
    abs($resumenHoy['devuelto'] - ($devuelto + 900) / 100) < 0.001 && abs($resumenHoy['neto'] - ($resumenHoy['total'] - $resumenHoy['devuelto'])) < 0.001);
$utilidad = $reportes->utilidadPorProducto(1, '2026-01-01', $hoy);
$porNombre = array_column($utilidad, null, 'producto');
$yogurt = $porNombre['Yogurt Gloria fresa 1 L'] ?? null;
revisar('Utilidad del yogurt: costo promedio S/ 5.90 de sus 2 ingresos y utilidad = ventas − costo', $yogurt !== null
    && $yogurt['costo_unitario'] === 5.9 && abs($yogurt['utilidad'] - ($yogurt['ventas'] - round($yogurt['cantidad'] * 5.9, 2))) < 0.001);
$leche = $porNombre['Leche evaporada Gloria entera 400 g'] ?? null;
revisar('La leche resta lo devuelto: 3 vendidas y 3 devueltas no cuentan', $leche !== null
    && abs($leche['cantidad'] - ((float) valor($db, "SELECT SUM(d.cantidad) FROM detalle_venta d JOIN venta v ON v.id_venta = d.id_venta
        JOIN serie_comprobante s ON s.id_serie = v.id_serie WHERE d.id_producto = 1 AND s.id_tienda = 1 AND v.estado = 'EMITIDA'") - 3)) < 0.001);
revisar('Un producto que nunca entró por ingreso queda sin costo', array_filter($utilidad, fn(array $p): bool => $p['costo'] === null) !== []);
$porTienda = $reportes->utilidadPorTienda([1 => 'Mass Cayma', 2 => 'Mass Cerro Colorado'], '2026-01-01', $hoy);
revisar('La utilidad de Cayma en la tabla por tienda suma la de sus productos', abs($porTienda[0]['utilidad']
    - array_sum(array_map(fn(array $p): float => $p['utilidad'] ?? 0.0, $utilidad))) < 0.01 && $porTienda[1]['tienda'] === 'Mass Cerro Colorado');
$cadena = [1, 2, 3];
$resumenCadena = $reportes->resumen($cadena, '2026-01-01', $hoy);
$sumaTiendas = array_sum(array_map(fn(int $t): float => $reportes->resumen($t, '2026-01-01', $hoy)['total'], $cadena));
revisar('El consolidado de la cadena suma lo vendido por cada tienda', abs($resumenCadena['total'] - $sumaTiendas) < 0.001
    && $resumenCadena['total'] > $reportes->resumen(1, '2026-01-01', $hoy)['total']);
$porTiendaVentas = $reportes->porTienda($cadena, '2026-01-01', $hoy);
revisar('Ventas por tienda: una fila por tienda con ventas y el mismo total que el consolidado',
    abs(array_sum(array_column($porTiendaVentas, 'total')) - $resumenCadena['total']) < 0.001
    && in_array('Mass Cerro Colorado', array_column($porTiendaVentas, 'tienda'), true));
$horas = $reportes->porHora(1, '2026-01-01', $hoy);
revisar('Ventas por hora: horas de 0 a 23 que suman lo vendido en la tienda', $horas !== []
    && min(array_column($horas, 'hora')) >= 0 && max(array_column($horas, 'hora')) <= 23
    && abs(array_sum(array_column($horas, 'total')) - $reportes->resumen(1, '2026-01-01', $hoy)['total']) < 0.001);
revisar('Por cajero y más vendidos aceptan varias tiendas', count($reportes->porCajero($cadena, '2026-01-01', $hoy)) >= count($reportes->porCajero(1, '2026-01-01', $hoy))
    && $reportes->masVendidos($cadena, '2026-01-01', $hoy, 3) !== []);
$reposicion = (new PanelRepository($db))->reposicion(1, Reposicion::DIAS_VENTA);
revisar('La lista para reponer trae lo que está en su mínimo, con lo vendido y el sobrante de otras tiendas',
    count($reposicion) === (new PanelRepository($db))->contarPorReponer(1)
    && array_filter($reposicion, fn(array $f): bool => $f['actual'] > $f['minimo']) === []);
$pagina = new Paginacion(51, '99', 25);
revisar('Página 99 de 51 filas se corrige a la 3 (offset 50)', $pagina->getPagina() === 3 && $pagina->offset() === 50);

echo "Conteo físico\n";
$papa = $stock(1, 8);
$arroz = $stock(1, 2);
$r = $inventario->ajustarPorConteo($carmen, 'CONTEO_FISICO', 'Conteo de prueba', [
    ['id_producto' => '8', 'cantidad' => '40.5'],
    ['id_producto' => '2', 'cantidad' => $arroz === '' ? '0' : (string) (int) $arroz],  // coincide: no se toca
    ['id_producto' => '1', 'cantidad' => ''],                                              // no se contó
]);
revisar("Ajuste {$r['idAjuste']}: la papa queda en lo contado (40.500)", $stock(1, 8) === '40.500' && $r['cambios'] === 1);
revisar('...y el kardex anota la diferencia con su tipo', valor($db,
    'SELECT CONCAT(id_tipo_movimiento, \'/\', cantidad) FROM movimiento_stock WHERE id_ajuste = ?', [$r['idAjuste']])
    === ((float) $papa > 40.5 ? '7/' : '6/') . number_format(abs((float) $papa - 40.5), 3, '.', ''));
$inventario->ajustarPorConteo($rosa, 'CORRECCION', null, [['id_producto' => '9', 'cantidad' => '0']]);
revisar('Rosa deja el plátano en 0 con una corrección', $stock(1, 9) === '0.000');
rechaza('Conteo igual al sistema', fn() => $inventario->ajustarPorConteo($carmen, 'CONTEO_FISICO', null, [['id_producto' => '8', 'cantidad' => '40.500']]));
rechaza('Producto contado dos veces', fn() => $inventario->ajustarPorConteo($carmen, 'CONTEO_FISICO', null, [
    ['id_producto' => '8', 'cantidad' => '1'], ['id_producto' => '8', 'cantidad' => '2']]));
rechaza('Cantidad negativa', fn() => $inventario->ajustarPorConteo($carmen, 'CONTEO_FISICO', null, [['id_producto' => '8', 'cantidad' => '-1']]));
rechaza('Un cajero no ajusta stock', fn() => $inventario->ajustarPorConteo($luis, 'CONTEO_FISICO', null, [['id_producto' => '8', 'cantidad' => '1']]));

echo "Topes de las columnas DECIMAL(10,2) y DECIMAL(10,3)\n";
try {
    $ventas->registrar($luis, 'B', '', [['id_producto' => '4', 'cantidad' => '9999999']], ['efectivo_recibido' => '99999999']);
    revisar('Venta de más de S/ 99 999 999.99 (no la rechazó)', false);
} catch (ReglaNegocioException $ex) {
    revisar('Venta de más de S/ 99 999 999.99 -> ' . $ex->getMessage(), str_contains($ex->getMessage(), 'divide la venta'));
}
$arrozAntes = $stock(1, 2);
$inventario->ajustarPorConteo($carmen, 'CORRECCION', 'Prueba de tope', [['id_producto' => '2', 'cantidad' => '9999999']]);
$almaceneroCerro = $empleados->buscarPorId(6); // ALMACENERO Cerro Colorado
rechaza('Un traslado no deja el stock por encima de 9 999 999.999', fn() => $inventario->trasladar($almaceneroCerro, 1,
    [['id_producto' => '2', 'cantidad' => '1']], null));
$inventario->ajustarPorConteo($carmen, 'CORRECCION', 'Fin de la prueba de tope', [['id_producto' => '2', 'cantidad' => (string) (int) $arrozAntes]]);
revisar('...y el arroz vuelve a su stock', $stock(1, 2) === $arrozAntes);
rechaza('Dos líneas que suman más que el máximo', fn() => $inventario->registrarMerma($carmen, 'VENCIDO', null, [
    ['id_producto' => '2', 'cantidad' => '9999999'], ['id_producto' => '2', 'cantidad' => '9999999']]));

echo "Tiendas y cajas\n";
$tiendas = new TiendaServicio($db);
$idTienda = $tiendas->abrir($rosa,
    ['nombre' => 'Mass Sachaca', 'direccion' => 'Av. de prueba 123', 'telefono' => '054 123456', 'distrito' => '1'],
    ['nombres' => 'Elena', 'apellidos' => 'Paz Rivas', 'dni' => '70000040', 'clave' => 'Sachaca2026']);
revisar("Tienda $idTienda abierta con series B004 y F004", valor($db,
    'SELECT GROUP_CONCAT(serie ORDER BY serie) FROM serie_comprobante WHERE id_tienda = ?', [$idTienda]) === 'B004,F004');
revisar('...con su caja 1 y todos los productos en stock 0', valor($db, 'SELECT COUNT(*) FROM caja WHERE id_tienda = ?', [$idTienda]) === '1'
    && valor($db, 'SELECT COUNT(*) FROM stock WHERE id_tienda = ?', [$idTienda]) === valor($db, 'SELECT COUNT(*) FROM producto'));
$elena = $empleados->credencialesPorDni('70000040');
$elena = $empleados->buscarPorId($elena['idEmpleado']);
revisar('...y su administradora Elena', $elena instanceof Administrador && $elena->getIdTienda() === $idTienda);
revisar('...que cambia en su primer ingreso la clave que le pusieron', valor($db, 'SELECT debe_cambiar_clave FROM empleado WHERE dni = ?', ['70000040']) === 'S');
rechaza('Nombre de tienda repetido', fn() => $tiendas->abrir($rosa,
    ['nombre' => 'Mass Cayma', 'direccion' => 'X', 'telefono' => '', 'distrito' => '1'],
    ['nombres' => 'A', 'apellidos' => 'B', 'dni' => '70000041', 'clave' => 'Clave2026']));
rechaza('Un almacenero no abre tiendas', fn() => $tiendas->abrir($carmen,
    ['nombre' => 'Mass X', 'direccion' => 'X', 'telefono' => '', 'distrito' => '1'],
    ['nombres' => 'A', 'apellidos' => 'B', 'dni' => '70000042', 'clave' => 'Clave2026']));
revisar('Elena agrega la caja 2', $tiendas->agregarCaja($elena) === 2);
rechaza('Rosa no edita la tienda de Elena', fn() => $tiendas->editar($rosa, $idTienda,
    ['nombre' => 'Mass Sachaca', 'direccion' => 'Otra', 'telefono' => '', 'distrito' => '1'], 'ACTIVA'));
rechaza('No se desactiva la caja 1 de Cayma: Luis tiene el turno abierto', fn() => $tiendas->cambiarEstadoCaja($rosa, 1, 'INACTIVA'));
$tiendas->cambiarEstadoCaja($rosa, 2, 'INACTIVA');
revisar('Rosa desactiva la caja 2 de Cayma', valor($db, 'SELECT estado FROM caja WHERE id_caja = 2') === 'INACTIVA');
rechaza('No se desactiva una tienda con turnos abiertos', fn() => $tiendas->editar($rosa, 1,
    ['nombre' => 'Mass Cayma', 'direccion' => 'Dirección de ejemplo, Cayma', 'telefono' => '', 'distrito' => '1'], 'INACTIVA'));
$tiendas->editar($elena, $idTienda, ['nombre' => 'Mass Sachaca', 'direccion' => 'Av. de prueba 123', 'telefono' => '', 'distrito' => '1'], 'INACTIVA');
$cajeroSachaca = $personal->crear($elena, 'Raul', 'Soto', '70000043', 2, 'Raul2026x');
$raul = $empleados->buscarPorId($cajeroSachaca);
rechaza('En una tienda inactiva no se abre caja', fn() => $caja->abrirTurno($raul, (int) valor($db,
    'SELECT id_caja FROM caja WHERE id_tienda = ? AND numero_caja = 1', [$idTienda]), '50'));
rechaza('Ni se le traslada mercadería', fn() => $inventario->trasladar($carmen, $idTienda, [['id_producto' => '2', 'cantidad' => '1']], null));
$almaceneroSachaca = $empleados->buscarPorId($personal->crear($elena, 'Nora', 'Vela', '70000044', 3, 'Nora2026x'));
rechaza('Ni recibe un ingreso de mercadería', fn() => $inventario->registrarIngreso($almaceneroSachaca, ['numero' => 'G001-1'] + $documento,
    [['id_producto' => '2', 'cantidad' => '1', 'costo' => '1.00']]));

echo "Auditoría\n";
$auditado = fn(string $accion, string $parte): bool => (int) valor($db,
    'SELECT COUNT(*) FROM auditoria WHERE accion = ? AND detalle LIKE ?', [$accion, '%' . $parte . '%']) > 0;
revisar('La anulación quedó en la auditoría con su comprobante', $auditado('VENTA_ANULADA', 'B001-'));
revisar('El cambio de precio de la leche quedó con el precio anterior y el nuevo', $auditado('PRECIO_CAMBIADO', '-> S/ 4.80'));
revisar('El mínimo, la categoría, el personal y las claves también', $auditado('STOCK_MINIMO', '12.500')
    && $auditado('CATEGORIA_CREADA', 'Panadería') && (int) valor($db, "SELECT COUNT(*) FROM auditoria WHERE accion IN ('EMPLEADO_CREADO','CLAVE_REINICIADA')") > 0);
revisar('La tienda nueva y sus cajas quedaron registradas', $auditado('TIENDA_ABIERTA', 'Mass Sachaca') && $auditado('CAJA_ESTADO', 'INACTIVA'));
revisar('Cada fila tiene la tienda de quien hizo la acción', valor($db,
    'SELECT COUNT(*) FROM auditoria a JOIN empleado e ON e.id_empleado = a.id_empleado WHERE a.id_tienda <> e.id_tienda') === '0');
$antesAuditoria = valor($db, 'SELECT COUNT(*) FROM auditoria');
rechaza('Una acción rechazada no deja rastro en la auditoría', fn() => $catalogo->crearCategoria($rosa, 'Panadería'));
revisar('...la auditoría sigue igual', valor($db, 'SELECT COUNT(*) FROM auditoria') === $antesAuditoria);

echo "Reglas del diseño (consultas 20 a 31 de consultas.sql: 0 filas)\n";
$sql = file_get_contents(__DIR__ . '/../database/consultas.sql');
preg_match_all('/^-- (2[0-9]|3[01])\. .*?\n(SELECT.*?);/ms', $sql, $consultas, PREG_SET_ORDER);
foreach ($consultas as [, $numero, $consulta]) {
    $filas = $db->query($consulta)->fetchAll();
    revisar("Consulta $numero", count($filas) === 0);
}

echo PHP_EOL . "$ok correctas, $fallas fallas" . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
