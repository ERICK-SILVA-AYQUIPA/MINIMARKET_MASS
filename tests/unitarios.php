<?php
declare(strict_types=1);

/**
 * Pruebas unitarias: las reglas que viven en las clases del modelo, SIN base de datos.
 *   php tests/unitarios.php
 *
 * Corren en un segundo y sirven para revisar un cambio antes de levantar Docker.
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la terminal.');
}

require __DIR__ . '/../models/Dinero.php';
require __DIR__ . '/../models/Cantidad.php';
require __DIR__ . '/../models/Paginacion.php';
require __DIR__ . '/../models/Clave.php';
require __DIR__ . '/../models/Producto.php';
require __DIR__ . '/../models/Stock.php';
require __DIR__ . '/../models/Administrador.php';
require __DIR__ . '/../models/Cajero.php';
require __DIR__ . '/../models/Almacenero.php';
require __DIR__ . '/../models/IngresoMercaderia.php';
require __DIR__ . '/../models/DetalleIngreso.php';
require __DIR__ . '/../models/DevolucionVenta.php';
require __DIR__ . '/../models/Vencimiento.php';
require __DIR__ . '/../models/Reposicion.php';

$ok = 0;
$fallas = 0;
function revisar(string $caso, bool $cumple): void
{
    global $ok, $fallas;
    $cumple ? $ok++ : $fallas++;
    echo ($cumple ? '  OK    ' : '  FALLA ') . $caso . PHP_EOL;
}
// El constructor debe rechazar el dato invalido, como lo haria el CHECK de la tabla
function rechazaDato(string $caso, callable $crear): void
{
    try {
        $crear();
        revisar($caso . ' (no lo rechazó)', false);
    } catch (InvalidArgumentException) {
        revisar($caso, true);
    }
}

echo "Dinero (céntimos enteros)\n";
revisar('0.1 + 0.2 suma 30 céntimos exactos', Dinero::centimos(0.1) + Dinero::centimos(0.2) === 30);
revisar('"12.5" -> 1250', Dinero::deTexto('12.5') === 1250);
revisar('" 7 " -> 700 (recorta espacios)', Dinero::deTexto(' 7 ') === 700);
revisar('"12.345" no es un monto', Dinero::deTexto('12.345') === null);
revisar('"-5" no es un monto', Dinero::deTexto('-5') === null);
revisar('"1e3" no es un monto', Dinero::deTexto('1e3') === null);
revisar('1205 -> "12.05"', Dinero::aTexto(1205) === '12.05');

echo "Cantidad (milésimas enteras)\n";
revisar('Al peso "1.25" -> 1250', Cantidad::deTexto('1.25', true) === 1250);
revisar('Por unidad "2" -> 2000', Cantidad::deTexto('2', false) === 2000);
revisar('Por unidad no acepta decimales', Cantidad::deTexto('1.5', false) === null);
revisar('Al peso no acepta 4 decimales', Cantidad::deTexto('1.2345', true) === null);
revisar('Cero no es una cantidad', Cantidad::deTexto('0', false) === null && Cantidad::deTexto('0.000', true) === null);
revisar('DECIMAL "98.000" -> 98000', Cantidad::deDecimal('98.000') === 98000);
revisar('40500 -> "40.500"', Cantidad::aTexto(40500) === '40.500');
revisar('Importe 1.250 kg x S/ 2.50 = 313 céntimos (redondeo de MySQL)', Cantidad::importe(1250, 250) === 313);
revisar('Importe 3 x S/ 4.50 = 1350 céntimos', Cantidad::importe(3000, 450) === 1350);

echo "Paginación\n";
$p = new Paginacion(51, '3', 25);
revisar('51 filas de 25 en 25 son 3 páginas', $p->totalPaginas() === 3);
revisar('La página 3 salta 50 filas', $p->offset() === 50 && $p->hayAnterior() && !$p->haySiguiente());
revisar('?pagina=9999 se corrige a la última', (new Paginacion(51, '9999', 25))->getPagina() === 3);
revisar('?pagina=0 se corrige a la primera', (new Paginacion(51, '0', 25))->getPagina() === 1);
revisar('?pagina=abc se toma como la primera', (new Paginacion(51, 'abc', 25))->getPagina() === 1);
revisar('Sin filas hay 1 página y offset 0', (new Paginacion(0, null))->totalPaginas() === 1 && (new Paginacion(0, null))->offset() === 0);

echo "Clave de acceso\n";
revisar('"Mass2026!" es válida', Clave::problema('Mass2026!') === null);
revisar('Menos de 8 caracteres no', Clave::problema('ab12') !== null);
revisar('Solo letras no', Clave::problema('solamente') !== null);
revisar('Solo números no', Clave::problema('12345678') !== null);
revisar('Más de 72 caracteres no', Clave::problema(str_repeat('a1', 37)) !== null);
revisar('El hash se verifica y no guarda la clave', password_verify('Mass2026!', $h = Clave::hash('Mass2026!')) && !str_contains($h, 'Mass2026'));

echo "Producto y stock\n";
$papa = new Producto(8, 'Papa blanca (por kg)', null, 2.50, 'S', 'ACTIVO', 1, 1, 2);
$leche = new Producto(1, 'Leche evaporada', '7751271001234', 4.50, 'N', 'DESCONTINUADO', 1, 1, 1);
revisar('La papa se vende al peso', $papa->esAlPeso() && $papa->estaActivo());
revisar('Al peso acepta 1.250 kg pero no 1.2505', $papa->cantidadValida(1.25) && !$papa->cantidadValida(1.2505));
revisar('Por unidad no acepta 1.5', !$leche->cantidadValida(1.5) && $leche->cantidadValida(2));
revisar('Importe 1.250 kg x S/ 2.50 = 3.13', $papa->importe(1.25) === 3.13);
revisar('La leche descontinuada no está activa', !$leche->estaActivo() && !$leche->esAlPeso());
rechazaDato('Precio 0 no es válido', fn() => new Producto(9, 'X', null, 0, 'N', 'ACTIVO', 1, 1, 1));
rechazaDato('se_vende_por_peso solo S o N', fn() => new Producto(9, 'X', null, 1, 'X', 'ACTIVO', 1, 1, 1));
rechazaDato('Código de barras de 5 dígitos no', fn() => new Producto(9, 'X', '12345', 1, 'N', 'ACTIVO', 1, 1, 1));
$s = new Stock(1, 8, 5.0, 10.0);
revisar('5 kg con mínimo 10 necesita reponer', $s->necesitaReponer() && !$s->estaAgotado());
revisar('Hay stock para 5 kg pero no para 5.5', $s->hayStock(5.0) && !$s->hayStock(5.5));
revisar('Stock en 0 está agotado', (new Stock(1, 8, 0.0, 10.0))->estaAgotado());
rechazaDato('El stock nunca es negativo', fn() => new Stock(1, 8, -1.0, 10.0));

echo "Ingreso de mercadería\n";
$ingreso = new IngresoMercaderia(1, '2026-10-27 10:00:00', 'FACTURA', 'F001-00012345', '2026-10-27', '20100055237', 'Distribuidora', 106.88, null, 1, 3);
revisar('El documento se muestra con su tipo', $ingreso->documento() === 'FACTURA F001-00012345');
rechazaDato('Tipo de documento fuera de la lista', fn() => new IngresoMercaderia(1, 'x', 'TICKET', 'T1-1', 'x', '20100055237', 'D', 1.0, null, 1, 3));
rechazaDato('RUC del emisor con 10 dígitos', fn() => new IngresoMercaderia(1, 'x', 'GUIA', 'T001-1', 'x', '2010005523', 'D', 1.0, null, 1, 3));
revisar('Costo total 10.5 kg x S/ 1.95 = 20.48 (redondeo como MySQL)', (new DetalleIngreso(1, 8, 10.5, 1.95, 20.48))->getCostoTotal() === 20.48);
rechazaDato('Costo total que no es cantidad x costo', fn() => new DetalleIngreso(1, 8, 10.5, 1.95, 20.47));

revisar('Detalle de ingreso con fecha de vencimiento opcional', (new DetalleIngreso(1, 1, 12, 3.40, 40.80, '2026-11-01'))->getFechaVencimiento() === '2026-11-01'
    && (new DetalleIngreso(1, 1, 12, 3.40, 40.80))->getFechaVencimiento() === null);
rechazaDato('Devolución con total 0', fn() => new DevolucionVenta(1, 'x', 'Motivo', 0.0, 1, 5, 2));

echo "Por vencer (reparto FIFO del stock entre lotes)\n";
$lotes = [
    ['id_ingreso' => 1, 'fecha_vencimiento' => '2026-10-27', 'milesimas' => 12000],
    ['id_ingreso' => 2, 'fecha_vencimiento' => null, 'milesimas' => 48000],
    ['id_ingreso' => 3, 'fecha_vencimiento' => '2026-11-02', 'milesimas' => 20000],
];
$reparto = Vencimiento::repartirStock($lotes, 30000);
revisar('Con 30 en stock quedan 20 del lote nuevo y 10 del anterior; el más antiguo ya se vendió',
    array_column($reparto, 'restante', 'id_ingreso') === [2 => 10000, 3 => 20000]);
revisar('Con más stock que lotes, cada lote queda entero', array_column(Vencimiento::repartirStock($lotes, 999000), 'restante') === [12000, 48000, 20000]);
revisar('Sin stock no queda nada', Vencimiento::repartirStock($lotes, 0) === [] && Vencimiento::repartirStock($lotes, -5) === []);
$vencen = Vencimiento::porVencer(Vencimiento::repartirStock($lotes, 999000), '2026-10-28', 7);
revisar('En 7 días: el vencido ayer y el que vence en 5, sin el lote sin fecha',
    array_column($vencen, 'dias', 'id_ingreso') === [1 => -1, 3 => 5] && $vencen[0]['vencido'] && !$vencen[1]['vencido']);
revisar('Un lote que vence en 5 días no sale si se miran 3', array_column(Vencimiento::porVencer($reparto, '2026-10-28', 3), 'id_ingreso') === []);
revisar('Días de la URL: 15 vale; 0, 999 y texto usan 30', Vencimiento::leerDias('15') === 15 && Vencimiento::leerDias('0') === 30
    && Vencimiento::leerDias('999') === 30 && Vencimiento::leerDias('x') === 30 && Vencimiento::leerDias(null) === 30);
revisar('Textos: vencido hace 1 día, vence hoy, vence en 4 días', Vencimiento::describir(-1) === 'Vencido hace 1 día'
    && Vencimiento::describir(0) === 'Vence hoy' && Vencimiento::describir(4) === 'Vence en 4 días');

echo "Reposición (cuánto pedir)\n";
revisar('70 vendidos en 14 días = 5 por día', Reposicion::ventaDiaria(70000) === 5000 && Reposicion::ventaDiaria(0) === 0);
revisar('Con 3 y venta de 5 por día se piden 67 para 14 días', Reposicion::sugerido(3000, 10000, 5000, false) === 67000);
revisar('Sin ventas se pide hasta el doble del mínimo', Reposicion::sugerido(4000, 10000, 0, false) === 16000);
revisar('Por unidad se redondea hacia arriba; al peso no', Reposicion::sugerido(0, 0, 333, false) === 5000
    && Reposicion::sugerido(0, 0, 333, true) === 4662);
revisar('Días que alcanza: 10 con venta de 4 por día = 2; sin ventas, null', Reposicion::diasCobertura(10000, 4000) === 2
    && Reposicion::diasCobertura(10000, 0) === null);

echo "Roles y permisos\n";
$rosa = new Administrador(1, 'Rosa', 'Quispe', '70000001', 'ACTIVO', 1);
$luis = new Cajero(2, 'Luis', 'Caceres', '70000002', 'ACTIVO', 1);
$carmen = new Almacenero(3, 'Carmen', 'Flores', '70000003', 'ACTIVO', 1);
$cesado = new Cajero(9, 'Ana', 'Ruiz', '70000099', 'INACTIVO', 1);
revisar('Solo el cajero vende', $luis->puedeVender() && !$rosa->puedeVender() && !$carmen->puedeVender());
revisar('Un cajero INACTIVO no vende ni maneja caja', !$cesado->puedeVender() && !$cesado->puedeManejarCaja());
revisar('El administrador anula solo en su tienda', $rosa->puedeAnularVenta(1) && !$rosa->puedeAnularVenta(2));
revisar('El cajero no anula', !$luis->puedeAnularVenta(1));
revisar('Devoluciones: solo el cajero activo de la tienda de la venta', $luis->puedeDevolverVenta(1) && !$luis->puedeDevolverVenta(2)
    && !$rosa->puedeDevolverVenta(1) && !$carmen->puedeDevolverVenta(1) && !$cesado->puedeDevolverVenta(1));
revisar('Solo el almacenero mueve mercadería', $carmen->puedeMoverMercaderia() && !$luis->puedeMoverMercaderia() && !$rosa->puedeMoverMercaderia());
revisar('Conteo: almacenero y administrador de SU tienda', $carmen->puedeAjustarStock(1) && !$carmen->puedeAjustarStock(2)
    && $rosa->puedeAjustarStock(1) && !$luis->puedeAjustarStock(1));
revisar('Ingreso de mercadería: lo registra el almacenero de SU tienda', $carmen->puedeRegistrarIngreso(1) && !$carmen->puedeRegistrarIngreso(2)
    && !$rosa->puedeRegistrarIngreso(1) && !$luis->puedeRegistrarIngreso(1));
revisar('Ingresos: los ven el almacenero y el administrador de la tienda', $carmen->puedeVerIngresos(1) && $rosa->puedeVerIngresos(1)
    && !$rosa->puedeVerIngresos(2) && !$luis->puedeVerIngresos(1));
revisar('Administra la tienda solo su administrador', $rosa->puedeAdministrarTienda(1) && !$rosa->puedeAdministrarTienda(3)
    && !$carmen->puedeAdministrarTienda(1));
revisar('Catálogo y nuevas tiendas: solo administradores', $rosa->puedeAdministrarCatalogo() && $rosa->puedeAbrirTiendas()
    && !$luis->puedeAdministrarCatalogo() && !$carmen->puedeAbrirTiendas());
revisar('El DNI se muestra oculto', $rosa->dniOculto() === '*****001');
rechazaDato('DNI de 7 dígitos no', fn() => new Cajero(10, 'A', 'B', '1234567', 'ACTIVO', 1));
rechazaDato('Estado desconocido no', fn() => new Cajero(10, 'A', 'B', '12345678', 'SUSPENDIDO', 1));

echo PHP_EOL . "$ok correctas, $fallas fallas" . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
