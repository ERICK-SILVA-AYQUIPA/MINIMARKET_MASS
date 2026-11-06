<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Promocion.php';

/**
 * Crear, pausar y reactivar promociones. Solo el administrador (de su tienda, o de toda la cadena)
 * y cada cambio queda en la auditoria.
 */
class PromocionServicio extends Servicio
{
    /**
     * @param array{nombre?: string, tipo?: string, id_producto?: string, alcance?: string, porcentaje?: string,
     *              precio?: string, cantidad_pack?: string, monto_minimo?: string, fecha_inicio?: string, fecha_fin?: string} $datos
     * @return int id_promocion
     */
    public function crear(Usuario $admin, array $datos): int
    {
        $idTienda = ($datos['alcance'] ?? '') === 'CADENA' ? null : $admin->getIdTienda();
        if (!$admin->puedeAdministrarPromociones($idTienda)) {
            throw new ReglaNegocioException('Solo el administrador crea promociones.');
        }
        $nombre = $this->textoObligatorio($datos['nombre'] ?? '', 60, 'El nombre de la promoción');
        $tipo = (string) ($datos['tipo'] ?? '');
        if (!in_array($tipo, Promocion::TIPOS, true)) {
            throw new ReglaNegocioException('Elige el tipo de promoción.');
        }
        $inicio = $this->fecha($datos['fecha_inicio'] ?? '', 'La fecha de inicio');
        $fin = $this->fecha($datos['fecha_fin'] ?? '', 'La fecha de fin');
        if ($fin < $inicio) {
            throw new ReglaNegocioException('La promoción no puede terminar antes de empezar.');
        }
        if ($fin < date('Y-m-d')) {
            throw new ReglaNegocioException('La fecha de fin ya pasó.');
        }

        $idProducto = null;
        $porcentaje = $precio = $pack = $minimo = null;
        $producto = null;
        if ($tipo !== 'VENTA') {
            $idTexto = trim((string) ($datos['id_producto'] ?? ''));
            $consulta = $this->db->prepare(
                'SELECT id_producto, nombre_producto, precio_venta, se_vende_por_peso, estado FROM producto WHERE id_producto = :id'
            );
            $consulta->execute(['id' => ctype_digit($idTexto) ? (int) $idTexto : 0]);
            $producto = $consulta->fetch();
            if ($producto === false) {
                throw new ReglaNegocioException('Elige el producto de la promoción.');
            }
            if ($producto['estado'] !== 'ACTIVO') {
                throw new ReglaNegocioException($producto['nombre_producto'] . ' está descontinuado.');
            }
            $idProducto = (int) $producto['id_producto'];
        }
        $precioLista = $producto !== null ? (int) Dinero::deTexto($producto['precio_venta']) : 0;

        switch ($tipo) {
            case 'PORCENTAJE':
            case 'VENTA':
                $porcentaje = Dinero::deTexto(trim((string) ($datos['porcentaje'] ?? '')));
                if ($porcentaje === null || $porcentaje <= 0 || $porcentaje > Promocion::PORCENTAJE_MAXIMO) {
                    throw new ReglaNegocioException('El porcentaje va de 0.01 a 90.');
                }
                if ($tipo === 'VENTA') {
                    $minimoTexto = trim((string) ($datos['monto_minimo'] ?? ''));
                    $minimo = $minimoTexto === '' ? 0 : Dinero::deTexto($minimoTexto);
                    if ($minimo === null) {
                        throw new ReglaNegocioException('La compra mínima no es un monto válido.');
                    }
                }
                break;
            case 'PRECIO':
                $precio = Dinero::deTexto(trim((string) ($datos['precio'] ?? '')));
                if ($precio === null || $precio <= 0 || $precio >= $precioLista) {
                    throw new ReglaNegocioException(sprintf('El precio de oferta debe ser menor que el precio normal (S/ %s).', Dinero::aTexto($precioLista)));
                }
                break;
            case 'PACK':
                if ($producto['se_vende_por_peso'] === 'S') {
                    throw new ReglaNegocioException('Los packs son para productos por unidad, no al peso.');
                }
                $packTexto = trim((string) ($datos['cantidad_pack'] ?? ''));
                $pack = preg_match('/^[0-9]{1,2}$/', $packTexto) === 1 ? (int) $packTexto : 0;
                if ($pack < 2) {
                    throw new ReglaNegocioException('El pack lleva de 2 a 99 unidades.');
                }
                $precio = Dinero::deTexto(trim((string) ($datos['precio'] ?? '')));
                $normal = $pack * $precioLista;
                if ($precio === null || $precio <= 0 || $precio >= $normal) {
                    throw new ReglaNegocioException(sprintf('El precio del pack debe ser menor que %d x S/ %s = S/ %s.',
                        $pack, Dinero::aTexto($precioLista), Dinero::aTexto($normal)));
                }
                break;
        }

        // Valida la combinacion con las mismas reglas que el CHECK de la tabla
        $promo = new Promocion(0, $nombre, $tipo, $idProducto, $idTienda, $porcentaje, $precio, $pack, $minimo, $inicio, $fin, 'ACTIVA');

        return $this->enTransaccion(function () use ($admin, $promo, $producto): int {
            $texto = fn(?int $c): ?string => $c === null ? null : Dinero::aTexto($c);
            $this->db->prepare(
                'INSERT INTO promocion (nombre, tipo, id_producto, id_tienda, porcentaje, precio, cantidad_pack, monto_minimo,
                                        fecha_inicio, fecha_fin, estado, id_empleado)
                 VALUES (:nombre, :tipo, :producto, :tienda, :porcentaje, :precio, :pack, :minimo, :inicio, :fin, \'ACTIVA\', :empleado)'
            )->execute([
                'nombre' => $promo->getNombre(), 'tipo' => $promo->getTipo(), 'producto' => $promo->getIdProducto(),
                'tienda' => $promo->getIdTienda(), 'porcentaje' => $texto($promo->getPorcentaje()), 'precio' => $texto($promo->getPrecio()),
                'pack' => $promo->getCantidadPack(), 'minimo' => $texto($promo->getMontoMinimo()),
                'inicio' => $promo->getFechaInicio(), 'fin' => $promo->getFechaFin(), 'empleado' => $admin->getIdEmpleado(),
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->auditar($admin, 'PROMOCION_CREADA', sprintf('#%d %s: %s%s, %s a %s, %s', $id, $promo->getNombre(),
                $producto !== null ? $producto['nombre_producto'] . ' ' : '',
                $promo->descripcion($producto !== null && $producto['se_vende_por_peso'] === 'S'),
                $promo->getFechaInicio(), $promo->getFechaFin(), $promo->getIdTienda() === null ? 'toda la cadena' : 'esta tienda'));
            return $id;
        });
    }

    // Pausar (deja de aplicarse al momento) o reactivar una promocion
    public function cambiarEstado(Usuario $admin, int $idPromocion, string $estado): void
    {
        if (!in_array($estado, ['ACTIVA', 'PAUSADA'], true)) {
            throw new ReglaNegocioException('Estado de promoción no válido.');
        }
        $this->enTransaccion(function () use ($admin, $idPromocion, $estado): void {
            $consulta = $this->db->prepare('SELECT id_tienda, nombre, estado FROM promocion WHERE id_promocion = :id FOR UPDATE');
            $consulta->execute(['id' => $idPromocion]);
            $promo = $consulta->fetch();
            if ($promo === false) {
                throw new ReglaNegocioException('La promoción no existe.');
            }
            if (!$admin->puedeAdministrarPromociones($promo['id_tienda'] === null ? null : (int) $promo['id_tienda'])) {
                throw new ReglaNegocioException('Solo el administrador de esa tienda cambia esta promoción.');
            }
            if ($promo['estado'] === $estado) {
                return;
            }
            $this->db->prepare('UPDATE promocion SET estado = :estado WHERE id_promocion = :id')
                ->execute(['estado' => $estado, 'id' => $idPromocion]);
            $this->auditar($admin, $estado === 'PAUSADA' ? 'PROMOCION_PAUSADA' : 'PROMOCION_ACTIVADA',
                sprintf('#%d %s', $idPromocion, $promo['nombre']));
        });
    }

    private function fecha(string $texto, string $campo): string
    {
        $texto = trim($texto);
        if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $texto, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new ReglaNegocioException($campo . ' no es válida.');
        }
        return $texto;
    }
}
