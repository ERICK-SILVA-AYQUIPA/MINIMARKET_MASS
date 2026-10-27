<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';

/**
 * Mantenimiento del catalogo: productos, categorias, marcas y el stock minimo de cada tienda.
 * El precio es uno solo para todas las tiendas; cambiarlo no toca las ventas pasadas,
 * porque detalle_venta guarda su propia copia del precio.
 */
class ProductoServicio extends Servicio
{
    private const UNIDAD_KG = 'kg';

    /**
     * Crea un producto ACTIVO y su fila de stock (en 0) en cada tienda. Devuelve el id.
     * @param array{nombre?: string, codigo?: string, precio?: string, por_peso?: string, categoria?: string, marca?: string, unidad?: string} $datos
     */
    public function crear(Usuario $admin, array $datos): int
    {
        $this->exigirAdministrador($admin);
        $porPeso = ($datos['por_peso'] ?? '') === 'S' ? 'S' : 'N';
        $campos = $this->leerDatos($datos, $porPeso);

        return $this->enTransaccion(function () use ($admin, $campos, $porPeso): int {
            try {
                $this->db->prepare(
                    'INSERT INTO producto (nombre_producto, codigo_barras, precio_venta, se_vende_por_peso, estado,
                                           id_categoria, id_marca, id_unidad)
                     VALUES (:nombre, :codigo, :precio, :peso, \'ACTIVO\', :categoria, :marca, :unidad)'
                )->execute($campos + ['peso' => $porPeso]);
            } catch (PDOException $ex) {
                throw $this->esDuplicado($ex) ? new ReglaNegocioException('Ya hay un producto con ese código de barras.') : $ex;
            }
            $id = (int) $this->db->lastInsertId();

            // Asi el producto aparece en el stock de cada tienda desde el primer dia
            $this->db->prepare(
                'INSERT INTO stock (id_tienda, id_producto, cantidad_actual, stock_minimo)
                 SELECT id_tienda, :producto, 0, 0 FROM tienda'
            )->execute(['producto' => $id]);
            $this->auditar($admin, 'PRODUCTO_CREADO', sprintf('%d %s a S/ %s', $id, $campos['nombre'], $campos['precio']));
            return $id;
        });
    }

    /**
     * Cambia nombre, codigo, precio, categoria, marca y unidad. La forma de venta (al peso o por unidad)
     * no cambia: las cantidades ya vendidas y en stock dependen de ella.
     */
    public function editar(Usuario $admin, int $idProducto, array $datos): void
    {
        $this->exigirAdministrador($admin);
        $this->enTransaccion(function () use ($admin, $idProducto, $datos): void {
            $consulta = $this->db->prepare(
                'SELECT se_vende_por_peso, nombre_producto, precio_venta FROM producto WHERE id_producto = :id FOR UPDATE'
            );
            $consulta->execute(['id' => $idProducto]);
            $antes = $consulta->fetch();
            if ($antes === false) {
                throw new ReglaNegocioException('El producto no existe.');
            }
            $campos = $this->leerDatos($datos, $antes['se_vende_por_peso']);

            try {
                $this->db->prepare(
                    'UPDATE producto
                        SET nombre_producto = :nombre, codigo_barras = :codigo, precio_venta = :precio,
                            id_categoria = :categoria, id_marca = :marca, id_unidad = :unidad
                      WHERE id_producto = :id'
                )->execute($campos + ['id' => $idProducto]);
            } catch (PDOException $ex) {
                throw $this->esDuplicado($ex) ? new ReglaNegocioException('Ya hay un producto con ese código de barras.') : $ex;
            }
            // Un cambio de precio queda aparte: es el que mas se revisa
            if ($antes['precio_venta'] !== $campos['precio']) {
                $this->auditar($admin, 'PRECIO_CAMBIADO', sprintf('%d %s: S/ %s -> S/ %s',
                    $idProducto, $campos['nombre'], $antes['precio_venta'], $campos['precio']));
            } else {
                $this->auditar($admin, 'PRODUCTO_EDITADO', sprintf('%d %s', $idProducto, $campos['nombre']));
            }
        });
    }

