<?php
declare(strict_types=1);

/**
 * Lo que el asistente sabe de COMO se usa el sistema de la tienda: los pasos de cada tarea, con la pantalla donde
 * se hace. Sale del manual de Ayuda (views/ayuda/ayuda.php) y de docs/. Sin IA: se busca por palabras.
 * Cada guia dice quien la usa ('vende', 'almacen', 'admin' o 'todos') para no ensenarle al cajero lo que no puede hacer.
 * Es una clase sin base de datos: se prueba sola en tests/unitarios.php.
 */
final class Conocimiento
{
    // clave => [titulo, quien, palabras para encontrarla, pasos, pantalla (clave de HerramientasAsistente::PAGINAS) o null]
    private const GUIAS = [
        'abrir_caja' => ['Abrir la caja (empezar el día)', 'vende', ['abrir caja', 'abro la caja', 'abrir turno', 'empezar el dia', 'empiezo', 'fondo inicial', 'iniciar turno', 'apertura'],
            "1. Entra a Caja y elige tu caja.\n2. Cuenta el efectivo inicial (o toca «Viene exacto» si el sobre trae el fondo justo).\n3. Toca Abrir turno. Ya puedes vender.", 'caja'],
        'vender' => ['Hacer una venta', 'vende', ['como vendo', 'como hago una venta', 'como se vende', 'registrar venta', 'cobrar', 'cobro', 'boleta', 'factura', 'f9'],
            "1. Nueva venta: escanea el código o toca la foto del producto (los de peso piden los kilos).\n2. Elige boleta o factura (la factura pide RUC).\n3. Toca Cobrar: efectivo que entrega (calcula el vuelto), tarjeta o Sip.\n4. Registrar venta (o F9). Sale el comprobante para imprimir o enviar por WhatsApp o correo.", 'nueva_venta'],
        'imprimir' => ['Imprimir o enviar el comprobante', 'vende', ['imprimir', 'ticket', 'reimprimir', 'whatsapp', 'enviar comprobante', 'correo'],
            "En el comprobante toca Imprimir ticket. Para mandarlo al cliente: escribe su celular o correo y toca WhatsApp o Gmail. Una venta pasada se busca en Ventas.", 'ventas'],
        'devolucion' => ['Devolver productos de una venta', 'vende', ['devolucion', 'devolver', 'devuelve', 'cambio de producto', 'reembolso'],
            "1. Ventas: busca el comprobante.\n2. Abrir comprobante completo → Devoluciones.\n3. Marca lo que devuelve y el motivo. El stock vuelve y la caja descuenta el dinero.", 'ventas'],
        'anular' => ['Anular una venta', 'admin', ['anular', 'anulo', 'anulacion', 'cancelar venta', 'venta equivocada'],
            "1. Ventas: busca el comprobante.\n2. Abrir comprobante → escribe el motivo → Anular.\nSolo el administrador anula; queda en la Auditoría con su nombre y la hora.", 'ventas'],
        'movimiento_efectivo' => ['Sacar o meter efectivo de la caja', 'vende', ['retiro', 'retirar', 'sacar plata', 'sacar dinero', 'ingreso de efectivo', 'sencillo', 'deposito', 'movimiento de efectivo', 'pago a proveedor'],
            "Caja → Movimientos de efectivo: elige Retiro o Ingreso, la categoría (depósito, sencillo, pago a proveedor…), el monto y el motivo. Cuenta en el cierre.", 'caja'],
        'cerrar_caja' => ['Cerrar la caja (terminar el día)', 'vende', ['cerrar caja', 'cierro la caja', 'cierro caja', 'cerrar la caja', 'cerrar turno', 'cierre', 'arqueo', 'terminar el dia', 'cuadrar', 'cuadre'],
            "1. Caja → Cerrar caja.\n2. Cuenta billetes y monedas sin ver lo esperado (conteo a ciegas).\n3. Confirma. Sale el comprobante de cierre con lo esperado, lo contado y la diferencia.", 'caja'],
        'cliente' => ['Registrar o buscar un cliente', 'vende', ['registrar cliente', 'nuevo cliente', 'cliente nuevo', 'dni', 'ruc', 'datos del cliente'],
            "Clientes → Registrar cliente: nombre, DNI (o RUC si pide factura), dirección y teléfono. En la venta se busca por DNI o RUC.", 'clientes'],
        'promocion' => ['Crear una promoción', 'todos', ['promocion', 'oferta', 'descuento', 'pack', '2x1', 'combo'],
            "Promociones → Nueva promoción: porcentaje, precio especial, pack (por ejemplo 3 por S/ 10) o monto mínimo, con fechas. En la venta se aplica sola.", 'promociones'],
        'ingreso' => ['Recibir mercadería del proveedor', 'almacen', ['recibir mercaderia', 'ingreso de mercaderia', 'llego mercaderia', 'llego el proveedor', 'registrar ingreso', 'entrada de stock', 'factura del proveedor'],
            "Movimientos → Ingresos → Registrar ingreso: el documento del proveedor, cada producto con cantidad, costo y fecha de vencimiento. El stock sube al guardar.", 'ingresos'],
        'orden_compra' => ['Hacer un pedido (orden de compra)', 'almacen', ['orden de compra', 'pedido al proveedor', 'hacer pedido', 'pedir mercaderia'],
            "Productos → Reponer → Armar la orden: llena lo sugerido (lo que falta para 14 días de venta). Revisa, elige el proveedor y guarda. Al llegar, regístralo como ingreso desde la orden.", 'compras'],
        'traslado' => ['Enviar mercadería a otra tienda', 'almacen', ['traslado', 'trasladar', 'enviar a otra tienda', 'pasar mercaderia', 'otra sede'],
            "Movimientos → Traslados → Nuevo traslado: tienda de destino, productos y cantidades. El stock sale de aquí y entra allá.", 'traslados'],
        'merma' => ['Dar de baja productos (merma)', 'almacen', ['merma', 'dar de baja', 'vencido', 'roto', 'malogrado', 'botar'],
            "Movimientos → Mermas: producto, cantidad y motivo (vencido, roto, robo). El stock baja y queda en el Kardex. Lo vencido se ve en Productos → Por vencer.", 'mermas'],
        'conteo' => ['Contar el inventario (ajuste)', 'almacen', ['conteo', 'inventario fisico', 'contar stock', 'ajuste', 'ajustar stock', 'stock no cuadra'],
            "Movimientos → Conteo: escribe lo que hay en el estante; el sistema ajusta la diferencia y la deja en el Kardex con tu nombre.", 'kardex'],
        'kardex' => ['Ver de dónde salió o entró un producto', 'almacen', ['kardex', 'historial del producto', 'movimientos del producto', 'quien saco'],
            "Movimientos → Kardex: elige el producto y ves cada entrada y salida (ventas, ingresos, traslados, mermas, ajustes) con la fecha y quién lo hizo.", 'kardex'],
        'producto' => ['Crear un producto o cambiar un precio', 'admin', ['crear producto', 'nuevo producto', 'cambiar precio', 'precio de venta', 'editar producto', 'importar', 'csv', 'foto del producto', 'codigo de barras'],
            "Productos → Catálogo → Nuevo producto (o Editar en la ficha): nombre, marca, categoría, precio, código de barras y foto. Para muchos a la vez: Importar CSV.", 'catalogo'],
        'etiquetas' => ['Imprimir etiquetas QR de los productos', 'admin', ['etiqueta', 'qr', 'etiquetas qr'],
            "Catálogo → Etiquetas QR: hoja A4 con el QR, el nombre y el precio, de todo, de una categoría o de un producto. La app del celular reconoce el QR.", 'catalogo'],
        'reportes' => ['Ver reportes y utilidad', 'admin', ['reporte', 'utilidad', 'ganancia', 'excel', 'pdf', 'estadistica'],
            "Administración → Reportes (ventas por día, producto, cajero, pago) y Utilidad (lo ganado sobre el costo). Se descargan en Excel o PDF.", 'reportes'],
        'personal' => ['Registrar a un empleado', 'admin', ['empleado', 'personal', 'trabajador', 'nuevo usuario', 'cajero nuevo', 'dar acceso'],
            "Administración → Personal → Registrar: nombres, DNI (entra con él), rol, tienda, teléfono y una clave inicial que cambia en Mi clave.", 'personal'],
        'auditoria' => ['Ver quién hizo qué (auditoría)', 'admin', ['auditoria', 'quien anulo', 'quien cambio', 'quien borro'],
            "Administración → Auditoría: anulaciones, cambios de precio, de clave y de permisos, con la persona y la hora.", null],
        'clave' => ['Cambiar mi clave o entrar si me bloqueé', 'todos', ['clave', 'contrasena', 'password', 'bloqueado', 'no puedo entrar', 'olvide'],
            "Mi clave (al pie del menú) cambia tu clave. Con 5 claves erradas se espera 15 minutos. Si la olvidaste, el administrador te pone una nueva en Personal.", null],
        'sin_internet' => ['Si se cae el internet o el servidor', 'todos', ['sin internet', 'se cayo', 'no carga', 'no funciona el sistema', 'contingencia', 'luz'],
            "Las ventas siguen en la red de la tienda si el servidor está prendido. Si el servidor se apaga: anota en la hoja de contingencia y regístralas luego. Ayuda tiene el teléfono de soporte.", 'ayuda'],
        'sesion' => ['Por qué se cerró mi sesión', 'todos', ['se cerro la sesion', 'me boto', 'me saco', 'sesion'],
            "Sin usar el sistema 30 minutos, la sesión se cierra sola por seguridad. Vuelve a entrar con tu DNI y clave.", null],
        'asistente' => ['Qué puede hacer el asistente', 'todos', ['que puedes hacer', 'que sabes', 'ayuda del asistente', 'para que sirves', 'como funcionas'],
            "Respondo con los datos de la tienda: ventas, precios, stock, lo que falta, lo que vence, qué pedir, horas punta, pronóstico y cómo usar cada pantalla. No cambio datos: te doy el botón a la pantalla donde se hace.", null],
    ];

