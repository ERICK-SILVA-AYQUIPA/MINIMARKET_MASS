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

    /** @return array{comprobantes: int, total: float, devuelto: float, neto: float, ticketPromedio: float, anuladas: int} */
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
        // Devoluciones (operaciones.sql) de las ventas EMITIDAS del periodo, igual que la utilidad
        $devuelto = $this->filas(
            "SELECT COALESCE(SUM(dv.total), 0) AS devuelto
               FROM devolucion_venta dv
               JOIN venta v ON v.id_venta = dv.id_venta
               JOIN serie_comprobante s ON s.id_serie = v.id_serie
              WHERE v.estado = 'EMITIDA' AND s.id_tienda = :tienda AND v.fecha_hora >= :desde AND v.fecha_hora < :hasta + INTERVAL 1 DAY",
            $idTienda, $desde, $hasta
        )[0];
        $comprobantes = (int) $f['comprobantes'];
        return [
            'comprobantes'   => $comprobantes,
            'total'          => (float) $f['total'],
            'devuelto'       => (float) $devuelto['devuelto'],
            'neto'           => round((float) $f['total'] - (float) $devuelto['devuelto'], 2),
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

    /**
     * Utilidad de cada producto vendido en la tienda: ventas menos devoluciones, menos su costo.
     * El costo es el promedio ponderado de lo comprado en toda la cadena hasta la fecha final
     * (detalle_ingreso); si el producto nunca entro por un ingreso, su costo queda en null.
     * @return array<int, array{producto: string, unidad: string, al_peso: bool, cantidad: float, ventas: float, costo_unitario: ?float, costo: ?float, utilidad: ?float, margen: ?float}>
     */
    public function utilidadPorProducto(int $idTienda, string $desde, string $hasta): array
    {
        $consulta = $this->db->prepare(
            "SELECT p.nombre_producto AS producto, u.abreviatura AS unidad, p.se_vende_por_peso,
                    vend.cantidad - COALESCE(dev.cantidad, 0) AS cantidad,
                    vend.importe - COALESCE(dev.importe, 0) AS ventas,
                    costo.unitario AS costo_unitario
               FROM (SELECT d.id_producto, SUM(d.cantidad) AS cantidad, SUM(d.importe) AS importe
                       FROM venta v
                       JOIN serie_comprobante s ON s.id_serie = v.id_serie
                       JOIN detalle_venta d ON d.id_venta = v.id_venta
                      WHERE v.estado = 'EMITIDA' AND s.id_tienda = :tienda
                        AND v.fecha_hora >= :desde AND v.fecha_hora < :hasta + INTERVAL 1 DAY
                      GROUP BY d.id_producto) vend
               JOIN producto p      ON p.id_producto = vend.id_producto
               JOIN unidad_medida u ON u.id_unidad = p.id_unidad
               LEFT JOIN (SELECT dd.id_producto, SUM(dd.cantidad) AS cantidad, SUM(dd.importe) AS importe
                            FROM devolucion_venta dv
                            JOIN venta v ON v.id_venta = dv.id_venta
                            JOIN serie_comprobante s ON s.id_serie = v.id_serie
                            JOIN detalle_devolucion dd ON dd.id_devolucion = dv.id_devolucion
                           WHERE v.estado = 'EMITIDA' AND s.id_tienda = :tienda2
                             AND v.fecha_hora >= :desde2 AND v.fecha_hora < :hasta2 + INTERVAL 1 DAY
                           GROUP BY dd.id_producto) dev ON dev.id_producto = vend.id_producto
               LEFT JOIN (SELECT di.id_producto, SUM(di.costo_total) / SUM(di.cantidad) AS unitario
                            FROM detalle_ingreso di
                            JOIN ingreso_mercaderia i ON i.id_ingreso = di.id_ingreso
                           WHERE i.fecha_hora < :hasta3 + INTERVAL 1 DAY
                           GROUP BY di.id_producto
                          HAVING SUM(di.cantidad) > 0) costo ON costo.id_producto = vend.id_producto
              ORDER BY ventas DESC, p.nombre_producto"
        );
        $consulta->execute([
            'tienda' => $idTienda, 'desde' => $desde . ' 00:00:00', 'hasta' => $hasta,
            'tienda2' => $idTienda, 'desde2' => $desde . ' 00:00:00', 'hasta2' => $hasta, 'hasta3' => $hasta,
        ]);

        $filas = [];
        foreach ($consulta->fetchAll() as $f) {
            $cantidad = (float) $f['cantidad'];
            $ventas = (float) $f['ventas'];
            $unitario = $f['costo_unitario'] === null ? null : (float) $f['costo_unitario'];
            $costo = $unitario === null ? null : round($cantidad * $unitario, 2);
            $utilidad = $costo === null ? null : round($ventas - $costo, 2);
            $filas[] = [
                'producto'       => $f['producto'],
                'unidad'         => $f['unidad'],
                'al_peso'        => $f['se_vende_por_peso'] === 'S',
                'cantidad'       => $cantidad,
                'ventas'         => $ventas,
                'costo_unitario' => $unitario === null ? null : round($unitario, 4),
                'costo'          => $costo,
                'utilidad'       => $utilidad,
                'margen'         => $utilidad === null || $ventas <= 0 ? null : round($utilidad / $ventas * 100, 1),
            ];
        }
        return $filas;
    }

    /**
     * Utilidad de cada tienda de la cadena en las fechas, sumando la de sus productos con costo conocido.
     * @return array<int, array{tienda: string, ventas: float, conCosto: float, costo: float, utilidad: float, margen: ?float}>
     */
    public function utilidadPorTienda(array $tiendas, string $desde, string $hasta): array
    {
        $filas = [];
        foreach ($tiendas as $idTienda => $nombre) {
            $productos = $this->utilidadPorProducto((int) $idTienda, $desde, $hasta);
            $conCosto = array_filter($productos, fn(array $p): bool => $p['costo'] !== null);
            $ventasConCosto = round(array_sum(array_column($conCosto, 'ventas')), 2);
            $utilidad = round(array_sum(array_column($conCosto, 'utilidad')), 2);
            $filas[] = [
                'tienda'   => $nombre,
                'ventas'   => round(array_sum(array_column($productos, 'ventas')), 2),
                'conCosto' => $ventasConCosto,
                'costo'    => round(array_sum(array_column($conCosto, 'costo')), 2),
                'utilidad' => $utilidad,
                'margen'   => $ventasConCosto > 0 ? round($utilidad / $ventasConCosto * 100, 1) : null,
            ];
        }
        return $filas;
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
