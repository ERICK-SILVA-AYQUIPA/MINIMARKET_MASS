<?php
declare(strict_types=1);

/**
 * Intentos de ingreso (database/seguridad.sql). Sirve para bloquear un rato
 * a quien prueba muchas claves seguidas.
 */
class IntentoLoginRepository
{
    public const MINUTOS = 15;
    public const MAXIMO_POR_DNI = 5;
    public const MAXIMO_POR_IP = 20;

    public function __construct(private PDO $db) {}

    // Verdadero si el DNI o la IP ya pasaron el limite de fallos en los ultimos 15 minutos
    public function estaBloqueado(?string $dni, string $ip): bool
    {
        $desde = 'NOW() - INTERVAL ' . self::MINUTOS . ' MINUTE';
        if ($dni !== null) {
            // Cuentan los fallos despues del ultimo ingreso correcto de ese DNI
            $consulta = $this->db->prepare(
                "SELECT COUNT(*) FROM intento_login
                  WHERE dni = :dni AND exito = 'N' AND fecha_hora >= $desde
                    AND id_intento > COALESCE((SELECT MAX(id_intento) FROM intento_login WHERE dni = :dni2 AND exito = 'S'), 0)"
            );
            $consulta->execute(['dni' => $dni, 'dni2' => $dni]);
            if ((int) $consulta->fetchColumn() >= self::MAXIMO_POR_DNI) {
                return true;
            }
        }
        $consulta = $this->db->prepare("SELECT COUNT(*) FROM intento_login WHERE ip = :ip AND exito = 'N' AND fecha_hora >= $desde");
        $consulta->execute(['ip' => $ip]);
        return (int) $consulta->fetchColumn() >= self::MAXIMO_POR_IP;
    }

    public function registrar(?string $dni, string $ip, bool $exito): void
    {
        $this->db->prepare('INSERT INTO intento_login (dni, ip, fecha_hora, exito) VALUES (:dni, :ip, NOW(), :exito)')
            ->execute(['dni' => $dni, 'ip' => substr($ip, 0, 45), 'exito' => $exito ? 'S' : 'N']);
    }
}
