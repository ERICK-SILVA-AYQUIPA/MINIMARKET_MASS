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
require_once __DIR__ . '/../models/ReglaNegocioException.php';
require __DIR__ . '/../models/MovimientoCaja.php';
require __DIR__ . '/../models/Caja.php';
require __DIR__ . '/../models/CajaTurno.php';
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
require __DIR__ . '/../models/CatalogoCsv.php';
require __DIR__ . '/../../frontend/views/parciales/funciones.php';
require_once __DIR__ . '/../models/FotoProducto.php';
require __DIR__ . '/../config/Migraciones.php';
require __DIR__ . '/../models/Tienda.php';
require __DIR__ . '/../models/Emisor.php';
require __DIR__ . '/../models/AnchoTicket.php';
require __DIR__ . '/../models/facturacion/FacturacionElectronica.php';
require_once __DIR__ . '/../models/exportar/LibroExcel.php';
require_once __DIR__ . '/../models/exportar/DocumentoPdf.php';
require_once __DIR__ . '/../controllers/ReporteController.php';

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

echo "Movimientos de caja: categorías y retiro de seguridad\n";
revisar('Un retiro acepta DEPOSITO, PROVEEDOR, GASTO y OTRO', MovimientoCaja::categoriaValida('RETIRO', 'DEPOSITO')
    && MovimientoCaja::categoriaValida('RETIRO', 'PROVEEDOR') && MovimientoCaja::categoriaValida('RETIRO', 'GASTO') && MovimientoCaja::categoriaValida('RETIRO', 'OTRO'));
revisar('Un retiro no acepta SENCILLO ni un ingreso PROVEEDOR', !MovimientoCaja::categoriaValida('RETIRO', 'SENCILLO') && !MovimientoCaja::categoriaValida('INGRESO', 'PROVEEDOR'));
revisar('Tipo que no existe no tiene categorías', !MovimientoCaja::categoriaValida('PRESTAMO', 'OTRO'));
revisar('Bajo el tope no se sugiere retiro', MovimientoCaja::retiroSugerido(50000, 50000, 10000) === 0);
revisar('Sobre el tope se retira lo que pasa del fondo, en múltiplos de S/ 10: 612.40 - 100 -> 510', MovimientoCaja::retiroSugerido(61240, 50000, 10000) === 51000);
revisar('El retiro sugerido deja al menos el fondo en la gaveta', 61240 - MovimientoCaja::retiroSugerido(61240, 50000, 10000) >= 10000);
revisar('Con un fondo mayor que lo esperado no se sugiere retiro', MovimientoCaja::retiroSugerido(60000, 50000, 70000) === 0);
revisar('Comprobante vacío = null', MovimientoCaja::comprobante('  ') === null);
revisar('Comprobante en mayúsculas', MovimientoCaja::comprobante(' b001-245 ') === 'B001-245');
$rechazado = false;
try {
    MovimientoCaja::comprobante('<b>1</b>');
} catch (ReglaNegocioException) {
    $rechazado = true;
}
revisar('Comprobante con < > se rechaza', $rechazado);

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
revisar('Como clave propia, la de prueba no (ni en minúsculas)', Clave::problemaPropia('Mass2026!', '70000002') !== null && Clave::problemaPropia('mass2026!', '70000002') !== null);
revisar('Como clave propia, una de las más usadas no', Clave::problemaPropia('Password123', '70000002') !== null);
revisar('Como clave propia, no puede llevar el DNI', Clave::problemaPropia('x70000002y', '70000002') !== null);
revisar('Una clave propia buena sí', Clave::problemaPropia('Cebolla-roja7', '70000002') === null);
revisar('La clave propia también sigue la regla general', Clave::problemaPropia('corta1', '70000002') !== null);
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

echo "Catálogo en CSV (lo que guarda Excel)\n";
$csv = CatalogoCsv::leer("\xEF\xBB\xBFcodigo_barras;nombre;categoria;marca;unidad;precio_venta;al_peso\r\n"
    . "7750000000011;Leche Gloria 400 g;Lacteos;Gloria;und;4.50;N\r\n;;;;;;\r\n;Papa amarilla;Verduras;Mass;kg;3.20;S\r\n");
revisar('Lee el CSV con ; y BOM, salta filas vacías y guarda el número de línea', $csv['errores'] === []
    && array_keys($csv['filas']) === [2, 4] && $csv['filas'][4]['nombre'] === 'Papa amarilla' && $csv['filas'][2]['precio_venta'] === '4.50');
