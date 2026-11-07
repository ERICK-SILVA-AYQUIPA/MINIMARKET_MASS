<?php
declare(strict_types=1);

/**
 * Consultas que el asistente necesita y que no estaban en otro Repository.
 * Los reportes de venta y lo que falta reponer los da ReporteRepository y PanelRepository.
 */
class AsistenteRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Productos ACTIVOS cuyo nombre o marca tienen todas las palabras buscadas (o con ese codigo de barras),
     * con su precio y el stock en cada tienda pedida.
     * @param int[] $tiendas
     * @return array<int, array{id_producto: string, nombre_producto: string, nombre_marca: string, precio_venta: string, abreviatura: string, codigo_barras: ?string, nombre_tienda: ?string, cantidad_actual: ?string, stock_minimo: ?string}>
     */
    public function buscarProductos(string $texto, array $tiendas, int $limite = 15): array
    {
        $palabras = array_slice(array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($texto))) ?: [],
            fn(string $p): bool => $p !== '')), 0, 5);
        $tiendas = array_values(array_map('intval', $tiendas)) ?: [0];
        if ($palabras === []) {
            return [];
        }
        $parametros = ['codigo' => preg_replace('/\D/', '', $texto) ?: '-'];
        $partes = [];
        foreach ($palabras as $i => $palabra) {
            $partes[] = '(LOWER(p.nombre_producto) LIKE :p' . $i . ' OR LOWER(m.nombre_marca) LIKE :p' . $i . 'm)';
            $comodin = '%' . addcslashes($palabra, '%_\\') . '%';
            $parametros['p' . $i] = $comodin;
            $parametros['p' . $i . 'm'] = $comodin;
        }
        $marcas = [];
        foreach ($tiendas as $i => $id) {
            $marcas[] = ':t' . $i;
            $parametros['t' . $i] = $id;
        }
        $condicion = '(' . implode(' AND ', $partes) . ') OR p.codigo_barras = :codigo';
        $sql = "SELECT p.id_producto, p.nombre_producto, m.nombre_marca, p.precio_venta, u.abreviatura, p.codigo_barras,
                       t.nombre_tienda, s.cantidad_actual, s.stock_minimo
                  FROM (SELECT p.id_producto FROM producto p JOIN marca m ON m.id_marca = p.id_marca
                         WHERE p.estado = 'ACTIVO' AND (" . $condicion . ')
                         ORDER BY p.nombre_producto LIMIT ' . max(1, min($limite, 30)) . ") elegidos
                  JOIN producto p      ON p.id_producto = elegidos.id_producto
                  JOIN marca m         ON m.id_marca = p.id_marca
                  JOIN unidad_medida u ON u.id_unidad = p.id_unidad
                  LEFT JOIN stock s    ON s.id_producto = p.id_producto AND s.id_tienda IN (" . implode(', ', $marcas) . ')
                  LEFT JOIN tienda t   ON t.id_tienda = s.id_tienda
                 ORDER BY p.nombre_producto, t.nombre_tienda';
        $consulta = $this->db->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    /**
     * Los clientes que mas compraron en la tienda entre dos fechas (ventas EMITIDAS con cliente).
     * @return array<int, array{cliente: string, documento: ?string, compras: string, total: string}>
     */
    public function mejoresClientes(int $idTienda, string $desde, string $hasta, int $limite = 10): array
    {
        $consulta = $this->db->prepare(
            "SELECT COALESCE(NULLIF(c.nombre, ''), c.dni, c.ruc) AS cliente, COALESCE(c.dni, c.ruc) AS documento,
                    COUNT(*) AS compras, SUM(v.total) AS total
               FROM venta v
               JOIN serie_comprobante s ON s.id_serie = v.id_serie
               JOIN cliente c           ON c.id_cliente = v.id_cliente
              WHERE v.estado = 'EMITIDA' AND s.id_tienda = :tienda
                AND v.fecha_hora >= :desde AND v.fecha_hora < :hasta + INTERVAL 1 DAY
              GROUP BY c.id_cliente, c.nombre, c.dni, c.ruc
              ORDER BY total DESC
              LIMIT " . max(1, min($limite, 30))
        );
        $consulta->execute(['tienda' => $idTienda, 'desde' => $desde . ' 00:00:00', 'hasta' => $hasta]);
        return $consulta->fetchAll();
    }

    /**
     * Nombre y unidad de cada producto pedido (para los productos por vencer, que llegan solo con su id).
     * @param int[] $ids
     * @return array<int, array{nombre: string, unidad: string}> id_producto => datos
     */
    public function nombresProductos(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $parametros = [];
        foreach ($ids as $i => $id) {
            $parametros['i' . $i] = $id;
        }
        $sql = 'SELECT p.id_producto, p.nombre_producto, u.abreviatura FROM producto p
                  JOIN unidad_medida u ON u.id_unidad = p.id_unidad
                 WHERE p.id_producto IN (' . implode(', ', array_map(fn(string $k): string => ':' . $k, array_keys($parametros))) . ')';
        $consulta = $this->db->prepare($sql);
        $consulta->execute($parametros);
        $resultado = [];
        foreach ($consulta->fetchAll() as $f) {
            $resultado[(int) $f['id_producto']] = ['nombre' => (string) $f['nombre_producto'], 'unidad' => (string) $f['abreviatura']];
        }
        return $resultado;
    }
}