    // DESCONTINUADO: sigue en el catalogo y en las ventas pasadas, pero ya no se vende ni se traslada
    public function cambiarEstado(Usuario $admin, int $idProducto, string $estado): void
    {
        $this->exigirAdministrador($admin);
        if (!in_array($estado, ['ACTIVO', 'DESCONTINUADO'], true)) {
            throw new ReglaNegocioException('Estado no válido.');
        }
        $this->enTransaccion(function () use ($admin, $idProducto, $estado): void {
            $consulta = $this->db->prepare('UPDATE producto SET estado = :estado WHERE id_producto = :id');
            $consulta->execute(['estado' => $estado, 'id' => $idProducto]);
            if ($consulta->rowCount() === 0 && !$this->existe('producto', 'id_producto', $idProducto)) {
                throw new ReglaNegocioException('El producto no existe.');
            }
            $this->auditar($admin, 'PRODUCTO_ESTADO', sprintf('%d: %s', $idProducto, $estado));
        });
    }

    // El administrador fija desde cuanto se marca REPONER en SU tienda
    public function fijarStockMinimo(Usuario $admin, int $idProducto, string $minimoTexto): void
    {
        if (!$admin->puedeAdministrarTienda($admin->getIdTienda())) {
            throw new ReglaNegocioException('Solo el administrador de la tienda fija el stock mínimo.');
        }
        $consulta = $this->db->prepare('SELECT se_vende_por_peso FROM producto WHERE id_producto = :id');
        $consulta->execute(['id' => $idProducto]);
        $porPeso = $consulta->fetchColumn();
        if ($porPeso === false) {
            throw new ReglaNegocioException('El producto no existe.');
        }
        $minimoTexto = trim($minimoTexto);
        // 0 = sin minimo ("0" o "0.000"); si no, la misma regla que una cantidad
        $milesimas = preg_match('/^0+(\.0{1,3})?$/', $minimoTexto) === 1 ? 0 : Cantidad::deTexto($minimoTexto, $porPeso === 'S');
        if ($milesimas === null) {
            throw new ReglaNegocioException('Stock mínimo no válido (' . ($porPeso === 'S' ? 'kg con hasta 3 decimales' : 'unidades enteras') . ').');
        }

        // Si el producto nunca llego a la tienda, se crea su fila con cantidad 0
        $this->enTransaccion(function () use ($admin, $idProducto, $milesimas): void {
            $this->db->prepare(
                'INSERT INTO stock (id_tienda, id_producto, cantidad_actual, stock_minimo) VALUES (:tienda, :producto, 0, :minimo)
                 ON DUPLICATE KEY UPDATE stock_minimo = VALUES(stock_minimo)'
            )->execute(['tienda' => $admin->getIdTienda(), 'producto' => $idProducto, 'minimo' => Cantidad::aTexto($milesimas)]);
            $this->auditar($admin, 'STOCK_MINIMO', sprintf('Producto %d: mínimo %s', $idProducto, Cantidad::aTexto($milesimas)));
        });
    }

    public function crearCategoria(Usuario $admin, string $nombre): int
    {
        $this->exigirAdministrador($admin);
        $nombre = $this->textoObligatorio($nombre, 50, 'Nombre de la categoría');
        return $this->insertarUnico($admin, 'CATEGORIA_CREADA', $nombre,
            'INSERT INTO categoria (nombre_categoria) VALUES (:nombre)', ['nombre' => $nombre], 'Esa categoría ya existe.'
        );
    }

    public function crearMarca(Usuario $admin, string $nombre, bool $esPropia): int
    {
        $this->exigirAdministrador($admin);
        $nombre = $this->textoObligatorio($nombre, 50, 'Nombre de la marca');
        return $this->insertarUnico($admin, 'MARCA_CREADA', $nombre,
            'INSERT INTO marca (nombre_marca, es_marca_propia) VALUES (:nombre, :propia)',
            ['nombre' => $nombre, 'propia' => $esPropia ? 'S' : 'N'],
            'Esa marca ya existe.'
        );
    }

