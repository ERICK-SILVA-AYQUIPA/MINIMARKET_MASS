<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';
require_once __DIR__ . '/../Merma.php';

/**
 * Traslados entre tiendas y mermas. Cada uno: cabecera + stock + kardex en una transaccion.
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
}
