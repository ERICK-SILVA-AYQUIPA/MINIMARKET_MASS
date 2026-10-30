<?php
declare(strict_types=1);

require_once __DIR__ . '/Cliente.php';

/**
 * Clientes, leidos de MySQL con consultas preparadas.
 */
class ClienteRepository
{
    public function __construct(private PDO $db) {}

    public function buscarPorId(int $idCliente): ?Cliente
    {
        $consulta = $this->db->prepare('SELECT id_cliente, dni, ruc FROM cliente WHERE id_cliente = :id');
        $consulta->execute(['id' => $idCliente]);
        $fila = $consulta->fetch();
        return $fila === false ? null : new Cliente((int) $fila['id_cliente'], $fila['dni'], $fila['ruc']);
    }

    /**
     * Una pagina de clientes con cuantas compras EMITIDAS tienen y cuanto suman.
     * Con $documento, solo los que tienen un DNI o RUC que empieza con esos digitos.
     * @return array<int, array{cliente: Cliente, compras: int, total: float}>
     */
    public function buscar(?string $documento, int $limite, int $offset): array
    {
        [$donde, $parametros] = $this->donde($documento);
        // LIMIT y OFFSET son enteros calculados en PHP
        $consulta = $this->db->prepare(
            "SELECT c.id_cliente, c.dni, c.ruc,
                    COUNT(v.id_venta) AS compras, COALESCE(SUM(v.total), 0) AS total
               FROM cliente c
               LEFT JOIN venta v ON v.id_cliente = c.id_cliente AND v.estado = 'EMITIDA'
              WHERE $donde
              GROUP BY c.id_cliente, c.dni, c.ruc
              ORDER BY total DESC, c.id_cliente
              LIMIT " . (int) $limite . ' OFFSET ' . (int) $offset
        );
        $consulta->execute($parametros);
        return array_map(fn(array $f): array => [
            'cliente' => new Cliente((int) $f['id_cliente'], $f['dni'], $f['ruc']),
            'compras' => (int) $f['compras'],
            'total'   => (float) $f['total'],
        ], $consulta->fetchAll());
    }

    public function contar(?string $documento): int
    {
        [$donde, $parametros] = $this->donde($documento);
        $consulta = $this->db->prepare("SELECT COUNT(*) FROM cliente c WHERE $donde");
        $consulta->execute($parametros);
        return (int) $consulta->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function donde(?string $documento): array
    {
        if ($documento === null) {
            return ['1 = 1', []];
        }
        // El documento ya viene validado como digitos; LIKE con prefijo usa el indice UNIQUE
        return ['(c.dni LIKE :dni OR c.ruc LIKE :ruc)', ['dni' => $documento . '%', 'ruc' => $documento . '%']];
    }
}
