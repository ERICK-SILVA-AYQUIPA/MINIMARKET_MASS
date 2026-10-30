<?php
declare(strict_types=1);

require_once __DIR__ . '/Categoria.php';
require_once __DIR__ . '/Marca.php';
require_once __DIR__ . '/UnidadMedida.php';
require_once __DIR__ . '/TipoMovimiento.php';

/**
 * Tablas de catalogo: categoria, marca, unidad_medida y tipo_movimiento.
 */
class CatalogoRepository
{
    public function __construct(private PDO $db) {}

    public function buscarCategoria(int $idCategoria): ?Categoria
    {
        $consulta = $this->db->prepare('SELECT id_categoria, nombre_categoria FROM categoria WHERE id_categoria = :id');
        $consulta->execute(['id' => $idCategoria]);
        $f = $consulta->fetch();
        return $f === false ? null : new Categoria((int) $f['id_categoria'], $f['nombre_categoria']);
    }

    public function buscarMarca(int $idMarca): ?Marca
    {
        $consulta = $this->db->prepare('SELECT id_marca, nombre_marca, es_marca_propia FROM marca WHERE id_marca = :id');
        $consulta->execute(['id' => $idMarca]);
        $f = $consulta->fetch();
        return $f === false ? null : new Marca((int) $f['id_marca'], $f['nombre_marca'], $f['es_marca_propia']);
    }

    public function buscarUnidad(int $idUnidad): ?UnidadMedida
    {
        $consulta = $this->db->prepare('SELECT id_unidad, abreviatura, nombre_unidad FROM unidad_medida WHERE id_unidad = :id');
        $consulta->execute(['id' => $idUnidad]);
        $f = $consulta->fetch();
        return $f === false ? null : new UnidadMedida((int) $f['id_unidad'], $f['abreviatura'], $f['nombre_unidad']);
    }

    /** @return array<int, Categoria> id_categoria => Categoria, por nombre */
    public function categorias(): array
    {
        $lista = [];
        foreach ($this->db->query('SELECT id_categoria, nombre_categoria FROM categoria ORDER BY nombre_categoria') as $f) {
            $lista[(int) $f['id_categoria']] = new Categoria((int) $f['id_categoria'], $f['nombre_categoria']);
        }
        return $lista;
    }

    /** @return array<int, Marca> id_marca => Marca, por nombre */
    public function marcas(): array
    {
        $lista = [];
        foreach ($this->db->query('SELECT id_marca, nombre_marca, es_marca_propia FROM marca ORDER BY nombre_marca') as $f) {
            $lista[(int) $f['id_marca']] = new Marca((int) $f['id_marca'], $f['nombre_marca'], $f['es_marca_propia']);
        }
        return $lista;
    }

    /** @return array<int, UnidadMedida> id_unidad => UnidadMedida */
    public function unidades(): array
    {
        $lista = [];
        foreach ($this->db->query('SELECT id_unidad, abreviatura, nombre_unidad FROM unidad_medida ORDER BY id_unidad') as $f) {
            $lista[(int) $f['id_unidad']] = new UnidadMedida((int) $f['id_unidad'], $f['abreviatura'], $f['nombre_unidad']);
        }
        return $lista;
    }

    /** @return array<int, TipoMovimiento> id_tipo_movimiento => TipoMovimiento */
    public function tiposMovimiento(): array
    {
        $tipos = [];
        $sql = 'SELECT id_tipo_movimiento, nombre_movimiento, efecto FROM tipo_movimiento ORDER BY id_tipo_movimiento';
        foreach ($this->db->query($sql) as $f) {
            $tipos[(int) $f['id_tipo_movimiento']] = new TipoMovimiento((int) $f['id_tipo_movimiento'], $f['nombre_movimiento'], $f['efecto']);
        }
        return $tipos;
    }
}
