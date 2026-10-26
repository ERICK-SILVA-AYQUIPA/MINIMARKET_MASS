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
}
