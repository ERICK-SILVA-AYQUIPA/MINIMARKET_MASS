<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/ReporteRepository.php';

/**
 * C: reportes de venta y de utilidad de la tienda del administrador, en pantalla o en CSV (para Excel).
 */
class ReporteController extends Controller
{
    private const DIAS_POR_DEFECTO = 30;
    private const DIAS_MAXIMO = 366;

    public function __construct(TiendaRepository $tiendas, Sesion $sesion, ?Usuario $usuario, private ReporteRepository $reportes)
    {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=reportes&desde=2026-10-01&hasta=2026-10-31
    public function ventas(?string $desdePedido, ?string $hastaPedido): void
    {
        if (!$this->puedeVer()) {
            $this->prohibido('Los reportes son para el administrador de la tienda.');
            return;
        }
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        [$desde, $hasta, $error] = $this->rango($desdePedido, $hastaPedido);
        $id = $tienda->getIdTienda();

        $resumen = $this->reportes->resumen($id, $desde, $hasta);
        $porDia = $this->reportes->porDia($id, $desde, $hasta);
        $porTipo = $this->reportes->porTipoComprobante($id, $desde, $hasta);
        $masVendidos = $this->reportes->masVendidos($id, $desde, $hasta);
        $porCategoria = $this->reportes->porCategoria($id, $desde, $hasta);
        $porCajero = $this->reportes->porCajero($id, $desde, $hasta);
        $porMetodo = $this->reportes->porMetodoPago($id, $desde, $hasta);
        $this->vista('reportes/ventas', get_defined_vars());
    }

    // GET /?ruta=utilidad&desde=2026-10-01&hasta=2026-10-31: ventas netas menos costo, por producto y por tienda
    public function utilidad(?string $desdePedido, ?string $hastaPedido): void
    {
        if (!$this->puedeVer()) {
            $this->prohibido('Los reportes son para el administrador de la tienda.');
            return;
        }
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        [$desde, $hasta, $error] = $this->rango($desdePedido, $hastaPedido);
        $productos = $this->reportes->utilidadPorProducto($tienda->getIdTienda(), $desde, $hasta);
        $conCosto = array_filter($productos, fn(array $p): bool => $p['costo'] !== null);
        $ventasConCosto = round(array_sum(array_column($conCosto, 'ventas')), 2);
        $totales = [
            'ventas'   => round(array_sum(array_column($productos, 'ventas')), 2),
            'costo'    => round(array_sum(array_column($conCosto, 'costo')), 2),
            'utilidad' => round(array_sum(array_column($conCosto, 'utilidad')), 2),
            'sinCosto' => count($productos) - count($conCosto),
        ];
        $totales['margen'] = $ventasConCosto > 0 ? round($totales['utilidad'] / $ventasConCosto * 100, 1) : null;

        // Comparacion entre tiendas: el administrador tambien abre tiendas, asi que ve la cadena completa
        $porTienda = [];
        if ($this->usuario->puedeAbrirTiendas()) {
            $nombres = [];
            foreach ($this->tiendas->todas() as $id => $t) {
                $nombres[$id] = $t->getNombreTienda();
            }
            $porTienda = $this->reportes->utilidadPorTienda($nombres, $desde, $hasta);
        }
        unset($conCosto);
        $this->vista('reportes/utilidad', get_defined_vars());
    }

    // GET /?ruta=reporte_csv&tipo=productos&desde=...&hasta=...
    public function csv(?string $tipo, ?string $desdePedido, ?string $hastaPedido): void
    {
        if (!$this->puedeVer()) {
            $this->prohibido('Los reportes son para el administrador de la tienda.');
            return;
        }
        [$desde, $hasta] = $this->rango($desdePedido, $hastaPedido);
        $id = $this->usuario->getIdTienda();
        [$encabezado, $filas] = match ($tipo) {
            'productos' => [['producto', 'unidad', 'cantidad', 'importe'], $this->reportes->masVendidos($id, $desde, $hasta, 100)],
            'cajeros'   => [['cajero', 'comprobantes', 'total'], $this->reportes->porCajero($id, $desde, $hasta)],
            'metodos'   => [['metodo', 'pagos', 'monto'], $this->reportes->porMetodoPago($id, $desde, $hasta)],
            'utilidad'  => [['producto', 'unidad', 'cantidad_neta', 'ventas_netas', 'costo_unitario', 'costo', 'utilidad', 'margen_%'],
                array_map(fn(array $p): array => [$p['producto'], $p['unidad'], $p['cantidad'], $p['ventas'], $p['costo_unitario'] ?? '',
                    $p['costo'] ?? '', $p['utilidad'] ?? '', $p['margen'] ?? ''], $this->reportes->utilidadPorProducto($id, $desde, $hasta))],
            default     => [['dia', 'comprobantes', 'total'], $this->reportes->porDia($id, $desde, $hasta)],
        };
        $nombre = sprintf('ventas_%s_%s_%s.csv', preg_replace('/[^a-z]/', '', (string) $tipo) ?: 'dias', $desde, $hasta);
        $this->enviarCsv($nombre, $encabezado, $filas);
    }

    /**
     * Rango de fechas pedido, o los ultimos 30 dias. Maximo un año, para no pedir consultas enormes.
     * @return array{0: string, 1: string, 2: ?string} desde, hasta y un aviso si se corrigio algo
     */
    private function rango(?string $desdePedido, ?string $hastaPedido): array
    {
        $hoy = new DateTimeImmutable('today');
        $hasta = $this->leerFecha($hastaPedido) ?? $hoy->format('Y-m-d');
        $desde = $this->leerFecha($desdePedido)
            ?? (new DateTimeImmutable($hasta))->modify('-' . self::DIAS_POR_DEFECTO . ' days')->format('Y-m-d');
        $error = null;
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
            $error = 'La fecha inicial era posterior a la final: se invirtieron.';
        }
        $limite = (new DateTimeImmutable($hasta))->modify('-' . self::DIAS_MAXIMO . ' days')->format('Y-m-d');
        if ($desde < $limite) {
            $desde = $limite;
            $error = 'El rango máximo es de un año.';
        }
        return [$desde, $hasta, $error];
    }

    private function puedeVer(): bool
    {
        return $this->usuario !== null && $this->usuario->puedeAdministrarTienda($this->usuario->getIdTienda());
    }
}
