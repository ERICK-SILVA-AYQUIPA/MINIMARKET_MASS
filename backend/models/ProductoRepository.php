<?php
declare(strict_types=1);

require_once __DIR__ . '/Producto.php';
require_once __DIR__ . '/Stock.php';

/**
 * De donde salen los datos de producto y stock: la base MySQL, con PDO.
 * Todas las consultas son preparadas; los valores van con :marcadores, nunca pegados al SQL.
 * El controlador y la vista no saben que hay una base detras.
 */
class ProductoRepository
{
    private const COLUMNAS = 'id_producto, nombre_producto, codigo_barras, precio_venta,
        se_vende_por_peso, estado, id_categoria, id_marca, id_unidad';

    public function __construct(private PDO $db) {}

    // En orden alfabetico: asi salen el catalogo, el stock, la hoja de conteo y las listas para elegir
    /** @return Producto[] */
    public function todos(): array
    {
        $consulta = $this->db->query('SELECT ' . self::COLUMNAS . ' FROM producto ORDER BY nombre_producto, id_producto');
        return array_map(fn(array $fila): Producto => $this->crear($fila), $consulta->fetchAll());
    }

    // null cuando no existe: el que llama decide que hacer (por ejemplo, un 404)
    public function buscarPorCodigo(string $codigoBarras): ?Producto
    {
        $consulta = $this->db->prepare('SELECT ' . self::COLUMNAS . ' FROM producto WHERE codigo_barras = :codigo');
        $consulta->execute(['codigo' => $codigoBarras]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $this->crear($fila);
    }

    // Los productos al peso no tienen codigo de barras: se buscan por id
    public function buscarPorId(int $idProducto): ?Producto
    {
        $consulta = $this->db->prepare('SELECT ' . self::COLUMNAS . ' FROM producto WHERE id_producto = :id');
        $consulta->execute(['id' => $idProducto]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $this->crear($fila);
    }

    public function stockEnTienda(int $idProducto, int $idTienda): ?Stock
    {
        $consulta = $this->db->prepare(
            'SELECT id_tienda, id_producto, cantidad_actual, stock_minimo
               FROM stock
              WHERE id_producto = :producto AND id_tienda = :tienda'
        );
        $consulta->execute(['producto' => $idProducto, 'tienda' => $idTienda]);
        $fila = $consulta->fetch();
        if ($fila === false) {
            return null;
        }
        return new Stock(
            (int) $fila['id_tienda'],
            (int) $fila['id_producto'],
            (float) $fila['cantidad_actual'],
            (float) $fila['stock_minimo']
        );
    }

    public function abreviaturaUnidad(int $idUnidad): string
    {
        $consulta = $this->db->prepare('SELECT abreviatura FROM unidad_medida WHERE id_unidad = :id');
        $consulta->execute(['id' => $idUnidad]);
        $abreviatura = $consulta->fetchColumn();
        return $abreviatura === false ? '-' : (string) $abreviatura;
    }

    /**
     * Cuantos productos se registraron desde la app (origen APP, migracion 018) con estos filtros.
     * $idTienda: la sucursal desde la que se registraron (null = todas). $desde/$hasta: AAAA-MM-DD ya validadas.
     */
    public function contarDesdeApp(?int $idTienda, ?string $desde, ?string $hasta, string $texto): int
    {
        [$donde, $parametros] = $this->dondeDesdeApp($idTienda, $desde, $hasta, $texto);
        $consulta = $this->db->prepare('SELECT COUNT(*) FROM producto p WHERE ' . $donde);
        $consulta->execute($parametros);
        return (int) $consulta->fetchColumn();
    }

    /**
     * Una pagina de los productos registrados desde la app, lo mas reciente primero: quien, desde que
     * sucursal, cuando, y el stock que tiene hoy en esa sucursal. Filas simples: es una lista para leer.
     * @return array<int, array{id_producto: int, nombre_producto: string, codigo_barras: ?string, precio_venta: string,
     *   estado: string, fecha_registro: string, categoria: string, marca: string, unidad: string, empleado: string,
     *   rol: string, id_tienda: int, tienda: string, stock: ?string}>
     */
    public function paginaDesdeApp(?int $idTienda, ?string $desde, ?string $hasta, string $texto, int $limite, int $offset): array
    {
        [$donde, $parametros] = $this->dondeDesdeApp($idTienda, $desde, $hasta, $texto);
        // LIMIT y OFFSET son enteros calculados en PHP
        $consulta = $this->db->prepare(
            "SELECT p.id_producto, p.nombre_producto, p.codigo_barras, p.precio_venta, p.estado, p.fecha_registro,
                    c.nombre_categoria AS categoria, m.nombre_marca AS marca, u.abreviatura AS unidad,
                    CONCAT(e.nombres, ' ', e.apellidos) AS empleado, r.nombre_rol AS rol,
                    t.id_tienda, t.nombre_tienda AS tienda, s.cantidad_actual AS stock
               FROM producto p
               JOIN categoria c     ON c.id_categoria = p.id_categoria
               JOIN marca m         ON m.id_marca = p.id_marca
               JOIN unidad_medida u ON u.id_unidad = p.id_unidad
               JOIN empleado e      ON e.id_empleado = p.id_empleado_registro
               JOIN rol r           ON r.id_rol = e.id_rol
               JOIN tienda t        ON t.id_tienda = p.id_tienda_registro
               LEFT JOIN stock s    ON s.id_producto = p.id_producto AND s.id_tienda = p.id_tienda_registro
              WHERE " . $donde . '
              ORDER BY p.fecha_registro DESC, p.id_producto DESC
              LIMIT ' . max(1, min($limite, 200)) . ' OFFSET ' . max(0, $offset)
        );
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    /**
     * Resumen para las tarjetas de arriba (con los mismos filtros): total, de los ultimos 7 dias y cuantas personas.
     * @return array{total: int, semana: int, personas: int}
     */
    public function resumenDesdeApp(?int $idTienda, ?string $desde, ?string $hasta, string $texto): array
    {
        [$donde, $parametros] = $this->dondeDesdeApp($idTienda, $desde, $hasta, $texto);
        $consulta = $this->db->prepare(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(p.fecha_registro >= CURDATE() - INTERVAL 6 DAY), 0) AS semana,
                    COUNT(DISTINCT p.id_empleado_registro) AS personas
               FROM producto p WHERE ' . $donde
        );
        $consulta->execute($parametros);
        $fila = $consulta->fetch();
        return ['total' => (int) $fila['total'], 'semana' => (int) $fila['semana'], 'personas' => (int) $fila['personas']];
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function dondeDesdeApp(?int $idTienda, ?string $desde, ?string $hasta, string $texto): array
    {
        $donde = ["p.origen = 'APP'"];
        $parametros = [];
        if ($idTienda !== null) {
            $donde[] = 'p.id_tienda_registro = :tienda';
            $parametros['tienda'] = $idTienda;
        }
        if ($desde !== null) {
            $donde[] = 'p.fecha_registro >= :desde';
            $parametros['desde'] = $desde . ' 00:00:00';
        }
        if ($hasta !== null) {
            $donde[] = 'p.fecha_registro < :hasta + INTERVAL 1 DAY';
            $parametros['hasta'] = $hasta;
        }
        if ($texto !== '') {
            // Nombre o codigo de barras; % y _ se buscan como letras
            $donde[] = '(p.nombre_producto LIKE :texto OR p.codigo_barras LIKE :texto2)';
            $parametros['texto'] = '%' . addcslashes($texto, '%_\\') . '%';
            $parametros['texto2'] = $parametros['texto'];
        }
        return [implode(' AND ', $donde), $parametros];
    }

    // MySQL devuelve DECIMAL como texto ("4.50"): se convierte al tipo de la clase
    private function crear(array $fila): Producto
    {
        return new Producto(
            (int) $fila['id_producto'],
            $fila['nombre_producto'],
            $fila['codigo_barras'],
            (float) $fila['precio_venta'],
            $fila['se_vende_por_peso'],
            $fila['estado'],
            (int) $fila['id_categoria'],
            (int) $fila['id_marca'],
            (int) $fila['id_unidad']
        );
    }
}
