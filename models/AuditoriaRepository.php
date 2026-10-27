<?php
declare(strict_types=1);

/**
 * Lee la bitacora de acciones delicadas (database/auditoria.sql). Las filas las escribe Servicio::auditar().
 * Devuelve filas simples: es una lista para leer, no una entidad con reglas.
 */
class AuditoriaRepository
{
    public function __construct(private PDO $db) {}

    public function contar(int $idTienda, ?string $accion): int
    {
        [$donde, $parametros] = $this->donde($idTienda, $accion);
        $consulta = $this->db->prepare('SELECT COUNT(*) FROM auditoria a WHERE ' . $donde);
        $consulta->execute($parametros);
        return (int) $consulta->fetchColumn();
    }

    /**
     * Una pagina de la bitacora de una tienda, lo mas reciente primero.
     * @return array<int, array{id_auditoria: string, fecha_hora: string, accion: string, detalle: ?string, empleado: string, rol: string}>
     */
    public function pagina(int $idTienda, ?string $accion, int $limite, int $offset): array
    {
        [$donde, $parametros] = $this->donde($idTienda, $accion);
        // LIMIT y OFFSET son enteros calculados en PHP
        $consulta = $this->db->prepare(
            "SELECT a.id_auditoria, a.fecha_hora, a.accion, a.detalle,
                    CONCAT(e.nombres, ' ', e.apellidos) AS empleado, r.nombre_rol AS rol
               FROM auditoria a
               JOIN empleado e ON e.id_empleado = a.id_empleado
               JOIN rol r      ON r.id_rol = e.id_rol
              WHERE " . $donde . '
              ORDER BY a.id_auditoria DESC
              LIMIT ' . max(1, min($limite, 200)) . ' OFFSET ' . max(0, $offset)
        );
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    /** @return string[] las acciones que ya aparecen en la bitacora de la tienda, para el filtro */
    public function acciones(int $idTienda): array
    {
        $consulta = $this->db->prepare('SELECT DISTINCT accion FROM auditoria WHERE id_tienda = :tienda ORDER BY accion');
        $consulta->execute(['tienda' => $idTienda]);
        return $consulta->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function donde(int $idTienda, ?string $accion): array
    {
        if ($accion === null) {
            return ['a.id_tienda = :tienda', ['tienda' => $idTienda]];
        }
        return ['a.id_tienda = :tienda AND a.accion = :accion', ['tienda' => $idTienda, 'accion' => $accion]];
    }
}
