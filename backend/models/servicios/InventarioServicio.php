<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';
require_once __DIR__ . '/../Merma.php';
require_once __DIR__ . '/../AjusteInventario.php';
require_once __DIR__ . '/../IngresoMercaderia.php';
require_once __DIR__ . '/../OrdenCompra.php';

/**
 * Ingresos de mercaderia, traslados entre tiendas, mermas y ajustes por conteo.
 * Cada uno: cabecera + stock + kardex en una transaccion.
 */
class InventarioServicio extends Servicio
{
    /**
     * El almacenero recibe mercaderia en SU tienda con el documento del emisor (guia, factura o boleta).
     * Suma el stock, deja cada producto en el kardex y anota el ingreso en la auditoria. Devuelve el id del ingreso.
     * El ingreso queda unido a su proveedor (si el RUC aun no esta en el directorio, se agrega).
     * Con 'orden' (id de una orden de compra abierta de la tienda) el proveedor sale de la orden, solo se reciben
     * productos de ella y hasta lo que falta, y la orden pasa a PARCIAL o RECIBIDA.
     * @param array{tipo?: string, numero?: string, fecha?: string, ruc?: string, razon_social?: string, observacion?: ?string, orden?: string} $documento
     * @param array<int, array{id_producto?: string, cantidad?: string, costo?: string, vence?: string}> $lineas
     */
    public function registrarIngreso(Usuario $almacenero, array $documento, array $lineas): int
    {
        $idTienda = $almacenero->getIdTienda();
        if (!$almacenero->puedeRegistrarIngreso($idTienda)) {
            throw new ReglaNegocioException('Solo el almacenero o el administrador de la tienda registran el ingreso de mercadería.');
        }
        $tipo = (string) ($documento['tipo'] ?? '');
        if (!in_array($tipo, IngresoMercaderia::TIPOS_DOCUMENTO, true)) {
            throw new ReglaNegocioException('Elige el tipo de documento: ' . implode(', ', IngresoMercaderia::TIPOS_DOCUMENTO) . '.');
        }
        $numero = strtoupper(trim((string) ($documento['numero'] ?? '')));
        if (preg_match('/^[A-Z0-9]{1,4}-[0-9]{1,8}$/', $numero) !== 1) {
            throw new ReglaNegocioException('El número de documento va como SERIE-NÚMERO, por ejemplo F001-00012345.');
        }
        $fecha = trim((string) ($documento['fecha'] ?? ''));
        if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $fecha, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new ReglaNegocioException('La fecha del documento no es válida.');
        }
        if ($fecha > date('Y-m-d')) {
            throw new ReglaNegocioException('La fecha del documento no puede ser futura.');
        }
        $ordenTexto = trim((string) ($documento['orden'] ?? ''));
        $idOrden = null;
        if ($ordenTexto !== '') {
            if (preg_match('/^[0-9]{1,9}$/', $ordenTexto) !== 1) {
                throw new ReglaNegocioException('La orden de compra no existe.');
            }
            $idOrden = (int) $ordenTexto;
        }
        // Contra una orden, el emisor es su proveedor: el RUC y la razon social se leen de la orden
        $ruc = trim((string) ($documento['ruc'] ?? ''));
        $razonSocial = '';
        if ($idOrden === null) {
            if (preg_match('/^[0-9]{11}$/', $ruc) !== 1) {
                throw new ReglaNegocioException('El RUC del emisor debe tener 11 dígitos.');
            }
            $razonSocial = $this->textoObligatorio($documento['razon_social'] ?? null, 100, 'La razón social del emisor');
        }
        $observacion = $this->textoOpcional($documento['observacion'] ?? null, 200, 'Observación');

