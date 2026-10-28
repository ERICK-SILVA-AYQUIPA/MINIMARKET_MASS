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
