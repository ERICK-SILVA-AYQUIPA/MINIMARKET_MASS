<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/VentaRepository.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/ClienteRepository.php';
require_once __DIR__ . '/../models/ProductoRepository.php';
require_once __DIR__ . '/../models/CajaRepository.php';
require_once __DIR__ . '/../models/Paginacion.php';
require_once __DIR__ . '/../models/servicios/VentaServicio.php';

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
        private VentaServicio $servicio
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=ventas&tienda=1&desde=2026-10-01&hasta=2026-10-31&estado=EMITIDA&pagina=2
    public function lista(?string $tiendaPedida, ?string $desde, ?string $hasta, ?string $estado, ?string $pagina, ?string $ver = null): void
    {
        $tienda = $this->leerTienda($tiendaPedida);
        $tiendas = $this->tiendas->todas();
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
            $detalleVenta = $elegida !== null ? $this->datosComprobante($elegida) : null;
        }
        unset($venta, $serie, $turno, $cajero, $elegida, $partes);
        $this->vista('ventas/lista', get_defined_vars());
    }

    // GET /?ruta=comprobante&serie=B001&numero=1  (con $ticket: el formato de 80 mm para imprimir o PDF)
    public function comprobante(?string $serieTexto, ?string $numeroTexto, bool $ticket = false): void
    {
        // Validar antes de buscar: serie B o F + 3 digitos, correlativo de 1 a 8 digitos
        if ($serieTexto === null || preg_match('/^[BF][0-9]{3}$/', $serieTexto) !== 1
            || $numeroTexto === null || preg_match('/^[0-9]{1,8}$/', $numeroTexto) !== 1) {
            $this->noEncontrado('El comprobante pedido no es válido.');
            return;
        }

        $venta = $this->ventas->buscarPorComprobante($serieTexto, (int) $numeroTexto);
        if ($venta === null) {
            $this->noEncontrado('No existe un comprobante con esa serie y correlativo.');
            return;
        }

        $this->vista($ticket ? 'ventas/ticket' : 'ventas/comprobante', $this->datosComprobante($venta));
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

        $puedeAnular = !$venta->estaAnulada() && $devoluciones === [] && $this->usuario?->puedeAnularVenta($tienda->getIdTienda()) === true;
        $detalleCuadra = $venta->detalleCuadra($detalles);
        $pagosCuadran = $venta->pagosCuadran($pagos);

        unset($producto, $detalle, $turno);
        return get_defined_vars();
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

        $productos = [];
        foreach ($this->productos->todos() as $producto) {
            if ($producto->estaActivo()) {
                $productos[] = [
                    'producto' => $producto,
                    'stock'    => $this->productos->stockEnTienda($producto->getIdProducto(), $tienda->getIdTienda()),
                ];
            }
        }
        $this->vista('ventas/nueva', get_defined_vars());
    }

    // POST /?ruta=venta_registrar
    public function registrar(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeVender()) {
            $this->prohibido('Solo un cajero puede registrar ventas.');
            return;
        }
        try {
            $r = $this->servicio->registrar(
                $this->usuario,
                $this->campo($post, 'tipo'),
                $this->campo($post, 'documento'),
                $this->lineasDelFormulario($post),
                [
                    'tarjeta_monto'        => $this->campo($post, 'tarjeta_monto'),
                    'tarjeta_tipo'         => $this->campo($post, 'tarjeta_tipo'),
                    'tarjeta_marca'        => $this->campo($post, 'tarjeta_marca'),
                    'tarjeta_ultimos4'     => $this->campo($post, 'tarjeta_ultimos4'),
                    'tarjeta_autorizacion' => $this->campo($post, 'tarjeta_autorizacion'),
                    'sip_monto'            => $this->campo($post, 'sip_monto'),
                    'sip_modalidad'        => $this->campo($post, 'sip_modalidad'),
                    'sip_operacion'        => $this->campo($post, 'sip_operacion'),
                    'efectivo_recibido'    => $this->campo($post, 'efectivo_recibido'),
                ]
            );
        } catch (ReglaNegocioException $ex) {
            // Se vuelve a mostrar el formulario con lo que escribio y el error
            http_response_code(422);
            $this->nueva($post, $ex->getMessage());
            return;
        }
        $this->redirigir(
            '/?ruta=comprobante&serie=' . urlencode($r['serie']) . '&numero=' . $r['numero'],
            'Venta registrada.'
        );
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
