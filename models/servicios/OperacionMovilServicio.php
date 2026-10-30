<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';

/**
 * Una venta o un ingreso de la app se registra una sola vez aunque el celular reintente.
 *
 * La app manda una clave unica (Idempotency-Key) por operacion. En UNA transaccion se guarda la clave,
 * se hace la operacion (VentaServicio o InventarioServicio se unen a esta transaccion) y se guarda la
 * respuesta. Si la misma clave llega otra vez, se devuelve la respuesta guardada sin repetir nada.
 * Si las dos llegan a la vez, la segunda espera el candado de la clave UNIQUE y despues lee la respuesta.
 */
class OperacionMovilServicio extends Servicio
{
    /**
     * @param callable(): array $operacion la venta o el ingreso; devuelve lo que se responde
     * @return array{respuesta: array, repetida: bool}
     */
    public function unaVez(Usuario $usuario, string $clave, string $tipo, callable $operacion): array
    {
        if (preg_match('/^[A-Za-z0-9-]{8,64}$/', $clave) !== 1) {
            throw new ReglaNegocioException('Falta la cabecera Idempotency-Key (de 8 a 64 letras, números o guiones).');
        }
        $guardada = $this->respuestaGuardada($usuario, $clave, $tipo);
        if ($guardada !== null) {
            return ['respuesta' => $guardada, 'repetida' => true];
        }

        try {
            $respuesta = $this->enTransaccion(function () use ($usuario, $clave, $tipo, $operacion): array {
                // Si otro pedido con la misma clave esta en curso, este INSERT espera a que termine
                $this->db->prepare(
                    'INSERT INTO api_operacion (clave, id_empleado, tipo, fecha_hora) VALUES (:clave, :empleado, :tipo, NOW())'
                )->execute(['clave' => $clave, 'empleado' => $usuario->getIdEmpleado(), 'tipo' => $tipo]);
                $idOperacion = (int) $this->db->lastInsertId();

                $respuesta = $operacion();

                $this->db->prepare('UPDATE api_operacion SET respuesta = :respuesta WHERE id_operacion = :id')
                    ->execute(['respuesta' => json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'id' => $idOperacion]);
                return $respuesta;
            });
        } catch (PDOException $ex) {
            // El otro pedido con la misma clave termino primero: se devuelve lo que el registro
            if ($this->esDuplicado($ex)) {
                $guardada = $this->respuestaGuardada($usuario, $clave, $tipo);
                if ($guardada !== null) {
                    return ['respuesta' => $guardada, 'repetida' => true];
                }
            }
            throw $ex;
        }
        return ['respuesta' => $respuesta, 'repetida' => false];
    }

    private function respuestaGuardada(Usuario $usuario, string $clave, string $tipo): ?array
    {
        $consulta = $this->db->prepare(
            'SELECT tipo, respuesta FROM api_operacion WHERE id_empleado = :empleado AND clave = :clave'
        );
        $consulta->execute(['empleado' => $usuario->getIdEmpleado(), 'clave' => $clave]);
        $fila = $consulta->fetch();
        if ($fila === false || $fila['respuesta'] === null) {
            return null;
        }
        if ($fila['tipo'] !== $tipo) {
            throw new ReglaNegocioException('Esa Idempotency-Key ya se usó para otra operación.');
        }
        $respuesta = json_decode((string) $fila['respuesta'], true);
        return is_array($respuesta) ? $respuesta : null;
    }
}
