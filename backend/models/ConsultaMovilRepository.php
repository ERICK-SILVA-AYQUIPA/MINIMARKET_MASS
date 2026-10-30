<?php
declare(strict_types=1);

/**
 * Lecturas para la app Android (API /api/v1): productos con su stock, ventas, kardex e ingresos.
 * Lee las MISMAS tablas que la pagina web; devuelve arreglos listos para JSON.
 * Las escrituras siguen en los servicios de siempre (VentaServicio, InventarioServicio, CajaServicio).
 */
class ConsultaMovilRepository
{
    private const COLUMNAS_PRODUCTO = 'p.id_producto, p.nombre_producto, p.codigo_barras, p.precio_venta, p.se_vende_por_peso,
        p.estado, c.nombre_categoria, m.nombre_marca, u.abreviatura, u.nombre_unidad, s.cantidad_actual, s.stock_minimo';

    // El stock es el de la tienda del usuario: LEFT JOIN porque un producto puede no haber llegado nunca a esa tienda
    private const DESDE_PRODUCTO = 'producto p
        JOIN categoria c     ON c.id_categoria = p.id_categoria
        JOIN marca m         ON m.id_marca = p.id_marca
        JOIN unidad_medida u ON u.id_unidad = p.id_unidad
        LEFT JOIN stock s    ON s.id_producto = p.id_producto AND s.id_tienda = :tienda';

    public function __construct(private PDO $db) {}

