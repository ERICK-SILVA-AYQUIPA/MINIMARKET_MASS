<?php
declare(strict_types=1);

require_once __DIR__ . '/Tienda.php';
require_once __DIR__ . '/Caja.php';

/**
 * Tiendas, distritos y cajas. Hoy: listas fijas copiadas de database/datos.sql.
 * Sesion 5: los mismos metodos leeran de MySQL con PDO.
 */
class TiendaRepository
{
    /** @var array<int, string> id_distrito => nombre_distrito */
    private array $distritos = [
        1 => 'Cayma',
        2 => 'Cerro Colorado',
        3 => 'Yanahuara',
    ];

    /** @var array<int, Tienda> */
    private array $tiendas = [];

    /** @var Caja[] */
    private array $cajas;

    public function __construct()
    {
        // INSERT INTO tienda ... (datos.sql)
        foreach ([
            new Tienda(1, 'Mass Cayma', 'Direccion de ejemplo, Cayma', null, 'ACTIVA', 1),
            new Tienda(2, 'Mass Cerro Colorado', 'Direccion de ejemplo, Cerro Colorado', '054000002', 'ACTIVA', 2),
            new Tienda(3, 'Mass Yanahuara', 'Direccion de ejemplo, Yanahuara', null, 'ACTIVA', 3),
        ] as $tienda) {
            $this->tiendas[$tienda->getIdTienda()] = $tienda;
        }

        // INSERT INTO caja ... (datos.sql): dos cajas por tienda
        $this->cajas = [
            new Caja(1, 1, 'ACTIVA', 1),
            new Caja(2, 2, 'ACTIVA', 1),
            new Caja(3, 1, 'ACTIVA', 2),
            new Caja(4, 2, 'ACTIVA', 2),
            new Caja(5, 1, 'ACTIVA', 3),
            new Caja(6, 2, 'ACTIVA', 3),
        ];
    }

    /** @return array<int, Tienda> id_tienda => Tienda */
    public function todas(): array
    {
        return $this->tiendas;
    }

    public function buscarPorId(int $idTienda): ?Tienda
    {
        return $this->tiendas[$idTienda] ?? null;
    }

    public function nombreDistrito(int $idDistrito): ?string
    {
        return $this->distritos[$idDistrito] ?? null;
    }

    public function buscarCaja(int $idCaja): ?Caja
    {
        foreach ($this->cajas as $caja) {
            if ($caja->getIdCaja() === $idCaja) {
                return $caja;
            }
        }
        return null;
    }

    /** @return Caja[] */
    public function cajasDeTienda(int $idTienda): array
    {
        return array_values(array_filter(
            $this->cajas,
            fn(Caja $caja): bool => $caja->getIdTienda() === $idTienda
        ));
    }
}