$csv = CatalogoCsv::leer("codigo_barras,nombre,categoria,marca,unidad,precio_venta,al_peso\n\"7750000000028\",\"Arroz, extra\",Abarrotes,Coste\xF1o,und,5.20,N\n");
revisar('También con comas, comillas y en ANSI (ñ de Windows)', $csv['errores'] === [] && $csv['filas'][2]['nombre'] === 'Arroz, extra'
    && $csv['filas'][2]['marca'] === 'Costeño');
revisar('Encabezado distinto: no lee nada', CatalogoCsv::leer("nombre;precio\nLeche;4.50\n")['filas'] === []);
$csv = CatalogoCsv::leer("codigo_barras;nombre;categoria;marca;unidad;precio_venta;al_peso\n123;Leche\n");
revisar('Una fila con columnas de menos se reporta con su línea', $csv['filas'] === [] && str_starts_with($csv['errores'][0], 'Línea 2:'));
revisar('Solo encabezado: avisa que no hay productos', CatalogoCsv::leer("codigo_barras;nombre;categoria;marca;unidad;precio_venta;al_peso\n")['errores'] !== []);

echo "Fechas en pantalla\n";
revisar('Fecha y hora de MySQL se muestran como dd/mm/aaaa hh:mm', fecha('2026-10-19 08:05:00') === '19/10/2026 08:05');
revisar('Fecha sola y vacía', fecha('2026-02-03') === '03/02/2026' && fecha(null) === '—' && fecha('Sigue abierto') === 'Sigue abierto');

echo "Fotos de producto\n";
revisar('Cada categoría tiene su icono y color', FotoProducto::icono('Lacteos') === 'lacteo' && FotoProducto::icono('Lácteos') === 'lacteo'
    && FotoProducto::tono('Lácteos') === 1 && FotoProducto::icono('Embutidos y huevos') === 'embutido'
    && FotoProducto::icono('Frutas y verduras') === 'fruta' && FotoProducto::tono('Bebidas') === 3);
revisar('Una categoría nueva usa el icono general y siempre el mismo color', FotoProducto::icono('Mascotas') === 'catalogo'
    && FotoProducto::tono('Mascotas') === FotoProducto::tono('MASCOTAS') && FotoProducto::tono('Mascotas') >= 1 && FotoProducto::tono('Mascotas') <= 8);
$carpetaFotos = sys_get_temp_dir() . '/mass_fotos_' . getmypid();
$fotos = new FotoProducto($carpetaFotos);
$lienzo = imagecreatetruecolor(1200, 800);
ob_start();
imagepng($lienzo);
$png = (string) ob_get_clean();
$fotos->guardar(5, $png);
$guardada = getimagesize($carpetaFotos . '/5.jpg');
revisar('Una foto de 1200x800 se guarda como JPEG de 600x400', $guardada !== false && $guardada[0] === 600 && $guardada[1] === 400 && $guardada[2] === IMAGETYPE_JPEG
    && str_starts_with((string) $fotos->url(5), '/img/productos/5.jpg?v='));
$rechazaFoto = function (string $contenido) use ($fotos): bool {
    try {
        $fotos->guardar(6, $contenido);
        return false;
    } catch (ReglaNegocioException) {
        return true;
    }
};
revisar('Un archivo que no es imagen no se guarda', $rechazaFoto('<?php echo 1; ?>') && $rechazaFoto("GIF89a<script>"));
revisar('Una foto de más de 2 MB no se guarda', $rechazaFoto(str_repeat('a', FotoProducto::MAXIMO_BYTES + 1)));
revisar('Sin foto: url null y la vista dibuja la categoría', $fotos->url(6) === null && $fotos->datos(6, 'Bebidas')['icono'] === 'bebida');
revisar('Quitar la foto borra el archivo', $fotos->borrar(5) && $fotos->url(5) === null && !$fotos->borrar(5));
@rmdir($carpetaFotos);

echo "Roles y permisos\n";
$rosa = new Administrador(1, 'Rosa', 'Quispe', '70000001', 'ACTIVO', 1);
$luis = new Cajero(2, 'Luis', 'Cáceres', '70000002', 'ACTIVO', 1);
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
revisar('Ingreso de mercadería: lo registran el almacenero y el administrador de SU tienda', $carmen->puedeRegistrarIngreso(1)
    && !$carmen->puedeRegistrarIngreso(2) && $rosa->puedeRegistrarIngreso(1) && !$rosa->puedeRegistrarIngreso(2) && !$luis->puedeRegistrarIngreso(1));
