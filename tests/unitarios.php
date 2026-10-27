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

echo "Roles y permisos\n";
$rosa = new Administrador(1, 'Rosa', 'Quispe', '70000001', 'ACTIVO', 1);
$luis = new Cajero(2, 'Luis', 'Caceres', '70000002', 'ACTIVO', 1);
$carmen = new Almacenero(3, 'Carmen', 'Flores', '70000003', 'ACTIVO', 1);
$cesado = new Cajero(9, 'Ana', 'Ruiz', '70000099', 'INACTIVO', 1);
revisar('Solo el cajero vende', $luis->puedeVender() && !$rosa->puedeVender() && !$carmen->puedeVender());
revisar('Un cajero INACTIVO no vende ni maneja caja', !$cesado->puedeVender() && !$cesado->puedeManejarCaja());
revisar('El administrador anula solo en su tienda', $rosa->puedeAnularVenta(1) && !$rosa->puedeAnularVenta(2));
revisar('El cajero no anula', !$luis->puedeAnularVenta(1));
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
