<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/VentaRepository.php';

/**
 * C: las 3 tiendas con su distrito, cajas, series de comprobante y personal.
 */
class TiendaController extends Controller
{
    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private EmpleadoRepository $empleados,
        private VentaRepository $ventas
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=tiendas
    public function lista(): void
    {
        $filas = [];
        foreach ($this->tiendas->todas() as $idTienda => $tienda) {
            $filas[] = [
                'tienda'    => $tienda,
                'distrito'  => $this->tiendas->nombreDistrito($tienda->getIdDistrito()) ?? '-',
                'cajas'     => $this->tiendas->cajasDeTienda($idTienda),
                'series'    => $this->ventas->seriesDeTienda($idTienda),
                'empleados' => $this->empleados->deTienda($idTienda),
            ];
        }

        $this->vista('tiendas/lista', get_defined_vars());
    }
}
