<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';
require_once __DIR__ . '/../Clave.php';

/**
 * Personal de una tienda (lo maneja su administrador) y la clave de cada empleado.
 */
class EmpleadoServicio extends Servicio
{
    private const ROLES = [1 => 'ADMINISTRADOR', 2 => 'CAJERO', 3 => 'ALMACENERO'];

    // El administrador da de alta a un empleado en SU tienda. Devuelve el id del empleado.
    public function crear(Usuario $admin, string $nombres, string $apellidos, string $dni, int $idRol, string $clave): int
    {
        $this->exigirAdministrador($admin, $admin->getIdTienda());
        $nombres = $this->textoObligatorio($nombres, 60, 'Nombres');
        $apellidos = $this->textoObligatorio($apellidos, 60, 'Apellidos');
        $dni = trim($dni);
        if (preg_match('/^[0-9]{8}$/', $dni) !== 1) {
            throw new ReglaNegocioException('El DNI debe tener 8 dígitos.');
        }
        $this->exigirRol($idRol);
        $this->exigirClave($clave);

        return $this->enTransaccion(function () use ($admin, $nombres, $apellidos, $dni, $idRol, $clave): int {
            try {
                $this->db->prepare(
                    'INSERT INTO empleado (nombres, apellidos, dni, clave_hash, estado, id_tienda, id_rol)
                     VALUES (:nombres, :apellidos, :dni, :hash, \'ACTIVO\', :tienda, :rol)'
                )->execute([
                    'nombres' => $nombres, 'apellidos' => $apellidos, 'dni' => $dni, 'hash' => Clave::hash($clave),
                    'tienda' => $admin->getIdTienda(), 'rol' => $idRol,
                ]);
            } catch (PDOException $ex) {
                if ($this->esDuplicado($ex)) {
                    throw new ReglaNegocioException('Ya hay un empleado con ese DNI.');
                }
                throw $ex;
            }
            $id = (int) $this->db->lastInsertId();
            $this->auditar($admin, 'EMPLEADO_CREADO', sprintf('%d %s %s, %s', $id, $nombres, $apellidos, self::ROLES[$idRol]));
            return $id;
        });
    }

    /**
     * Cambia nombres, rol y estado de un empleado de la tienda del administrador.
     * El administrador no cambia su propio rol ni se desactiva (no se queda sin acceso).
     */
    public function editar(Usuario $admin, int $idEmpleado, string $nombres, string $apellidos, int $idRol, string $estado): void
    {
        $nombres = $this->textoObligatorio($nombres, 60, 'Nombres');
        $apellidos = $this->textoObligatorio($apellidos, 60, 'Apellidos');
        $this->exigirRol($idRol);
        if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            throw new ReglaNegocioException('Estado no válido.');
        }

        $this->enTransaccion(function () use ($admin, $idEmpleado, $nombres, $apellidos, $idRol, $estado): void {
            $empleado = $this->empleadoDeLaTienda($admin, $idEmpleado);
            $esElMismo = $idEmpleado === $admin->getIdEmpleado();
            if ($esElMismo && ($idRol !== (int) $empleado['id_rol'] || $estado !== 'ACTIVO')) {
                throw new ReglaNegocioException('No puedes cambiar tu propio rol ni desactivarte.');
            }

            // Un cajero con la caja abierta primero cierra su turno (si no, el turno queda sin dueño activo)
            $dejaDeSerCajero = (int) $empleado['id_rol'] === 2 && ($idRol !== 2 || $estado !== 'ACTIVO');
            if ($dejaDeSerCajero) {
                $turno = $this->db->prepare('SELECT COUNT(*) FROM caja_turno WHERE id_empleado = :id AND estado = \'ABIERTO\'');
                $turno->execute(['id' => $idEmpleado]);
                if ((int) $turno->fetchColumn() > 0) {
                    throw new ReglaNegocioException('Ese cajero tiene un turno abierto: que lo cierre primero.');
                }
            }

            $this->db->prepare(
                'UPDATE empleado SET nombres = :nombres, apellidos = :apellidos, id_rol = :rol, estado = :estado
                  WHERE id_empleado = :id'
            )->execute(['nombres' => $nombres, 'apellidos' => $apellidos, 'rol' => $idRol, 'estado' => $estado, 'id' => $idEmpleado]);
            $this->auditar($admin, 'EMPLEADO_EDITADO', sprintf('%d %s %s, %s, %s', $idEmpleado, $nombres, $apellidos, self::ROLES[$idRol], $estado));
        });
    }

