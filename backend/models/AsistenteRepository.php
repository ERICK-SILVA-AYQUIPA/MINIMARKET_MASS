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
}