revisar('Producto nuevo al escanear: cualquier empleado activo, el cajero también', $carmen->puedeCrearProducto()
    && $rosa->puedeCrearProducto() && $luis->puedeCrearProducto() && !$cesado->puedeCrearProducto()
    && !(new Almacenero(11, 'Ex', 'Almacen', '70000098', 'INACTIVO', 1))->puedeCrearProducto());
revisar('...pero el catálogo (precios de lo que ya existe) sigue siendo solo del administrador',
    !$carmen->puedeAdministrarCatalogo() && !$luis->puedeAdministrarCatalogo());
revisar('Ingresos: los ven el almacenero y el administrador de la tienda', $carmen->puedeVerIngresos(1) && $rosa->puedeVerIngresos(1)
    && !$rosa->puedeVerIngresos(2) && !$luis->puedeVerIngresos(1));
revisar('Administra la tienda solo su administrador', $rosa->puedeAdministrarTienda(1) && !$rosa->puedeAdministrarTienda(3)
    && !$carmen->puedeAdministrarTienda(1));
revisar('Catálogo y nuevas tiendas: solo administradores', $rosa->puedeAdministrarCatalogo() && $rosa->puedeAbrirTiendas()
    && !$luis->puedeAdministrarCatalogo() && !$carmen->puedeAbrirTiendas());
revisar('El DNI se muestra oculto', $rosa->dniOculto() === '*****001');
rechazaDato('DNI de 7 dígitos no', fn() => new Cajero(10, 'A', 'B', '1234567', 'ACTIVO', 1));
rechazaDato('Estado desconocido no', fn() => new Cajero(10, 'A', 'B', '12345678', 'SUSPENDIDO', 1));

echo "Migraciones de la base\n";
revisar('Divide en sentencias', Migraciones::sentencias("SELECT 1;\nSELECT 2;") === ['SELECT 1', 'SELECT 2']);
revisar('Un ; dentro de un texto no corta', Migraciones::sentencias("INSERT INTO t VALUES ('a;b', 'it''s; ok');") === ["INSERT INTO t VALUES ('a;b', 'it''s; ok')"]);
revisar('Comentarios fuera (-- , # y /* */), aunque tengan ;', Migraciones::sentencias("-- uno; dos\n/* tres; */ SELECT 1; # cuatro;\n") === ['SELECT 1']);
revisar('DELIMITER para procedimientos', Migraciones::sentencias("DELIMITER //\nCREATE PROCEDURE p() BEGIN SELECT 1; SELECT 2; END //\nDELIMITER ;\nSELECT 3;")
    === ['CREATE PROCEDURE p() BEGIN SELECT 1; SELECT 2; END', 'SELECT 3']);
revisar('Saltos de línea de Windows', Migraciones::sentencias("SELECT 1;\r\nSELECT 2;\r\n") === ['SELECT 1', 'SELECT 2']);
revisar('La huella no cambia con CRLF (Windows) o LF (Linux)', Migraciones::huella("a\r\nb\r\n") === Migraciones::huella("a\nb\n")
    && Migraciones::huella("a\n") !== Migraciones::huella("b\n"));
$archivosMigracion = Migraciones::archivos();
$numeros = array_map(static fn(string $nombre): string => substr($nombre, 0, 3), array_keys($archivosMigracion));
revisar('Las migraciones empiezan en 013 y no repiten número', ($numeros[0] ?? '') === '013' && count($numeros) === count(array_unique($numeros)));
$sueltos = array_filter(scandir(__DIR__ . '/../database/migraciones') ?: [], static fn(string $n): bool =>
    !in_array($n, ['.', '..'], true) && !isset($archivosMigracion[$n]));
