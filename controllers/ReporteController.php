<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/ReporteRepository.php';

/**
 * C: reportes de venta de la tienda del administrador, en pantalla o en CSV (para Excel).
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
            default     => [['dia', 'comprobantes', 'total'], $this->reportes->porDia($id, $desde, $hasta)],
        };
        $nombre = sprintf('ventas_%s_%s_%s.csv', preg_replace('/[^a-z]/', '', (string) $tipo) ?: 'dias', $desde, $hasta);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        $salida = fopen('php://output', 'w');
        fwrite($salida, "\xEF\xBB\xBF"); // BOM: Excel abre bien las tildes
        fputcsv($salida, $encabezado, ',', '"', '');
        foreach ($filas as $fila) {
            // Un texto que empieza con = + - @ (o tabulador / retorno) se abre en Excel como formula: se le antepone un apostrofo
            fputcsv($salida, array_map(
                fn($v): string => preg_match('/^[=+\-@\t\r]/', (string) $v) === 1 ? "'" . $v : (string) $v,
                array_values($fila)
            ), ',', '"', '');
        }
        fclose($salida);
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
