<?php
declare(strict_types=1);

/**
 * Indicadores del panel de inicio de una tienda: lo que hay que mirar al empezar el dia.
 * Devuelve filas simples (son resumenes, no filas de una tabla).
 */
class PanelRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Productos que llegaron a su stock minimo (como Stock::necesitaReponer y la consulta 4),
     * los mas urgentes primero: los que tienen menos en proporcion a su minimo.
     * @return array<int, array{id_producto: string, nombre_producto: string, abreviatura: string, cantidad_actual: string, stock_minimo: string}>
     */
    public function porReponer(int $idTienda, int $limite = 8): array
    {
        $consulta = $this->db->prepare(
            "SELECT p.id_producto, p.nombre_producto, u.abreviatura, s.cantidad_actual, s.stock_minimo
               FROM stock s
               JOIN producto p      ON p.id_producto = s.id_producto
               JOIN unidad_medida u ON u.id_unidad = p.id_unidad
              WHERE s.id_tienda = :tienda AND p.estado = 'ACTIVO' AND s.cantidad_actual <= s.stock_minimo
              ORDER BY s.cantidad_actual / NULLIF(s.stock_minimo, 0), p.nombre_producto
              LIMIT " . max(1, min($limite, 50))
        );
        $consulta->execute(['tienda' => $idTienda]);
        return $consulta->fetchAll();
    }

    public function contarPorReponer(int $idTienda): int
    {
        $consulta = $this->db->prepare(
            "SELECT COUNT(*) FROM stock s JOIN producto p ON p.id_producto = s.id_producto
              WHERE s.id_tienda = :tienda AND p.estado = 'ACTIVO' AND s.cantidad_actual <= s.stock_minimo"
        );
        $consulta->execute(['tienda' => $idTienda]);
        return (int) $consulta->fetchColumn();
    }

    /**
     * Turnos ABIERTOS de la tienda con quien los atiende y lo cobrado en efectivo hasta ahora.
     * @return array<int, array{numero_caja: string, cajero: string, fecha_hora_apertura: string, monto_inicial: string, ventas: string, efectivo: string}>
     */
    public function turnosAbiertos(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            "SELECT c.numero_caja, CONCAT(e.nombres, ' ', e.apellidos) AS cajero, ct.fecha_hora_apertura, ct.monto_inicial,
                    (SELECT COUNT(*) FROM venta v WHERE v.id_caja_turno = ct.id_caja_turno AND v.estado = 'EMITIDA') AS ventas,
                    COALESCE((SELECT SUM(pv.monto_pagado) FROM venta v
                                JOIN pago_venta pv ON pv.id_venta = v.id_venta
                               WHERE v.id_caja_turno = ct.id_caja_turno AND pv.estado = 'VALIDO' AND pv.id_metodo_pago = 1), 0) AS efectivo
               FROM caja_turno ct
               JOIN caja c     ON c.id_caja = ct.id_caja
               JOIN empleado e ON e.id_empleado = ct.id_empleado
              WHERE c.id_tienda = :tienda AND ct.estado = 'ABIERTO'
              ORDER BY c.numero_caja"
        );
        $consulta->execute(['tienda' => $idTienda]);
        return $consulta->fetchAll();
    }

    /**
     * Total EMITIDO de cada uno de los ultimos $dias dias (incluye los dias sin ventas, en 0).
     * @return array<string, float> AAAA-MM-DD => total
     */
    public function ventasPorDia(int $idTienda, int $dias = 14): array
    {
        $hasta = new DateTimeImmutable('today');
        $desde = $hasta->modify('-' . ($dias - 1) . ' days');
        $serie = [];
        for ($dia = $desde; $dia <= $hasta; $dia = $dia->modify('+1 day')) {
            $serie[$dia->format('Y-m-d')] = 0.0;
        }

        $consulta = $this->db->prepare(
            "SELECT DATE(v.fecha_hora) AS dia, SUM(v.total) AS total
               FROM venta v JOIN serie_comprobante s ON s.id_serie = v.id_serie
              WHERE s.id_tienda = :tienda AND v.estado = 'EMITIDA'
                AND v.fecha_hora >= :desde AND v.fecha_hora < :hasta + INTERVAL 1 DAY
              GROUP BY DATE(v.fecha_hora)"
        );
        $consulta->execute(['tienda' => $idTienda, 'desde' => $desde->format('Y-m-d 00:00:00'), 'hasta' => $hasta->format('Y-m-d')]);
        foreach ($consulta->fetchAll() as $f) {
            $serie[$f['dia']] = (float) $f['total'];
        }
        return $serie;
    }
}