revisar('En migraciones/ solo hay archivos NNN_nombre.sql', $sueltos === []);
$compose = (string) file_get_contents(__DIR__ . '/../../docker-compose.yml');
preg_match_all('#backend/database/([a-z_]+)\.sql:/docker-entrypoint-initdb\.d/[0-9]{2}_#', $compose, $montados);
revisar('docker-compose.yml monta los 12 scripts base en el mismo orden', $montados[1] === Migraciones::BASE);
echo "Cajas libres, ocupadas e inactivas\n";
$turnoDe = fn(int $id, string $estado, int $caja, int $empleado): array =>
    ['turno' => $estado === 'ABIERTO'
        ? new CajaTurno($id, '2026-11-04 08:00:00', 100.0, null, null, $estado, $caja, $empleado)
        : new CajaTurno($id, '2026-11-04 08:00:00', 100.0, '2026-11-04 16:00:00', 150.0, $estado, $caja, $empleado, 150.0, 0.0)];
// Luis (2) tiene la caja 1 abierta; la caja 2 tuvo un turno ya cerrado; la caja 3 esta apagada
$abiertos = CajaTurno::abiertosPorCaja([$turnoDe(5, 'ABIERTO', 1, 2), $turnoDe(3, 'CERRADO', 2, 2), $turnoDe(4, 'OBSERVADO', 2, 10)]);
revisar('Solo los turnos ABIERTOS ocupan su caja', array_keys($abiertos) === [1] && $abiertos[1]['turno']->getIdEmpleado() === 2);
$caja1 = new Caja(1, 1, 'ACTIVA', 1);
$caja2 = new Caja(2, 2, 'ACTIVA', 1);
$caja3 = new Caja(3, 3, 'INACTIVA', 1);
revisar('La caja activa con turno de otro cajero está ocupada, no libre', $caja1->estadoAhora(isset($abiertos[1])) === 'ocupada');
revisar('La caja activa sin turno abierto está libre (no inactiva)', $caja2->estadoAhora(isset($abiertos[2])) === 'libre' && $caja2->estaActiva());
revisar('Solo la caja apagada por el administrador sale inactiva', $caja3->estadoAhora(false) === 'inactiva' && !$caja3->estaActiva());

echo "Descuentos y promociones\n";
require_once __DIR__ . '/../models/Descuentos.php';
$promo = fn(int $id, string $tipo, ?int $producto, ?int $pct = null, ?int $precio = null, ?int $pack = null, ?int $minimo = null, string $estado = 'ACTIVA', ?int $tienda = null): Promocion
    => new Promocion($id, 'Promo ' . $id, $tipo, $producto, $tienda, $pct, $precio, $pack, $minimo, '2026-11-01', '2026-11-30', $estado);
$leche = ['milesimas' => 3000, 'precio' => 450, 'al_peso' => false];   // 3 x S/ 4.50 = 13.50
$papa = ['milesimas' => 1250, 'precio' => 250, 'al_peso' => true];     // 1.250 kg x S/ 2.50 = 3.13
revisar('15 % de 13.50 = 2.03 (redondeo como MySQL)', $promo(1, 'PORCENTAJE', 1, 1500)->descuentoLinea(3000, 450, false) === 203);
revisar('Precio de oferta S/ 2.00 el kg: 1.250 kg ahorra 3.13 - 2.50 = 0.63', $promo(2, 'PRECIO', 8, null, 200)->descuentoLinea(1250, 250, true) === 63);
revisar('Oferta más cara que el precio normal no descuenta', $promo(3, 'PRECIO', 1, null, 500)->descuentoLinea(1000, 450, false) === 0);
revisar('Pack 3 x S/ 10.00: 7 leches = 2 packs (ahorra 2 x 3.50) y 1 suelta', $promo(4, 'PACK', 1, null, 1000, 3)->descuentoLinea(7000, 450, false) === 700);
revisar('3x2 es un pack de 3 al precio de 2', $promo(5, 'PACK', 1, null, 900, 3)->descuentoLinea(3000, 450, false) === 450);
revisar('El pack no aplica a lo que va al peso', $promo(6, 'PACK', 8, null, 500, 3)->descuentoLinea(3000, 250, true) === 0);
revisar('Promoción a toda la compra: no llega al mínimo = 0', $promo(7, 'VENTA', null, 500, null, null, 10000)->descuentoVenta(9999) === 0
    && $promo(7, 'VENTA', null, 500, null, null, 10000)->descuentoVenta(10000) === 500);
revisar('Pausada o de otra tienda no rige', !$promo(8, 'PORCENTAJE', 1, 1000, estado: 'PAUSADA')->rigeEn('2026-11-05', 1)
    && !$promo(9, 'PORCENTAJE', 1, 1000, tienda: 2)->rigeEn('2026-11-05', 1) && $promo(10, 'PORCENTAJE', 1, 1000)->rigeEn('2026-11-30', 3)
    && !$promo(10, 'PORCENTAJE', 1, 1000)->rigeEn('2026-12-01', 3));
