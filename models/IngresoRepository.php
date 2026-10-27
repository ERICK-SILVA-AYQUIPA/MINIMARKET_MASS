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
            'SELECT id_ingreso, id_producto, cantidad, costo_unitario, costo_total
               FROM detalle_ingreso WHERE id_ingreso = :id ORDER BY id_producto'
        );
        $consulta->execute(['id' => $idIngreso]);
        return array_map(fn(array $f): DetalleIngreso => new DetalleIngreso(
            (int) $f['id_ingreso'],
            (int) $f['id_producto'],
            (float) $f['cantidad'],
            (float) $f['costo_unitario'],
            (float) $f['costo_total']
        ), $consulta->fetchAll());
    }
}
