<?php
declare(strict_types=1);

/**
 * Deja una base RECIEN INSTALADA lista para la tienda real: borra los datos de ejemplo y deja solo
 * lo minimo para empezar (issue #82).
 *   php backend/scripts/quitar_datos_demo.php --confirmar --dni=40123456 --nombres="Ana" --apellidos="Diaz Flores"
 *
 * Se queda:  roles, tipos de comprobante, metodos de pago, tipos de movimiento, unidades, categorias,
 *            marcas, distritos, la tienda 1 con sus cajas, series (en 0) y reglas de caja,
 *            y UN administrador: el empleado 1 con el DNI y el nombre que se pasan aqui.
 * Se borra:  las otras tiendas, los otros empleados, productos y stock, ventas, pagos, turnos, arqueos,
 *            ingresos, traslados, mermas, ajustes, devoluciones, kardex, clientes, auditoria,
 *            intentos de login y sesiones de la app.
 *
 * El administrador recibe una clave temporal que se muestra UNA vez: al entrar se le pide cambiarla.
 * Despues: Tienda -> editar (nombre y direccion reales), Catalogo -> Importar CSV y Personal.
 *
 * Solo corre sobre una base que nadie ha usado: solo los empleados de ejemplo (9 de datos.sql y los 3 cajeros
 * de la migracion 024) y todos con la clave de prueba sin cambiar. Si alguien ya cambio su clave (la tienda ya trabaja), no borra nada.
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la consola.');
}
require __DIR__ . '/../config/Database.php';
require __DIR__ . '/../models/Clave.php';
date_default_timezone_set('America/Lima');

$opciones = getopt('', ['confirmar', 'dni:', 'nombres:', 'apellidos:']);
$dni = trim((string) ($opciones['dni'] ?? ''));
$nombres = trim((string) ($opciones['nombres'] ?? ''));
$apellidos = trim((string) ($opciones['apellidos'] ?? ''));
if (!isset($opciones['confirmar']) || preg_match('/^[0-9]{8}$/', $dni) !== 1 || $nombres === '' || $apellidos === ''
    || mb_strlen($nombres) > 60 || mb_strlen($apellidos) > 60) {
    fwrite(STDERR, "Uso: php backend/scripts/quitar_datos_demo.php --confirmar --dni=12345678 --nombres=\"...\" --apellidos=\"...\"\n"
        . "     (BORRA los datos de ejemplo; el DNI y el nombre son los del administrador real de la tienda)\n");
    exit(1);
}

try {
    $db = Database::conexion();
} catch (PDOException) {
    fwrite(STDERR, "[ERROR] No se pudo conectar a MySQL con los datos de .env.\n");
    exit(1);
}

// La base tiene que ser la de ejemplo, sin usar: solo empleados de ejemplo (los 9 de datos.sql, y los cajeros de
// la migracion 024 si ya corrio) y ninguno cambio la clave de prueba
const DNI_DE_EJEMPLO = ['70000001', '70000002', '70000003', '70000004', '70000005', '70000006', '70000007',
    '70000008', '70000009', '70000101', '70000102', '70000103'];
$marcas = implode(',', array_fill(0, count(DNI_DE_EJEMPLO), '?'));
$ajenos = $db->prepare("SELECT COUNT(*) FROM empleado WHERE dni NOT IN ($marcas)");
$ajenos->execute(DNI_DE_EJEMPLO);
$empleados = (int) $db->query('SELECT COUNT(*) FROM empleado')->fetchColumn();
$sinCambiar = (int) $db->query("SELECT COUNT(*) FROM empleado WHERE debe_cambiar_clave = 'S'")->fetchColumn();
$admin = $db->query("SELECT id_rol, id_tienda FROM empleado WHERE id_empleado = 1")->fetch();
if ((int) $ajenos->fetchColumn() !== 0 || $empleados < 9 || $sinCambiar !== $empleados || $admin === false || (int) $admin['id_rol'] !== 1 || (int) $admin['id_tienda'] !== 1) {
    fwrite(STDERR, "[ERROR] Esta base no es la de ejemplo recien instalada (ya hay empleados nuevos o claves cambiadas).\n"
        . "        No se borra nada. Para una tienda que ya trabaja, los datos se corrigen desde la pagina web.\n");
    exit(1);
}

// Tablas de movimientos: se vacian enteras (orden: primero las que apuntan a otras)
$vaciar = [
    'arqueo_detalle', 'arqueo_caja', 'movimiento_caja', 'detalle_devolucion', 'devolucion_venta',
    'efectivo', 'tarjeta', 'billetera_digital_sip', 'pago_venta', 'anulacion_venta', 'movimiento_stock',
    'detalle_venta', 'venta', 'caja_turno', 'detalle_ingreso', 'ingreso_mercaderia', 'detalle_orden_compra', 'orden_compra', 'proveedor',
    'ajuste_inventario',
    'merma', 'traslado_mercaderia', 'producto_codigo', 'stock', 'producto', 'cliente', 'auditoria', 'api_operacion', 'api_token', 'intento_login',
];
$claveTemporal = 'Mass' . bin2hex(random_bytes(4)) . random_int(10, 99);

$db->beginTransaction();
try {
    foreach ($vaciar as $tabla) {
        $db->exec('DELETE FROM ' . $tabla);
    }
    $db->exec('DELETE FROM empleado WHERE id_empleado <> 1');
    $db->exec('DELETE FROM regla_caja WHERE id_tienda <> 1');
    $db->exec('DELETE FROM serie_comprobante WHERE id_tienda <> 1');
    $db->exec('DELETE FROM caja WHERE id_tienda <> 1');
    $db->exec('DELETE FROM tienda WHERE id_tienda <> 1');
    $db->exec('UPDATE serie_comprobante SET ultimo_numero = 0');
    $db->exec("UPDATE caja SET estado = 'ACTIVA'");
    $db->prepare(
        "UPDATE empleado SET dni = :dni, nombres = :nombres, apellidos = :apellidos, clave_hash = :hash,
                version_clave = version_clave + 1, debe_cambiar_clave = 'S', estado = 'ACTIVO'
          WHERE id_empleado = 1"
    )->execute(['dni' => $dni, 'nombres' => $nombres, 'apellidos' => $apellidos, 'hash' => Clave::hash($claveTemporal)]);
    $db->commit();
} catch (Throwable $ex) {
    $db->rollBack();
    fwrite(STDERR, '[ERROR] No se borro nada: ' . $ex->getMessage() . "\n");
    exit(1);
}

// Los numeros (id) empiezan otra vez desde el siguiente libre (ALTER TABLE confirma solo: va despues del commit)
foreach (array_merge($vaciar, ['empleado', 'tienda', 'caja', 'serie_comprobante']) as $tabla) {
    $db->exec('ALTER TABLE ' . $tabla . ' AUTO_INCREMENT = 1');
}

echo "[OK] Se borraron los datos de ejemplo. Quedan la tienda 1, sus cajas y el administrador.\n";
echo "     Entra con DNI $dni y esta clave temporal (se muestra solo esta vez): $claveTemporal\n";
echo "     Al entrar se te pide cambiarla. Luego: Tienda (nombre y direccion), Catalogo -> Importar CSV y Personal.\n";
