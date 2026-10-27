<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';
require_once __DIR__ . '/../Merma.php';
require_once __DIR__ . '/../AjusteInventario.php';
require_once __DIR__ . '/../IngresoMercaderia.php';

/**
 * Ingresos de mercaderia, traslados entre tiendas, mermas y ajustes por conteo.
 * Cada uno: cabecera + stock + kardex en una transaccion.
 */
class InventarioServicio extends Servicio
{
    /**
     * El almacenero recibe mercaderia en SU tienda con el documento del emisor (guia, factura o boleta).
     * Suma el stock, deja cada producto en el kardex y anota el ingreso en la auditoria. Devuelve el id del ingreso.
     * @param array{tipo?: string, numero?: string, fecha?: string, ruc?: string, razon_social?: string, observacion?: ?string} $documento
     * @param array<int, array{id_producto?: string, cantidad?: string, costo?: string}> $lineas
     */
    public function registrarIngreso(Usuario $almacenero, array $documento, array $lineas): int
    {
        $idTienda = $almacenero->getIdTienda();
        if (!$almacenero->puedeRegistrarIngreso($idTienda)) {
            throw new ReglaNegocioException('Solo el almacenero de la tienda registra el ingreso de mercadería.');
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
        $ruc = trim((string) ($documento['ruc'] ?? ''));
        if (preg_match('/^[0-9]{11}$/', $ruc) !== 1) {
            throw new ReglaNegocioException('El RUC del emisor debe tener 11 dígitos.');
        }
        $razonSocial = $this->textoObligatorio($documento['razon_social'] ?? null, 100, 'La razón social del emisor');
        $observacion = $this->textoOpcional($documento['observacion'] ?? null, 200, 'Observación');

        return $this->enTransaccion(function () use ($almacenero, $idTienda, $tipo, $numero, $fecha, $ruc, $razonSocial, $observacion, $lineas): int {
            // Una tienda cerrada no recibe mercaderia, igual que en los traslados
            $tienda = $this->db->prepare('SELECT estado FROM tienda WHERE id_tienda = :id');
            $tienda->execute(['id' => $idTienda]);
            if ($tienda->fetchColumn() !== 'ACTIVA') {
                throw new ReglaNegocioException('Tu tienda no está activa: no puede recibir mercadería.');
            }
            $productos = $this->leerLineasIngreso($lineas);
            $total = array_sum(array_column($productos, 'costoTotal'));
            if ($total > Dinero::MAXIMO_CENTIMOS) {
                throw new ReglaNegocioException('El total del ingreso es demasiado grande.');
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
                                                     razon_social, total, observacion, id_tienda, id_empleado)
                     VALUES (NOW(), :tipo, :numero, :fecha, :ruc, :razon, :total, :observacion, :tienda, :empleado)'
                )->execute([
                    'tipo'        => $tipo,
                    'numero'      => $numero,
                    'fecha'       => $fecha,
                    'ruc'         => $ruc,
                    'razon'       => $razonSocial,
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
            foreach ($productos as $idProducto => $p) {
                $this->db->prepare(
                    'INSERT INTO detalle_ingreso (id_ingreso, id_producto, cantidad, costo_unitario, costo_total)
                     VALUES (:ingreso, :producto, :cantidad, :costo, :total)'
                )->execute([
                    'ingreso'  => $idIngreso,
                    'producto' => $idProducto,
                    'cantidad' => Cantidad::aTexto($p['milesimas']),
                    'costo'    => Dinero::aTexto($p['costo']),
                    'total'    => Dinero::aTexto($p['costoTotal']),
                ]);
                $this->moverStock($idTienda, $idProducto, $p['milesimas'], TipoMovimiento::INGRESO,
                    $almacenero->getIdEmpleado(), ['id_ingreso' => $idIngreso], $documento);
            }

            $this->auditar($almacenero, 'INGRESO_REGISTRADO', sprintf('Ingreso %d: %s de %s (RUC %s), %d producto(s), S/ %s',
                $idIngreso, $documento, $razonSocial, $ruc, count($productos), Dinero::aTexto($total)));
            return $idIngreso;
        });
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
     * Lineas del ingreso -> [id_producto => milesimas, costo y costo total en centimos], ordenado por id.
     * Cada producto va una sola vez, porque cada uno tiene su costo.
     * @return array<int, array{milesimas: int, costo: int, costoTotal: int}>
     */
    private function leerLineasIngreso(array $lineas): array
    {
        $resultado = [];
        foreach ($lineas as $linea) {
            $costoTexto = trim((string) ($linea['costo'] ?? ''));
            if (trim((string) ($linea['id_producto'] ?? '')) === '' && trim((string) ($linea['cantidad'] ?? '')) === '' && $costoTexto === '') {
                continue;
            }
            // Producto activo y cantidad valida, con los mismos mensajes que una venta
            $leida = $this->leerLineas([$linea]);
            $idProducto = array_key_first($leida);
            $nombre = $leida[$idProducto]['nombre'];
            if (isset($resultado[$idProducto])) {
                throw new ReglaNegocioException($nombre . ' está dos veces: anota la cantidad total en una sola línea.');
            }
            $costo = Dinero::deTexto($costoTexto);
            if ($costo === null) {
                throw new ReglaNegocioException($nombre . ': costo unitario no válido (soles con hasta 2 decimales).');
            }
            $costoTotal = Cantidad::importe($leida[$idProducto]['milesimas'], $costo);
            if ($costoTotal > Dinero::MAXIMO_CENTIMOS) {
                throw new ReglaNegocioException($nombre . ': el costo total es demasiado grande.');
            }
            $resultado[$idProducto] = ['milesimas' => $leida[$idProducto]['milesimas'], 'costo' => $costo, 'costoTotal' => $costoTotal];
        }
        if ($resultado === []) {
            throw new ReglaNegocioException('Agrega al menos un producto con su cantidad y costo.');
        }
        ksort($resultado);
        return $resultado;
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
