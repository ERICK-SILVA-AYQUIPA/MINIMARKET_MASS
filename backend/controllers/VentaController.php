<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/VentaRepository.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/ClienteRepository.php';
require_once __DIR__ . '/../models/ProductoRepository.php';
require_once __DIR__ . '/../models/CatalogoRepository.php';
require_once __DIR__ . '/../models/FotoProducto.php';
require_once __DIR__ . '/../models/CajaRepository.php';
require_once __DIR__ . '/../models/Paginacion.php';
require_once __DIR__ . '/../models/PromocionRepository.php';
require_once __DIR__ . '/../models/servicios/VentaServicio.php';
require_once __DIR__ . '/../models/servicios/OperacionMovilServicio.php';
require_once __DIR__ . '/../models/Emisor.php';
require_once __DIR__ . '/../models/DesgloseIgv.php';
require_once __DIR__ . '/../models/AnchoTicket.php';
require_once __DIR__ . '/../models/facturacion/FacturacionElectronica.php';
require_once __DIR__ . '/../models/envio/ComprobanteParaCliente.php';
require_once __DIR__ . '/../models/envio/CorreoSmtp.php';

/**
 * C: ventas por tienda, comprobante (boleta o factura), registrar una venta, anularla o devolver parte.
 */
class VentaController extends Controller
{
    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private VentaRepository $ventas,
        private EmpleadoRepository $empleados,
        private ClienteRepository $clientes,
        private ProductoRepository $productos,
        private CajaRepository $cajas,
        private VentaServicio $servicio,
        private CatalogoRepository $catalogo,
        private PromocionRepository $promociones,
        private ?OperacionMovilServicio $operaciones = null,
        private FotoProducto $fotos = new FotoProducto()
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=ventas&tienda=1&desde=2026-10-01&hasta=2026-10-31&estado=EMITIDA&pagina=2
    public function lista(?string $tiendaPedida, ?string $desde, ?string $hasta, ?string $estado, ?string $pagina, ?string $ver = null): void
    {
        $tienda = $this->leerTienda($tiendaPedida);
        $tiendas = $this->tiendasVisibles();
        $rutaSelector = 'ventas';

        // Lo que no es valido se ignora (se muestra sin ese filtro)
        $filtro = [
            'desde'  => $this->leerFecha($desde),
            'hasta'  => $this->leerFecha($hasta),
            'estado' => in_array($estado, ['EMITIDA', 'ANULADA'], true) ? $estado : null,
        ];
        $resumen = $this->ventas->resumenVentas($tienda->getIdTienda(), $filtro);
        $paginacion = new Paginacion($resumen['cantidad'], $pagina);
        $totalEmitido = $resumen['totalEmitido'];

        $filas = [];
        $ventas = $this->ventas->buscarVentas($tienda->getIdTienda(), $filtro, $paginacion->getPorPagina(), $paginacion->offset());
        foreach ($ventas as $venta) {
            $serie = $this->ventas->buscarSerie($venta->getIdSerie());
            $turno = $this->ventas->buscarTurno($venta->getIdCajaTurno());
            $cajero = $turno !== null ? $this->empleados->buscarPorId($turno->getIdEmpleado()) : null;
            $filas[] = [
                'venta'       => $venta,
                'serie'       => $serie,
                'cajero'      => $cajero,
                'cliente'     => $this->documentoCliente($venta, $serie),
                'pagosCuadran' => $venta->pagosCuadran($this->ventas->pagosDeVenta($venta->getIdVenta())),
            ];
        }

        // Los enlaces de pagina conservan la tienda y los filtros
        $urlPagina = '/?' . http_build_query(['ruta' => 'ventas', 'tienda' => $tienda->getIdTienda()] + array_filter($filtro));

        // Detalle de un comprobante en el panel lateral (&ver=B001-12); si no existe, solo se muestra la lista
        $detalleVenta = null;
        if ($ver !== null && preg_match('/^([BF][0-9]{3})-([0-9]{1,8})$/', $ver, $partes) === 1) {
            $elegida = $this->ventas->buscarPorComprobante($partes[1], (int) $partes[2]);
            $detalleVenta = $elegida !== null && $this->esDeTiendaVisible($elegida) ? $this->datosComprobante($elegida) : null;
        }
        unset($venta, $serie, $turno, $cajero, $elegida, $partes);
        $this->vista('ventas/lista', get_defined_vars());
    }

