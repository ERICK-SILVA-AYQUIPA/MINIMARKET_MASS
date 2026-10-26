<?php
declare(strict_types=1);

require_once __DIR__ . '/Administrador.php';
require_once __DIR__ . '/Cajero.php';
require_once __DIR__ . '/Almacenero.php';

/**
 * Empleados de las 3 tiendas, leidos de MySQL. Cada fila se convierte
 * en el objeto de su rol segun id_rol: 1 Administrador, 2 Cajero, 3 Almacenero.
 */
class EmpleadoRepository
{
    private const COLUMNAS = 'id_empleado, nombres, apellidos, dni, estado, id_tienda, id_rol';

    public function __construct(private PDO $db) {}

    /** @return Usuario[] */
    public function todos(): array
    {
        $consulta = $this->db->query('SELECT ' . self::COLUMNAS . ' FROM empleado ORDER BY id_empleado');
        return array_map(fn(array $fila): Usuario => $this->crear($fila), $consulta->fetchAll());
    }

    public function buscarPorId(int $idEmpleado): ?Usuario
    {
        $consulta = $this->db->prepare('SELECT ' . self::COLUMNAS . ' FROM empleado WHERE id_empleado = :id');
        $consulta->execute(['id' => $idEmpleado]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $this->crear($fila);
    }

    /**
     * Para el login: id y hash de la clave del empleado con ese DNI. null si el DNI no existe.
     * El hash nunca sale de aqui hacia una vista.
     * @return array{idEmpleado: int, claveHash: ?string}|null
     */
    public function credencialesPorDni(string $dni): ?array
    {
        $consulta = $this->db->prepare('SELECT id_empleado, clave_hash FROM empleado WHERE dni = :dni');
        $consulta->execute(['dni' => $dni]);
        $f = $consulta->fetch();
        return $f === false ? null : ['idEmpleado' => (int) $f['id_empleado'], 'claveHash' => $f['clave_hash']];
    }

    /** @return Usuario[] */
    public function deTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM empleado WHERE id_tienda = :tienda ORDER BY id_rol, id_empleado'
        );
        $consulta->execute(['tienda' => $idTienda]);
        return array_map(fn(array $fila): Usuario => $this->crear($fila), $consulta->fetchAll());
    }

    // Elige la clase hija segun el rol de la fila
    private function crear(array $fila): Usuario
    {
        $datos = [
            (int) $fila['id_empleado'],
            $fila['nombres'],
            $fila['apellidos'],
            $fila['dni'],
            $fila['estado'],
            (int) $fila['id_tienda'],
        ];
        return match ((int) $fila['id_rol']) {
            1 => new Administrador(...$datos),
            2 => new Cajero(...$datos),
            3 => new Almacenero(...$datos),
            default => throw new UnexpectedValueException('id_rol desconocido: ' . $fila['id_rol']),
        };
    }
}
