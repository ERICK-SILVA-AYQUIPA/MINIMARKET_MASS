<?php
declare(strict_types=1);

require_once __DIR__ . '/MovimientoStock.php';
require_once __DIR__ . '/TrasladoMercaderia.php';
require_once __DIR__ . '/Merma.php';

/**
 * Lecturas del inventario: kardex, traslados y mermas.
 * Las escrituras estan en servicios/InventarioServicio.php (con transacciones).
 */
class InventarioRepository
{
    private const COLUMNAS_MOVIMIENTO = 'm.id_movimiento, m.fecha_hora, m.cantidad, m.stock_nuevo, m.observacion,
        m.id_tienda, m.id_producto, m.id_tipo_movimiento, m.id_empleado, m.id_venta, m.id_traslado, m.id_merma';

    public function __construct(private PDO $db) {}

    /**
     * Kardex de una tienda, del mas reciente al mas antiguo. Con producto, solo ese producto.
     * @return MovimientoStock[]
     */
    public function kardex(int $idTienda, ?int $idProducto, int $limite = 200): array
    {
        $sql = 'SELECT ' . self::COLUMNAS_MOVIMIENTO . ' FROM movimiento_stock m WHERE m.id_tienda = :tienda';
        $parametros = ['tienda' => $idTienda];
        if ($idProducto !== null) {
            $sql .= ' AND m.id_producto = :producto';
            $parametros['producto'] = $idProducto;
        }
        $sql .= ' ORDER BY m.id_movimiento DESC LIMIT ' . max(1, min($limite, 1000));

        $consulta = $this->db->prepare($sql);
        $consulta->execute($parametros);
        return array_map(fn(array $f): MovimientoStock => $this->crearMovimiento($f), $consulta->fetchAll());
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
            $f['id_merma'] === null ? null : (int) $f['id_merma']
        );
    }
}