    /**
     * Valida los campos del formulario y los deja listos para el SQL.
     * @return array{nombre: string, codigo: ?string, precio: string, categoria: int, marca: int, unidad: int}
     */
    private function leerDatos(array $datos, string $porPeso): array
    {
        $nombre = $this->textoObligatorio($datos['nombre'] ?? '', 100, 'Nombre del producto');

        $codigo = trim((string) ($datos['codigo'] ?? ''));
        $codigo = $codigo === '' ? null : $codigo;
        if ($codigo !== null && preg_match('/^[0-9]{8,14}$/', $codigo) !== 1) {
            throw new ReglaNegocioException('El código de barras debe tener de 8 a 14 dígitos.');
        }
        if ($codigo === null && $porPeso !== 'S') {
            throw new ReglaNegocioException('Un producto por unidad necesita código de barras.');
        }

        $precio = Dinero::deTexto((string) ($datos['precio'] ?? ''));
        if ($precio === null || $precio <= 0) {
            throw new ReglaNegocioException('El precio debe ser mayor que 0, con hasta 2 decimales.');
        }

        $categoria = $this->idExistente($datos['categoria'] ?? '', 'categoria', 'id_categoria', 'Elige una categoría.');
        $marca = $this->idExistente($datos['marca'] ?? '', 'marca', 'id_marca', 'Elige una marca.');
        $unidad = $this->idExistente($datos['unidad'] ?? '', 'unidad_medida', 'id_unidad', 'Elige una unidad.');

        // Lo que se vende al peso se mide en kg; lo que se vende por unidad, no
        $consulta = $this->db->prepare('SELECT abreviatura FROM unidad_medida WHERE id_unidad = :id');
        $consulta->execute(['id' => $unidad]);
        $esKg = $consulta->fetchColumn() === self::UNIDAD_KG;
        if ($esKg !== ($porPeso === 'S')) {
            throw new ReglaNegocioException($porPeso === 'S'
                ? 'Un producto al peso usa la unidad kg.'
                : 'Un producto por unidad no usa la unidad kg.');
        }

        return [
            'nombre' => $nombre, 'codigo' => $codigo, 'precio' => Dinero::aTexto($precio),
            'categoria' => $categoria, 'marca' => $marca, 'unidad' => $unidad,
        ];
    }

    // $tabla y $columna son fijos en el codigo, nunca vienen del formulario
    private function idExistente(mixed $valor, string $tabla, string $columna, string $mensaje): int
    {
        $valor = is_string($valor) ? trim($valor) : '';
        if (!ctype_digit($valor) || !$this->existe($tabla, $columna, (int) $valor)) {
            throw new ReglaNegocioException($mensaje);
        }
        return (int) $valor;
    }

    private function existe(string $tabla, string $columna, int $id): bool
    {
        $consulta = $this->db->prepare("SELECT 1 FROM $tabla WHERE $columna = :id");
        $consulta->execute(['id' => $id]);
        return $consulta->fetchColumn() !== false;
    }

    private function insertarUnico(Usuario $admin, string $accion, string $nombre, string $sql, array $parametros, string $mensajeDuplicado): int
    {
        return $this->enTransaccion(function () use ($admin, $accion, $nombre, $sql, $parametros, $mensajeDuplicado): int {
            try {
                $this->db->prepare($sql)->execute($parametros);
            } catch (PDOException $ex) {
                throw $this->esDuplicado($ex) ? new ReglaNegocioException($mensajeDuplicado) : $ex;
            }
            $id = (int) $this->db->lastInsertId();
            $this->auditar($admin, $accion, $id . ' ' . $nombre);
            return $id;
        });
    }

    private function exigirAdministrador(Usuario $admin): void
    {
        if (!$admin->puedeAdministrarCatalogo()) {
            throw new ReglaNegocioException('Solo un administrador cambia el catálogo.');
        }
    }
}