    // GET /?ruta=comprobante&serie=B001&numero=1  (con $ticket: el ticket de 58 u 80 mm para la ticketera termica o PDF)
    public function comprobante(?string $serieTexto, ?string $numeroTexto, bool $ticket = false, ?string $ancho = null): void
    {
        // Validar antes de buscar: serie B o F + 3 digitos, correlativo de 1 a 8 digitos
        if ($serieTexto === null || preg_match('/^[BF][0-9]{3}$/', $serieTexto) !== 1
            || $numeroTexto === null || preg_match('/^[0-9]{1,8}$/', $numeroTexto) !== 1) {
            $this->noEncontrado('El comprobante pedido no es válido.');
            return;
        }

        $venta = $this->ventas->buscarPorComprobante($serieTexto, (int) $numeroTexto);
        // El cajero solo abre los comprobantes de su sucursal (como si los de otra no existieran)
        if ($venta === null || !$this->esDeTiendaVisible($venta)) {
            $this->noEncontrado('No existe un comprobante con esa serie y correlativo.');
            return;
        }

        $datos = $this->datosComprobante($venta);
        if ($ticket) {
            $datos['ancho'] = AnchoTicket::elegir($ancho);
        }
        $this->vista($ticket ? 'ventas/ticket' : 'ventas/comprobante', $datos);
    }

    // La venta es de una sucursal que este usuario puede ver (la suya; el administrador, todas)
    private function esDeTiendaVisible(Venta $venta): bool
    {
        $serie = $this->ventas->buscarSerie($venta->getIdSerie());
        return $serie !== null && $this->puedeVerTienda($serie->getIdTienda());
    }

