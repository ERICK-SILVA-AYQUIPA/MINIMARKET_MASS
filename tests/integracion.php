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
require __DIR__ . '/../models/Paginacion.php';
require __DIR__ . '/../models/IngresoRepository.php';
require __DIR__ . '/../models/CajaRepository.php';
require __DIR__ . '/../models/VentaRepository.php';
require __DIR__ . '/../models/Vencimiento.php';
require __DIR__ . '/../models/InventarioRepository.php';

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
rechaza('El administrador tampoco: lo revisa', fn() => $inventario->registrarIngreso($rosa, ['numero' => 'F001-2'] + $documento,
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
revisar('IngresoRepository devuelve la cabecera y su detalle', $ingresosRepo->contar(1) === $ingresosAntes + 1
    && $ingresosRepo->pagina(1, 25, 0)[0]->documento() === 'FACTURA F001-00012345'
    && count($detalle) === 2 && $detalle[1]->getCostoTotal() === 20.48
    && $detalle[0]->getFechaVencimiento() === null && $detalle[1]->getFechaVencimiento() === date('Y-m-d', strtotime('+5 days')));

echo "Por vencer\n";
$lotes = $ingresosRepo->lotesConVencimiento(1);
$porVencer = [];
foreach ($lotes as $idProducto => $datos) {
    foreach (Vencimiento::porVencer(Vencimiento::repartirStock($datos['lotes'], $datos['stock']), date('Y-m-d'), 30) as $lote) {
        $porVencer[] = $idProducto . '/' . $lote['id_ingreso'] . '/' . $lote['dias'];
    }
}
revisar('Leche, plátano, yogurt y la papa recién recibida tienen lotes con fecha', array_keys($lotes) === [1, 8, 9, 11]);
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
$caja->registrarMovimiento($luis, 'RETIRO', '50.00', 'Depósito al banco');
$caja->registrarMovimiento($luis, 'INGRESO', '20.00', 'Sencillo del almacén');
revisar('El arqueo resta el retiro y suma el ingreso: esperado baja 30.00', round($esperadoAntes - $fila()['esperado'], 2) === 30.0
    && $fila()['retiros'] === 50.0 && $fila()['ingresos'] === 20.0 && count($cajasRepo->movimientosDeTurno($turnoLuis)) === 2);
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

echo "Personal y claves\n";
$idNuevo = $personal->crear($rosa, 'Diana', 'Torres Lazo', '70000010', 2, 'Caja2026x');
revisar("Rosa registra a la cajera $idNuevo en Cayma", valor($db, 'SELECT id_tienda FROM empleado WHERE id_empleado = ?', [$idNuevo]) === '1');
revisar('...con la clave en hash, no en texto', password_verify('Caja2026x', valor($db, 'SELECT clave_hash FROM empleado WHERE id_empleado = ?', [$idNuevo])));
rechaza('DNI repetido', fn() => $personal->crear($rosa, 'Otra', 'Persona', '70000002', 2, 'Caja2026x'));
rechaza('Clave sin números', fn() => $personal->crear($rosa, 'Otra', 'Persona', '70000011', 2, 'solamenteletras'));
rechaza('Jorge no edita personal de Cayma', fn() => $personal->editar($jorge, 2, 'Luis', 'Caceres Pinto', 2, 'INACTIVO'));
rechaza('Rosa no se desactiva a sí misma', fn() => $personal->editar($rosa, 1, 'Rosa', 'Quispe Huaman', 1, 'INACTIVO'));
rechaza('No se desactiva a un cajero con turno abierto', fn() => $personal->editar($rosa, 2, 'Luis', 'Caceres Pinto', 2, 'INACTIVO'));
$personal->editar($rosa, $idNuevo, 'Diana', 'Torres Lazo', 3, 'INACTIVO');
revisar('Diana pasa a almacenera INACTIVA', valor($db, 'SELECT CONCAT(id_rol, estado) FROM empleado WHERE id_empleado = ?', [$idNuevo]) === '3INACTIVO');
$personal->cambiarMiClave($luis, 'Mass2026!', 'NuevaClave1', 'NuevaClave1');
revisar('Luis cambia su clave', password_verify('NuevaClave1', valor($db, 'SELECT clave_hash FROM empleado WHERE id_empleado = 2')));
rechaza('Cambiar clave con la actual errada', fn() => $personal->cambiarMiClave($luis, 'Mass2026!', 'OtraClave2', 'OtraClave2'));
$personal->reiniciarClave($rosa, 2, 'Mass2026!');
revisar('Rosa le devuelve una clave a Luis', password_verify('Mass2026!', valor($db, 'SELECT clave_hash FROM empleado WHERE id_empleado = 2')));

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
    ['nombre' => 'Mass Cayma', 'direccion' => 'Direccion de ejemplo, Cayma', 'telefono' => '', 'distrito' => '1'], 'INACTIVA'));
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