$noValida = function (callable $crear): bool {
    try { $crear(); return false; } catch (InvalidArgumentException) { return true; }
};
revisar('No hay promociones de más de 90 % ni packs de 1', $noValida(fn() => $promo(11, 'PORCENTAJE', 1, 9500))
    && $noValida(fn() => $promo(12, 'PACK', 1, null, 100, 1)) && $noValida(fn() => $promo(13, 'VENTA', 1, 500, null, null, 0)));

$r = Descuentos::calcular([1 => $leche, 8 => $papa], [$promo(1, 'PORCENTAJE', 1, 1000), $promo(4, 'PACK', 1, null, 1000, 3)]);
revisar('Se usa la promoción que más ahorra: pack (3.50) y no 10 % (1.35)', $r['lineas'][1]['descuento'] === 350 && $r['lineas'][1]['id_promocion'] === 4);
revisar('Subtotal 16.63, descuento 3.50, total 13.13', $r['subtotal'] === 1663 && $r['descuentoTotal'] === 350 && $r['total'] === 1313);

$manual = ['unidad' => 'PORCENTAJE', 'valor' => 1000, 'motivo' => 'Empaque dañado'];
$r = Descuentos::calcular([1 => $leche, 8 => $papa], [], $manual, Cajero::TOPE_DESCUENTO);
revisar('Descuento manual 10 % de 16.63 = 1.66 repartido sin perder céntimos', $r['general']['monto'] === 166 && $r['total'] === 1497
    && $r['lineas'][1]['general'] + $r['lineas'][8]['general'] === 166 && $r['lineas'][1]['neto'] + $r['lineas'][8]['neto'] === 1497);
$r = Descuentos::calcular([1 => $leche], [$promo(7, 'VENTA', null, 2000, null, null, 0)], $manual, Cajero::TOPE_DESCUENTO);
revisar('Promoción de toda la compra y descuento manual no se suman: va el mayor (20 %)', $r['general']['origen'] === 'PROMOCION'
    && $r['general']['monto'] === 270 && $r['manualIgnorado']);
$rechazaCalculo = function (callable $calc): bool {
    try { $calc(); return false; } catch (ReglaNegocioException) { return true; }
};
revisar('El cajero no pasa su tope (10 %) ni en soles', $rechazaCalculo(fn() => Descuentos::calcular([1 => $leche], [], ['unidad' => 'PORCENTAJE', 'valor' => 1500, 'motivo' => 'x'], 1000))
    && $rechazaCalculo(fn() => Descuentos::calcular([1 => $leche], [], ['unidad' => 'MONTO', 'valor' => 136, 'motivo' => 'x'], 1000))
    && !$rechazaCalculo(fn() => Descuentos::calcular([1 => $leche], [], ['unidad' => 'MONTO', 'valor' => 135, 'motivo' => 'x'], 1000)));
revisar('Sin motivo o sin permiso (tope 0) no hay descuento manual', $rechazaCalculo(fn() => Descuentos::calcular([1 => $leche], [], ['unidad' => 'MONTO', 'valor' => 50, 'motivo' => ' '], 1000))
    && $rechazaCalculo(fn() => Descuentos::calcular([1 => $leche], [], $manual, 0)));
$rosaU = new Administrador(1, 'Rosa', 'Q', '12345678', 'ACTIVO', 1);
$luisU = new Cajero(2, 'Luis', 'R', '23456789', 'ACTIVO', 1);
revisar('Permisos: el administrador maneja promociones de su tienda y de la cadena; el cajero no, pero descuenta hasta 10 %',
    $rosaU->puedeAdministrarPromociones(1) && !$rosaU->puedeAdministrarPromociones(2) && $rosaU->puedeAdministrarPromociones(null)
    && !$luisU->puedeAdministrarPromociones(1) && $luisU->topeDescuentoManual() === 1000 && $rosaU->topeDescuentoManual() === 0);
