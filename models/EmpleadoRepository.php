<?php
declare(strict_types=1);

require_once __DIR__ . '/Administrador.php';
require_once __DIR__ . '/Cajero.php';
require_once __DIR__ . '/Almacenero.php';

/**
 * Empleados de las 3 tiendas. Cada fila se convierte en el objeto de su rol
 * segun id_rol: 1 Administrador, 2 Cajero, 3 Almacenero.
 */
class EmpleadoRepository
{
    /** @var Usuario[] */
    private array $empleados = [];

    public function __construct()
    {
        // INSERT INTO empleado ... (datos.sql): id, nombres, apellidos, dni, estado, id_tienda, id_rol
        $filas = [
            [1, 'Rosa', 'Quispe Huaman', '70000001', 'ACTIVO', 1, 1],
            [2, 'Luis', 'Caceres Pinto', '70000002', 'ACTIVO', 1, 2],
            [3, 'Carmen', 'Ccori Flores', '70000003', 'ACTIVO', 1, 3],
            [4, 'Jorge', 'Mendoza Salas', '70000004', 'ACTIVO', 2, 1],
            [5, 'Ana', 'Valdivia Rojas', '70000005', 'ACTIVO', 2, 2],
            [6, 'Pedro', 'Choque Ramos', '70000006', 'ACTIVO', 2, 3],
            [7, 'Lucia', 'Zegarra Paredes', '70000007', 'ACTIVO', 3, 1],
            [8, 'Miguel', 'Nunez Cuadros', '70000008', 'ACTIVO', 3, 2],
            [9, 'Sofia', 'Linares Avila', '70000009', 'ACTIVO', 3, 3],
        ];

        foreach ($filas as [$id, $nombres, $apellidos, $dni, $estado, $idTienda, $idRol]) {
            $this->empleados[] = $this->crear($id, $nombres, $apellidos, $dni, $estado, $idTienda, $idRol);
        }
    }

    // Elige la clase hija segun el rol. En la sesion 5 cada fila de PDO pasara por aqui.
    private function crear(int $id, string $nombres, string $apellidos, string $dni, string $estado, int $idTienda, int $idRol): Usuario
    {
        return match ($idRol) {
            1 => new Administrador($id, $nombres, $apellidos, $dni, $estado, $idTienda),
            2 => new Cajero($id, $nombres, $apellidos, $dni, $estado, $idTienda),
            3 => new Almacenero($id, $nombres, $apellidos, $dni, $estado, $idTienda),
            default => throw new InvalidArgumentException('id_rol desconocido: ' . $idRol),
        };
    }

    /** @return Usuario[] */
    public function todos(): array
    {
        return $this->empleados;
    }

    public function buscarPorId(int $idEmpleado): ?Usuario
    {
        foreach ($this->empleados as $empleado) {
            if ($empleado->getIdEmpleado() === $idEmpleado) {
                return $empleado;
            }
        }
        return null;
    }

    /** @return Usuario[] */
    public function deTienda(int $idTienda): array
    {
        return array_values(array_filter(
            $this->empleados,
            fn(Usuario $empleado): bool => $empleado->getIdTienda() === $idTienda
        ));
    }
}