    // El administrador le pone una clave nueva a un empleado de su tienda (por ejemplo, si la olvido)
    public function reiniciarClave(Usuario $admin, int $idEmpleado, string $claveNueva): void
    {
        $this->exigirClave($claveNueva);
        $this->enTransaccion(function () use ($admin, $idEmpleado, $claveNueva): void {
            $this->empleadoDeLaTienda($admin, $idEmpleado);
            $this->guardarClave($idEmpleado, $claveNueva);
            $this->auditar($admin, 'CLAVE_REINICIADA', 'Empleado ' . $idEmpleado);
        });
    }

    // Cada empleado cambia su propia clave, escribiendo antes la actual
    public function cambiarMiClave(Usuario $usuario, string $actual, string $nueva, string $repetida): void
    {
        $consulta = $this->db->prepare('SELECT clave_hash FROM empleado WHERE id_empleado = :id');
        $consulta->execute(['id' => $usuario->getIdEmpleado()]);
        $hash = $consulta->fetchColumn();
        if (!is_string($hash) || !password_verify($actual, $hash)) {
            throw new ReglaNegocioException('La clave actual no es correcta.');
        }
        if ($nueva !== $repetida) {
            throw new ReglaNegocioException('La clave nueva y su repetición no coinciden.');
        }
        if (hash_equals($actual, $nueva)) {
            throw new ReglaNegocioException('La clave nueva debe ser distinta de la actual.');
        }
        $this->exigirClave($nueva);
        $this->enTransaccion(function () use ($usuario, $nueva): void {
            $this->guardarClave($usuario->getIdEmpleado(), $nueva);
            $this->auditar($usuario, 'CLAVE_CAMBIADA', 'Cambió su propia clave');
        });
    }

    private function guardarClave(int $idEmpleado, string $clave): void
    {
        $this->db->prepare('UPDATE empleado SET clave_hash = :hash WHERE id_empleado = :id')
            ->execute(['hash' => Clave::hash($clave), 'id' => $idEmpleado]);
    }

    // Bloquea la fila del empleado y revisa que sea de la tienda del administrador
    private function empleadoDeLaTienda(Usuario $admin, int $idEmpleado): array
    {
        $consulta = $this->db->prepare('SELECT id_tienda, id_rol FROM empleado WHERE id_empleado = :id FOR UPDATE');
        $consulta->execute(['id' => $idEmpleado]);
        $empleado = $consulta->fetch();
        if ($empleado === false) {
            throw new ReglaNegocioException('El empleado no existe.');
        }
        $this->exigirAdministrador($admin, (int) $empleado['id_tienda']);
        return $empleado;
    }

    private function exigirAdministrador(Usuario $admin, int $idTienda): void
    {
        if (!$admin->puedeAdministrarTienda($idTienda)) {
            throw new ReglaNegocioException('Solo el administrador de la tienda maneja su personal.');
        }
    }

    private function exigirRol(int $idRol): void
    {
        if (!isset(self::ROLES[$idRol])) {
            throw new ReglaNegocioException('Rol no válido.');
        }
    }

    private function exigirClave(string $clave): void
    {
        $problema = Clave::problema($clave);
        if ($problema !== null) {
            throw new ReglaNegocioException($problema);
        }
    }
}
