<?php
declare(strict_types=1);

/**
 * Reportes de venta de UNA tienda entre dos fechas. Son las consultas 10 a 16 de consultas.sql,
 * filtradas por tienda y fecha. Solo cuentan ventas EMITIDAS (y pagos VALIDOS).
 * Devuelven filas simples (arreglos) porque son resumenes, no filas de una tabla.
 */
class ReporteRepository
{

    public function __construct(private PDO $db) {}

    /** @return array{comprobantes: int, total: float, ticketPromedio: float, anuladas: int} */
    public function resumen(int $idTienda, string $desde, string $hasta): array
    {
        $f = $this->filas('SELECT COUNT(*) AS comprobantes, COALESCE(SUM(v.total), 0) AS total ' . $this->ventas(),
            $idTienda, $desde, $hasta)[0];
        $anuladas = $this->filas(
            "SELECT COUNT(*) AS n FROM venta v JOIN serie_comprobante s ON s.id_serie = v.id_serie
              WHERE v.estado = 'ANULADA' AND s.id_tienda = :tienda
                AND v.fecha_hora >= :desde AND v.fecha_hora < :hasta + INTERVAL 1 DAY",
            $idTienda, $desde, $hasta
        )[0];
        $comprobantes = (int) $f['comprobantes'];
        return [
            'comprobantes'   => $comprobantes,
            'total'          => (float) $f['total'],
            'ticketPromedio' => $comprobantes > 0 ? round((float) $f['total'] / $comprobantes, 2) : 0.0,
            'anuladas'       => (int) $anuladas['n'],
        ];
    }

    /** Consulta 10/11: comprobantes y total de cada dia */
    public function porDia(int $idTienda, string $desde, string $hasta): array
    {
        return $this->filas(
            'SELECT DATE(v.fecha_hora) AS dia, COUNT(*) AS comprobantes, SUM(v.total) AS total '
            . $this->ventas() . ' GROUP BY DATE(v.fecha_hora) ORDER BY dia',
            $idTienda, $desde, $hasta
        );
    }

    /** Consulta 12: boletas y facturas */
    public function porTipoComprobante(int $idTienda, string $desde, string $hasta): array
    {
        return $this->filas(
            'SELECT tc.nombre_comprobante AS tipo, COUNT(*) AS comprobantes, SUM(v.total) AS total '
            . $this->ventas('JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = s.id_tipo_comprobante')
            . ' GROUP BY tc.id_tipo_comprobante, tc.nombre_comprobante ORDER BY tc.nombre_comprobante',
            $idTienda, $desde, $hasta
        );
    }

    /** Consulta 13: productos mas vendidos por importe */
    public function masVendidos(int $idTienda, string $desde, string $hasta, int $limite = 10): array
    {
        return $this->filas(
            'SELECT p.nombre_producto AS producto, u.abreviatura AS unidad,
                    SUM(d.cantidad) AS cantidad, SUM(d.importe) AS importe '
            . $this->ventas('JOIN detalle_venta d ON d.id_venta = v.id_venta
                    JOIN producto p ON p.id_producto = d.id_producto
                    JOIN unidad_medida u ON u.id_unidad = p.id_unidad')
            . ' GROUP BY p.id_producto, p.nombre_producto, u.abreviatura ORDER BY importe DESC LIMIT ' . max(1, min($limite, 100)),
            $idTienda, $desde, $hasta
        );
    }

    /** Consulta 14: importe por categoria */
    public function porCategoria(int $idTienda, string $desde, string $hasta): array
    {
        return $this->filas(
            'SELECT c.nombre_categoria AS categoria, SUM(d.importe) AS importe '
            . $this->ventas('JOIN detalle_venta d ON d.id_venta = v.id_venta
                    JOIN producto p ON p.id_producto = d.id_producto
                    JOIN categoria c ON c.id_categoria = p.id_categoria')
            . ' GROUP BY c.id_categoria, c.nombre_categoria ORDER BY importe DESC',
            $idTienda, $desde, $hasta
        );
    }

    /** Consulta 15: lo que vendio cada cajero (el responsable del turno) */
    public function porCajero(int $idTienda, string $desde, string $hasta): array
    {
        return $this->filas(
            "SELECT CONCAT(e.nombres, ' ', e.apellidos) AS cajero, COUNT(*) AS comprobantes, SUM(v.total) AS total "
            . $this->ventas('JOIN caja_turno ct ON ct.id_caja_turno = v.id_caja_turno
                    JOIN empleado e ON e.id_empleado = ct.id_empleado')
            . ' GROUP BY e.id_empleado, e.nombres, e.apellidos ORDER BY total DESC',
            $idTienda, $desde, $hasta
        );
    }

    /** Consulta 16: lo cobrado con cada metodo de pago */
    public function porMetodoPago(int $idTienda, string $desde, string $hasta): array
    {
        return $this->filas(
            "SELECT mp.nombre_metodo AS metodo, COUNT(*) AS pagos, SUM(pv.monto_pagado) AS monto "
            . $this->ventas("JOIN pago_venta pv ON pv.id_venta = v.id_venta AND pv.estado = 'VALIDO'
                    JOIN metodo_pago mp ON mp.id_metodo_pago = pv.id_metodo_pago")
            . ' GROUP BY mp.id_metodo_pago, mp.nombre_metodo ORDER BY monto DESC',
            $idTienda, $desde, $hasta
        );
    }

    // Ventas EMITIDAS de la tienda entre las fechas, con las tablas extra que pida cada reporte.
    // La tienda sale de la serie del comprobante; las fechas llegan como AAAA-MM-DD ya validadas.
    private function ventas(string $joins = ''): string
    {
        return "FROM venta v
            JOIN serie_comprobante s ON s.id_serie = v.id_serie
            $joins
           WHERE v.estado = 'EMITIDA' AND s.id_tienda = :tienda
             AND v.fecha_hora >= :desde AND v.fecha_hora < :hasta + INTERVAL 1 DAY";
    }

    private function filas(string $sql, int $idTienda, string $desde, string $hasta): array
    {
        $consulta = $this->db->prepare($sql);
        $consulta->execute(['tienda' => $idTienda, 'desde' => $desde . ' 00:00:00', 'hasta' => $hasta]);
        return $consulta->fetchAll();
    }
}
