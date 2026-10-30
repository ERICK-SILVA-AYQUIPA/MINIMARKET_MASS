<?php
declare(strict_types=1);

require_once __DIR__ . '/../Dinero.php';
require_once __DIR__ . '/../Cantidad.php';
require_once __DIR__ . '/../ReglaNegocioException.php';
require_once __DIR__ . '/../TipoMovimiento.php';
require_once __DIR__ . '/../Usuario.php';

/**
 * Base de los servicios que ESCRIBEN en la base.
 * Cada operacion corre en una transaccion: o se guarda todo, o no se guarda nada.
 */
abstract class Servicio
{
    public function __construct(protected PDO $db) {}

    /**
     * Ejecuta $operacion dentro de una transaccion. Si algo falla, deshace todo (rollBack).
     * @template T
     * @param callable(): T $operacion
     * @return T
     */
    protected function enTransaccion(callable $operacion): mixed
    {
        // Ya hay una transaccion abierta (la API la abre para guardar la respuesta junto con la venta):
        // la operacion se une a ella y el commit o el rollBack los hace quien la abrio
        if ($this->db->inTransaction()) {
            return $operacion();
        }
        $this->db->beginTransaction();
        try {
            $resultado = $operacion();
            $this->db->commit();
            return $resultado;
        } catch (Throwable $ex) {
            $this->db->rollBack();
            throw $ex;
        }
    }

    /**
     * Convierte las lineas del formulario en [id_producto => milesimas], validando cada producto.
     * Lineas vacias se ignoran; el mismo producto en dos lineas se suma.
     * @param array<int, array{id_producto?: string, cantidad?: string}> $lineas
     * @return array<int, array{milesimas: int, precio: int, nombre: string}> ordenado por id_producto
     */
    protected function leerLineas(array $lineas): array
    {
        $resultado = [];
        foreach ($lineas as $linea) {
            $idTexto = trim((string) ($linea['id_producto'] ?? ''));
            $cantidadTexto = trim((string) ($linea['cantidad'] ?? ''));
            if ($idTexto === '' && $cantidadTexto === '') {
                continue;
            }
            if (!ctype_digit($idTexto)) {
                throw new ReglaNegocioException('Elige un producto en cada línea con cantidad.');
            }

            $consulta = $this->db->prepare(
                'SELECT id_producto, nombre_producto, precio_venta, se_vende_por_peso, estado
                   FROM producto WHERE id_producto = :id'
            );
            $consulta->execute(['id' => (int) $idTexto]);
            $producto = $consulta->fetch();
            if ($producto === false) {
                throw new ReglaNegocioException('Un producto elegido no existe.');
            }
            if ($producto['estado'] !== 'ACTIVO') {
                throw new ReglaNegocioException($producto['nombre_producto'] . ' está descontinuado.');
            }

            $alPeso = $producto['se_vende_por_peso'] === 'S';
            $milesimas = Cantidad::deTexto($cantidadTexto, $alPeso);
            if ($milesimas === null) {
                throw new ReglaNegocioException($producto['nombre_producto'] . ': cantidad no válida ('
                    . ($alPeso ? 'kg con hasta 3 decimales' : 'solo unidades enteras') . ').');
            }

            $id = (int) $producto['id_producto'];
            if (isset($resultado[$id])) {
                $resultado[$id]['milesimas'] += $milesimas;
                if ($resultado[$id]['milesimas'] > Cantidad::MAXIMO_MILESIMAS) {
                    throw new ReglaNegocioException($producto['nombre_producto'] . ': la cantidad total es demasiado grande.');
                }
            } else {
                $resultado[$id] = [
                    'milesimas' => $milesimas,
                    'precio'    => Dinero::deTexto($producto['precio_venta']),
                    'nombre'    => $producto['nombre_producto'],
                ];
            }
        }

        if ($resultado === []) {
            throw new ReglaNegocioException('Agrega al menos un producto con su cantidad.');
        }
        // Siempre en el mismo orden: asi dos operaciones a la vez bloquean el stock sin trabarse
        ksort($resultado);
        return $resultado;
    }

