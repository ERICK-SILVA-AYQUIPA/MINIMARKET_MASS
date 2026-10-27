<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';
require_once __DIR__ . '/../Merma.php';
require_once __DIR__ . '/../AjusteInventario.php';

/**
 * Traslados entre tiendas, mermas y ajustes por conteo. Cada uno: cabecera + stock + kardex en una transaccion.
 */
class InventarioServicio extends Servicio
{
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
