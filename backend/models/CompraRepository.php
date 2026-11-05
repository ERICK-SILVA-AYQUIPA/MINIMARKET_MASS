<?php
declare(strict_types=1);

require_once __DIR__ . '/Proveedor.php';
require_once __DIR__ . '/OrdenCompra.php';
require_once __DIR__ . '/DetalleOrdenCompra.php';
require_once __DIR__ . '/Cantidad.php';
require_once __DIR__ . '/Dinero.php';

/**
 * Lee proveedores y ordenes de compra (migraciones/015). Los escriben CompraServicio
 * y, al recibir mercaderia contra una orden, InventarioServicio::registrarIngreso().
 */
class CompraRepository
{
    public function __construct(private PDO $db) {}

    /** @return array<int, Proveedor> id => proveedor, por razon social (los activos primero) */
    public function proveedores(): array
    {
        $proveedores = [];
        foreach ($this->db->query(
            "SELECT * FROM proveedor ORDER BY estado = 'INACTIVO', razon_social, id_proveedor"
        ) as $f) {
            $proveedores[(int) $f['id_proveedor']] = $this->proveedorDeFila($f);
        }
        return $proveedores;
    }

    public function proveedor(int $idProveedor): ?Proveedor
    {
        $consulta = $this->db->prepare('SELECT * FROM proveedor WHERE id_proveedor = :id');
        $consulta->execute(['id' => $idProveedor]);
        $f = $consulta->fetch();
        return $f === false ? null : $this->proveedorDeFila($f);
    }

    /**
     * Cuanto se le compro a cada proveedor en una tienda y cuantas ordenes siguen abiertas.
     * @return array<int, array{ingresos: int, comprado: float, ultimo: ?string, abiertas: int}> id_proveedor => resumen
     */
    public function resumenProveedores(int $idTienda): array
    {
        $resumen = [];
        $consulta = $this->db->prepare(
            'SELECT id_proveedor, COUNT(*) AS ingresos, SUM(total) AS comprado, MAX(fecha_hora) AS ultimo
               FROM ingreso_mercaderia WHERE id_tienda = :tienda AND id_proveedor IS NOT NULL GROUP BY id_proveedor'
        );
        $consulta->execute(['tienda' => $idTienda]);
        foreach ($consulta->fetchAll() as $f) {
            $resumen[(int) $f['id_proveedor']] = ['ingresos' => (int) $f['ingresos'], 'comprado' => (float) $f['comprado'],
                'ultimo' => $f['ultimo'], 'abiertas' => 0];
        }
        $consulta = $this->db->prepare(
            "SELECT id_proveedor, COUNT(*) FROM orden_compra
              WHERE id_tienda = :tienda AND estado IN ('PENDIENTE', 'PARCIAL') GROUP BY id_proveedor"
        );
        $consulta->execute(['tienda' => $idTienda]);
        foreach ($consulta->fetchAll(PDO::FETCH_NUM) as [$id, $abiertas]) {
            $resumen[(int) $id] ??= ['ingresos' => 0, 'comprado' => 0.0, 'ultimo' => null, 'abiertas' => 0];
            $resumen[(int) $id]['abiertas'] = (int) $abiertas;
        }
        return $resumen;
    }

    public function contarOrdenes(int $idTienda, ?string $estado): int
    {
        [$donde, $parametros] = $this->dondeOrdenes($idTienda, $estado);
        $consulta = $this->db->prepare('SELECT COUNT(*) FROM orden_compra WHERE ' . $donde);
        $consulta->execute($parametros);
        return (int) $consulta->fetchColumn();
    }

    /**
     * Una pagina de las ordenes de una tienda: primero las abiertas, luego la mas reciente.
     * @return OrdenCompra[]
     */
    public function ordenes(int $idTienda, ?string $estado, int $limite, int $offset): array
    {
        [$donde, $parametros] = $this->dondeOrdenes($idTienda, $estado);
        // LIMIT y OFFSET son enteros calculados en PHP
        $consulta = $this->db->prepare(
            "SELECT * FROM orden_compra WHERE $donde
              ORDER BY estado NOT IN ('PENDIENTE', 'PARCIAL'), id_orden_compra DESC
              LIMIT " . max(1, min($limite, 1000)) . ' OFFSET ' . max(0, $offset)
        );
        $consulta->execute($parametros);
        return array_map(fn(array $f): OrdenCompra => $this->ordenDeFila($f), $consulta->fetchAll());
    }

    public function orden(int $idOrdenCompra): ?OrdenCompra
    {
        $consulta = $this->db->prepare('SELECT * FROM orden_compra WHERE id_orden_compra = :id');
        $consulta->execute(['id' => $idOrdenCompra]);
        $f = $consulta->fetch();
        return $f === false ? null : $this->ordenDeFila($f);
    }

    /** @return array<int, DetalleOrdenCompra> id_producto => linea */
    public function detalleOrden(int $idOrdenCompra): array
    {
        $consulta = $this->db->prepare(
            'SELECT * FROM detalle_orden_compra WHERE id_orden_compra = :id ORDER BY id_producto'
        );
        $consulta->execute(['id' => $idOrdenCompra]);
        $detalle = [];
        foreach ($consulta->fetchAll() as $f) {
            $detalle[(int) $f['id_producto']] = new DetalleOrdenCompra(
                (int) $f['id_orden_compra'],
                (int) $f['id_producto'],
                Cantidad::deDecimal($f['cantidad_pedida']),
                Cantidad::deDecimal($f['cantidad_recibida']),
                (int) Dinero::deTexto($f['costo_unitario']),
                (int) Dinero::deTexto($f['costo_total'])
            );
        }
        return $detalle;
    }