    /**
     * Todo lo que muestra un comprobante (pagina, ticket o panel lateral de la lista de ventas).
     * @return array<string, mixed>
     */
    private function datosComprobante(Venta $venta): array
    {
        $serie = $this->ventas->buscarSerie($venta->getIdSerie());
        $tienda = $this->tiendas->buscarPorId($serie->getIdTienda());
        $turno = $this->ventas->buscarTurno($venta->getIdCajaTurno());
        $caja = $this->tiendas->buscarCaja($turno->getIdCaja());
        $cajero = $this->empleados->buscarPorId($turno->getIdEmpleado());
        $cliente = $this->documentoCliente($venta, $serie);

        $lineas = [];
        foreach ($this->ventas->detallesDeVenta($venta->getIdVenta()) as $detalle) {
            $producto = $this->productos->buscarPorId($detalle->getIdProducto());
            $lineas[] = [
                'detalle'  => $detalle,
                'producto' => $producto,
                'unidad'   => $producto !== null ? $this->productos->abreviaturaUnidad($producto->getIdUnidad()) : '-',
            ];
        }
        $detalles = array_column($lineas, 'detalle');
        $pagos = $this->ventas->pagosDeVenta($venta->getIdVenta());

        // Descuentos (migracion 020): el de cada linea con el nombre de su promocion y el de toda la venta
        $nombresPromocion = $this->promociones->nombres(array_filter(array_map(fn(DetalleVenta $d): ?int => $d->getIdPromocion(), $detalles)));
        $descuentoVenta = $this->ventas->descuentoDeVenta($venta->getIdVenta());
        $subtotal = array_sum(array_map(fn(DetalleVenta $d): int => Dinero::centimos($d->getImporte()), $detalles)) / 100;
        $descuentoProductos = array_sum(array_map(fn(DetalleVenta $d): int => Dinero::centimos($d->getDescuento()), $detalles)) / 100;

        $anulacion = $this->ventas->anulacionDeVenta($venta->getIdVenta());
        $anuladaPor = $anulacion !== null ? $this->empleados->buscarPorId($anulacion->getIdEmpleado()) : null;

        // Quien puede anular esta venta: el permiso se comprueba en PHP con cada objeto
        $puedenAnular = array_values(array_filter(
            $this->empleados->todos(),
            fn(Usuario $u): bool => $u->puedeAnularVenta($tienda->getIdTienda())
        ));

        // Devoluciones ya hechas y lo que todavia se puede devolver de cada linea
        $devoluciones = $this->ventas->devolucionesDeVenta($venta->getIdVenta());
        $devuelto = $this->ventas->cantidadesDevueltas($venta->getIdVenta());
        $nombres = [];
        $alPesoPorId = [];
        foreach ($lineas as &$linea) {
            $vendido = Cantidad::deDecimal(number_format($linea['detalle']->getCantidad(), 3, '.', ''));
            $linea['disponible'] = $vendido - ($devuelto[$linea['detalle']->getIdProducto()] ?? 0);
            $nombres[$linea['detalle']->getIdProducto()] = $linea['producto']?->getNombreProducto() ?? '-';
            $alPesoPorId[$linea['detalle']->getIdProducto()] = $linea['producto']?->esAlPeso() ?? true;
        }
        unset($linea);
        $totalDevuelto = array_sum(array_map(fn(array $d): float => $d['devolucion']->getTotal(), $devoluciones));
        $aTiempo = strtotime($venta->getFechaHora()) >= strtotime('-' . VentaServicio::DIAS_DEVOLUCION . ' days');
        $quedaPorDevolver = array_sum(array_column($lineas, 'disponible')) > 0;
        $puedeDevolver = !$venta->estaAnulada() && $aTiempo && $quedaPorDevolver
            && $this->usuario?->puedeDevolverVenta($tienda->getIdTienda()) === true
            && $this->cajas->turnoAbiertoDe($this->usuario->getIdEmpleado()) !== null;

        // Efectivo cobrado en un turno que ya cerro su arqueo: anular lo descuadraria (VentaServicio::anular lo rechaza)
        $efectivoDeTurnoCerrado = $turno !== null && !$turno->estaAbierto() && array_filter(
            $pagos, fn(PagoVenta $p): bool => $p->esValido() && $p->getIdMetodoPago() === 1
        ) !== [];
        $puedeAnular = !$venta->estaAnulada() && $devoluciones === [] && !$efectivoDeTurnoCerrado
            && $this->usuario?->puedeAnularVenta($tienda->getIdTienda()) === true;
        $detalleCuadra = $venta->detalleCuadra($detalles);
        $pagosCuadran = $venta->pagosCuadran($pagos);

        // Cabecera del ticket (.env) y op. gravada / IGV / total, linea por linea como lo pediria SUNAT
        $emisor = Emisor::desdeEntorno();
        // Sobre lo cobrado de cada linea (importe menos sus descuentos): asi el total del desglose es el de la venta
        $desglose = DesgloseIgv::deLineas(array_map(fn(DetalleVenta $d): int => Dinero::centimos($d->getNeto()), $detalles));
        // Hasta que la venta se envie a un OSE/PSE, el ticket dice que es interno y no vale ante SUNAT
        $ticketInterno = FacturacionElectronica::ticketEsInterno(FacturacionElectronica::desdeEntorno());

        // Enviar al cliente por WhatsApp o correo: el celular de su ficha ya viene escrito
        $envio = $this->paraCliente($venta, $serie, $tienda, $cliente, $lineas, $pagos, $descuentoVenta, $emisor, $ticketInterno);
        $celularCliente = $venta->getIdCliente() !== null ? $this->clientes->buscarPorId($venta->getIdCliente())?->getTelefono() : null;
        $correoDesdeServidor = CorreoSmtp::desdeEntorno() !== null;

        unset($producto, $detalle, $turno);
        return get_defined_vars();
    }

