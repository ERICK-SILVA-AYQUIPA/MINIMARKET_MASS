<?php
declare(strict_types=1);

/**
 * Sesiones de la app Android (database/api_movil.sql).
 * El celular guarda el token; la base guarda solo su SHA-256.
 */
class TokenApiRepository
{
    // Un token dura un turno de trabajo; despues la app pide el login otra vez
    public const HORAS_VIGENCIA = 12;
    // ultimo_uso se anota cada tantos minutos, no en cada pedido: asi una consulta de la app no escribe en la base
    public const MINUTOS_ULTIMO_USO = 5;

    public function __construct(private PDO $db) {}

    /**
     * Crea un token para el empleado y lo devuelve (es la unica vez que se ve completo).
     * @return array{token: string, expira: string}
     */
    public function crear(int $idEmpleado, ?string $dispositivo): array
    {
        $token = bin2hex(random_bytes(32));
        $this->db->prepare(
            'INSERT INTO api_token (token_hash, id_empleado, dispositivo, fecha_creacion, ultimo_uso, fecha_expira)
             VALUES (:hash, :empleado, :dispositivo, NOW(), NOW(), NOW() + INTERVAL ' . (int) self::HORAS_VIGENCIA . ' HOUR)'
        )->execute([
            'hash'        => hash('sha256', $token),
            'empleado'    => $idEmpleado,
            'dispositivo' => $dispositivo,
        ]);
        $expira = $this->db->prepare('SELECT fecha_expira FROM api_token WHERE id_token = :id');
        $expira->execute(['id' => (int) $this->db->lastInsertId()]);
        return ['token' => $token, 'expira' => (string) $expira->fetchColumn()];
    }

    // Id del empleado dueno de un token vigente (no vencido ni revocado), o null
    public function empleadoDe(string $token): ?int
    {
        if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) {
            return null;
        }
        $consulta = $this->db->prepare(
            "SELECT id_token, id_empleado,
                    ultimo_uso < NOW() - INTERVAL " . (int) self::MINUTOS_ULTIMO_USO . " MINUTE AS toca_anotar
               FROM api_token
              WHERE token_hash = :hash AND revocado = 'N' AND fecha_expira > NOW()"
        );
        $consulta->execute(['hash' => hash('sha256', $token)]);
        $fila = $consulta->fetch();
        if ($fila === false) {
            return null;
        }
        if ((int) $fila['toca_anotar'] === 1) {
            $this->db->prepare('UPDATE api_token SET ultimo_uso = NOW() WHERE id_token = :id')
                ->execute(['id' => (int) $fila['id_token']]);
        }
        return (int) $fila['id_empleado'];
    }

    public function revocar(string $token): void
    {
        $this->db->prepare("UPDATE api_token SET revocado = 'S' WHERE token_hash = :hash")
            ->execute(['hash' => hash('sha256', $token)]);
    }
}
