<?php
declare(strict_types=1);

require_once __DIR__ . '/IngresoMercaderia.php';
require_once __DIR__ . '/DetalleIngreso.php';

/**
 * Lee los ingresos de mercaderia (database/ingresos.sql). Los escribe InventarioServicio::registrarIngreso().
 */
class IngresoRepository
{
    public function __construct(private PDO $db) {}

    public function contar(int $idTienda): int
    {
        $consulta = $this->db->prepare('SELECT COUNT(*) FROM ingreso_mercaderia WHERE id_tienda = :tienda');
        $consulta->execute(['tienda' => $idTienda]);
        return (int) $consulta->fetchColumn();
    }

    /** @return IngresoMercaderia[] una pagina de los ingresos de una tienda, el mas reciente primero */
    public function pagina(int $idTienda, int $limite, int $offset): array
    {
        // LIMIT y OFFSET son enteros calculados en PHP
        $consulta = $this->db->prepare(
            'SELECT id_ingreso, fecha_hora, tipo_documento, numero_documento, fecha_documento, ruc_emisor,
                    razon_social, total, observacion, id_tienda, id_empleado
               FROM ingreso_mercaderia
              WHERE id_tienda = :tienda
              ORDER BY id_ingreso DESC
              LIMIT ' . max(1, min($limite, 1000)) . ' OFFSET ' . max(0, $offset)
        );
        $consulta->execute(['tienda' => $idTienda]);
        return array_map(fn(array $f): IngresoMercaderia => new IngresoMercaderia(
            (int) $f['id_ingreso'],
            $f['fecha_hora'],
            $f['tipo_documento'],
            $f['numero_documento'],
            $f['fecha_documento'],
            $f['ruc_emisor'],
            $f['razon_social'],
            (float) $f['total'],
            $f['observacion'],
            (int) $f['id_tienda'],
            (int) $f['id_empleado']
        ), $consulta->fetchAll());
    }

    /** @return DetalleIngreso[] productos de un ingreso, en orden de producto */
    public function detalle(int $idIngreso): array
    {
        $consulta = $this->db->prepare(
            'SELECT id_ingreso, id_producto, cantidad, costo_unitario, costo_total, fecha_vencimiento
               FROM detalle_ingreso WHERE id_ingreso = :id ORDER BY id_producto'
        );
        $consulta->execute(['id' => $idIngreso]);
        return array_map(fn(array $f): DetalleIngreso => new DetalleIngreso(
            (int) $f['id_ingreso'],
            (int) $f['id_producto'],
            (float) $f['cantidad'],
            (float) $f['costo_unitario'],
            (float) $f['costo_total'],
            $f['fecha_vencimiento']
        ), $consulta->fetchAll());
    }

    /**
     * Los lotes recibidos de cada producto que alguna vez llego con fecha de vencimiento, con su stock actual.
     * Van todos los lotes del producto (tambien los sin fecha), porque el reparto FIFO los necesita.
     * @return array<int, array{stock: int, lotes: array<int, array{id_ingreso: int, recibido: string, documento: string,
     *         fecha_vencimiento: ?string, milesimas: int, costo: float}>}> id_producto => datos
     */
    public function lotesConVencimiento(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            'SELECT di.id_producto, di.id_ingreso, i.fecha_hora, i.tipo_documento, i.numero_documento,
                    di.fecha_vencimiento, di.cantidad, di.costo_unitario, s.cantidad_actual
               FROM detalle_ingreso di
               JOIN ingreso_mercaderia i ON i.id_ingreso = di.id_ingreso
               JOIN stock s ON s.id_tienda = i.id_tienda AND s.id_producto = di.id_producto
              WHERE i.id_tienda = :tienda
                AND di.id_producto IN (SELECT d2.id_producto FROM detalle_ingreso d2
                                         JOIN ingreso_mercaderia i2 ON i2.id_ingreso = d2.id_ingreso
                                        WHERE i2.id_tienda = :tienda2 AND d2.fecha_vencimiento IS NOT NULL)
              ORDER BY di.id_producto, i.fecha_hora, di.id_ingreso'
        );
        $consulta->execute(['tienda' => $idTienda, 'tienda2' => $idTienda]);
        $resultado = [];
        foreach ($consulta->fetchAll() as $f) {
            $id = (int) $f['id_producto'];
            $resultado[$id] ??= ['stock' => Cantidad::deDecimal($f['cantidad_actual']), 'lotes' => []];
            $resultado[$id]['lotes'][] = [
                'id_ingreso'        => (int) $f['id_ingreso'],
                'recibido'          => $f['fecha_hora'],
                'documento'         => $f['tipo_documento'] . ' ' . $f['numero_documento'],
                'fecha_vencimiento' => $f['fecha_vencimiento'],
                'milesimas'         => Cantidad::deDecimal($f['cantidad']),
                'costo'             => (float) $f['costo_unitario'],
            ];
        }
        return $resultado;
    }
}
