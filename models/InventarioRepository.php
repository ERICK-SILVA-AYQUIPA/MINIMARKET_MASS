<?php
declare(strict_types=1);

require_once __DIR__ . '/MovimientoStock.php';
require_once __DIR__ . '/TrasladoMercaderia.php';
require_once __DIR__ . '/Merma.php';
require_once __DIR__ . '/AjusteInventario.php';

/**
 * Lecturas del inventario: kardex, traslados, mermas y ajustes por conteo.
 * Las escrituras estan en servicios/InventarioServicio.php (con transacciones).
 */
class InventarioRepository
{
    private const COLUMNAS_MOVIMIENTO = 'm.id_movimiento, m.fecha_hora, m.cantidad, m.stock_nuevo, m.observacion,
        m.id_tienda, m.id_producto, m.id_tipo_movimiento, m.id_empleado, m.id_venta, m.id_traslado, m.id_merma, m.id_ajuste, m.id_ingreso,
        m.id_devolucion';

    public function __construct(private PDO $db) {}

    /**
     * Una pagina del kardex de una tienda, del mas reciente al mas antiguo. Con producto, solo ese producto.
     * @return MovimientoStock[]
     */
    public function kardex(int $idTienda, ?int $idProducto, int $limite = 50, int $offset = 0): array
    {
        [$donde, $parametros] = $this->dondeKardex($idTienda, $idProducto);
        // LIMIT y OFFSET son enteros calculados en PHP
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS_MOVIMIENTO . ' FROM movimiento_stock m WHERE ' . $donde
            . ' ORDER BY m.id_movimiento DESC LIMIT ' . max(1, min($limite, 1000)) . ' OFFSET ' . max(0, $offset)
        );
        $consulta->execute($parametros);
        return array_map(fn(array $f): MovimientoStock => $this->crearMovimiento($f), $consulta->fetchAll());
    }

    public function contarKardex(int $idTienda, ?int $idProducto): int
    {
        [$donde, $parametros] = $this->dondeKardex($idTienda, $idProducto);
        $consulta = $this->db->prepare('SELECT COUNT(*) FROM movimiento_stock m WHERE ' . $donde);
        $consulta->execute($parametros);
        return (int) $consulta->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, int>} */
    private function dondeKardex(int $idTienda, ?int $idProducto): array
    {
        if ($idProducto === null) {
            return ['m.id_tienda = :tienda', ['tienda' => $idTienda]];
        }
        return ['m.id_tienda = :tienda AND m.id_producto = :producto', ['tienda' => $idTienda, 'producto' => $idProducto]];
    }

    /** @return MovimientoStock[] */
    public function movimientosDeTraslado(int $idTraslado): array
    {
        return $this->movimientosPor('m.id_traslado = :id', $idTraslado);
    }

    /** @return MovimientoStock[] */
    public function movimientosDeMerma(int $idMerma): array
    {
        return $this->movimientosPor('m.id_merma = :id', $idMerma);
    }

    /**
     * Traslados donde la tienda es origen o destino, del mas reciente al mas antiguo.
     * @return TrasladoMercaderia[]
     */
    public function trasladosDeTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            'SELECT id_traslado, fecha_hora, observacion, id_tienda_origen, id_tienda_destino, id_empleado
               FROM traslado_mercaderia
              WHERE id_tienda_origen = :origen OR id_tienda_destino = :destino
              ORDER BY id_traslado DESC'
        );
        $consulta->execute(['origen' => $idTienda, 'destino' => $idTienda]);
        return array_map(fn(array $f): TrasladoMercaderia => new TrasladoMercaderia(
            (int) $f['id_traslado'],
            $f['fecha_hora'],
            $f['observacion'],
            (int) $f['id_tienda_origen'],
            (int) $f['id_tienda_destino'],
            (int) $f['id_empleado']
        ), $consulta->fetchAll());
    }

    /**
     * Mermas de una tienda: la tienda sale del empleado que la registro.
     * @return Merma[]
     */
    public function mermasDeTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            'SELECT mm.id_merma, mm.fecha_hora, mm.motivo, mm.observacion, mm.id_empleado
               FROM merma mm
               JOIN empleado e ON e.id_empleado = mm.id_empleado
              WHERE e.id_tienda = :tienda
              ORDER BY mm.id_merma DESC'
        );
        $consulta->execute(['tienda' => $idTienda]);
        return array_map(fn(array $f): Merma => new Merma(
            (int) $f['id_merma'],
            $f['fecha_hora'],
            $f['motivo'],
            $f['observacion'],
            (int) $f['id_empleado']
        ), $consulta->fetchAll());
    }

    /** @return AjusteInventario[] ajustes de una tienda, el mas reciente primero */
    public function ajustesDeTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            'SELECT id_ajuste, fecha_hora, motivo, observacion, id_tienda, id_empleado
               FROM ajuste_inventario WHERE id_tienda = :tienda ORDER BY id_ajuste DESC'
        );
        $consulta->execute(['tienda' => $idTienda]);
        return array_map(fn(array $f): AjusteInventario => new AjusteInventario(
            (int) $f['id_ajuste'],
            $f['fecha_hora'],
            $f['motivo'],
            $f['observacion'],
            (int) $f['id_tienda'],
            (int) $f['id_empleado']
        ), $consulta->fetchAll());
    }

    /** @return MovimientoStock[] */
    public function movimientosDeAjuste(int $idAjuste): array
    {
        return $this->movimientosPor('m.id_ajuste = :id', $idAjuste);
    }

    /** @return MovimientoStock[] */
    private function movimientosPor(string $condicion, int $id): array
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS_MOVIMIENTO . ' FROM movimiento_stock m WHERE ' . $condicion . ' ORDER BY m.id_movimiento'
        );
        $consulta->execute(['id' => $id]);
        return array_map(fn(array $f): MovimientoStock => $this->crearMovimiento($f), $consulta->fetchAll());
    }

    private function crearMovimiento(array $f): MovimientoStock
    {
        return new MovimientoStock(
            (int) $f['id_movimiento'],
            $f['fecha_hora'],
            (float) $f['cantidad'],
            (float) $f['stock_nuevo'],
            $f['observacion'],
            (int) $f['id_tienda'],
            (int) $f['id_producto'],
            (int) $f['id_tipo_movimiento'],
            (int) $f['id_empleado'],
            $f['id_venta'] === null ? null : (int) $f['id_venta'],
            $f['id_traslado'] === null ? null : (int) $f['id_traslado'],
            $f['id_merma'] === null ? null : (int) $f['id_merma'],
            $f['id_ajuste'] === null ? null : (int) $f['id_ajuste'],
            $f['id_ingreso'] === null ? null : (int) $f['id_ingreso'],
            $f['id_devolucion'] === null ? null : (int) $f['id_devolucion']
        );
    }
}
