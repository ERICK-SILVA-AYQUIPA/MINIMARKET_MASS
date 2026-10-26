<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/VentaRepository.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/ClienteRepository.php';
require_once __DIR__ . '/../models/ProductoRepository.php';
require_once __DIR__ . '/../models/CajaRepository.php';
require_once __DIR__ . '/../models/servicios/VentaServicio.php';

/**
 * C: ventas por tienda, comprobante (boleta o factura), registrar una venta y anularla.
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

    // GET /?ruta=ventas&tienda=1
    public function lista(?string $tiendaPedida): void
    {
        $tienda = $this->leerTienda($tiendaPedida);
        $tiendas = $this->tiendas->todas();
        $rutaSelector = 'ventas';

        $filas = [];
        $totalEmitido = 0.0;
        foreach ($this->ventas->ventasDeTienda($tienda->getIdTienda()) as $venta) {
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
            if (!$venta->estaAnulada()) {
                $totalEmitido += $venta->getTotal();
            }
        }

        $this->vista('ventas/lista', get_defined_vars());
    }

    // GET /?ruta=comprobante&serie=B001&numero=1
    public function comprobante(?string $serieTexto, ?string $numeroTexto): void
    {
        // Validar antes de buscar: serie B o F + 3 digitos, correlativo de 1 a 8 digitos
        if ($serieTexto === null || preg_match('/^[BF][0-9]{3}$/', $serieTexto) !== 1
            || $numeroTexto === null || preg_match('/^[0-9]{1,8}$/', $numeroTexto) !== 1) {
            $this->noEncontrado('El comprobante pedido no es valido.');
            return;
        }

        $venta = $this->ventas->buscarPorComprobante($serieTexto, (int) $numeroTexto);
        if ($venta === null) {
            $this->noEncontrado('No existe un comprobante con esa serie y correlativo.');
            return;
        }

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

        $puedeAnular = !$venta->estaAnulada() && $this->usuario?->puedeAnularVenta($tienda->getIdTienda()) === true;
        $detalleCuadra = $venta->detalleCuadra($detalles);
        $pagosCuadran = $venta->pagosCuadran($pagos);

        $this->vista('ventas/comprobante', get_defined_vars());
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
            $this->noEncontrado('El comprobante pedido no es valido.');
            return;
        }
        $venta = $this->ventas->buscarPorComprobante($serie, (int) $numero);
        if ($venta === null) {
            $this->noEncontrado('No existe un comprobante con esa serie y correlativo.');
            return;
        }
        $url = '/?ruta=comprobante&serie=' . urlencode($serie) . '&numero=' . (int) $numero;
        try {
            $this->servicio->anular($this->usuario, $venta->getIdVenta(), $this->campo($post, 'motivo'));
        } catch (ReglaNegocioException $ex) {
            $this->sesion->avisar('error', $ex->getMessage());
            header('Location: ' . $url, true, 303);
            return;
        }
        $this->redirigir($url, 'Venta anulada: se devolvió el stock y se anularon sus pagos.');
    }

    // Como la columna `cliente` de vw_comprobante: sin cliente es CLIENTE VARIOS
    private function documentoCliente(Venta $venta, SerieComprobante $serie): string
    {
        $idCliente = $venta->getIdCliente();
        $cliente = $idCliente !== null ? $this->clientes->buscarPorId($idCliente) : null;
        return $cliente?->documentoParaComprobante($serie->esFactura()) ?? 'CLIENTE VARIOS';
    }
}
