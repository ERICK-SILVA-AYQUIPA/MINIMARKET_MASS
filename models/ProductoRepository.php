<?php
declare(strict_types=1);

require_once __DIR__ . '/Producto.php';
require_once __DIR__ . '/Stock.php';

/**
 * De donde salen los datos de producto y stock (las tiendas estan en TiendaRepository).
 * Hoy: listas fijas copiadas de database/datos.sql.
 * Sesion 5: estos mismos metodos leeran de MySQL (puerto 3307) con PDO,
 * y el controlador y la vista no cambian.
 */
class ProductoRepository
{
    /** @var Producto[] */
    private array $productos;

    /** @var Stock[] */
    private array $stocks;

    /** @var array<int, string> id_unidad => abreviatura (tabla unidad_medida) */
    private array $unidades = [
        1 => 'und',
        2 => 'kg',
        3 => 'lt',
    ];

    public function __construct()
    {
        // INSERT INTO producto ... (datos.sql). Los productos al peso no tienen codigo de barras.
        $this->productos = [
            new Producto(1, 'Leche evaporada Gloria entera 400 g', '7750000000011', 4.50, 'N', 'ACTIVO', 2, 1, 1),
            new Producto(2, 'Arroz Costeno extra 1 kg', '7750000000028', 5.20, 'N', 'ACTIVO', 1, 2, 1),
            new Producto(3, 'Gaseosa Inca Kola 1.5 L', '7750000000035', 6.50, 'N', 'ACTIVO', 3, 3, 1),
            new Producto(4, 'Aceite vegetal Primor 1 L', '7750000000042', 10.50, 'N', 'ACTIVO', 1, 4, 1),
            new Producto(5, 'Atun Florida filete 170 g', '7750000000059', 5.50, 'N', 'ACTIVO', 1, 5, 1),
            new Producto(6, 'Detergente Bolivar 800 g', '7750000000066', 9.90, 'N', 'ACTIVO', 5, 6, 1),
            new Producto(7, 'Fideos spaghetti Don Vittorio 500 g', '7750000000073', 3.20, 'N', 'ACTIVO', 1, 7, 1),
            new Producto(8, 'Papa blanca (por kg)', null, 2.50, 'S', 'ACTIVO', 4, 8, 2),
            new Producto(9, 'Platano de seda (por kg)', null, 3.90, 'S', 'ACTIVO', 4, 8, 2),
            new Producto(10, 'Azucar rubia Costeno 1 kg', '7750000000080', 4.80, 'N', 'ACTIVO', 1, 2, 1),
            new Producto(11, 'Yogurt Gloria fresa 1 L', '7750000000097', 7.90, 'N', 'ACTIVO', 2, 1, 1),
            new Producto(12, 'Cebolla roja (por kg)', null, 3.20, 'S', 'ACTIVO', 4, 8, 2),
        ];

        // INSERT INTO stock ... (datos.sql): tienda, producto, cantidad_actual, stock_minimo
        $this->stocks = [
            new Stock(1, 1, 98.000, 20.000),
            new Stock(1, 2, 78.000, 20.000),
            new Stock(1, 3, 91.000, 20.000),
            new Stock(1, 4, 95.000, 20.000),
            new Stock(1, 5, 86.000, 20.000),
            new Stock(1, 6, 98.000, 20.000),
            new Stock(1, 7, 89.000, 20.000),
            new Stock(1, 8, 44.200, 10.000),
            new Stock(1, 9, 60.000, 10.000),
            new Stock(1, 10, 90.000, 20.000),
            new Stock(1, 11, 100.000, 20.000),
            new Stock(1, 12, 58.250, 10.000),
            new Stock(2, 1, 96.000, 20.000),
            new Stock(2, 2, 100.000, 20.000),
            new Stock(2, 3, 97.000, 20.000),
            new Stock(2, 4, 100.000, 20.000),
            new Stock(2, 5, 100.000, 20.000),
            new Stock(2, 6, 20.000, 20.000),
            new Stock(2, 7, 100.000, 20.000),
            new Stock(2, 8, 57.000, 10.000),
            new Stock(2, 9, 56.000, 10.000),
            new Stock(2, 10, 98.000, 20.000),
            new Stock(2, 11, 99.000, 20.000),
            new Stock(2, 12, 60.000, 10.000),
            new Stock(3, 1, 16.000, 20.000),
            new Stock(3, 2, 120.000, 20.000),
            new Stock(3, 3, 98.000, 20.000),
            new Stock(3, 4, 96.000, 20.000),
            new Stock(3, 5, 94.000, 20.000),
            new Stock(3, 6, 97.000, 20.000),
            new Stock(3, 7, 96.000, 20.000),
            new Stock(3, 8, 69.300, 10.000),
            new Stock(3, 9, 60.000, 10.000),
            new Stock(3, 10, 100.000, 20.000),
            new Stock(3, 11, 100.000, 20.000),
            new Stock(3, 12, 60.000, 10.000),
        ];
    }

    /** @return Producto[] */
    public function todos(): array
    {
        return $this->productos;
    }

    // null cuando no existe: el que llama decide que hacer (por ejemplo, un 404)
    public function buscarPorCodigo(string $codigoBarras): ?Producto
    {
        foreach ($this->productos as $producto) {
            if ($producto->getCodigoBarras() === $codigoBarras) {
                return $producto;
            }
        }
        return null;
    }

    // Los productos al peso no tienen codigo de barras: se buscan por id
    public function buscarPorId(int $idProducto): ?Producto
    {
        foreach ($this->productos as $producto) {
            if ($producto->getIdProducto() === $idProducto) {
                return $producto;
            }
        }
        return null;
    }

    public function stockEnTienda(int $idProducto, int $idTienda): ?Stock
    {
        foreach ($this->stocks as $stock) {
            if ($stock->getIdProducto() === $idProducto && $stock->getIdTienda() === $idTienda) {
                return $stock;
            }
        }
        return null;
    }

    public function abreviaturaUnidad(int $idUnidad): string
    {
        return $this->unidades[$idUnidad] ?? '-';
    }
}