$reparto = Descuentos::repartir(100, [1 => 500, 2 => 500, 3 => 500]);
revisar('Repartir 1.00 en 3 partes iguales: 34 + 33 + 33', array_sum($reparto) === 100 && max($reparto) === 34 && min($reparto) === 33);
echo "\nTicket: IGV, emisor y ancho del papel\n";
revisar('S/ 1.18 con IGV = 1.00 gravado + 0.18 de IGV', DesgloseIgv::valorSinIgv(118) === 100);
$d = DesgloseIgv::deLineas([350, 1290, 99]);
revisar('Gravada + IGV es siempre el total exacto', $d->getGravada() + $d->getIgv() === $d->getTotal() && $d->getTotal() === 1739);
revisar('Se calcula línea por línea: 2.97 + 10.93 + 0.84 = 14.74 gravado', $d->getGravada() === 297 + 1093 + 84 && $d->getIgv() === 1739 - 1474);
revisar('Un comprobante sin líneas da todo en cero', DesgloseIgv::deLineas([])->getTotal() === 0 && DesgloseIgv::deLineas([])->getIgv() === 0);
rechazaDato('Una línea con importe negativo', fn() => DesgloseIgv::deLineas([100, -5]));
$ningunaMenor = true;
for ($c = 0; $c <= 5000; $c++) {
    $sinIgv = DesgloseIgv::valorSinIgv($c);
    $ningunaMenor = $ningunaMenor && $sinIgv === (int) round($c / 1.18) && $sinIgv <= $c;
}
revisar('valorSinIgv redondea como round(importe / 1.18) de 0 a S/ 50', $ningunaMenor);

revisar('RUC con dígito verificador correcto', Emisor::rucValido('20600000005') && Emisor::rucValido('10123456781'));
revisar('RUC con dígito verificador equivocado', !Emisor::rucValido('20600000004'));
revisar('RUC corto, con letras o que no empieza con 10/15/16/17/20', !Emisor::rucValido('2060000000') && !Emisor::rucValido('2060000000A') && !Emisor::rucValido('30600000005'));
revisar('Emisor sin razón social no está configurado', !(new Emisor('', '20600000005'))->estaConfigurado());
revisar('Emisor con razón social y RUC válido está configurado', (new Emisor('MINIMARKET DE PRUEBA S.A.C.', '20600000005'))->estaConfigurado());
putenv('EMPRESA_RAZON_SOCIAL=MINIMARKET DE PRUEBA S.A.C.');
putenv('EMPRESA_RUC=206 0000 0005');
putenv('EMPRESA_NOMBRE_COMERCIAL');
$emisor = Emisor::desdeEntorno();
revisar('El emisor sale de .env (RUC sin espacios, nombre comercial por defecto)', $emisor->getRuc() === '20600000005'
    && $emisor->getRazonSocial() === 'MINIMARKET DE PRUEBA S.A.C.' && $emisor->getNombreComercial() === 'TIENDA MASS' && $emisor->estaConfigurado());
putenv('EMPRESA_RAZON_SOCIAL');
putenv('EMPRESA_RUC');

revisar('La dirección de ejemplo de los datos de prueba no va al ticket', (new Tienda(1, 'Mass Cayma', 'Direccion de ejemplo, Cayma', null, 'ACTIVA', 1))->direccionPendiente());
revisar('Una dirección real sí va al ticket', !(new Tienda(1, 'Mass Cayma', 'Av. Ejército 101, Cayma', null, 'ACTIVA', 1))->direccionPendiente());

putenv('TICKET_ANCHO_MM=58');
revisar('Ancho por defecto el de TICKET_ANCHO_MM (58 mm imprime 48 mm)', AnchoTicket::elegir(null)->getPapelMm() === 58 && AnchoTicket::elegir(null)->getImprimibleMm() === 48);
revisar('La caja puede pedir 80 mm (imprime 72 mm)', AnchoTicket::elegir('80')->getPapelMm() === 80 && AnchoTicket::elegir('80')->getImprimibleMm() === 72);
revisar('Un ancho que no existe usa el de .env', AnchoTicket::elegir('100')->getPapelMm() === 58 && AnchoTicket::elegir('58mm')->getPapelMm() === 58);
putenv('TICKET_ANCHO_MM=33');
revisar('Si .env tampoco trae un ancho válido, 80 mm', AnchoTicket::elegir('abc')->getPapelMm() === 80);
putenv('TICKET_ANCHO_MM');

