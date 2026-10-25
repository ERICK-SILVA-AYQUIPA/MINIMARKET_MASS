<?php
declare(strict_types=1);

require_once __DIR__ . '/Cliente.php';

/**
 * Clientes. Hoy: lista fija de database/datos.sql.
 */
class ClienteRepository
{
    /** @var Cliente[] */
    private array $clientes;

    public function __construct()
    {
        // INSERT INTO cliente ... (datos.sql): solo DNI, solo RUC (empresa), ambos
        $this->clientes = [
            new Cliente(1, '45678912', null),
            new Cliente(2, null, '20123456789'),
            new Cliente(3, '71234567', '10712345678'),
        ];
    }

    public function buscarPorId(int $idCliente): ?Cliente
    {
        foreach ($this->clientes as $cliente) {
            if ($cliente->getIdCliente() === $idCliente) {
                return $cliente;
            }
        }
        return null;
    }
}
