<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/VentaRepository.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/ClienteRepository.php';
require_once __DIR__ . '/../models/ProductoRepository.php';

/**
 * C: ventas por tienda y comprobante (boleta o factura) por serie y correlativo.
 */
class VentaController extends Controller
{
    public function __construct(
        TiendaRepository $tiendas,
        private VentaRepository $ventas,
        private EmpleadoRepository $empleados,
        private ClienteRepository $clientes,
        private ProductoRepository $productos
    ) {
        parent::__construct($tiendas);
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

        require __DIR__ . '/../views/ventas/lista.php';
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

        $detalleCuadra = $venta->detalleCuadra($detalles);
        $pagosCuadran = $venta->pagosCuadran($pagos);

        require __DIR__ . '/../views/ventas/comprobante.php';
    }

    // Como la columna `cliente` de vw_comprobante: sin cliente es CLIENTE VARIOS
    private function documentoCliente(Venta $venta, SerieComprobante $serie): string
    {
        $idCliente = $venta->getIdCliente();
        $cliente = $idCliente !== null ? $this->clientes->buscarPorId($idCliente) : null;
        return $cliente?->documentoParaComprobante($serie->esFactura()) ?? 'CLIENTE VARIOS';
    }
}
