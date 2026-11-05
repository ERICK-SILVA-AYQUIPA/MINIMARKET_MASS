<?php
declare(strict_types=1);

require_once __DIR__ . '/Promocion.php';

/**
 * Promociones (migracion 015) y lo que ya se desconto con cada una. Las escribe PromocionServicio.
 */
class PromocionRepository
{
    private const COLUMNAS = 'p.id_promocion, p.nombre, p.tipo, p.id_producto, p.id_tienda, p.porcentaje, p.precio,
        p.cantidad_pack, p.monto_minimo, p.fecha_inicio, p.fecha_fin, p.estado, p.id_empleado, p.fecha_registro';

    public function __construct(private PDO $db) {}

    /**
     * Las que rigen ese dia en la tienda: ACTIVA, dentro de sus fechas y de esa tienda o de toda la cadena.
     * @return Promocion[]
     */
    public function vigentes(int $idTienda, ?string $dia = null): array
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM promocion p
              WHERE p.estado = \'ACTIVA\' AND p.fecha_inicio <= :dia1 AND p.fecha_fin >= :dia2
                AND (p.id_tienda IS NULL OR p.id_tienda = :tienda)
              ORDER BY p.id_promocion'
        );
        $dia ??= date('Y-m-d');
        $consulta->execute(['dia1' => $dia, 'dia2' => $dia, 'tienda' => $idTienda]);
        return array_map(fn(array $f): Promocion => $this->crear($f), $consulta->fetchAll());
    }

    /**
     * Las de la tienda y las de toda la cadena, las mas nuevas primero, con lo que ya descontaron.
     * @return array<int, array{promocion: Promocion, producto: ?string, al_peso: bool, usos: int, descontado: int}>
     */
    public function deTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . ', pr.nombre_producto, pr.se_vende_por_peso,
                    COALESCE(uso.usos, 0) AS usos, COALESCE(uso.descontado, 0) AS descontado
               FROM promocion p
               LEFT JOIN producto pr ON pr.id_producto = p.id_producto
               LEFT JOIN (
                    SELECT x.id_promocion, COUNT(DISTINCT x.id_venta) AS usos, SUM(x.monto) AS descontado
                      FROM (SELECT d.id_promocion, d.id_venta, d.descuento AS monto
                              FROM detalle_venta d JOIN venta v ON v.id_venta = d.id_venta AND v.estado = \'EMITIDA\'
                             WHERE d.id_promocion IS NOT NULL
                            UNION ALL
                            SELECT dv.id_promocion, dv.id_venta, dv.monto
                              FROM descuento_venta dv JOIN venta v ON v.id_venta = dv.id_venta AND v.estado = \'EMITIDA\'
                             WHERE dv.id_promocion IS NOT NULL) x
                     GROUP BY x.id_promocion
               ) uso ON uso.id_promocion = p.id_promocion
              WHERE p.id_tienda IS NULL OR p.id_tienda = :tienda
              ORDER BY p.id_promocion DESC'
        );
        $consulta->execute(['tienda' => $idTienda]);
        return array_map(fn(array $f): array => [
            'promocion'  => $this->crear($f),
            'producto'   => $f['nombre_producto'],
            'al_peso'    => $f['se_vende_por_peso'] === 'S',
            'usos'       => (int) $f['usos'],
            'descontado' => (int) Dinero::deTexto((string) $f['descontado']),
        ], $consulta->fetchAll());
    }

    public function buscarPorId(int $idPromocion): ?Promocion
    {
        $consulta = $this->db->prepare('SELECT ' . self::COLUMNAS . ' FROM promocion p WHERE p.id_promocion = :id');
        $consulta->execute(['id' => $idPromocion]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $this->crear($fila);
    }

    /** @return array<int, string> id_promocion => nombre, para mostrar el comprobante */
    public function nombres(array $ids): array
    {
        $nombres = [];
        $consulta = $this->db->prepare('SELECT nombre FROM promocion WHERE id_promocion = :id');
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $consulta->execute(['id' => $id]);
            $nombre = $consulta->fetchColumn();
            if ($nombre !== false) {
                $nombres[$id] = (string) $nombre;
            }
        }
        return $nombres;
    }

    private function crear(array $f): Promocion
    {
        $centimos = fn(?string $v): ?int => $v === null ? null : Dinero::deTexto($v);
        return new Promocion(
            (int) $f['id_promocion'],
            $f['nombre'],
            $f['tipo'],
            $f['id_producto'] === null ? null : (int) $f['id_producto'],
            $f['id_tienda'] === null ? null : (int) $f['id_tienda'],
            $centimos($f['porcentaje']),
            $centimos($f['precio']),
            $f['cantidad_pack'] === null ? null : (int) $f['cantidad_pack'],
            $centimos($f['monto_minimo']),
            $f['fecha_inicio'],
            $f['fecha_fin'],
            $f['estado'],
            (int) $f['id_empleado'],
            $f['fecha_registro']
        );
    }
}