echo "\nFacturación electrónica (OSE/PSE)\n";
$ninguno = FacturacionElectronica::crear('ninguno');
revisar('Sin proveedor el ticket es interno', $ninguno instanceof SinFacturacionElectronica && FacturacionElectronica::ticketEsInterno($ninguno));
revisar('Un proveedor desconocido no se toma como válido', FacturacionElectronica::crear('otro') instanceof SinFacturacionElectronica);
$listo = FacturacionElectronica::crear('nubefact', 'https://api.proveedor.test/ruta', str_repeat('t', 32));
revisar('Con Nubefact configurado el ticket sigue interno hasta conectar el envío a la venta',
    $listo->estaListo() && FacturacionElectronica::ticketEsInterno($listo));
revisar('Nubefact sin token o con http no está listo', !FacturacionElectronica::crear('nubefact', 'https://x.test', '')->estaListo()
    && !FacturacionElectronica::crear('nubefact', 'http://x.test', str_repeat('t', 32))->estaListo());

$boleta = new ComprobanteParaEnviar(false, 'B001', 12, '2026-11-05 10:30:00', '1', '45678912', 'CLIENTE DE PRUEBA', [
    ['codigo' => '7750001000017', 'descripcion' => 'Leche evaporada 400 g', 'alPeso' => false, 'milesimas' => 2000, 'precioCentimos' => 450, 'importeCentimos' => 900],
    ['codigo' => '8', 'descripcion' => 'Papa amarilla', 'alPeso' => true, 'milesimas' => 1250, 'precioCentimos' => 380, 'importeCentimos' => 475],
]);
rechazaDato('Una factura sin RUC del cliente', fn() => new ComprobanteParaEnviar(true, 'F001', 1, '2026-11-05', '1', '45678912', 'X', $boleta->lineas));
rechazaDato('Un comprobante sin líneas', fn() => new ComprobanteParaEnviar(false, 'B001', 1, '2026-11-05', '-', '-', 'CLIENTE VARIOS', []));
$enviado = null;
$nubefact = new NubefactProveedor('https://api.proveedor.test/ruta', str_repeat('t', 32),
    function (string $url, array $cabeceras, string $cuerpo) use (&$enviado): array {
        $enviado = ['url' => $url, 'cabeceras' => $cabeceras, 'pedido' => json_decode($cuerpo, true)];
        return ['codigo' => 200, 'cuerpo' => '{"aceptada_por_sunat":true,"sunat_description":"La Boleta numero B001-12, ha sido aceptada","codigo_hash":"abc=","enlace_del_pdf":"https://pdf.test/1"}'];
    });
$pedido = $nubefact->armarPedido($boleta);
revisar('El pedido es una boleta (tipo 2) con serie, número y fecha dd-mm-aaaa', $pedido['tipo_de_comprobante'] === 2 && $pedido['serie'] === 'B001'
    && $pedido['numero'] === 12 && $pedido['fecha_de_emision'] === '05-11-2026');
revisar('Totales del pedido = desglose del ticket (gravada 11.66, IGV 2.09, total 13.75)', $pedido['total_gravada'] === 11.66
    && $pedido['total_igv'] === 2.09 && $pedido['total'] === 13.75 && $boleta->desglose()->getGravada() === 1166);
revisar('Cada línea con su unidad SUNAT (NIU / KGM), subtotal e IGV', $pedido['items'][0]['unidad_de_medida'] === 'NIU' && $pedido['items'][1]['unidad_de_medida'] === 'KGM'
    && $pedido['items'][1]['cantidad'] === 1.25 && $pedido['items'][0]['subtotal'] + $pedido['items'][0]['igv'] === 9.0);
$respuesta = $nubefact->enviar($boleta);
revisar('Envía por POST con el token en la cabecera y lee la respuesta', $respuesta->aceptado && $respuesta->hash === 'abc=' && $respuesta->enlacePdf === 'https://pdf.test/1'
    && $enviado['url'] === 'https://api.proveedor.test/ruta' && in_array('Authorization: Token token="' . str_repeat('t', 32) . '"', $enviado['cabeceras'], true)
    && $enviado['pedido']['total'] === 13.75);
