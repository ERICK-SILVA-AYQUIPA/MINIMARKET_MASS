<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/PanelRepository.php';
require_once __DIR__ . '/../models/ReporteRepository.php';
require_once __DIR__ . '/../models/InventarioRepository.php';
require_once __DIR__ . '/../models/CajaRepository.php';
require_once __DIR__ . '/../models/ProductoRepository.php';
require_once __DIR__ . '/../models/CatalogoRepository.php';

/**
 * C: panel de inicio. Cada rol ve los indicadores de SU tienda que le sirven para trabajar.
 */
class PanelController extends Controller
{
    private const DIAS_GRAFICO = 14;

    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private PanelRepository $panel,
        private ReporteRepository $reportes,
        private InventarioRepository $inventario,
        private CajaRepository $cajas,
        private ProductoRepository $productos,
        private CatalogoRepository $catalogo
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

        $esAdministrador = $usuario->puedeAdministrarTienda($id);
        $this->vista('panel/inicio', get_defined_vars());
    }
}