        return $this->enTransaccion(function () use ($almacenero, $idTienda, $tipo, $numero, $fecha, $ruc, $razonSocial, $observacion, $lineas, $idOrden): int {
            // Una tienda cerrada no recibe mercaderia, igual que en los traslados
            $tienda = $this->db->prepare('SELECT estado FROM tienda WHERE id_tienda = :id');
            $tienda->execute(['id' => $idTienda]);
            if ($tienda->fetchColumn() !== 'ACTIVA') {
                throw new ReglaNegocioException('Tu tienda no está activa: no puede recibir mercadería.');
            }
            if ($idOrden !== null) {
                // Al recibir una orden, el formulario llega lleno con lo que falta: lo que no vino se deja en 0 o vacio
                $lineas = array_filter($lineas, static fn(array $l): bool =>
                    preg_match('/^(0+(\.0*)?)?$/', trim((string) ($l['cantidad'] ?? ''))) !== 1);
                if ($lineas === []) {
                    throw new ReglaNegocioException('Anota la cantidad que llegó de al menos un producto de la orden.');
                }
            }
            $productos = $this->leerLineasIngreso($lineas);
            $total = array_sum(array_column($productos, 'costoTotal'));
            if ($total > Dinero::MAXIMO_CENTIMOS) {
                throw new ReglaNegocioException('El total del ingreso es demasiado grande.');
            }

            $pedidos = [];
            if ($idOrden !== null) {
                // La orden bloqueada hasta el fin: dos recepciones a la vez no pasan de lo pedido
                $orden = $this->db->prepare(
                    'SELECT o.estado, o.id_tienda, o.id_proveedor, p.ruc, p.razon_social
                       FROM orden_compra o JOIN proveedor p ON p.id_proveedor = o.id_proveedor
                      WHERE o.id_orden_compra = :id FOR UPDATE'
                );
                $orden->execute(['id' => $idOrden]);
                $filaOrden = $orden->fetch();
                $numeroOrden = OrdenCompra::formatear($idOrden);
                if ($filaOrden === false || (int) $filaOrden['id_tienda'] !== $idTienda) {
                    throw new ReglaNegocioException('Esa orden de compra no es de tu tienda.');
                }
                if (!in_array($filaOrden['estado'], OrdenCompra::ABIERTAS, true)) {
                    throw new ReglaNegocioException("La orden $numeroOrden ya está " . strtolower($filaOrden['estado']) . ': no recibe más mercadería.');
                }
                $idProveedor = (int) $filaOrden['id_proveedor'];
                $ruc = $filaOrden['ruc'];
                $razonSocial = $filaOrden['razon_social'];

                $detalle = $this->db->prepare(
                    'SELECT id_producto, cantidad_pedida, cantidad_recibida FROM detalle_orden_compra WHERE id_orden_compra = :id'
                );
                $detalle->execute(['id' => $idOrden]);
                foreach ($detalle->fetchAll() as $d) {
                    $pedidos[(int) $d['id_producto']] = [
                        'pedida'   => Cantidad::deDecimal($d['cantidad_pedida']),
                        'recibida' => Cantidad::deDecimal($d['cantidad_recibida']),
                    ];
                }
                foreach ($productos as $idProducto => $p) {
                    $nombre = $this->nombreProducto($idProducto);
                    if (!isset($pedidos[$idProducto])) {
                        throw new ReglaNegocioException("$nombre no está en la orden $numeroOrden: regístralo en un ingreso aparte.");
                    }
                    $falta = $pedidos[$idProducto]['pedida'] - $pedidos[$idProducto]['recibida'];
                    if ($p['milesimas'] > $falta) {
                        throw new ReglaNegocioException(sprintf('%s: de la orden %s faltan %s y llegaron %s. Lo que sobra va en un ingreso aparte.',
                            $nombre, $numeroOrden, Cantidad::aTexto($falta), Cantidad::aTexto($p['milesimas'])));
                    }
                    $pedidos[$idProducto]['recibida'] += $p['milesimas'];
                }
            } else {
                $idProveedor = $this->proveedorDelEmisor($ruc, $razonSocial);
            }

            $repetido = $this->db->prepare(
                'SELECT id_ingreso FROM ingreso_mercaderia WHERE ruc_emisor = :ruc AND tipo_documento = :tipo AND numero_documento = :numero'
            );
            $repetido->execute(['ruc' => $ruc, 'tipo' => $tipo, 'numero' => $numero]);
            $idRepetido = $repetido->fetchColumn();
            if ($idRepetido !== false) {
                throw new ReglaNegocioException("Ese documento ya se registró en el ingreso $idRepetido.");
            }

            try {
                $this->db->prepare(
                    'INSERT INTO ingreso_mercaderia (fecha_hora, tipo_documento, numero_documento, fecha_documento, ruc_emisor,
                                                     razon_social, id_proveedor, id_orden_compra, total, observacion, id_tienda, id_empleado)
                     VALUES (NOW(), :tipo, :numero, :fecha, :ruc, :razon, :proveedor, :orden, :total, :observacion, :tienda, :empleado)'
                )->execute([
                    'tipo'        => $tipo,
                    'numero'      => $numero,
                    'fecha'       => $fecha,
                    'ruc'         => $ruc,
                    'razon'       => $razonSocial,
                    'proveedor'   => $idProveedor,
                    'orden'       => $idOrden,
                    'total'       => Dinero::aTexto($total),
                    'observacion' => $observacion,
                    'tienda'      => $idTienda,
                    'empleado'    => $almacenero->getIdEmpleado(),
                ]);
            } catch (PDOException $ex) {
                // Otro almacenero lo registro al mismo tiempo
                if ($this->esDuplicado($ex)) {
                    throw new ReglaNegocioException('Ese documento ya se registró.');
                }
                throw $ex;
            }
            $idIngreso = (int) $this->db->lastInsertId();

            $documento = $tipo . ' ' . $numero;
            // En el kardex se ve tambien la orden: "FACTURA F001-00012345 · OC-000012"
            $observacionKardex = $idOrden === null ? $documento : $documento . ' · ' . OrdenCompra::formatear($idOrden);
            foreach ($productos as $idProducto => $p) {
                $this->db->prepare(
                    'INSERT INTO detalle_ingreso (id_ingreso, id_producto, cantidad, costo_unitario, costo_total, fecha_vencimiento)
                     VALUES (:ingreso, :producto, :cantidad, :costo, :total, :vence)'
                )->execute([
                    'ingreso'  => $idIngreso,
                    'producto' => $idProducto,
                    'cantidad' => Cantidad::aTexto($p['milesimas']),
                    'costo'    => Dinero::aTexto($p['costo']),
                    'total'    => Dinero::aTexto($p['costoTotal']),
                    'vence'    => $p['vence'],
                ]);
                $this->moverStock($idTienda, $idProducto, $p['milesimas'], TipoMovimiento::INGRESO,
                    $almacenero->getIdEmpleado(), ['id_ingreso' => $idIngreso], $observacionKardex);
            }

            if ($idOrden !== null) {
                foreach ($productos as $idProducto => $p) {
                    $this->db->prepare(
                        'UPDATE detalle_orden_compra SET cantidad_recibida = :recibida WHERE id_orden_compra = :orden AND id_producto = :producto'
                    )->execute(['recibida' => Cantidad::aTexto($pedidos[$idProducto]['recibida']), 'orden' => $idOrden, 'producto' => $idProducto]);
                }
                $this->db->prepare('UPDATE orden_compra SET estado = :estado WHERE id_orden_compra = :id')
                    ->execute(['estado' => OrdenCompra::estadoPorRecepcion($pedidos), 'id' => $idOrden]);
            }

            $this->auditar($almacenero, 'INGRESO_REGISTRADO', sprintf('Ingreso %d: %s de %s (RUC %s), %d producto(s), S/ %s%s',
                $idIngreso, $documento, $razonSocial, $ruc, count($productos), Dinero::aTexto($total),
                $idOrden === null ? '' : ', contra ' . OrdenCompra::formatear($idOrden)));
            return $idIngreso;
        });
    }

    /**
     * El proveedor de un emisor por su RUC. Si aun no esta en el directorio, se agrega con la razon social del documento:
     * asi todo ingreso queda unido a un proveedor, aunque se haya comprado sin orden.
     */
    private function proveedorDelEmisor(string $ruc, string $razonSocial): int
    {
        $consulta = $this->db->prepare('SELECT id_proveedor FROM proveedor WHERE ruc = :ruc');
        $consulta->execute(['ruc' => $ruc]);
        $id = $consulta->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        // INSERT IGNORE: si otro ingreso lo agrego al mismo tiempo, se usa ese
        $this->db->prepare('INSERT IGNORE INTO proveedor (ruc, razon_social) VALUES (:ruc, :razon)')
            ->execute(['ruc' => $ruc, 'razon' => $razonSocial]);
        $consulta->execute(['ruc' => $ruc]);
        return (int) $consulta->fetchColumn();
    }

    private function nombreProducto(int $idProducto): string
    {
        $consulta = $this->db->prepare('SELECT nombre_producto FROM producto WHERE id_producto = :id');
        $consulta->execute(['id' => $idProducto]);
        return (string) $consulta->fetchColumn();
    }

    /**
     * El almacenero envia mercaderia desde SU tienda a otra. Devuelve el id del traslado.
     * @param array<int, array{id_producto?: string, cantidad?: string}> $lineas
     */
    public function trasladar(Usuario $almacenero, int $idTiendaDestino, array $lineas, ?string $observacion): int
    {
        if (!$almacenero->puedeMoverMercaderia()) {
            throw new ReglaNegocioException('Solo un almacenero activo registra traslados.');
        }
        $idOrigen = $almacenero->getIdTienda();
        if ($idTiendaDestino === $idOrigen) {
            throw new ReglaNegocioException('El destino debe ser otra tienda.');
        }
        $observacion = $this->textoOpcional($observacion, 200, 'Observación');

        return $this->enTransaccion(function () use ($almacenero, $idOrigen, $idTiendaDestino, $lineas, $observacion): int {
            $destino = $this->db->prepare('SELECT estado FROM tienda WHERE id_tienda = :id');
            $destino->execute(['id' => $idTiendaDestino]);
            if ($destino->fetchColumn() !== 'ACTIVA') {
                throw new ReglaNegocioException('La tienda de destino no existe o no está activa.');
            }

            $productos = $this->leerLineas($lineas);

            $this->db->prepare(
                'INSERT INTO traslado_mercaderia (fecha_hora, observacion, id_tienda_origen, id_tienda_destino, id_empleado)
                 VALUES (NOW(), :observacion, :origen, :destino, :empleado)'
            )->execute([
                'observacion' => $observacion,
                'origen'      => $idOrigen,
                'destino'     => $idTiendaDestino,
                'empleado'    => $almacenero->getIdEmpleado(),
            ]);
            $idTraslado = (int) $this->db->lastInsertId();

            foreach ($productos as $idProducto => $p) {
                $this->moverStock($idOrigen, $idProducto, -$p['milesimas'], TipoMovimiento::TRASLADO_SALIDA,
                    $almacenero->getIdEmpleado(), ['id_traslado' => $idTraslado]);
                $this->moverStock($idTiendaDestino, $idProducto, $p['milesimas'], TipoMovimiento::TRASLADO_ENTRADA,
                    $almacenero->getIdEmpleado(), ['id_traslado' => $idTraslado]);
            }
            return $idTraslado;
        });
    }

    /**
     * El almacenero da de baja mercaderia de SU tienda. Devuelve el id de la merma.
     * @param array<int, array{id_producto?: string, cantidad?: string}> $lineas
     */
    public function registrarMerma(Usuario $almacenero, string $motivo, ?string $observacion, array $lineas): int
    {
        if (!$almacenero->puedeMoverMercaderia()) {
            throw new ReglaNegocioException('Solo un almacenero activo registra mermas.');
        }
        if (!in_array($motivo, Merma::MOTIVOS, true)) {
            throw new ReglaNegocioException('Elige el motivo: ' . implode(', ', Merma::MOTIVOS) . '.');
        }
        $observacion = $this->textoOpcional($observacion, 200, 'Observación');

        return $this->enTransaccion(function () use ($almacenero, $motivo, $observacion, $lineas): int {
            $productos = $this->leerLineas($lineas);

            $this->db->prepare(
                'INSERT INTO merma (fecha_hora, motivo, observacion, id_empleado) VALUES (NOW(), :motivo, :observacion, :empleado)'
            )->execute(['motivo' => $motivo, 'observacion' => $observacion, 'empleado' => $almacenero->getIdEmpleado()]);
            $idMerma = (int) $this->db->lastInsertId();

            foreach ($productos as $idProducto => $p) {
                $this->moverStock($almacenero->getIdTienda(), $idProducto, -$p['milesimas'], TipoMovimiento::MERMA,
                    $almacenero->getIdEmpleado(), ['id_merma' => $idMerma]);
            }
            return $idMerma;
        });
    }

    /**
     * Conteo fisico de la tienda del empleado: para cada producto contado, el stock pasa a ser
     * lo contado y la diferencia queda en el kardex (AJUSTE DE ENTRADA o DE SALIDA).
     * Devuelve el id del ajuste y cuantos productos cambiaron.
     * @param array<int, array{id_producto?: string, cantidad?: string}> $lineas cantidad = lo contado (puede ser 0)
     * @return array{idAjuste: int, cambios: int}
     */
    public function ajustarPorConteo(Usuario $empleado, string $motivo, ?string $observacion, array $lineas): array
    {
        $idTienda = $empleado->getIdTienda();
        if (!$empleado->puedeAjustarStock($idTienda)) {
            throw new ReglaNegocioException('Solo el almacenero o el administrador de la tienda ajustan su stock.');
        }
        if (!in_array($motivo, AjusteInventario::MOTIVOS, true)) {
            throw new ReglaNegocioException('Elige el motivo: ' . implode(', ', AjusteInventario::MOTIVOS) . '.');
        }
        $observacion = $this->textoOpcional($observacion, 200, 'Observación');

        return $this->enTransaccion(function () use ($empleado, $idTienda, $motivo, $observacion, $lineas): array {
            $contados = $this->leerConteo($lineas);

            // Diferencia de cada producto, con la fila de stock bloqueada (en orden de id, como las ventas)
            $diferencias = [];
            foreach ($contados as $idProducto => $contado) {
                $consulta = $this->db->prepare(
                    'SELECT cantidad_actual FROM stock WHERE id_tienda = :tienda AND id_producto = :producto FOR UPDATE'
                );
                $consulta->execute(['tienda' => $idTienda, 'producto' => $idProducto]);
                $actual = $consulta->fetchColumn();
                $diferencia = $contado - ($actual === false ? 0 : Cantidad::deDecimal((string) $actual));
                if ($diferencia !== 0) {
                    $diferencias[$idProducto] = $diferencia;
                }
            }
            if ($diferencias === []) {
                throw new ReglaNegocioException('Lo contado coincide con el sistema: no hay nada que ajustar.');
            }

            $this->db->prepare(
                'INSERT INTO ajuste_inventario (fecha_hora, motivo, observacion, id_tienda, id_empleado)
                 VALUES (NOW(), :motivo, :observacion, :tienda, :empleado)'
            )->execute(['motivo' => $motivo, 'observacion' => $observacion, 'tienda' => $idTienda, 'empleado' => $empleado->getIdEmpleado()]);
            $idAjuste = (int) $this->db->lastInsertId();

            foreach ($diferencias as $idProducto => $diferencia) {
                $this->moverStock($idTienda, $idProducto, $diferencia,
                    $diferencia > 0 ? TipoMovimiento::AJUSTE_ENTRADA : TipoMovimiento::AJUSTE_SALIDA,
                    $empleado->getIdEmpleado(), ['id_ajuste' => $idAjuste], $observacion);
            }
            return ['idAjuste' => $idAjuste, 'cambios' => count($diferencias)];
        });
    }

    /**
     * Lineas del conteo -> [id_producto => milesimas contadas], ordenado por id.
     * A diferencia de una venta, lo contado puede ser 0 y el producto puede estar descontinuado.
     * @return array<int, int>
     */
    private function leerConteo(array $lineas): array
    {
        $contados = [];
        foreach ($lineas as $linea) {
            $idTexto = trim((string) ($linea['id_producto'] ?? ''));
            $cantidadTexto = trim((string) ($linea['cantidad'] ?? ''));
            // Sin cantidad = ese producto no se conto: no se toca
            if ($cantidadTexto === '') {
                continue;
            }
            if (!ctype_digit($idTexto)) {
                throw new ReglaNegocioException('Un producto contado no es válido.');
            }
            $consulta = $this->db->prepare('SELECT nombre_producto, se_vende_por_peso FROM producto WHERE id_producto = :id');
            $consulta->execute(['id' => (int) $idTexto]);
            $producto = $consulta->fetch();
            if ($producto === false) {
                throw new ReglaNegocioException('Un producto contado no existe.');
            }
            $alPeso = $producto['se_vende_por_peso'] === 'S';
            $milesimas = preg_match('/^0+(\.0{1,3})?$/', $cantidadTexto) === 1 ? 0 : Cantidad::deTexto($cantidadTexto, $alPeso);
            if ($milesimas === null) {
                throw new ReglaNegocioException($producto['nombre_producto'] . ': cantidad contada no válida ('
                    . ($alPeso ? 'kg con hasta 3 decimales' : 'solo unidades enteras') . ').');
            }
            if (isset($contados[(int) $idTexto])) {
                throw new ReglaNegocioException($producto['nombre_producto'] . ' está dos veces: anota el total contado una sola vez.');
            }
            $contados[(int) $idTexto] = $milesimas;
        }
        if ($contados === []) {
            throw new ReglaNegocioException('Anota al menos un producto contado.');
        }
        ksort($contados);
        return $contados;
    }
}