    /**
     * Suma (positivo) o resta (negativo) stock de un producto en una tienda y lo anota en el kardex.
     * Bloquea la fila de stock (FOR UPDATE) hasta el fin de la transaccion.
     * @param array{id_venta?: int, id_traslado?: int, id_merma?: int, id_ajuste?: int, id_ingreso?: int, id_devolucion?: int} $origen exactamente uno
     */
    protected function moverStock(
        int $idTienda,
        int $idProducto,
        int $milesimas,
        int $idTipoMovimiento,
        int $idEmpleado,
        array $origen,
        ?string $observacion = null
    ): void {
        $consulta = $this->db->prepare(
            'SELECT cantidad_actual FROM stock WHERE id_tienda = :tienda AND id_producto = :producto FOR UPDATE'
        );
        $consulta->execute(['tienda' => $idTienda, 'producto' => $idProducto]);
        $actual = $consulta->fetchColumn();

        if ($actual === false) {
            if ($milesimas < 0) {
                throw new ReglaNegocioException('El producto no tiene stock registrado en esta tienda.');
            }
            // Primera vez que llega este producto a la tienda: se crea su fila de stock con minimo 0
            $this->db->prepare(
                'INSERT INTO stock (id_tienda, id_producto, cantidad_actual, stock_minimo) VALUES (:tienda, :producto, 0, 0)'
            )->execute(['tienda' => $idTienda, 'producto' => $idProducto]);
            $actual = '0';
        }

        $nuevo = Cantidad::deDecimal((string) $actual) + $milesimas;
        if ($nuevo > Cantidad::MAXIMO_MILESIMAS) {
            throw new ReglaNegocioException('El stock pasaría de ' . Cantidad::aTexto(Cantidad::MAXIMO_MILESIMAS) . ', el máximo que se puede guardar.');
        }
        if ($nuevo < 0) {
            throw new ReglaNegocioException(sprintf(
                'Stock insuficiente: hay %s y se necesitan %s.',
                Cantidad::aTexto(Cantidad::deDecimal((string) $actual)),
                Cantidad::aTexto(-$milesimas)
            ));
        }

        $this->db->prepare(
            'UPDATE stock SET cantidad_actual = :nuevo WHERE id_tienda = :tienda AND id_producto = :producto'
        )->execute(['nuevo' => Cantidad::aTexto($nuevo), 'tienda' => $idTienda, 'producto' => $idProducto]);

        $this->db->prepare(
            'INSERT INTO movimiento_stock
                    (cantidad, stock_nuevo, observacion, id_tienda, id_producto, id_tipo_movimiento,
                     id_empleado, id_venta, id_traslado, id_merma, id_ajuste, id_ingreso, id_devolucion)
             VALUES (:cantidad, :nuevo, :observacion, :tienda, :producto, :tipo,
                     :empleado, :venta, :traslado, :merma, :ajuste, :ingreso, :devolucion)'
        )->execute([
            'cantidad'    => Cantidad::aTexto(abs($milesimas)),
            'nuevo'       => Cantidad::aTexto($nuevo),
            'observacion' => $observacion,
            'tienda'      => $idTienda,
            'producto'    => $idProducto,
            'tipo'        => $idTipoMovimiento,
            'empleado'    => $idEmpleado,
            'venta'       => $origen['id_venta'] ?? null,
            'traslado'    => $origen['id_traslado'] ?? null,
            'merma'       => $origen['id_merma'] ?? null,
            'ajuste'      => $origen['id_ajuste'] ?? null,
            'ingreso'     => $origen['id_ingreso'] ?? null,
            'devolucion'  => $origen['id_devolucion'] ?? null,
        ]);
    }

    /**
     * Efectivo que deberia haber en la gaveta de un turno, en centimos:
     * monto inicial + efectivo cobrado - devoluciones + ingresos de sencillo - retiros.
     */
    protected function efectivoEnGaveta(int $idTurno): int
    {
        // DECIMAL es exacto en MySQL: la suma se hace alli y vuelve ya en centimos
        $consulta = $this->db->prepare(
            'SELECT CAST((ct.monto_inicial
                    + COALESCE((SELECT SUM(pv.monto_pagado) FROM venta v JOIN pago_venta pv ON pv.id_venta = v.id_venta
                                 WHERE v.id_caja_turno = ct.id_caja_turno AND pv.estado = \'VALIDO\' AND pv.id_metodo_pago = 1), 0)
                    - COALESCE((SELECT SUM(d.total) FROM devolucion_venta d WHERE d.id_caja_turno = ct.id_caja_turno), 0)
                    + COALESCE((SELECT SUM(IF(m.tipo = \'INGRESO\', m.monto, -m.monto)) FROM movimiento_caja m
                                 WHERE m.id_caja_turno = ct.id_caja_turno), 0)) * 100 AS SIGNED)
               FROM caja_turno ct WHERE ct.id_caja_turno = :turno'
        );
        $consulta->execute(['turno' => $idTurno]);
        return (int) $consulta->fetchColumn();
    }

    // Texto obligatorio del formulario: sin espacios a los lados, de 1 al maximo de la columna
    protected function textoObligatorio(?string $texto, int $maximo, string $campo): string
    {
        $texto = $this->textoOpcional($texto, $maximo, $campo);
        if ($texto === null) {
            throw new ReglaNegocioException($campo . ' es obligatorio.');
        }
        return $texto;
    }

    /**
     * Anota una accion delicada en la bitacora (database/auditoria.sql), con la tienda de quien la hizo.
     * Se llama dentro de la misma transaccion que el cambio: o quedan los dos, o ninguno.
     */
    protected function auditar(Usuario $usuario, string $accion, ?string $detalle = null): void
    {
        $this->db->prepare(
            'INSERT INTO auditoria (fecha_hora, accion, detalle, id_tienda, id_empleado)
             VALUES (NOW(), :accion, :detalle, :tienda, :empleado)'
        )->execute([
            'accion'   => $accion,
            'detalle'  => $detalle === null ? null : mb_substr($detalle, 0, 250),
            'tienda'   => $usuario->getIdTienda(),
            'empleado' => $usuario->getIdEmpleado(),
        ]);
    }

    // Error 1062 de MySQL: el valor ya existe en una columna UNIQUE
    protected function esDuplicado(PDOException $ex): bool
    {
        return ($ex->errorInfo[1] ?? null) === 1062;
    }

    // Texto opcional del formulario: vacio -> null; mas largo que la columna -> error
    protected function textoOpcional(?string $texto, int $maximo, string $campo): ?string
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return null;
        }
        if (mb_strlen($texto) > $maximo) {
            throw new ReglaNegocioException(sprintf('%s: máximo %d caracteres.', $campo, $maximo));
        }
        return $texto;
    }
}
