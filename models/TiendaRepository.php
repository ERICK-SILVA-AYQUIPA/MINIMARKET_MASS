<?php
declare(strict_types=1);

require_once __DIR__ . '/Tienda.php';
require_once __DIR__ . '/Caja.php';

/**
 * Tiendas, distritos y cajas, leidos de MySQL con consultas preparadas.
 */
class TiendaRepository
{
    private const COLUMNAS = 'id_tienda, nombre_tienda, direccion, telefono, estado, id_distrito';

    public function __construct(private PDO $db) {}

    /** @return array<int, Tienda> id_tienda => Tienda */
    public function todas(): array
    {
        $tiendas = [];
        foreach ($this->db->query('SELECT ' . self::COLUMNAS . ' FROM tienda ORDER BY id_tienda') as $fila) {
            $tienda = $this->crear($fila);
            $tiendas[$tienda->getIdTienda()] = $tienda;
        }
        return $tiendas;
    }

    public function buscarPorId(int $idTienda): ?Tienda
    {
        $consulta = $this->db->prepare('SELECT ' . self::COLUMNAS . ' FROM tienda WHERE id_tienda = :id');
        $consulta->execute(['id' => $idTienda]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $this->crear($fila);
    }

    public function nombreDistrito(int $idDistrito): ?string
    {
        $consulta = $this->db->prepare('SELECT nombre_distrito FROM distrito WHERE id_distrito = :id');
        $consulta->execute(['id' => $idDistrito]);
        $nombre = $consulta->fetchColumn();
        return $nombre === false ? null : (string) $nombre;
    }

    public function buscarCaja(int $idCaja): ?Caja
    {
        $consulta = $this->db->prepare('SELECT id_caja, numero_caja, estado, id_tienda FROM caja WHERE id_caja = :id');
        $consulta->execute(['id' => $idCaja]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $this->crearCaja($fila);
    }

    /** @return Caja[] */
    public function cajasDeTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            'SELECT id_caja, numero_caja, estado, id_tienda FROM caja WHERE id_tienda = :tienda ORDER BY numero_caja'
        );
        $consulta->execute(['tienda' => $idTienda]);
        return array_map(fn(array $fila): Caja => $this->crearCaja($fila), $consulta->fetchAll());
    }

    private function crear(array $fila): Tienda
    {
        return new Tienda(
            (int) $fila['id_tienda'],
            $fila['nombre_tienda'],
            $fila['direccion'],
            $fila['telefono'],
            $fila['estado'],
            (int) $fila['id_distrito']
        );
    }

    private function crearCaja(array $fila): Caja
    {
        return new Caja((int) $fila['id_caja'], (int) $fila['numero_caja'], $fila['estado'], (int) $fila['id_tienda']);
    }
}
