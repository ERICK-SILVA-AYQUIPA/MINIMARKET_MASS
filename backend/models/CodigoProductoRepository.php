<?php
declare(strict_types=1);

require_once __DIR__ . '/CodigoBarras.php';

/**
 * Que producto tiene un codigo de barras: el suyo (producto.codigo_barras) o uno de sus otros codigos
 * (producto_codigo, migracion 023), en cualquiera de sus variantes (ceros adelante, UPC-E: ver CodigoBarras).
 * Lo usan el escaner de la app, la ficha web por codigo y las reglas que impiden repetir un codigo.
 */
class CodigoProductoRepository
{
    public function __construct(private PDO $db) {}

    /**
     * El producto de ese codigo, o null. Si varias variantes coinciden gana la mas parecida a lo leido
     * (la exacta primero) y, a la par, el codigo propio del producto antes que un codigo vinculado.
     * @param int|null $excepto no contar este producto (al editarlo, su propio codigo no lo "ocupa")
     * @param bool $bloquear dentro de una transaccion que va a guardar un codigo: FOR UPDATE bloquea esas
     *                       variantes en las dos tablas (aunque no existan aun) hasta el commit, asi dos celulares
     *                       no guardan el mismo codigo a la vez en producto y en producto_codigo
     */
    public function idPorCodigo(string $codigo, ?int $excepto = null, bool $bloquear = false): ?int
    {
        $variantes = CodigoBarras::variantes($codigo);
        if ($variantes === []) {
            return null;
        }
        // Solo signos ? (uno por variante): los codigos van como parametros
        $marcas = implode(', ', array_fill(0, count($variantes), '?'));
        $bloqueo = $bloquear ? ' FOR UPDATE' : '';
        $sql = 'SELECT id_producto, codigo_barras AS codigo FROM producto WHERE codigo_barras IN (' . $marcas . ')' . $bloqueo;
        $propios = $this->db->prepare($sql);
        $propios->execute($variantes);
        $sql = 'SELECT id_producto, codigo FROM producto_codigo WHERE codigo IN (' . $marcas . ')' . $bloqueo;
        $otros = $this->db->prepare($sql);
        $otros->execute($variantes);

        $orden = array_flip($variantes);
        $mejor = null;
        $mejorPuesto = PHP_INT_MAX;
        // Puesto: 2 * (posicion de la variante) + 0 si es el codigo propio, + 1 si es un codigo vinculado
        foreach ([0 => $propios->fetchAll(), 1 => $otros->fetchAll()] as $extra => $filas) {
            foreach ($filas as $f) {
                $id = (int) $f['id_producto'];
                $puesto = 2 * ($orden[(string) $f['codigo']] ?? count($variantes)) + $extra;
                if ($id !== $excepto && $puesto < $mejorPuesto) {
                    $mejor = $id;
                    $mejorPuesto = $puesto;
                }
            }
        }
        return $mejor;
    }

    /**
     * Los otros codigos de un producto, el mas reciente primero, con quien y desde que tienda los vinculo.
     * @return array<int, array{id: int, codigo: string, fecha: string, empleado: string, tienda: string}>
     */
    public function codigosDe(int $idProducto): array
    {
        $consulta = $this->db->prepare(
            "SELECT pc.id_producto_codigo, pc.codigo, pc.fecha_registro, CONCAT(e.nombres, ' ', e.apellidos) AS empleado, t.nombre_tienda
               FROM producto_codigo pc
               JOIN empleado e ON e.id_empleado = pc.id_empleado_registro
               JOIN tienda t   ON t.id_tienda = pc.id_tienda_registro
              WHERE pc.id_producto = :producto
              ORDER BY pc.fecha_registro DESC, pc.id_producto_codigo DESC"
        );
        $consulta->execute(['producto' => $idProducto]);
        return array_map(static fn(array $f): array => [
            'id'       => (int) $f['id_producto_codigo'],
            'codigo'   => (string) $f['codigo'],
            'fecha'    => (string) $f['fecha_registro'],
            'empleado' => (string) $f['empleado'],
            'tienda'   => (string) $f['nombre_tienda'],
        ], $consulta->fetchAll());
    }

    /**
     * Los otros codigos de todos los productos (para el lector de la venta web).
     * @return array<int, string[]> id_producto => codigos
     */
    public function todos(): array
    {
        $codigos = [];
        foreach ($this->db->query('SELECT id_producto, codigo FROM producto_codigo ORDER BY id_producto_codigo')->fetchAll() as $f) {
            $codigos[(int) $f['id_producto']][] = (string) $f['codigo'];
        }
        return $codigos;
    }
}