    /**
     * Los ingresos que se recibieron contra una orden, con lo que llego en cada uno.
     * @return array<int, array{id_ingreso: int, fecha_hora: string, documento: string, total: float, id_empleado: int}>
     */
    public function ingresosDeOrden(int $idOrdenCompra): array
    {
        $consulta = $this->db->prepare(
            'SELECT id_ingreso, fecha_hora, tipo_documento, numero_documento, total, id_empleado
               FROM ingreso_mercaderia WHERE id_orden_compra = :id ORDER BY id_ingreso'
        );
        $consulta->execute(['id' => $idOrdenCompra]);
        return array_map(fn(array $f): array => [
            'id_ingreso'  => (int) $f['id_ingreso'],
            'fecha_hora'  => $f['fecha_hora'],
            'documento'   => $f['tipo_documento'] . ' ' . $f['numero_documento'],
            'total'       => (float) $f['total'],
            'id_empleado' => (int) $f['id_empleado'],
        ], $consulta->fetchAll());
    }

    /**
     * La ultima compra de cada producto en toda la cadena (de un ingreso con proveedor): a quien y a cuanto.
     * Sirve para sugerir el proveedor y el costo al armar una orden desde Reponer.
     * @return array<int, array{id_proveedor: int, proveedor: string, costo: int}> id_producto => ultima compra (costo en centimos)
     */
    public function ultimaCompraPorProducto(): array
    {
        $ultima = [];
        $consulta = $this->db->query(
            'SELECT d.id_producto, i.id_proveedor, p.razon_social, d.costo_unitario
               FROM detalle_ingreso d
               JOIN ingreso_mercaderia i ON i.id_ingreso = d.id_ingreso
               JOIN proveedor p ON p.id_proveedor = i.id_proveedor
              WHERE i.id_ingreso = (SELECT MAX(d2.id_ingreso) FROM detalle_ingreso d2
                                      JOIN ingreso_mercaderia i2 ON i2.id_ingreso = d2.id_ingreso
                                     WHERE d2.id_producto = d.id_producto AND i2.id_proveedor IS NOT NULL)'
        );
        foreach ($consulta->fetchAll() as $f) {
            $ultima[(int) $f['id_producto']] = [
                'id_proveedor' => (int) $f['id_proveedor'],
                'proveedor'    => $f['razon_social'],
                'costo'        => (int) Dinero::deTexto($f['costo_unitario']),
            ];
        }
        return $ultima;
    }

    /**
     * Lo que ya esta pedido y todavia no llega, por producto, en las ordenes abiertas de una tienda.
     * Reponer lo descuenta para no pedir dos veces lo mismo.
     * @return array<int, int> id_producto => milesimas por llegar
     */
    public function porLlegar(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            "SELECT d.id_producto, SUM(d.cantidad_pedida - d.cantidad_recibida) AS falta
               FROM detalle_orden_compra d
               JOIN orden_compra o ON o.id_orden_compra = d.id_orden_compra
              WHERE o.id_tienda = :tienda AND o.estado IN ('PENDIENTE', 'PARCIAL')
              GROUP BY d.id_producto"
        );
        $consulta->execute(['tienda' => $idTienda]);
        $porLlegar = [];
        foreach ($consulta->fetchAll() as $f) {
            $porLlegar[(int) $f['id_producto']] = Cantidad::deDecimal((string) $f['falta']);
        }
        return $porLlegar;
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function dondeOrdenes(int $idTienda, ?string $estado): array
    {
        if ($estado === 'ABIERTAS') {
            return ["id_tienda = :tienda AND estado IN ('PENDIENTE', 'PARCIAL')", ['tienda' => $idTienda]];
        }
        if ($estado !== null && in_array($estado, OrdenCompra::ESTADOS, true)) {
            return ['id_tienda = :tienda AND estado = :estado', ['tienda' => $idTienda, 'estado' => $estado]];
        }
        return ['id_tienda = :tienda', ['tienda' => $idTienda]];
    }

    private function proveedorDeFila(array $f): Proveedor
    {
        return new Proveedor(
            (int) $f['id_proveedor'],
            $f['ruc'],
            $f['razon_social'],
            $f['contacto'],
            $f['telefono'],
            $f['correo'],
            $f['dias_entrega'] === null ? null : (int) $f['dias_entrega'],
            $f['estado']
        );
    }

    private function ordenDeFila(array $f): OrdenCompra
    {
        return new OrdenCompra(
            (int) $f['id_orden_compra'],
            $f['fecha_hora'],
            $f['fecha_entrega'],
            $f['estado'],
            (float) $f['total'],
            $f['observacion'],
            (int) $f['id_proveedor'],
            (int) $f['id_tienda'],
            (int) $f['id_empleado'],
            $f['fecha_cierre'],
            $f['id_empleado_cierre'] === null ? null : (int) $f['id_empleado_cierre'],
            $f['motivo_cierre']
        );
    }
}
