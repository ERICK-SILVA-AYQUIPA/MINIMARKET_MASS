<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';
require_once __DIR__ . '/../FotoProducto.php';

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

        return $this->enTransaccion(fn(): int => $this->insertarProducto($admin, $campos, $porPeso, 'WEB'));
    }

    /**
     * Producto nuevo desde el escaner de la app: llego mercaderia con un codigo que aun no esta en el catalogo.
     * Lo hace cualquier empleado activo (el cajero tambien, desde la venta). Siempre por unidad y con el codigo leido.
     * La marca se elige de la lista (marca) o se escribe (marca_nombre): si no existe, se crea.
     * stock_inicial (opcional): cuantos hay ya en la tienda de quien lo registra. Queda como un ajuste
     * CONTEO_FISICO en el kardex con su nombre, asi el cajero puede venderlo en el momento.
     * @param array{nombre?: string, codigo?: string, precio?: string, categoria?: string, marca?: string, marca_nombre?: string, unidad?: string, stock_inicial?: string} $datos
     */
    public function crearAlEscanear(Usuario $usuario, array $datos): int
    {
        if (!$usuario->puedeCrearProducto()) {
            throw new ReglaNegocioException('Tu usuario no puede registrar productos nuevos.');
        }
        if (trim((string) ($datos['codigo'] ?? '')) === '') {
            throw new ReglaNegocioException('Escanea el código de barras del producto.');
        }
        $stockTexto = trim((string) ($datos['stock_inicial'] ?? ''));
        $stockInicial = $stockTexto === '' ? 0 : Cantidad::deTexto($stockTexto, false);
        if ($stockInicial === null) {
            throw new ReglaNegocioException('Cuántos hay en la tienda: solo unidades enteras.');
        }

        return $this->enTransaccion(function () use ($usuario, $datos, $stockInicial): int {
            $marcaNombre = trim((string) ($datos['marca_nombre'] ?? ''));
            if (trim((string) ($datos['marca'] ?? '')) === '' && $marcaNombre !== '') {
                $creadas = [];
                $datos['marca'] = (string) $this->idPorNombre(true, $marcaNombre, $creadas);
                if ($creadas !== []) {
                    $this->auditar($usuario, 'MARCA_CREADA', $datos['marca'] . ' ' . $creadas[0] . ' (app)');
                }
            }
            $campos = $this->leerDatos($datos, 'N');
            $id = $this->insertarProducto($usuario, $campos, 'N', 'APP');

            if ($stockInicial > 0) {
                $this->db->prepare(
                    'INSERT INTO ajuste_inventario (fecha_hora, motivo, observacion, id_tienda, id_empleado)
                     VALUES (NOW(), \'CONTEO_FISICO\', :observacion, :tienda, :empleado)'
                )->execute(['observacion' => 'Stock inicial al registrar el producto (app)',
                    'tienda' => $usuario->getIdTienda(), 'empleado' => $usuario->getIdEmpleado()]);
                $this->moverStock($usuario->getIdTienda(), $id, $stockInicial, TipoMovimiento::AJUSTE_ENTRADA,
                    $usuario->getIdEmpleado(), ['id_ajuste' => (int) $this->db->lastInsertId()], 'Stock inicial (app)');
                $this->auditar($usuario, 'STOCK_INICIAL', sprintf('Producto %d: %s en la tienda %d',
                    $id, Cantidad::aTexto($stockInicial), $usuario->getIdTienda()));
            }
            return $id;
        });
    }

    /**
     * El administrador da por revisados productos recien agregados (migracion 022): nombre, categoria, precio y foto
     * estan bien. Solo cuentan los que estaban pendientes; los ya revisados o de la base inicial se ignoran.
     * Devuelve cuantos se marcaron. Queda en la auditoria con sus ids.
     * @param int[] $ids
     */
    public function marcarRevisados(Usuario $admin, array $ids): int
    {
        $this->exigirAdministrador($admin);
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            throw new ReglaNegocioException('Elige al menos un producto para marcarlo como revisado.');
        }
        if (count($ids) > 200) {
            throw new ReglaNegocioException('Marca hasta 200 productos a la vez.');
        }

        return $this->enTransaccion(function () use ($admin, $ids): int {
            $consulta = $this->db->prepare(
                'UPDATE producto SET fecha_revision = NOW(), id_empleado_revision = :revisor
                  WHERE id_producto = :id AND origen IS NOT NULL AND fecha_revision IS NULL'
            );
            // Solo se anotan en la auditoria los que de verdad estaban por revisar
            $revisados = [];
            foreach ($ids as $id) {
                $consulta->execute(['revisor' => $admin->getIdEmpleado(), 'id' => $id]);
                if ($consulta->rowCount() > 0) {
                    $revisados[] = $id;
                }
            }
            $marcados = count($revisados);
            if ($marcados > 0) {
                $this->auditar($admin, 'PRODUCTO_REVISADO', sprintf('%d producto(s) nuevo(s) revisado(s): %s',
                    $marcados, implode(', ', $revisados)));
            }
            return $marcados;
        });
    }

    /**
     * Crea un producto ACTIVO y su fila de stock (en 0) en cada tienda. Va dentro de una transaccion.
     * $origen: WEB (formulario) o APP (escaner); queda en el producto con quien, cuando y desde que tienda (migracion 018).
     */
    private function insertarProducto(Usuario $usuario, array $campos, string $porPeso, string $origen): int
    {
        $revisor = $usuario->puedeAdministrarCatalogo() ? $usuario->getIdEmpleado() : null;
        try {
            $this->db->prepare(
                'INSERT INTO producto (nombre_producto, codigo_barras, precio_venta, se_vende_por_peso, estado,
                                       id_categoria, id_marca, id_unidad,
                                       origen, fecha_registro, id_empleado_registro, id_tienda_registro,
                                       fecha_revision, id_empleado_revision)
                 VALUES (:nombre, :codigo, :precio, :peso, \'ACTIVO\', :categoria, :marca, :unidad,
                         :origen, NOW(), :empleado, :tienda, IF(:revisor IS NULL, NULL, NOW()), :revisor2)'
            )->execute($campos + ['peso' => $porPeso, 'origen' => $origen,
                'empleado' => $usuario->getIdEmpleado(), 'tienda' => $usuario->getIdTienda(),
                // Lo que registra el administrador ya nace revisado; lo de un cajero o almacenero queda por revisar (migracion 022)
                'revisor' => $revisor, 'revisor2' => $revisor]);
        } catch (PDOException $ex) {
            throw $this->esDuplicado($ex) ? new ReglaNegocioException('Ya hay un producto con ese código de barras.') : $ex;
        }
        $id = (int) $this->db->lastInsertId();

        // Asi el producto aparece en el stock de cada tienda desde el primer dia
        $this->db->prepare(
            'INSERT INTO stock (id_tienda, id_producto, cantidad_actual, stock_minimo)
             SELECT id_tienda, :producto, 0, 0 FROM tienda'
        )->execute(['producto' => $id]);
        $this->auditar($usuario, 'PRODUCTO_CREADO', sprintf('%d %s a S/ %s%s', $id, $campos['nombre'], $campos['precio'],
            $origen === 'APP' ? ' (app)' : ''));
        return $id;
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

    /**
     * Sube (o quita, con $contenido null) la foto de un producto. La foto vive en public/img/productos/,
     * fuera de la base, asi que primero se revisa el producto y al final queda en la auditoria.
     */
    public function cambiarFoto(Usuario $admin, int $idProducto, ?string $contenido, FotoProducto $fotos): void
    {
        $this->exigirAdministrador($admin);
        $consulta = $this->db->prepare('SELECT nombre_producto FROM producto WHERE id_producto = :id');
        $consulta->execute(['id' => $idProducto]);
        $nombre = $consulta->fetchColumn();
        if ($nombre === false) {
            throw new ReglaNegocioException('El producto no existe.');
        }
        if ($contenido === null) {
            if (!$fotos->borrar($idProducto)) {
                throw new ReglaNegocioException('Este producto no tenía foto.');
            }
            $this->auditar($admin, 'PRODUCTO_FOTO', sprintf('%d %s: foto quitada', $idProducto, $nombre));
            return;
        }
        $fotos->guardar($idProducto, $contenido);
        $this->auditar($admin, 'PRODUCTO_FOTO', sprintf('%d %s: foto nueva', $idProducto, $nombre));
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
     * Importa el catalogo leido por CatalogoCsv: crea los productos nuevos (con stock 0 en cada tienda),
     * actualiza los que ya existen (por codigo de barras, o por nombre si se venden al peso sin codigo)
     * y crea las categorias y marcas que falten. Todo o nada: si una fila tiene error, no se guarda ninguna.
     * Con $soloRevisar hace lo mismo y al final lo deshace, para ver el resultado antes de importar.
     * @param array<int, array<string, string>> $filas numero de linea => columnas de CatalogoCsv::COLUMNAS
     * @return array{creados: int, actualizados: int, sinCambios: int, precios: int, categorias: string[], marcas: string[], errores: string[]}
     */
    public function importarCatalogo(Usuario $admin, array $filas, bool $soloRevisar): array
    {
        $this->exigirAdministrador($admin);
        $r = ['creados' => 0, 'actualizados' => 0, 'sinCambios' => 0, 'precios' => 0, 'categorias' => [], 'marcas' => [], 'errores' => []];

        $this->db->beginTransaction();
        try {
            $unidades = [];
            foreach ($this->db->query('SELECT id_unidad, abreviatura FROM unidad_medida')->fetchAll() as $u) {
                $unidades[mb_strtolower($u['abreviatura'])] = (int) $u['id_unidad'];
            }
            $vistos = [];
            foreach ($filas as $linea => $f) {
                try {
                    $this->importarFila($admin, (int) $linea, $f, $unidades, $vistos, $r);
                } catch (ReglaNegocioException $ex) {
                    $r['errores'][] = "Línea $linea: " . $ex->getMessage();
                    if (count($r['errores']) >= 50) {
                        $r['errores'][] = 'Hay más errores: corrige estos y vuelve a subir el archivo.';
                        break;
                    }
                }
            }
            if ($r['errores'] === [] && !$soloRevisar) {
                $this->auditar($admin, 'CATALOGO_IMPORTADO', sprintf('%d nuevos, %d actualizados (%d precios), %d categorías y %d marcas nuevas',
                    $r['creados'], $r['actualizados'], $r['precios'], count($r['categorias']), count($r['marcas'])));
                $this->db->commit();
            } else {
                $this->db->rollBack();
            }
        } catch (Throwable $ex) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $ex;
        }
        return $r;
    }

    /**
     * Una fila del CSV. Lanza ReglaNegocioException con el problema de esa fila.
     * @param array<string, int> $unidades abreviatura => id
     * @param array<string, int> $vistos   productos ya leidos en este archivo, para no repetirlos
     */
    private function importarFila(Usuario $admin, int $linea, array $f, array $unidades, array &$vistos, array &$r): void
    {
        $alPeso = match (mb_strtoupper($f['al_peso'])) {
            'S', 'SI', 'SÍ' => 'S',
            'N', 'NO'       => 'N',
            default         => throw new ReglaNegocioException('al_peso debe ser S o N.'),
        };
        $unidad = $unidades[mb_strtolower($f['unidad'])] ?? throw new ReglaNegocioException(
            'unidad "' . $f['unidad'] . '" no existe (usa ' . implode(', ', array_keys($unidades)) . ').');
        $categoria = $this->idPorNombre(false, $f['categoria'], $r['categorias']);
        $marca = $this->idPorNombre(true, $f['marca'], $r['marcas']);
        $campos = $this->leerDatos(['nombre' => $f['nombre'], 'codigo' => $f['codigo_barras'], 'precio' => $f['precio_venta'],
            'categoria' => (string) $categoria, 'marca' => (string) $marca, 'unidad' => (string) $unidad], $alPeso);

        $clave = $campos['codigo'] ?? 'nombre:' . mb_strtolower($campos['nombre']);
        if (isset($vistos[$clave])) {
            throw new ReglaNegocioException('el producto ya está en la línea ' . $vistos[$clave] . ' del archivo.');
        }
        $vistos[$clave] = $linea;

        // Ya existe: por codigo de barras, o por nombre si no tiene codigo
        $consulta = $campos['codigo'] !== null
            ? $this->db->prepare('SELECT id_producto, nombre_producto, precio_venta, se_vende_por_peso, id_categoria, id_marca, id_unidad
                                    FROM producto WHERE codigo_barras = :clave FOR UPDATE')
            : $this->db->prepare('SELECT id_producto, nombre_producto, precio_venta, se_vende_por_peso, id_categoria, id_marca, id_unidad
                                    FROM producto WHERE codigo_barras IS NULL AND nombre_producto = :clave FOR UPDATE');
        $consulta->execute(['clave' => $campos['codigo'] ?? $campos['nombre']]);
        $actual = $consulta->fetch();

        if ($actual === false) {
            $this->db->prepare(
                'INSERT INTO producto (nombre_producto, codigo_barras, precio_venta, se_vende_por_peso, estado, id_categoria, id_marca, id_unidad,
                                       origen, fecha_registro, id_empleado_registro, id_tienda_registro, fecha_revision, id_empleado_revision)
                 VALUES (:nombre, :codigo, :precio, :peso, \'ACTIVO\', :categoria, :marca, :unidad, \'CSV\', NOW(), :empleado, :tienda,
                         NOW(), :revisor)'
            )->execute($campos + ['peso' => $alPeso, 'empleado' => $admin->getIdEmpleado(), 'tienda' => $admin->getIdTienda(),
                'revisor' => $admin->getIdEmpleado()]);
            $this->db->prepare(
                'INSERT INTO stock (id_tienda, id_producto, cantidad_actual, stock_minimo) SELECT id_tienda, :producto, 0, 0 FROM tienda'
            )->execute(['producto' => (int) $this->db->lastInsertId()]);
            $r['creados']++;
            return;
        }
        if ($actual['se_vende_por_peso'] !== $alPeso) {
            throw new ReglaNegocioException($campos['nombre'] . ': no se puede cambiar entre por unidad y al peso.');
        }
        $igual = $actual['nombre_producto'] === $campos['nombre'] && $actual['precio_venta'] === $campos['precio']
            && (int) $actual['id_categoria'] === $campos['categoria'] && (int) $actual['id_marca'] === $campos['marca']
            && (int) $actual['id_unidad'] === $campos['unidad'];
        if ($igual) {
            $r['sinCambios']++;
            return;
        }
        $this->db->prepare(
            'UPDATE producto SET nombre_producto = :nombre, precio_venta = :precio, id_categoria = :categoria, id_marca = :marca, id_unidad = :unidad
              WHERE id_producto = :id'
        )->execute(['nombre' => $campos['nombre'], 'precio' => $campos['precio'], 'categoria' => $campos['categoria'],
            'marca' => $campos['marca'], 'unidad' => $campos['unidad'], 'id' => (int) $actual['id_producto']]);
        $r['actualizados']++;
        if ($actual['precio_venta'] !== $campos['precio']) {
            $r['precios']++;
            $this->auditar($admin, 'PRECIO_CAMBIADO', sprintf('%d %s: S/ %s -> S/ %s (CSV)',
                (int) $actual['id_producto'], $campos['nombre'], $actual['precio_venta'], $campos['precio']));
        }
    }

    /**
     * Id de una categoria (o marca) por su nombre, sin importar mayusculas; si no existe, la crea.
     * @param string[] $creadas nombres creados en esta importacion
     */
    private function idPorNombre(bool $esMarca, string $nombre, array &$creadas): int
    {
        $nombre = $this->textoObligatorio($nombre, 50, $esMarca ? 'Marca' : 'Categoría');
        $sql = $esMarca
            ? 'SELECT id_marca FROM marca WHERE LOWER(nombre_marca) = LOWER(:nombre)'
            : 'SELECT id_categoria FROM categoria WHERE LOWER(nombre_categoria) = LOWER(:nombre)';
        $consulta = $this->db->prepare($sql);
        $consulta->execute(['nombre' => $nombre]);
        $id = $consulta->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        $sql = $esMarca
            ? 'INSERT INTO marca (nombre_marca, es_marca_propia) VALUES (:nombre, \'N\')'
            : 'INSERT INTO categoria (nombre_categoria) VALUES (:nombre)';
        $this->db->prepare($sql)->execute(['nombre' => $nombre]);
        $creadas[] = $nombre;
        return (int) $this->db->lastInsertId();
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