    /**
     * El comprobante en textos para el cliente (WhatsApp, correo y PDF).
     * @param array<int, array{detalle: DetalleVenta, producto: ?Producto, unidad: string}> $lineas
     * @param PagoVenta[] $pagos
     * @param ?array{monto: float} $descuentoVenta
     */
    private function paraCliente(Venta $venta, SerieComprobante $serie, Tienda $tienda, string $cliente, array $lineas, array $pagos,
        ?array $descuentoVenta, Emisor $emisor, bool $interno): ComprobanteParaCliente
    {
        $filas = [];
        foreach ($lineas as $linea) {
            $d = $linea['detalle'];
            $alPeso = $linea['producto']?->esAlPeso() ?? true;
            $filas[] = [
                'nombre'    => $linea['producto']?->getNombreProducto() ?? '(producto)',
                'cantidad'  => ($alPeso ? number_format($d->getCantidad(), 3, '.', '') . ' ' . $linea['unidad'] : (string) (int) round($d->getCantidad())),
                'precio'    => $d->getPrecioUnitario(),
                'importe'   => $d->getImporte(),
                'descuento' => $d->getDescuento(),
            ];
        }
        $validos = array_values(array_filter($pagos, fn(PagoVenta $p): bool => $p->esValido()));
        return new ComprobanteParaCliente(
            $emisor->getNombreComercial() !== '' ? $emisor->getNombreComercial() : 'TIENDA MASS',
            $tienda->getNombreTienda(),
            $tienda->getDireccion(),
            $serie->nombreComprobante(),
            $serie->formatear($venta->getNumeroCorrelativo()),
            date('d/m/Y H:i', strtotime($venta->getFechaHora()) ?: time()),
            $cliente,
            $filas,
            (float) ($descuentoVenta['monto'] ?? 0),
            $venta->getTotal(),
            array_map(fn(PagoVenta $p): array => ['metodo' => $p->getNombreMetodo(), 'monto' => $p->getMontoPagado()], $validos),
            $interno
        );
    }

    // POST /?ruta=comprobante_correo: manda el comprobante (con su PDF) al correo del cliente por el SMTP del .env
    public function enviarCorreo(array $post): void
    {
        $serieTexto = $this->campo($post, 'serie');
        $numeroTexto = $this->campo($post, 'numero');
        $venta = preg_match('/^[BF][0-9]{3}$/', $serieTexto) === 1 && preg_match('/^[0-9]{1,8}$/', $numeroTexto) === 1
            ? $this->ventas->buscarPorComprobante($serieTexto, (int) $numeroTexto) : null;
        if ($venta === null || !$this->esDeTiendaVisible($venta)) {
            $this->noEncontrado('No existe un comprobante con esa serie y correlativo.');
            return;
        }
        $url = '/?ruta=comprobante&serie=' . urlencode($serieTexto) . '&numero=' . (int) $numeroTexto . '#enviar';
        $correo = ComprobanteParaCliente::correo($this->campo($post, 'correo'));
        $smtp = CorreoSmtp::desdeEntorno();
        if ($venta->estaAnulada()) {
            $this->redirigir($url, 'Este comprobante está anulado: no se envía al cliente.', 'alerta');
            return;
        }
        if ($correo === null) {
            $this->redirigir($url, 'Escribe un correo válido del cliente (ej.: nombre@gmail.com).', 'alerta');
            return;
        }
        if ($smtp === null) {
            $this->redirigir($url, 'El correo del sistema no está configurado: usa el botón Abrir en Gmail.', 'alerta');
            return;
        }
        $envio = $this->datosComprobante($venta)['envio'];
        try {
            $smtp->enviar($correo, $envio->asunto(), $envio->texto(false), $envio->html(), [$envio->nombrePdf() => $envio->pdf()]);
        } catch (RuntimeException $ex) {
            $this->redirigir($url, 'No se pudo enviar el correo: ' . $ex->getMessage(), 'alerta');
            return;
        }
        $this->redirigir($url, 'Comprobante ' . $envio->getNumero() . ' enviado a ' . $correo . '.');
    }

