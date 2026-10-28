<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/PanelRepository.php';
require_once __DIR__ . '/../models/ReporteRepository.php';
require_once __DIR__ . '/../models/InventarioRepository.php';
require_once __DIR__ . '/../models/CajaRepository.php';
require_once __DIR__ . '/../models/ProductoRepository.php';
require_once __DIR__ . '/../models/CatalogoRepository.php';
require_once __DIR__ . '/../models/IngresoRepository.php';
require_once __DIR__ . '/../models/Vencimiento.php';
require_once __DIR__ . '/../models/Reposicion.php';

/**
 * C: panel de inicio y lista de reposicion. Cada rol ve los indicadores de SU tienda que le sirven para trabajar.
 */
class PanelController extends Controller
{
    private const DIAS_GRAFICO = 14;
    private const DIAS_POR_VENCER = 7;   // el panel avisa solo lo urgente; la pantalla "Por vencer" llega a 60

    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private PanelRepository $panel,
        private ReporteRepository $reportes,
        private InventarioRepository $inventario,
        private CajaRepository $cajas,
        private ProductoRepository $productos,
        private CatalogoRepository $catalogo,
        private IngresoRepository $ingresos
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=inicio
    public function inicio(): void
    {
        $usuario = $this->usuario;
        $tienda = $this->tiendas->buscarPorId($usuario->getIdTienda());
        $id = $tienda->getIdTienda();
        $hoy = (new DateTimeImmutable('today'))->format('Y-m-d');
        $ayer = (new DateTimeImmutable('yesterday'))->format('Y-m-d');

        $hoyResumen = $this->reportes->resumen($id, $hoy, $hoy);
        $ayerResumen = $this->reportes->resumen($id, $ayer, $ayer);
        $ventasPorDia = $this->panel->ventasPorDia($id, self::DIAS_GRAFICO);
        $porReponer = $this->panel->porReponer($id);
        $totalPorReponer = $this->panel->contarPorReponer($id);
        $turnos = $this->panel->turnosAbiertos($id);
        $miTurno = $usuario->puedeManejarCaja() ? $this->cajas->turnoAbiertoDe($usuario->getIdEmpleado()) : null;

        // Ultimos movimientos del kardex, con el nombre del producto y del tipo
        $movimientos = $this->inventario->kardex($id, null, 6);
        $productosPorId = [];
        foreach ($this->productos->todos() as $p) {
            $productosPorId[$p->getIdProducto()] = $p;
        }
        $tipos = $this->catalogo->tiposMovimiento();

        // Lotes que vencen esta semana (o ya vencieron), para quien recibe la mercaderia y su administrador
        $porVencer = [];
        $veVencimientos = $usuario->puedeVerIngresos($id);
        if ($veVencimientos) {
            foreach ($this->ingresos->lotesConVencimiento($id) as $idProducto => $datos) {
                foreach (Vencimiento::porVencer(Vencimiento::repartirStock($datos['lotes'], $datos['stock']), $hoy, self::DIAS_POR_VENCER) as $lote) {
                    $porVencer[] = $lote + ['nombre' => ($productosPorId[$idProducto] ?? null)?->getNombreProducto() ?? '-'];
                }
            }
            usort($porVencer, fn(array $a, array $b): int => $a['dias'] <=> $b['dias']);
        }

        $esAdministrador = $usuario->puedeAdministrarTienda($id);
        $this->vista('panel/inicio', get_defined_vars());
    }

    // GET /?ruta=reponer: lo que llego a su minimo, cuanto pedir y que tienda puede trasladarlo
    public function reponer(): void
    {
        if (!$this->puedeVerReposicion()) {
            $this->prohibido('La lista de reposición es para el almacenero y el administrador de la tienda.');
            return;
        }
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        $filas = $this->filasReposicion($tienda->getIdTienda());
        $diasVenta = Reposicion::DIAS_VENTA;
        $diasObjetivo = Reposicion::DIAS_OBJETIVO;
        $sinStock = count(array_filter($filas, fn(array $f): bool => $f['actual'] === 0));
        $conSobrante = count(array_filter($filas, fn(array $f): bool => $f['otras'] !== []));
        $puedeTrasladar = $this->usuario->puedeMoverMercaderia();
        $this->vista('panel/reponer', get_defined_vars());
    }

    // GET /?ruta=reponer_csv: la misma lista, para armar el pedido en Excel
    public function reponerCsv(): void
    {
        if (!$this->puedeVerReposicion()) {
            $this->prohibido('La lista de reposición es para el almacenero y el administrador de la tienda.');
            return;
        }
        $idTienda = $this->usuario->getIdTienda();
        $filas = array_map(fn(array $f): array => [
            $f['nombre'],
            $f['unidad'],
            Cantidad::aTexto($f['actual']),
            Cantidad::aTexto($f['minimo']),
            Cantidad::aTexto($f['ventaDiaria']),
            $f['cobertura'] ?? '',
            Cantidad::aTexto($f['sugerido']),
            implode('; ', array_map(fn(array $o): string => $o['tienda'] . ' ' . Cantidad::aTexto($o['sobrante']), $f['otras'])),
        ], $this->filasReposicion($idTienda));
        $this->enviarCsv(sprintf('reponer_tienda%d_%s.csv', $idTienda, date('Y-m-d')),
            ['producto', 'unidad', 'hay', 'minimo', 'venta_diaria', 'dias_que_alcanza', 'pedir', 'sobrante_en_otras_tiendas'], $filas);
    }

    /** @return array<int, array<string, mixed>> filas de PanelRepository::reposicion() con venta diaria, cobertura y sugerido */
    private function filasReposicion(int $idTienda): array
    {
        $filas = [];
        foreach ($this->panel->reposicion($idTienda, Reposicion::DIAS_VENTA) as $f) {
            $diaria = Reposicion::ventaDiaria($f['vendido']);
            $filas[] = $f + [
                'ventaDiaria' => $diaria,
                'cobertura'   => Reposicion::diasCobertura($f['actual'], $diaria),
                'sugerido'    => Reposicion::sugerido($f['actual'], $f['minimo'], $diaria, $f['al_peso']),
            ];
        }
        return $filas;
    }

    // Quien recibe mercaderia y su administrador (los mismos que ven los ingresos)
    private function puedeVerReposicion(): bool
    {
        return $this->usuario !== null && $this->usuario->puedeVerIngresos($this->usuario->getIdTienda());
    }
}
