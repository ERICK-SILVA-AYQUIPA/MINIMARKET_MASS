<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/ClienteRepository.php';
require_once __DIR__ . '/../models/VentaRepository.php';
require_once __DIR__ . '/../models/Paginacion.php';
require_once __DIR__ . '/../models/servicios/ClienteServicio.php';

/**
 * C: clientes (DNI y RUC), sus compras y completar el documento que les falta.
 * El DNI y el RUC son datos personales: solo los ven el cajero y el administrador.
 */
class ClienteController extends Controller
{
    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private ClienteRepository $clientes,
        private VentaRepository $ventas,
        private ClienteServicio $servicio
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=clientes&documento=7012&pagina=2
    public function lista(?string $documento, ?string $pagina): void
    {
        if (!$this->puedeVerClientes()) {
            $this->prohibido('Los clientes los ven el cajero y el administrador.');
            return;
        }
        // Solo digitos: el inicio de un DNI o de un RUC
        $documento = $documento !== null && preg_match('/^[0-9]{1,11}$/', $documento) === 1 ? $documento : null;
        $paginacion = new Paginacion($this->clientes->contar($documento), $pagina);
        $filas = $this->clientes->buscar($documento, $paginacion->getPorPagina(), $paginacion->offset());
        $urlPagina = '/?' . http_build_query(['ruta' => 'clientes', 'documento' => $documento]);
        $this->vista('clientes/lista', get_defined_vars());
    }

    // GET /?ruta=cliente&id=2
    public function detalle(?string $id, ?string $error = null): void
    {
        if (!$this->puedeVerClientes()) {
            $this->prohibido('Los clientes los ven el cajero y el administrador.');
            return;
        }
        $cliente = $this->clientes->buscarPorId($this->leerId($id));
        if ($cliente === null) {
            $this->noEncontrado('El cliente no existe.');
            return;
        }
        $compras = [];
        foreach ($this->ventas->ventasDeCliente($cliente->getIdCliente()) as $venta) {
            $serie = $this->ventas->buscarSerie($venta->getIdSerie());
            $compras[] = ['venta' => $venta, 'serie' => $serie, 'tienda' => $this->tiendas->buscarPorId($serie->getIdTienda())];
        }
        $this->vista('clientes/detalle', get_defined_vars());
    }

    // POST /?ruta=cliente_documento
    public function completarDocumento(array $post): void
    {
        if (!$this->puedeVerClientes()) {
            $this->prohibido('Los clientes los ven el cajero y el administrador.');
            return;
        }
        $id = $this->campo($post, 'id');
        try {
            $this->servicio->completarDocumento($this->usuario, $this->leerId($id), $this->campo($post, 'dni'), $this->campo($post, 'ruc'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->detalle($id, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=cliente&id=' . $this->leerId($id), 'Documento agregado.');
    }

    private function puedeVerClientes(): bool
    {
        return $this->usuario !== null
            && ($this->usuario->puedeVender() || $this->usuario->puedeAdministrarTienda($this->usuario->getIdTienda()));
    }
}