    // GET /?ruta=venta_nueva: formulario del cajero (con su turno abierto)
    public function nueva(array $viejo = [], ?string $error = null): void
    {
        if ($this->usuario === null || !$this->usuario->puedeVender()) {
            $this->prohibido('Solo un cajero puede registrar ventas.');
            return;
        }
        $turno = $this->cajas->turnoAbiertoDe($this->usuario->getIdEmpleado());
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        $numeroCaja = $turno !== null ? $this->tiendas->buscarCaja($turno->getIdCaja())?->getNumeroCaja() : null;

        // Categorias que tienen algo para vender: son los filtros de la cuadricula de productos
        $nombresCategoria = [];
        foreach ($this->catalogo->categorias() as $id => $c) {
            $nombresCategoria[$id] = $c->getNombreCategoria();
        }
        $categorias = [];
        $productos = [];
        foreach ($this->productos->todos() as $producto) {
            if ($producto->estaActivo()) {
                $idCategoria = $producto->getIdCategoria();
                $categorias[$idCategoria] = $nombresCategoria[$idCategoria] ?? '';
                $productos[] = [
                    'producto' => $producto,
                    'stock'    => $this->productos->stockEnTienda($producto->getIdProducto(), $tienda->getIdTienda()),
                    'foto'     => $this->fotos->datos($producto->getIdProducto(), $nombresCategoria[$idCategoria] ?? ''),
                ];
            }
        }
        asort($categorias);
        // El lector de codigos de la venta tambien reconoce los otros codigos de cada producto (migracion 023)
        $otrosCodigos = $this->productos->otrosCodigosTodos();

        // Promociones de hoy: el cartel de oferta en cada producto y las de toda la compra arriba del cobro
        $ofertas = [];
        $promocionesVenta = [];
        $paraJs = [];
        $alPesoPorId = [];
        foreach ($productos as $fila) {
            $alPesoPorId[$fila['producto']->getIdProducto()] = $fila['producto']->esAlPeso();
        }
        foreach ($this->promociones->vigentes($tienda->getIdTienda()) as $promo) {
            $paraJs[] = ['tipo' => $promo->getTipo(), 'nombre' => $promo->getNombre(), 'producto' => $promo->getIdProducto(),
                'porcentaje' => $promo->getPorcentaje(), 'precio' => $promo->getPrecio(), 'pack' => $promo->getCantidadPack(),
                'minimo' => $promo->getMontoMinimo()];
            if ($promo->esDeVenta()) {
                $promocionesVenta[] = $promo;
            } elseif (isset($alPesoPorId[(int) $promo->getIdProducto()])) {
                $ofertas[(int) $promo->getIdProducto()][] = $promo->descripcion($alPesoPorId[(int) $promo->getIdProducto()]);
            }
        }
        $topeDescuento = $this->usuario->topeDescuentoManual();
        $datosPromociones = (string) json_encode(['promociones' => $paraJs, 'tope' => $topeDescuento]);
        // Clave unica de esta venta: si el envio se corta y se reenvia, el servidor no la registra dos veces.
        // Al volver con un error se conserva la misma (esa venta no se registro).
        $claveOperacion = $this->claveValida($this->campo($viejo, 'clave_operacion')) ?? bin2hex(random_bytes(16));
        unset($nombresCategoria, $id, $c, $producto, $idCategoria, $promo, $fila);
        $this->vista('ventas/nueva', get_defined_vars());
    }

