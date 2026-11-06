<?php
declare(strict_types=1);

require_once __DIR__ . '/Producto.php';
require_once __DIR__ . '/Stock.php';
require_once __DIR__ . '/CodigoProductoRepository.php';

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

    /**
     * null cuando no existe: el que llama decide que hacer (por ejemplo, un 404).
     * Lo encuentra por su codigo o por uno de sus otros codigos, aunque venga con otros ceros adelante o como UPC-E.
     */
    public function buscarPorCodigo(string $codigoBarras): ?Producto
    {
        $id = (new CodigoProductoRepository($this->db))->idPorCodigo($codigoBarras);
        return $id === null ? null : $this->buscarPorId($id);
    }

    /**
     * Los otros codigos de un producto (migracion 023), con quien y donde los vinculo.
     * @return array<int, array{id: int, codigo: string, fecha: string, empleado: string, tienda: string}>
     */
    public function otrosCodigos(int $idProducto): array
    {
        return (new CodigoProductoRepository($this->db))->codigosDe($idProducto);
    }

    /**
     * Los otros codigos de todos los productos, para el lector de codigos de la venta web.
     * @return array<int, string[]> id_producto => codigos
     */
    public function otrosCodigosTodos(): array
    {
        return (new CodigoProductoRepository($this->db))->todos();
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
     * Pestaña "Recien agregados" (?ruta=productos_nuevos): productos nuevos de cualquier origen (migraciones 018 y 022).
     * Filtros ya validados por el controlador:
     *   tienda  ?int     sucursal desde la que se registro (null = todas)
     *   desde   ?string  AAAA-MM-DD    hasta ?string AAAA-MM-DD
     *   texto   string   nombre o codigo de barras
     *   origen  ?string  APP, WEB o CSV (null = todos)
     *   estado  ?string  'pendientes' (sin revisar) o 'revisados' (null = todos)
     * @param array{tienda?: ?int, desde?: ?string, hasta?: ?string, texto?: string, origen?: ?string, estado?: ?string} $filtros
     */
    public function contarNuevos(array $filtros): int
    {
        [$donde, $parametros] = $this->dondeNuevos($filtros);
        $consulta = $this->db->prepare('SELECT COUNT(*) FROM producto p WHERE ' . $donde);
        $consulta->execute($parametros);
        return (int) $consulta->fetchColumn();
    }

    /**
     * Una pagina de productos recien agregados, lo mas reciente primero: origen, quien, desde que sucursal, cuando,
     * quien lo reviso, el stock inicial que declaro (app) y el stock y minimo de hoy en esa sucursal.
     * @param array{tienda?: ?int, desde?: ?string, hasta?: ?string, texto?: string, origen?: ?string, estado?: ?string} $filtros
     * @return array<int, array<string, mixed>>
     */
    public function paginaNuevos(array $filtros, int $limite, int $offset): array
    {
        [$donde, $parametros] = $this->dondeNuevos($filtros);
        // LIMIT y OFFSET son enteros calculados en PHP
        $consulta = $this->db->prepare(
            "SELECT p.id_producto, p.nombre_producto, p.codigo_barras, p.precio_venta, p.estado, p.se_vende_por_peso,
                    p.origen, p.fecha_registro, p.fecha_revision, p.id_categoria, p.id_marca,
                    c.nombre_categoria AS categoria, m.nombre_marca AS marca, u.abreviatura AS unidad,
                    CONCAT(e.nombres, ' ', e.apellidos) AS empleado, r.nombre_rol AS rol,
                    CONCAT(er.nombres, ' ', er.apellidos) AS revisor,
                    t.id_tienda, t.nombre_tienda AS tienda, s.cantidad_actual AS stock, s.stock_minimo,
                    (SELECT ms.cantidad FROM movimiento_stock ms
                      WHERE ms.id_producto = p.id_producto AND ms.id_tienda = p.id_tienda_registro
                        AND ms.observacion = 'Stock inicial (app)'
                      ORDER BY ms.id_movimiento LIMIT 1) AS stock_inicial
               FROM producto p
               JOIN categoria c       ON c.id_categoria = p.id_categoria
               JOIN marca m           ON m.id_marca = p.id_marca
               JOIN unidad_medida u   ON u.id_unidad = p.id_unidad
               JOIN empleado e        ON e.id_empleado = p.id_empleado_registro
               JOIN rol r             ON r.id_rol = e.id_rol
               JOIN tienda t          ON t.id_tienda = p.id_tienda_registro
               LEFT JOIN empleado er  ON er.id_empleado = p.id_empleado_revision
               LEFT JOIN stock s      ON s.id_producto = p.id_producto AND s.id_tienda = p.id_tienda_registro
              WHERE " . $donde . '
              ORDER BY p.fecha_registro DESC, p.id_producto DESC
              LIMIT ' . max(1, min($limite, 200)) . ' OFFSET ' . max(0, $offset)
        );
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    /**
     * Las tarjetas de arriba, con los mismos filtros: total, de hoy, de los ultimos 7 dias, desde la app y por revisar.
     * @param array{tienda?: ?int, desde?: ?string, hasta?: ?string, texto?: string, origen?: ?string, estado?: ?string} $filtros
     * @return array{total: int, hoy: int, semana: int, app: int, pendientes: int}
     */
    public function resumenNuevos(array $filtros): array
    {
        [$donde, $parametros] = $this->dondeNuevos($filtros);
        $consulta = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(p.fecha_registro >= CURDATE()), 0) AS hoy,
                    COALESCE(SUM(p.fecha_registro >= CURDATE() - INTERVAL 6 DAY), 0) AS semana,
                    COALESCE(SUM(p.origen = 'APP'), 0) AS app,
                    COALESCE(SUM(p.fecha_revision IS NULL), 0) AS pendientes
               FROM producto p WHERE " . $donde
        );
        $consulta->execute($parametros);
        $fila = $consulta->fetch();
        return ['total' => (int) $fila['total'], 'hoy' => (int) $fila['hoy'], 'semana' => (int) $fila['semana'],
            'app' => (int) $fila['app'], 'pendientes' => (int) $fila['pendientes']];
    }

    /**
     * Ids de los productos registrados desde $desde (AAAA-MM-DD, de cualquier sucursal): la etiqueta NUEVO del catalogo.
     * @return array<int, true>
     */
    public function idsNuevos(string $desde): array
    {
        $consulta = $this->db->prepare(
            'SELECT id_producto FROM producto WHERE origen IS NOT NULL AND fecha_registro >= :desde'
        );
        $consulta->execute(['desde' => $desde . ' 00:00:00']);
        return array_fill_keys(array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    /**
     * @param array{tienda?: ?int, desde?: ?string, hasta?: ?string, texto?: string, origen?: ?string, estado?: ?string} $f
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function dondeNuevos(array $f): array
    {
        // Productos de la base inicial (origen NULL) no son nuevos: no tienen quien ni cuando
        $donde = ['p.origen IS NOT NULL', 'p.fecha_registro IS NOT NULL'];
        $parametros = [];
        if (($f['tienda'] ?? null) !== null) {
            $donde[] = 'p.id_tienda_registro = :tienda';
            $parametros['tienda'] = $f['tienda'];
        }
        if (in_array($f['origen'] ?? null, ['APP', 'WEB', 'CSV'], true)) {
            $donde[] = 'p.origen = :origen';
            $parametros['origen'] = $f['origen'];
        }
        if (($f['estado'] ?? null) === 'pendientes') {
            $donde[] = 'p.fecha_revision IS NULL';
        } elseif (($f['estado'] ?? null) === 'revisados') {
            $donde[] = 'p.fecha_revision IS NOT NULL';
        }
        if (($f['desde'] ?? null) !== null) {
            $donde[] = 'p.fecha_registro >= :desde';
            $parametros['desde'] = $f['desde'] . ' 00:00:00';
        }
        if (($f['hasta'] ?? null) !== null) {
            $donde[] = 'p.fecha_registro < :hasta + INTERVAL 1 DAY';
            $parametros['hasta'] = $f['hasta'];
        }
        $texto = $f['texto'] ?? '';
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