    private const RELLENO = ['como', 'donde', 'que', 'hago', 'hacer', 'se', 'puedo', 'para', 'el', 'la', 'los', 'las', 'un', 'una', 'de', 'del',
        'en', 'mi', 'me', 'y', 'a', 'es', 'por', 'cual', 'pasos', 'quiero', 'necesito', 'debo', 'tengo'];

    /** @param string[] $roles lo que puede el usuario: 'vende', 'almacen', 'admin' ('todos' siempre) */
    public function __construct(private array $roles) {}

    private const QUIEN = ['vende' => 'a quien vende en caja', 'almacen' => 'al almacenero o al administrador de la tienda',
        'admin' => 'al administrador de la tienda'];

    /**
     * Las guias que mejor responden la pregunta: la mejor y las que empatan con ella (como mucho $cuantas).
     * Si la mejor es de otro rol, devuelve solo esa con 'permitida' false (para decir quien lo hace, sin los pasos).
     * @return array<int, array{clave: string, titulo: string, pasos: string, pantalla: ?string, permitida: bool, quien: string}>
     */
    public function buscar(string $pregunta, int $cuantas = 2): array
    {
        $texto = ' ' . self::normalizar($pregunta) . ' ';
        $palabras = array_values(array_diff(explode(' ', trim($texto)), self::RELLENO, ['']));
        $puntajes = [];
        foreach (self::GUIAS as $clave => [$titulo, , $claves]) {
            $puntos = 0;
            foreach ($claves as $c) {
                if (str_contains($texto, ' ' . $c)) {
                    $puntos += 3 * count(explode(' ', $c));   // una frase entera vale mas que una palabra suelta
                }
            }
            // Palabras sueltas de la pregunta que aparecen en el titulo ("merma" en "Dar de baja productos (merma)")
            $enTitulo = ' ' . self::normalizar($titulo) . ' ';
            foreach ($palabras as $p) {
                if (mb_strlen($p) >= 4 && str_contains($enTitulo, ' ' . $p)) {
                    $puntos++;
                }
            }
            if ($puntos > 0) {
                $puntajes[$clave] = $puntos;
            }
        }
        if ($puntajes === []) {
            return [];
        }
        arsort($puntajes);
        $guias = self::GUIAS;
        $disponibles = $this->disponibles();
        $mejor = (string) array_key_first($puntajes);
        $dar = fn(string $c): array => ['clave' => $c, 'titulo' => $guias[$c][0], 'pasos' => $guias[$c][3], 'pantalla' => $guias[$c][4],
            'permitida' => isset($disponibles[$c]), 'quien' => self::QUIEN[$guias[$c][1]] ?? 'todos'];
        if (!isset($disponibles[$mejor])) {
            return [$dar($mejor)];
        }
        $empatan = array_keys(array_filter($puntajes, fn(int $p, string $c): bool => $p === $puntajes[$mejor] && isset($disponibles[$c]),
            ARRAY_FILTER_USE_BOTH));
        return array_map($dar, array_slice($empatan, 0, max(1, $cuantas)));
    }

    /** @return array<int, string> los titulos de las guias que este usuario puede usar */
    public function titulos(): array
    {
        return array_values(array_map(fn(array $g): string => $g[0], $this->disponibles()));
    }

    // ¿Pregunta como hacer algo? ("como abro la caja", "donde veo el kardex", "pasos para anular")
    public static function esComoHacer(string $pregunta): bool
    {
        $t = ' ' . self::normalizar($pregunta) . ' ';
        foreach ([' como ', ' donde ', ' pasos ', ' que hago ', ' se puede ', ' puedo ', ' por que ', ' que puedes ', ' que sabes ', ' para que sirves '] as $pista) {
            if (str_contains($t, $pista)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string, array{0: string, 1: string, 2: string[], 3: string, 4: ?string}> */
    private function disponibles(): array
    {
        return array_filter(self::GUIAS, fn(array $g): bool => $g[1] === 'todos' || in_array($g[1], $this->roles, true));
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', $texto) ?? '') ?? '');
    }
}