    // POST /?ruta=venta_registrar
    public function registrar(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeVender()) {
            $this->prohibido('Solo un cajero puede registrar ventas.');
            return;
        }
        $usuario = $this->usuario;
        $registrar = fn(): array => $this->servicio->registrar(
            $usuario,
            $this->campo($post, 'tipo'),
            $this->campo($post, 'documento'),
            $this->lineasDelFormulario($post),
            [
                'medio'                => $this->campo($post, 'medio_pago'),
                'tarjeta_monto'        => $this->campo($post, 'tarjeta_monto'),
                'tarjeta_tipo'         => $this->campo($post, 'tarjeta_tipo'),
                'tarjeta_marca'        => $this->campo($post, 'tarjeta_marca'),
                'tarjeta_ultimos4'     => $this->campo($post, 'tarjeta_ultimos4'),
                'tarjeta_autorizacion' => $this->campo($post, 'tarjeta_autorizacion'),
                'sip_monto'            => $this->campo($post, 'sip_monto'),
                'sip_modalidad'        => $this->campo($post, 'sip_modalidad'),
                'sip_operacion'        => $this->campo($post, 'sip_operacion'),
                'efectivo_recibido'    => $this->campo($post, 'efectivo_recibido'),
            ],
            $this->descuentoDelFormulario($post)
        );
        $clave = $this->claveValida($this->campo($post, 'clave_operacion'));
        $repetida = false;
        try {
            if ($clave !== null && $this->operaciones !== null) {
                // La clave y la venta se guardan en la misma transaccion: o quedan las dos o ninguna
                $resultado = $this->operaciones->unaVez($usuario, $clave, 'VENTA', fn(): array => [
                    'origen' => 'web',
                ] + array_intersect_key($registrar(), ['serie' => true, 'numero' => true]));
                $r = $resultado['respuesta'];
                $repetida = $resultado['repetida'];
            } else {
                $r = $registrar();
            }
        } catch (ReglaNegocioException $ex) {
            // Se vuelve a mostrar el formulario con lo que escribio y el error
            http_response_code(422);
            $this->nueva($post, $ex->getMessage());
            return;
        }
        if (!is_string($r['serie'] ?? null) || !is_numeric($r['numero'] ?? null)) {
            // La clave ya se uso en la app para otra venta (no deberia pasar: cada formulario trae una nueva)
            http_response_code(422);
            $this->nueva(['clave_operacion' => ''] + $post, 'No se pudo confirmar esta venta. Revisa Ventas antes de cobrar otra vez.');
            return;
        }
        $this->redirigir(
            // &registrada=: app.js borra la copia de esta venta que guardaba el navegador por si se cortaba la red
            '/?ruta=comprobante&serie=' . urlencode($r['serie']) . '&numero=' . (int) $r['numero']
                . ($clave !== null ? '&registrada=' . $clave : ''),
            $repetida ? 'Esta venta ya estaba registrada (se envió dos veces): no se cobró ni se descontó el stock otra vez.' : 'Venta registrada.',
            $repetida ? 'alerta' : 'ok'
        );
    }

    /** @return array{valor: string, unidad: string, motivo: string} */
    private function descuentoDelFormulario(array $datos): array
    {
        return [
            'valor'  => $this->campo($datos, 'descuento_valor'),
            'unidad' => $this->campo($datos, 'descuento_unidad'),
            'motivo' => $this->campo($datos, 'descuento_motivo'),
        ];
    }

    /**
     * GET /?ruta=venta_estado&clave=...  (JSON, la usa app.js al volver la conexion)
     * Dice si la venta con esa clave quedo registrada: asi el cajero sabe si la cobro o si debe registrarla.
     */
    public function estado(?string $clave): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        if ($this->usuario === null || !$this->usuario->puedeVender() || $this->operaciones === null) {
            http_response_code(403);
            echo json_encode(['error' => 'Solo un cajero puede consultar sus ventas.']);
            return;
        }
        $clave = $this->claveValida($clave ?? '');
        $respuesta = $clave !== null ? $this->operaciones->respuestaDe($this->usuario, $clave, 'VENTA') : null;
        if ($respuesta === null) {
            echo json_encode(['registrada' => false]);
            return;
        }
        // La app guarda la respuesta completa ("B001-12"); la web guarda serie y numero
        $serie = $respuesta['serie'] ?? null;
        $numero = $respuesta['numero'] ?? null;
        if (!is_string($serie) && is_string($respuesta['comprobante'] ?? null)
            && preg_match('/^([BF][0-9]{3})-([0-9]{1,8})$/', $respuesta['comprobante'], $m) === 1) {
            [$serie, $numero] = [$m[1], $m[2]];
        }
        $numero = is_numeric($numero) ? (int) $numero : 0;
        echo json_encode([
            'registrada'  => true,
            'comprobante' => is_string($serie) ? sprintf('%s-%08d', $serie, $numero) : null,
            'url'         => is_string($serie) ? '/?ruta=comprobante&serie=' . urlencode($serie) . '&numero=' . $numero : null,
        ]);
    }

    // La clave que manda el formulario (32 hex de random_bytes); otra cosa se ignora
    private function claveValida(string $clave): ?string
    {
        return preg_match('/^[A-Za-z0-9-]{8,64}$/', $clave) === 1 ? $clave : null;
    }

    // POST /?ruta=venta_anular (desde el comprobante)
    public function anular(array $post): void
    {
        $serie = $this->campo($post, 'serie');
        $numero = $this->campo($post, 'numero');
        if ($this->usuario === null || preg_match('/^[BF][0-9]{3}$/', $serie) !== 1 || preg_match('/^[0-9]{1,8}$/', $numero) !== 1) {
            $this->noEncontrado('El comprobante pedido no es válido.');
            return;
        }
        $venta = $this->ventas->buscarPorComprobante($serie, (int) $numero);
        if ($venta === null) {
            $this->noEncontrado('No existe un comprobante con esa serie y correlativo.');
            return;
        }
        $url = '/?ruta=comprobante&serie=' . urlencode($serie) . '&numero=' . (int) $numero;
        $idTienda = $this->ventas->buscarSerie($venta->getIdSerie())->getIdTienda();
        if (!$this->usuario->puedeAnularVenta($idTienda)) {
            $this->prohibido('Solo el administrador de la tienda de la venta puede anularla.');
            return;
        }
        try {
            $this->servicio->anular($this->usuario, $venta->getIdVenta(), $this->campo($post, 'motivo'));
        } catch (ReglaNegocioException $ex) {
            $this->sesion->avisar('error', $ex->getMessage());
            header('Location: ' . $url, true, 303);
            return;
        }
        $this->redirigir($url, 'Venta anulada: se devolvió el stock y se anularon sus pagos.');
    }

    // POST /?ruta=venta_devolver (desde el comprobante): el cliente devuelve parte de lo que compro
    public function devolver(array $post): void
    {
        $serie = $this->campo($post, 'serie');
        $numero = $this->campo($post, 'numero');
        if ($this->usuario === null || preg_match('/^[BF][0-9]{3}$/', $serie) !== 1 || preg_match('/^[0-9]{1,8}$/', $numero) !== 1) {
            $this->noEncontrado('El comprobante pedido no es válido.');
            return;
        }
        $venta = $this->ventas->buscarPorComprobante($serie, (int) $numero);
        if ($venta === null) {
            $this->noEncontrado('No existe un comprobante con esa serie y correlativo.');
            return;
        }
        $url = '/?ruta=comprobante&serie=' . urlencode($serie) . '&numero=' . (int) $numero;
        $idTienda = $this->ventas->buscarSerie($venta->getIdSerie())->getIdTienda();
        if (!$this->usuario->puedeDevolverVenta($idTienda)) {
            $this->prohibido('Solo un cajero de la tienda de la venta registra devoluciones.');
            return;
        }
        try {
            $total = $this->servicio->devolver($this->usuario, $venta->getIdVenta(), $this->lineasDelFormulario($post), $this->campo($post, 'motivo'));
        } catch (ReglaNegocioException $ex) {
            $this->sesion->avisar('error', $ex->getMessage());
            header('Location: ' . $url, true, 303);
            return;
        }
        $this->redirigir($url, 'Devolución registrada: entrega S/ ' . Dinero::aTexto($total) . ' al cliente. El producto volvió al stock.');
    }

    // Como la columna `cliente` de vw_comprobante: sin cliente es CLIENTE VARIOS
    private function documentoCliente(Venta $venta, SerieComprobante $serie): string
    {
        $idCliente = $venta->getIdCliente();
        $cliente = $idCliente !== null ? $this->clientes->buscarPorId($idCliente) : null;
        return $cliente?->documentoParaComprobante($serie->esFactura()) ?? 'CLIENTE VARIOS';
    }
}