$rechaza = new NubefactProveedor('https://api.proveedor.test/ruta', str_repeat('t', 32), fn(): array => ['codigo' => 400, 'cuerpo' => '{"errors":"El numero ya existe"}']);
revisar('Un rechazo del proveedor no cuenta como aceptado', !$rechaza->enviar($boleta)->aceptado);
$caido = new NubefactProveedor('https://api.proveedor.test/ruta', str_repeat('t', 32), fn(): array => throw new RuntimeException('sin internet'));
revisar('Sin internet no rompe nada: queda pendiente para reintentar', !$caido->enviar($boleta)->aceptado);
revisar('Sin token no sale nada a internet', !(new NubefactProveedor('https://x.test', '', fn(): array => throw new LogicException('no debía enviar')))->enviar($boleta)->aceptado);
echo "Reportes: mes anterior, Excel y PDF\n";
revisar('El mes anterior de un día cualquiera es el mismo día', ReporteController::mesAnterior('2026-10-15') === '2026-09-15');
revisar('Fin de mes con fin de mes: 31/10 -> 30/09 y 30/09 -> 31/08', ReporteController::mesAnterior('2026-10-31') === '2026-09-30'
    && ReporteController::mesAnterior('2026-09-30') === '2026-08-31');
revisar('El 30 de marzo cae en el último de febrero; enero pasa a diciembre del año anterior',
    ReporteController::mesAnterior('2026-03-30') === '2026-02-28' && ReporteController::mesAnterior('2026-01-10') === '2025-12-10');
$libro = new LibroExcel();
$libro->agregarHoja('Productos más vendidos', ['Producto', 'Importe'], [['=HYPERLINK("x")', 12.5], ['Leche <Gloria> & más', '3']], ['texto', 'soles']);
$libro->agregarHoja('Productos más vendidos', ['A'], []);
$xlsx = $libro->contenido();
revisar('El Excel es un ZIP con libro, hojas y estilos', str_starts_with($xlsx, "PK\x03\x04") && str_contains($xlsx, 'xl/workbook.xml')
    && str_contains($xlsx, 'xl/worksheets/sheet2.xml') && str_contains($xlsx, 'xl/styles.xml') && str_contains($xlsx, "PK\x05\x06"));
revisar('Los textos van como texto (una fórmula no se ejecuta) y escapados; los montos como número',
    str_contains($xlsx, '<t xml:space="preserve">=HYPERLINK(&quot;x&quot;)</t>') && !str_contains($xlsx, '<f>')
    && str_contains($xlsx, 'Leche &lt;Gloria&gt; &amp; más') && str_contains($xlsx, 's="2"><v>12.5</v>') && str_contains($xlsx, 's="2"><v>3</v>'));
revisar('Dos hojas con el mismo nombre no rompen el libro', str_contains($xlsx, 'name="Productos más vendidos"') && str_contains($xlsx, 'name="Productos más vendidos 2"'));
if (class_exists('ZipArchive')) {
    $temporal = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($temporal, $xlsx);
    $zip = new ZipArchive();
    revisar('PHP abre el Excel como ZIP válido (CRC y directorio central)', $zip->open($temporal, ZipArchive::CHECKCONS) === true
        && $zip->numFiles === 7 && simplexml_load_string((string) $zip->getFromName('xl/worksheets/sheet1.xml')) !== false);
    $zip->close();
    @unlink($temporal);
}
$pdf = new DocumentoPdf('Prueba', 'Pie');
$pdf->titulo('Reporte de ventas: Mass Cayma');
$pdf->barras(['Leche', 'Pan (francés)'], [10.0, 5.0], ['S/ 10.00', 'S/ 5.00']);
$pdf->tabla([['Producto', 3, 'izq'], ['Importe', 1, 'der']], array_fill(0, 120, ['Leche \\ (Gloria)', 'S/ 1.00']));
$documento = $pdf->contenido();
preg_match_all('/\/Type \/Page\b/', $documento, $paginas);
preg_match('/startxref\n(\d+)/', $documento, $xref);
revisar('El PDF empieza y termina bien y su tabla larga sigue en otra página', str_starts_with($documento, '%PDF-1.4')
    && str_ends_with($documento, "%%EOF\n") && count($paginas[0]) >= 2 && str_contains($documento, "(P\xE1gina 1 de "));
revisar('El PDF apunta bien a su tabla xref y escapa paréntesis; las tildes van en Windows-1252',
    substr($documento, (int) ($xref[1] ?? 0), 4) === 'xref' && str_contains($documento, '(Pan \\(franc' . "\xE9" . 's\\))'));

revisar('instalar_laragon.sql está al día (php backend/scripts/generar_instalador.php)',
    str_replace("\r", '', (string) file_get_contents(__DIR__ . '/../database/instalar_laragon.sql')) === Migraciones::instalador());

echo PHP_EOL . "$ok correctas, $fallas fallas" . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
