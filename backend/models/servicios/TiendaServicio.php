<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';
require_once __DIR__ . '/../Clave.php';

/**
 * Mantenimiento de tiendas, cajas, series y distritos.
 * Abrir una tienda deja todo listo para vender: caja 1, una serie de boleta y una de factura,
 * stock en 0 de cada producto y su primer administrador.
 */
class TiendaServicio extends Servicio
{
    private const ID_ROL_ADMINISTRADOR = 1;

    /**
     * Abre una tienda nueva de la cadena. Devuelve el id de la tienda.
     * @param array{nombre?: string, direccion?: string, telefono?: string, distrito?: string} $datos
     * @param array{nombres?: string, apellidos?: string, dni?: string, clave?: string} $administrador su primer administrador
     */
    public function abrir(Usuario $usuario, array $datos, array $administrador): int
    {
        if (!$usuario->puedeAbrirTiendas()) {
            throw new ReglaNegocioException('Solo un administrador abre tiendas nuevas.');
        }
        $campos = $this->leerTienda($datos);
        $nombres = $this->textoObligatorio($administrador['nombres'] ?? '', 60, 'Nombres del administrador');
        $apellidos = $this->textoObligatorio($administrador['apellidos'] ?? '', 60, 'Apellidos del administrador');
        $dni = trim((string) ($administrador['dni'] ?? ''));
        if (preg_match('/^[0-9]{8}$/', $dni) !== 1) {
            throw new ReglaNegocioException('El DNI del administrador debe tener 8 dígitos.');
        }
        $clave = (string) ($administrador['clave'] ?? '');
        $problema = Clave::problema($clave);
        if ($problema !== null) {
            throw new ReglaNegocioException($problema);
        }

        return $this->enTransaccion(function () use ($usuario, $campos, $nombres, $apellidos, $dni, $clave): int {
            try {
                $this->db->prepare(
                    'INSERT INTO tienda (nombre_tienda, direccion, telefono, estado, id_distrito)
                     VALUES (:nombre, :direccion, :telefono, \'ACTIVA\', :distrito)'
                )->execute($campos);
            } catch (PDOException $ex) {
                throw $this->esDuplicado($ex) ? new ReglaNegocioException('Ya hay una tienda con ese nombre.') : $ex;
            }
            $idTienda = (int) $this->db->lastInsertId();

            $this->db->prepare('INSERT INTO caja (numero_caja, estado, id_tienda) VALUES (1, \'ACTIVA\', :tienda)')
                ->execute(['tienda' => $idTienda]);

            // Una serie de boleta y una de factura, con el siguiente numero libre de la cadena (B004, F004...)
            $tipos = $this->db->query('SELECT id_tipo_comprobante, letra_serie FROM tipo_comprobante ORDER BY id_tipo_comprobante');
            foreach ($tipos->fetchAll() as $tipo) {
                $this->db->prepare(
                    'INSERT INTO serie_comprobante (serie, ultimo_numero, id_tienda, id_tipo_comprobante)
                     VALUES (:serie, 0, :tienda, :tipo)'
                )->execute([
                    'serie'  => $this->siguienteSerie($tipo['letra_serie']),
                    'tienda' => $idTienda,
                    'tipo'   => (int) $tipo['id_tipo_comprobante'],
                ]);
            }

            // El catalogo es de toda la cadena: cada producto aparece en la tienda con stock 0
            $this->db->prepare(
                'INSERT INTO stock (id_tienda, id_producto, cantidad_actual, stock_minimo)
                 SELECT :tienda, id_producto, 0, 0 FROM producto'
            )->execute(['tienda' => $idTienda]);

            try {
                $this->db->prepare(
                    'INSERT INTO empleado (nombres, apellidos, dni, clave_hash, estado, id_tienda, id_rol)
                     VALUES (:nombres, :apellidos, :dni, :hash, \'ACTIVO\', :tienda, :rol)'
                )->execute([
                    'nombres' => $nombres, 'apellidos' => $apellidos, 'dni' => $dni, 'hash' => Clave::hash($clave),
                    'tienda' => $idTienda, 'rol' => self::ID_ROL_ADMINISTRADOR,
                ]);
            } catch (PDOException $ex) {
                throw $this->esDuplicado($ex) ? new ReglaNegocioException('Ya hay un empleado con el DNI del administrador.') : $ex;
            }
            $this->auditar($usuario, 'TIENDA_ABIERTA', sprintf('%d %s, administrador %s %s', $idTienda, $campos['nombre'], $nombres, $apellidos));
            return $idTienda;
        });
    }

    // El administrador cambia los datos y el estado de SU tienda
    public function editar(Usuario $admin, int $idTienda, array $datos, string $estado): void
    {
        if (!$admin->puedeAdministrarTienda($idTienda)) {
            throw new ReglaNegocioException('Solo el administrador de la tienda cambia sus datos.');
        }
        if (!in_array($estado, ['ACTIVA', 'INACTIVA'], true)) {
            throw new ReglaNegocioException('Estado no válido.');
        }
        $campos = $this->leerTienda($datos);

        $this->enTransaccion(function () use ($admin, $idTienda, $campos, $estado): void {
            $this->db->prepare('SELECT id_tienda FROM tienda WHERE id_tienda = :id FOR UPDATE')->execute(['id' => $idTienda]);
            if ($estado === 'INACTIVA' && $this->turnosAbiertos('c.id_tienda = :id', $idTienda) > 0) {
                throw new ReglaNegocioException('La tienda tiene turnos de caja abiertos: ciérrenlos antes de desactivarla.');
            }
            try {
                $this->db->prepare(
                    'UPDATE tienda SET nombre_tienda = :nombre, direccion = :direccion, telefono = :telefono,
                                      id_distrito = :distrito, estado = :estado
                      WHERE id_tienda = :id'
                )->execute($campos + ['estado' => $estado, 'id' => $idTienda]);
            } catch (PDOException $ex) {
                throw $this->esDuplicado($ex) ? new ReglaNegocioException('Ya hay una tienda con ese nombre.') : $ex;
            }
            $this->auditar($admin, 'TIENDA_EDITADA', sprintf('%s, %s', $campos['nombre'], $estado));
        });
    }

    // Una caja mas en la tienda del administrador, con el siguiente numero. Devuelve el numero.
    public function agregarCaja(Usuario $admin): int
    {
        $idTienda = $admin->getIdTienda();
        if (!$admin->puedeAdministrarTienda($idTienda)) {
            throw new ReglaNegocioException('Solo el administrador de la tienda agrega cajas.');
        }
        return $this->enTransaccion(function () use ($admin, $idTienda): int {
            // Bloquea la tienda: dos administradores a la vez no sacan el mismo numero
            $this->db->prepare('SELECT id_tienda FROM tienda WHERE id_tienda = :id FOR UPDATE')->execute(['id' => $idTienda]);
            $consulta = $this->db->prepare('SELECT COALESCE(MAX(numero_caja), 0) + 1 FROM caja WHERE id_tienda = :tienda');
            $consulta->execute(['tienda' => $idTienda]);
            $numero = (int) $consulta->fetchColumn();
            if ($numero > 255) {
                throw new ReglaNegocioException('La tienda llegó al máximo de cajas.');
            }
            $this->db->prepare('INSERT INTO caja (numero_caja, estado, id_tienda) VALUES (:numero, \'ACTIVA\', :tienda)')
                ->execute(['numero' => $numero, 'tienda' => $idTienda]);
            $this->auditar($admin, 'CAJA_AGREGADA', 'Caja ' . $numero);
            return $numero;
        });
    }

    // Activa o desactiva una caja de SU tienda. Una caja con turno abierto no se desactiva.
    public function cambiarEstadoCaja(Usuario $admin, int $idCaja, string $estado): void
    {
        if (!in_array($estado, ['ACTIVA', 'INACTIVA'], true)) {
            throw new ReglaNegocioException('Estado no válido.');
        }
        $this->enTransaccion(function () use ($admin, $idCaja, $estado): void {
            $consulta = $this->db->prepare('SELECT id_tienda, numero_caja FROM caja WHERE id_caja = :id FOR UPDATE');
            $consulta->execute(['id' => $idCaja]);
            $caja = $consulta->fetch();
            if ($caja === false || !$admin->puedeAdministrarTienda((int) $caja['id_tienda'])) {
                throw new ReglaNegocioException('Esa caja no es de tu tienda.');
            }
            if ($estado === 'INACTIVA' && $this->turnosAbiertos('ct.id_caja = :id', $idCaja) > 0) {
                throw new ReglaNegocioException('La caja tiene un turno abierto: que el cajero lo cierre primero.');
            }
            $this->db->prepare('UPDATE caja SET estado = :estado WHERE id_caja = :id')->execute(['estado' => $estado, 'id' => $idCaja]);
            $this->auditar($admin, 'CAJA_ESTADO', sprintf('Caja %d: %s', $caja['numero_caja'], $estado));
        });
    }

    public function crearDistrito(Usuario $usuario, string $nombre): int
    {
        if (!$usuario->puedeAbrirTiendas()) {
            throw new ReglaNegocioException('Solo un administrador agrega distritos.');
        }
        $nombre = $this->textoObligatorio($nombre, 40, 'Nombre del distrito');
        return $this->enTransaccion(function () use ($usuario, $nombre): int {
            try {
                $this->db->prepare('INSERT INTO distrito (nombre_distrito) VALUES (:nombre)')->execute(['nombre' => $nombre]);
            } catch (PDOException $ex) {
                throw $this->esDuplicado($ex) ? new ReglaNegocioException('Ese distrito ya existe.') : $ex;
            }
            $id = (int) $this->db->lastInsertId();
            $this->auditar($usuario, 'DISTRITO_CREADO', $id . ' ' . $nombre);
            return $id;
        });
    }

    /**
     * Valida los datos de la tienda del formulario.
     * @return array{nombre: string, direccion: string, telefono: ?string, distrito: int}
     */
    private function leerTienda(array $datos): array
    {
        $nombre = $this->textoObligatorio($datos['nombre'] ?? '', 60, 'Nombre de la tienda');
        $direccion = $this->textoObligatorio($datos['direccion'] ?? '', 120, 'Dirección');
        $telefono = $this->textoOpcional($datos['telefono'] ?? '', 15, 'Teléfono');
        if ($telefono !== null && preg_match('/^[0-9+ -]{6,15}$/', $telefono) !== 1) {
            throw new ReglaNegocioException('El teléfono solo lleva dígitos, espacios, + o - (6 a 15).');
        }
        $distrito = trim((string) ($datos['distrito'] ?? ''));
        $consulta = $this->db->prepare('SELECT 1 FROM distrito WHERE id_distrito = :id');
        $consulta->execute(['id' => ctype_digit($distrito) ? (int) $distrito : 0]);
        if ($consulta->fetchColumn() === false) {
            throw new ReglaNegocioException('Elige un distrito.');
        }
        return ['nombre' => $nombre, 'direccion' => $direccion, 'telefono' => $telefono, 'distrito' => (int) $distrito];
    }

    // B004 si la mayor serie B de la cadena es B003. Se busca en toda la cadena para no repetir series.
    private function siguienteSerie(string $letra): string
    {
        $consulta = $this->db->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(serie, 2) AS UNSIGNED)), 0) + 1 FROM serie_comprobante WHERE serie LIKE :letra FOR UPDATE'
        );
        $consulta->execute(['letra' => $letra . '%']);
        $numero = (int) $consulta->fetchColumn();
        if ($numero > 999) {
            throw new ReglaNegocioException('No quedan series libres para ' . $letra . '.');
        }
        return $letra . str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
    }

    // $condicion es fija en el codigo; solo el id viene de afuera
    private function turnosAbiertos(string $condicion, int $id): int
    {
        $consulta = $this->db->prepare(
            "SELECT COUNT(*) FROM caja_turno ct JOIN caja c ON c.id_caja = ct.id_caja
              WHERE ct.estado = 'ABIERTO' AND $condicion"
        );
        $consulta->execute(['id' => $id]);
        return (int) $consulta->fetchColumn();
    }
}
