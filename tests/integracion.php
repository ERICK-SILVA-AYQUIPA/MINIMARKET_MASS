<?php
declare(strict_types=1);

/**
 * Pruebas de integracion contra la base MySQL de Docker.
 *   php tests/integracion.php
 *
 * OJO: escribe ventas, traslados, mermas y turnos de verdad. Para volver a los datos de datos.sql:
 *   docker compose down -v ; docker compose up -d
 *
 * Al final corre las consultas 20 a 28 de consultas.sql: cada una debe devolver 0 filas.
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la terminal.');
}

require __DIR__ . '/../config/Database.php';
require __DIR__ . '/../models/EmpleadoRepository.php';
require __DIR__ . '/../models/servicios/VentaServicio.php';
require __DIR__ . '/../models/servicios/InventarioServicio.php';
require __DIR__ . '/../models/servicios/CajaServicio.php';

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

echo "Caja\n";
rechaza('Abrir con un turno ya abierto', fn() => $caja->abrirTurno($luis, 2, '100'));
rechaza('Abrir una caja de otra tienda', fn() => $caja->abrirTurno($miguel, 1, '100'));
$idTurno = $caja->abrirTurno($miguel, 6, '150.00');
revisar("Miguel abre el turno $idTurno en la caja 2 de Yanahuara", valor($db, 'SELECT estado FROM caja_turno WHERE id_caja_turno = ?', [$idTurno]) === 'ABIERTO');
$ventas->registrar($miguel, 'B', '', [['id_producto' => '3', 'cantidad' => '2']], ['efectivo_recibido' => '20']);
$caja->cerrarTurno($miguel, '163.00');
revisar('Cierra con 150 + 13 cobrados = 163.00', valor($db, 'SELECT monto_cierre FROM caja_turno WHERE id_caja_turno = ?', [$idTurno]) === '163.00');
rechaza('Cerrar sin turno abierto', fn() => $caja->cerrarTurno($miguel, '0'));

echo "Reglas del diseño (consultas 20 a 28 de consultas.sql: 0 filas)\n";
$sql = file_get_contents(__DIR__ . '/../database/consultas.sql');
preg_match_all('/^-- (2[0-8])\. .*?\n(SELECT.*?);/ms', $sql, $consultas, PREG_SET_ORDER);
foreach ($consultas as [, $numero, $consulta]) {
    $filas = $db->query($consulta)->fetchAll();
    revisar("Consulta $numero", count($filas) === 0);
}

echo PHP_EOL . "$ok correctas, $fallas fallas" . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
