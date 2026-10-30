<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../../models/ConsultaMovilRepository.php';
require_once __DIR__ . '/../../models/IngresoMercaderia.php';
require_once __DIR__ . '/../../models/servicios/InventarioServicio.php';
require_once __DIR__ . '/../../models/servicios/OperacionMovilServicio.php';

/**
 * Entrada de mercaderia desde el celular: el mismo InventarioServicio::registrarIngreso() de la web
 * (documento del emisor, cantidad y costo por producto, stock + kardex INGRESO DE MERCADERIA + auditoria).
 * No es una venta ni un ajuste: queda como ingreso, con quien lo recibio y la fecha.
 */
class IngresoApiController extends ApiController
{
    public function __construct(
        ?Usuario $usuario,
        private ConsultaMovilRepository $consultas,
        private InventarioServicio $servicio,
        private OperacionMovilServicio $operaciones
    ) {
        parent::__construct($usuario);
    }

    /**
     * POST /api/v1/ingresos  (cabecera Idempotency-Key)
     * {"documento": {"tipo": "GUIA", "numero": "T001-123", "fecha": "2026-10-30", "ruc": "20100070970",
     *                "razon_social": "Alicorp S.A.A.", "observacion": ""},
     *  "lineas": [{"id_producto": 1, "cantidad": "10", "costo": "3.20", "vence": "2027-01-31"}]}
     * @return array{respuesta: array, repetida: bool}
     */
    public function registrar(array $datos, string $clave): array
    {
        $almacenero = $this->usuario();
        if (!$almacenero->puedeRegistrarIngreso($almacenero->getIdTienda())) {
            $this->prohibido('Solo el almacenero de la tienda registra el ingreso de mercadería.');
        }
        $documento = is_array($datos['documento'] ?? null) ? $datos['documento'] : [];
        $cabecera = [
            'tipo'         => $this->texto($documento, 'tipo'),
            'numero'       => $this->texto($documento, 'numero'),
            'fecha'        => $this->texto($documento, 'fecha'),
            'ruc'          => $this->texto($documento, 'ruc'),
            'razon_social' => $this->texto($documento, 'razon_social'),
            'observacion'  => $this->texto($documento, 'observacion'),
        ];
        $lineas = $this->lineas($datos, ['costo', 'vence']);

        return $this->operaciones->unaVez($almacenero, $clave, 'INGRESO', function () use ($almacenero, $cabecera, $lineas): array {
            $idIngreso = $this->servicio->registrarIngreso($almacenero, $cabecera, $lineas);
            return $this->consultas->ingreso($idIngreso);
        });
    }

    // GET /api/v1/ingresos?pagina=1: los ultimos ingresos de la tienda (con costos: almacenero y administrador)
    public function lista(?string $pagina): array
    {
        $usuario = $this->usuario();
        if (!$usuario->puedeVerIngresos($usuario->getIdTienda())) {
            $this->prohibido('Los ingresos de mercadería los ven el almacenero y el administrador de la tienda.');
        }
        $numero = $this->pagina($pagina);
        return [
            'ingresos'         => $this->consultas->ingresos($usuario->getIdTienda(), 30, ($numero - 1) * 30),
            'pagina'           => $numero,
            'tipos_documento'  => IngresoMercaderia::TIPOS_DOCUMENTO,
        ];
    }

    // GET /api/v1/ingresos/7
    public function detalle(string $id): array
    {
        $usuario = $this->usuario();
        if (!$usuario->puedeVerIngresos($usuario->getIdTienda())) {
            $this->prohibido('Los ingresos de mercadería los ven el almacenero y el administrador de la tienda.');
        }
        $ingreso = $this->consultas->ingreso($this->id($id));
        if ($ingreso === null || $ingreso['id_tienda'] !== $usuario->getIdTienda()) {
            $this->noEncontrado('El ingreso no existe en tu tienda.');
        }
        return $ingreso;
    }
}