    /**
     * Productos por nombre o codigo, con el stock de la tienda.
     * @param string $filtro 'todos', 'agotados' (sin stock) o 'bajo' (en el minimo o debajo, incluidos los agotados)
     * @return array<int, array<string, mixed>>
     */
    public function productos(int $idTienda, ?string $texto, string $filtro, int $limite, int $offset): array
    {
        [$donde, $parametros] = $this->dondeProductos($idTienda, $texto, $filtro);
        // LIMIT y OFFSET son enteros calculados en PHP
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS_PRODUCTO . ' FROM ' . self::DESDE_PRODUCTO . ' WHERE ' . $donde
            . ' ORDER BY p.nombre_producto, p.id_producto LIMIT ' . max(1, min($limite, 200)) . ' OFFSET ' . max(0, $offset)
        );
        $consulta->execute($parametros);
        return array_map(fn(array $f): array => $this->producto($f), $consulta->fetchAll());
    }

    public function contarProductos(int $idTienda, ?string $texto, string $filtro): int
    {
        [$donde, $parametros] = $this->dondeProductos($idTienda, $texto, $filtro);
        $consulta = $this->db->prepare('SELECT COUNT(*) FROM ' . self::DESDE_PRODUCTO . ' WHERE ' . $donde);
        $consulta->execute($parametros);
        return (int) $consulta->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function dondeProductos(int $idTienda, ?string $texto, string $filtro): array
    {
        $condiciones = ['1 = 1'];
        $parametros = ['tienda' => $idTienda];
        $texto = trim((string) $texto);
        if ($texto !== '') {
            // % y _ escritos por el usuario se buscan tal cual, no como comodines
            $patron = '%' . addcslashes($texto, '\\%_') . '%';
            $condiciones[] = '(p.nombre_producto LIKE :texto OR p.codigo_barras LIKE :codigo)';
            $parametros['texto'] = $patron;
            $parametros['codigo'] = $patron;
        }
        if ($filtro === 'agotados') {
            $condiciones[] = "p.estado = 'ACTIVO' AND COALESCE(s.cantidad_actual, 0) = 0";
        } elseif ($filtro === 'bajo') {
            $condiciones[] = "p.estado = 'ACTIVO' AND COALESCE(s.cantidad_actual, 0) <= COALESCE(s.stock_minimo, 0)";
        }
        return [implode(' AND ', $condiciones), $parametros];
    }

    /** @return array<string, mixed>|null */
    public function productoPorId(int $idProducto, int $idTienda): ?array
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS_PRODUCTO . ' FROM ' . self::DESDE_PRODUCTO . ' WHERE p.id_producto = :id'
        );
        $consulta->execute(['tienda' => $idTienda, 'id' => $idProducto]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $this->producto($fila);
    }

    /**
     * El producto con ese codigo de barras. Una camara lee UPC-A con 12 digitos y el catalogo puede
     * tenerlo como EAN-13 (con un 0 adelante), o al reves: se prueban las dos formas.
     * @return array<string, mixed>|null
     */
    public function productoPorCodigo(string $codigo, int $idTienda): ?array
    {
        if (preg_match('/^[0-9]{8,14}$/', $codigo) !== 1) {
            return null;
        }
        $otra = match (true) {
            strlen($codigo) === 12                            => '0' . $codigo,
            strlen($codigo) === 13 && str_starts_with($codigo, '0') => substr($codigo, 1),
            default                                           => $codigo,
        };
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS_PRODUCTO . ' FROM ' . self::DESDE_PRODUCTO . '
              WHERE p.codigo_barras IN (:codigo, :otra)
              ORDER BY p.codigo_barras = :exacto DESC LIMIT 1'
        );
        $consulta->execute(['tienda' => $idTienda, 'codigo' => $codigo, 'otra' => $otra, 'exacto' => $codigo]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $this->producto($fila);
    }

    /** @return array<string, mixed> */
    private function producto(array $f): array
    {
        $tieneStock = $f['cantidad_actual'] !== null;
        $cantidad = $tieneStock ? Cantidad::deDecimal((string) $f['cantidad_actual']) : 0;
        $minimo = $f['stock_minimo'] !== null ? Cantidad::deDecimal((string) $f['stock_minimo']) : 0;
        return [
            'id_producto'      => (int) $f['id_producto'],
            'nombre'           => $f['nombre_producto'],
            'codigo_barras'    => $f['codigo_barras'],
            'precio_venta'     => $f['precio_venta'],
            'al_peso'          => $f['se_vende_por_peso'] === 'S',
            'estado'           => $f['estado'],
            'categoria'        => $f['nombre_categoria'],
            'marca'            => $f['nombre_marca'],
            'unidad'           => $f['abreviatura'],
            'unidad_nombre'    => $f['nombre_unidad'],
            // Cantidades como texto con 3 decimales (DECIMAL(10,3)): la app no las pasa por float
            'stock'            => Cantidad::aTexto($cantidad),
            'stock_minimo'     => Cantidad::aTexto($minimo),
            'stock_registrado' => $tieneStock,
            'agotado'          => $cantidad === 0,
            // La misma regla que Stock::necesitaReponer(): lo que queda llego al minimo
            'stock_bajo'       => $cantidad <= $minimo,
        ];
    }

    /**
     * Movimientos recientes de un producto en una tienda (kardex), el mas reciente primero.
     * @return array<int, array<string, mixed>>
     */
    public function kardex(int $idTienda, ?int $idProducto, int $limite, int $offset): array
    {
        $donde = 'm.id_tienda = :tienda';
        $parametros = ['tienda' => $idTienda];
        if ($idProducto !== null) {
            $donde .= ' AND m.id_producto = :producto';
            $parametros['producto'] = $idProducto;
        }
        $consulta = $this->db->prepare(
            "SELECT m.id_movimiento, m.fecha_hora, m.cantidad, m.stock_nuevo, m.observacion, m.id_producto,
                    p.nombre_producto, t.nombre_movimiento, t.efecto, CONCAT(e.nombres, ' ', e.apellidos) AS empleado,
                    m.id_venta, m.id_traslado, m.id_merma, m.id_ajuste, m.id_ingreso, m.id_devolucion
               FROM movimiento_stock m
               JOIN producto p        ON p.id_producto = m.id_producto
               JOIN tipo_movimiento t ON t.id_tipo_movimiento = m.id_tipo_movimiento
               JOIN empleado e        ON e.id_empleado = m.id_empleado
              WHERE " . $donde . '
              ORDER BY m.id_movimiento DESC LIMIT ' . max(1, min($limite, 200)) . ' OFFSET ' . max(0, $offset)
        );
        $consulta->execute($parametros);
        return array_map(fn(array $f): array => [
            'id_movimiento' => (int) $f['id_movimiento'],
            'fecha_hora'    => $f['fecha_hora'],
            'id_producto'   => (int) $f['id_producto'],
            'producto'      => $f['nombre_producto'],
            'tipo'          => $f['nombre_movimiento'],
            'entrada'       => $f['efecto'] === 'E',
            'cantidad'      => $f['cantidad'],
            'stock_nuevo'   => $f['stock_nuevo'],
            'empleado'      => $f['empleado'],
            'observacion'   => $f['observacion'],
            'origen'        => match (true) {
                $f['id_venta'] !== null    => 'Venta ' . $f['id_venta'],
                $f['id_traslado'] !== null => 'Traslado ' . $f['id_traslado'],
                $f['id_merma'] !== null    => 'Merma ' . $f['id_merma'],
                $f['id_ajuste'] !== null   => 'Ajuste ' . $f['id_ajuste'],
                $f['id_ingreso'] !== null  => 'Ingreso ' . $f['id_ingreso'],
                default                    => 'Devolución ' . $f['id_devolucion'],
            },
        ], $consulta->fetchAll());
    }

    /**
     * Ventas de una tienda, la mas reciente primero, con el cajero y los metodos de pago.
     * @param array{desde?: ?string, hasta?: ?string, estado?: ?string} $filtro fechas AAAA-MM-DD ya validadas
     * @return array<int, array<string, mixed>>
     */
    public function ventas(int $idTienda, array $filtro, int $limite, int $offset): array
    {
        [$donde, $parametros] = $this->dondeVentas($idTienda, $filtro);
        $consulta = $this->db->prepare(
            "SELECT v.id_venta, s.serie, v.numero_correlativo, v.fecha_hora, v.total, v.estado,
                    CONCAT(e.nombres, ' ', e.apellidos) AS cajero,
                    (SELECT GROUP_CONCAT(DISTINCT mp.nombre_metodo ORDER BY mp.id_metodo_pago SEPARATOR ', ')
                       FROM pago_venta pv JOIN metodo_pago mp ON mp.id_metodo_pago = pv.id_metodo_pago
                      WHERE pv.id_venta = v.id_venta) AS metodos,
                    (SELECT COUNT(*) FROM detalle_venta dv WHERE dv.id_venta = v.id_venta) AS productos
               FROM venta v
               JOIN serie_comprobante s ON s.id_serie = v.id_serie
               JOIN caja_turno ct       ON ct.id_caja_turno = v.id_caja_turno
               JOIN empleado e          ON e.id_empleado = ct.id_empleado
              WHERE " . $donde . '
              ORDER BY v.fecha_hora DESC, v.id_venta DESC LIMIT ' . max(1, min($limite, 100)) . ' OFFSET ' . max(0, $offset)
        );
        $consulta->execute($parametros);
        return array_map(fn(array $f): array => [
            'id_venta'    => (int) $f['id_venta'],
            'comprobante' => sprintf('%s-%08d', $f['serie'], $f['numero_correlativo']),
            'fecha_hora'  => $f['fecha_hora'],
            'total'       => $f['total'],
            'estado'      => $f['estado'],
            'cajero'      => $f['cajero'],
            'metodos'     => (string) $f['metodos'],
            'productos'   => (int) $f['productos'],
        ], $consulta->fetchAll());
    }

    public function contarVentas(int $idTienda, array $filtro): int
    {
        [$donde, $parametros] = $this->dondeVentas($idTienda, $filtro);
        $consulta = $this->db->prepare(
            'SELECT COUNT(*) FROM venta v JOIN serie_comprobante s ON s.id_serie = v.id_serie WHERE ' . $donde
        );
        $consulta->execute($parametros);
        return (int) $consulta->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function dondeVentas(int $idTienda, array $filtro): array
    {
        $condiciones = ['s.id_tienda = :tienda'];
        $parametros = ['tienda' => $idTienda];
        if (($filtro['desde'] ?? null) !== null) {
            $condiciones[] = 'v.fecha_hora >= :desde';
            $parametros['desde'] = $filtro['desde'] . ' 00:00:00';
        }
        if (($filtro['hasta'] ?? null) !== null) {
            $condiciones[] = 'v.fecha_hora < :hasta + INTERVAL 1 DAY';
            $parametros['hasta'] = $filtro['hasta'];
        }
        if (in_array($filtro['estado'] ?? null, ['EMITIDA', 'ANULADA'], true)) {
            $condiciones[] = 'v.estado = :estado';
            $parametros['estado'] = $filtro['estado'];
        }
        if (($filtro['serie'] ?? null) !== null) {
            $condiciones[] = 's.serie = :serie AND v.numero_correlativo = :numero';
            $parametros['serie'] = $filtro['serie'];
            $parametros['numero'] = (int) $filtro['numero'];
        }
        return [implode(' AND ', $condiciones), $parametros];
    }

    /**
     * Cabecera de una venta con su tienda, caja, cajero y cliente. null si no existe.
     * @return array<string, mixed>|null
     */
    public function venta(int $idVenta): ?array
    {
        $consulta = $this->db->prepare(
            "SELECT v.id_venta, s.serie, s.id_tienda, t.nombre_tienda, v.numero_correlativo, v.fecha_hora, v.total, v.estado,
                    tc.nombre_comprobante, c.numero_caja, CONCAT(e.nombres, ' ', e.apellidos) AS cajero,
                    cl.dni, cl.ruc, tc.letra_serie
               FROM venta v
               JOIN serie_comprobante s ON s.id_serie = v.id_serie
               JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = s.id_tipo_comprobante
               JOIN tienda t            ON t.id_tienda = s.id_tienda
               JOIN caja_turno ct       ON ct.id_caja_turno = v.id_caja_turno
               JOIN caja c              ON c.id_caja = ct.id_caja
               JOIN empleado e          ON e.id_empleado = ct.id_empleado
               LEFT JOIN cliente cl     ON cl.id_cliente = v.id_cliente
              WHERE v.id_venta = :venta"
        );
        $consulta->execute(['venta' => $idVenta]);
        $f = $consulta->fetch();
        if ($f === false) {
            return null;
        }
        // Igual que la columna `cliente` de vw_comprobante: la factura muestra el RUC; sin cliente es CLIENTE VARIOS
        $cliente = match (true) {
            $f['letra_serie'] === 'F' && $f['ruc'] !== null => 'RUC ' . $f['ruc'],
            $f['dni'] !== null                              => 'DNI ' . $f['dni'],
            $f['ruc'] !== null                              => 'RUC ' . $f['ruc'],
            default                                         => 'CLIENTE VARIOS',
        };
        return [
            'id_venta'    => (int) $f['id_venta'],
            'comprobante' => sprintf('%s-%08d', $f['serie'], $f['numero_correlativo']),
            'tipo'        => $f['nombre_comprobante'],
            'id_tienda'   => (int) $f['id_tienda'],
            'tienda'      => $f['nombre_tienda'],
            'fecha_hora'  => $f['fecha_hora'],
            'total'       => $f['total'],
            'estado'      => $f['estado'],
            'caja'        => (int) $f['numero_caja'],
            'cajero'      => $f['cajero'],
            'cliente'     => $cliente,
        ];
    }

    /** @return array<int, array<string, mixed>> productos vendidos, en el orden del detalle */
    public function detalleVenta(int $idVenta): array
    {
        $consulta = $this->db->prepare(
            'SELECT dv.id_producto, p.nombre_producto, p.codigo_barras, u.abreviatura, dv.cantidad, dv.precio_unitario, dv.importe
               FROM detalle_venta dv
               JOIN producto p      ON p.id_producto = dv.id_producto
               JOIN unidad_medida u ON u.id_unidad = p.id_unidad
              WHERE dv.id_venta = :venta
              ORDER BY dv.id_detalle_venta'
        );
        $consulta->execute(['venta' => $idVenta]);
        return array_map(fn(array $f): array => [
            'id_producto'     => (int) $f['id_producto'],
            'nombre'          => $f['nombre_producto'],
            'codigo_barras'   => $f['codigo_barras'],
            'unidad'          => $f['abreviatura'],
            'cantidad'        => $f['cantidad'],
            'precio_unitario' => $f['precio_unitario'],
            'importe'         => $f['importe'],
        ], $consulta->fetchAll());
    }

    /**
     * Ingresos de mercaderia de una tienda, el mas reciente primero, con quien los recibio.
     * @return array<int, array<string, mixed>>
     */
    public function ingresos(int $idTienda, int $limite, int $offset): array
    {
        $consulta = $this->db->prepare(
            "SELECT i.id_ingreso, i.id_tienda, i.fecha_hora, i.tipo_documento, i.numero_documento, i.fecha_documento, i.ruc_emisor,
                    i.razon_social, i.total, i.observacion, CONCAT(e.nombres, ' ', e.apellidos) AS empleado,
                    (SELECT COUNT(*) FROM detalle_ingreso d WHERE d.id_ingreso = i.id_ingreso) AS productos
               FROM ingreso_mercaderia i
               JOIN empleado e ON e.id_empleado = i.id_empleado
              WHERE i.id_tienda = :tienda
              ORDER BY i.id_ingreso DESC LIMIT " . max(1, min($limite, 100)) . ' OFFSET ' . max(0, $offset)
        );
        $consulta->execute(['tienda' => $idTienda]);
        return array_map(fn(array $f): array => $this->filaIngreso($f), $consulta->fetchAll());
    }

    /** @return array<string, mixed>|null el ingreso con sus productos y el stock que tienen ahora */
    public function ingreso(int $idIngreso): ?array
    {
        $consulta = $this->db->prepare(
            "SELECT i.id_ingreso, i.id_tienda, i.fecha_hora, i.tipo_documento, i.numero_documento, i.fecha_documento, i.ruc_emisor,
                    i.razon_social, i.total, i.observacion, CONCAT(e.nombres, ' ', e.apellidos) AS empleado,
                    (SELECT COUNT(*) FROM detalle_ingreso d WHERE d.id_ingreso = i.id_ingreso) AS productos
               FROM ingreso_mercaderia i
               JOIN empleado e ON e.id_empleado = i.id_empleado
              WHERE i.id_ingreso = :id"
        );
        $consulta->execute(['id' => $idIngreso]);
        $f = $consulta->fetch();
        if ($f === false) {
            return null;
        }
        $lineas = $this->db->prepare(
            'SELECT d.id_producto, p.nombre_producto, u.abreviatura, d.cantidad, d.costo_unitario, d.costo_total,
                    d.fecha_vencimiento, s.cantidad_actual
               FROM detalle_ingreso d
               JOIN producto p      ON p.id_producto = d.id_producto
               JOIN unidad_medida u ON u.id_unidad = p.id_unidad
               LEFT JOIN stock s    ON s.id_producto = d.id_producto AND s.id_tienda = :tienda
              WHERE d.id_ingreso = :id
              ORDER BY p.nombre_producto'
        );
        $lineas->execute(['tienda' => (int) $f['id_tienda'], 'id' => $idIngreso]);
        return $this->filaIngreso($f) + ['lineas' => array_map(fn(array $l): array => [
            'id_producto'       => (int) $l['id_producto'],
            'nombre'            => $l['nombre_producto'],
            'unidad'            => $l['abreviatura'],
            'cantidad'          => $l['cantidad'],
            'costo_unitario'    => $l['costo_unitario'],
            'costo_total'       => $l['costo_total'],
            'fecha_vencimiento' => $l['fecha_vencimiento'],
            'stock_actual'      => $l['cantidad_actual'] ?? '0.000',
        ], $lineas->fetchAll())];
    }

    /** @return array<string, mixed> */
    private function filaIngreso(array $f): array
    {
        return [
            'id_ingreso'      => (int) $f['id_ingreso'],
            'id_tienda'       => (int) $f['id_tienda'],
            'fecha_hora'      => $f['fecha_hora'],
            'tipo_documento'  => $f['tipo_documento'],
            'numero_documento' => $f['numero_documento'],
            'fecha_documento' => $f['fecha_documento'],
            'ruc_emisor'      => $f['ruc_emisor'],
            'razon_social'    => $f['razon_social'],
            'total'           => $f['total'],
            'observacion'     => $f['observacion'],
            'empleado'        => $f['empleado'],
            'productos'       => (int) $f['productos'],
        ];
    }
}
