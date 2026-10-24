<?php
declare(strict_types=1);

/**
 * Clase base de los roles. Representa una fila de la tabla `empleado`
 * (en nuestro diseno el usuario del sistema es el empleado y entra con su DNI).
 * Es abstract: no existe un "usuario" sin rol.
 */
abstract class Usuario
{
    public function __construct(
        private int $idEmpleado,      // id_empleado MEDIUMINT UNSIGNED
        private string $nombres,      // nombres VARCHAR(60)
        private string $apellidos,    // apellidos VARCHAR(60)
        private string $dni,          // dni CHAR(8), unico
        private string $estado,       // estado: 'ACTIVO' o 'INACTIVO'
        private int $idTienda         // id_tienda: la tienda donde trabaja
    ) {
        if (preg_match('/^[0-9]{8}$/', $dni) !== 1) {
            throw new InvalidArgumentException('El DNI debe tener 8 digitos');
        }
        if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            throw new InvalidArgumentException("estado debe ser 'ACTIVO' o 'INACTIVO'");
        }
    }

    // Cada rol hijo dice su id_rol y su nombre_rol (tabla rol)
    abstract public function getIdRol(): int;
    abstract public function getNombreRol(): string;

    public function getIdEmpleado(): int { return $this->idEmpleado; }
    public function getNombres(): string { return $this->nombres; }
    public function getApellidos(): string { return $this->apellidos; }
    public function getEstado(): string { return $this->estado; }
    public function getIdTienda(): int { return $this->idTienda; }

    public function nombreCompleto(): string
    {
        return $this->nombres . ' ' . $this->apellidos;
    }

    // Para mostrar en pantalla sin exponer el DNI completo
    public function dniOculto(): string
    {
        return str_repeat('*', 5) . substr($this->dni, -3);
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    // ----- Permisos: por defecto NO. Cada rol hijo habilita los suyos. -----

    public function puedeVender(): bool
    {
        return false;
    }

    public function puedeAnularVenta(int $idTiendaDeLaVenta): bool
    {
        return false;
    }

    public function puedeMoverMercaderia(): bool
    {
        return false;
    }
}
