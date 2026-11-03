<?php
declare(strict_types=1);

/**
 * Migraciones de la base: cambios numerados que se agregan sin borrar datos.
 *
 * - La base "inicial" son los 12 scripts de backend/database/ (BASE, en el orden de docker-compose.yml).
 *   Esos ya no se cambian: un cambio nuevo va en backend/database/migraciones/NNN_que_hace.sql.
 * - La tabla migracion_aplicada (backend/database/migracion_aplicada.sql) anota cada migracion
 *   con su huella SHA-256. Si alguien cambia una migracion que ya se aplico, migrar.php avisa y no sigue.
 * - La usan backend/scripts/migrar.php (servidor, Laragon y actualizar.sh/.bat),
 *   backend/scripts/generar_instalador.php (arma instalar_laragon.sql) y las pruebas.
 *   En Docker, una base recien creada aplica las migraciones con docker/mysql/98_migraciones.sh.
 */
final class Migraciones
{
    // Los 12 scripts de la base inicial, en orden de ejecucion
    public const BASE = [
        'schema', 'datos', 'boleta', 'login', 'seguridad', 'ajustes',
        'auditoria', 'ingresos', 'operaciones', 'catalogo', 'api_movil', 'caja_apertura',
    ];

    // NNN_nombre.sql: tres digitos, guion bajo y minusculas, numeros o guiones bajos
    private const PATRON = '/^[0-9]{3}_[a-z0-9_]+\.sql$/';

    private string $carpeta;

    public function __construct(private PDO $db, ?string $carpetaBd = null)
    {
        $this->carpeta = $carpetaBd ?? dirname(__DIR__) . '/database';
    }

    /**
     * Archivos de migraciones/, ordenados por numero.
     * @return array<string, string> nombre => ruta
     */
    public static function archivos(?string $carpetaBd = null): array
    {
        $carpeta = ($carpetaBd ?? dirname(__DIR__) . '/database') . '/migraciones';
        $archivos = [];
        foreach (scandir($carpeta) ?: [] as $nombre) {
            if (preg_match(self::PATRON, $nombre) === 1) {
                $archivos[$nombre] = $carpeta . '/' . $nombre;
            }
        }
        ksort($archivos, SORT_STRING);
        return $archivos;
    }

    // Huella del archivo sin los \r: el mismo archivo da la misma huella en Windows (CRLF) y en Linux (LF)
    public static function huella(string $contenido): string
    {
        return hash('sha256', str_replace("\r", '', $contenido));
    }

    /**
     * Divide un script en sentencias, como el cliente mysql: respeta comentarios, textos entre comillas
     * y las lineas DELIMITER (para procedimientos y triggers).
     * @return string[]
     */
    public static function sentencias(string $sql): array
    {
        $sql = str_replace("\r", '', $sql);
        $sentencias = [];
        $actual = '';
        $delimitador = ';';
        $largo = strlen($sql);
        $i = 0;
        while ($i < $largo) {
            // DELIMITER solo cuenta al inicio de una linea, fuera de una sentencia a medias
            if (trim($actual) === '' && ($i === 0 || $sql[$i - 1] === "\n")
                && preg_match('/\GDELIMITER[ \t]+(\S+)[ \t]*(\n|$)/i', $sql, $m, 0, $i) === 1) {
                $delimitador = $m[1];
                $actual = '';
                $i += strlen($m[0]);
                continue;
            }
            $c = $sql[$i];
            if ($c === '-' && substr($sql, $i, 2) === '--' && ($i + 2 >= $largo || ctype_space($sql[$i + 2]))) {
                $fin = strpos($sql, "\n", $i);
                $i = $fin === false ? $largo : $fin + 1;
                $actual .= "\n";
                continue;
            }
            if ($c === '#') {
                $fin = strpos($sql, "\n", $i);
                $i = $fin === false ? $largo : $fin + 1;
                $actual .= "\n";
                continue;
            }
            if ($c === '/' && substr($sql, $i, 2) === '/*') {
                $fin = strpos($sql, '*/', $i + 2);
                $i = $fin === false ? $largo : $fin + 2;
                $actual .= ' ';
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $j = $i + 1;
                while ($j < $largo) {
                    if ($sql[$j] === '\\' && $c !== '`') {
                        $j += 2;
                        continue;
                    }
                    if ($sql[$j] === $c) {
                        if ($j + 1 < $largo && $sql[$j + 1] === $c) {   // '' dentro del texto
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $j++;
                }
                $actual .= substr($sql, $i, $j - $i + 1);
                $i = $j + 1;
                continue;
            }
            if (substr($sql, $i, strlen($delimitador)) === $delimitador) {
                if (trim($actual) !== '') {
                    $sentencias[] = trim($actual);
                }
                $actual = '';
                $i += strlen($delimitador);
                continue;
            }
            $actual .= $c;
            $i++;
        }
        if (trim($actual) !== '') {
            $sentencias[] = trim($actual);
        }
        return $sentencias;
    }

    // Crea la tabla migracion_aplicada si la base es de antes de las migraciones
    public function prepararTabla(): void
    {
        foreach (self::sentencias((string) file_get_contents($this->carpeta . '/migracion_aplicada.sql')) as $sentencia) {
            $this->db->exec($sentencia);
        }
    }

    /** @return array<string, string> nombre => huella de las ya aplicadas */
    public function aplicadas(): array
    {
        $aplicadas = [];
        foreach ($this->db->query('SELECT nombre, checksum FROM migracion_aplicada ORDER BY nombre') as $fila) {
            $aplicadas[(string) $fila['nombre']] = (string) $fila['checksum'];
        }
        return $aplicadas;
    }

    /**
     * Compara las migraciones del repositorio con las de la base.
     * @return array{pendientes: string[], cambiadas: string[], desconocidas: string[]}
     *   pendientes: faltan aplicar; cambiadas: se aplicaron pero el archivo cambio despues;
     *   desconocidas: estan en la base pero no en el repositorio (base mas nueva que el codigo)
     */
    public function estado(): array
    {
        $aplicadas = $this->aplicadas();
        $estado = ['pendientes' => [], 'cambiadas' => [], 'desconocidas' => []];
        foreach (self::archivos($this->carpeta) as $nombre => $ruta) {
            if (!isset($aplicadas[$nombre])) {
                $estado['pendientes'][] = $nombre;
            } elseif (!hash_equals($aplicadas[$nombre], self::huella((string) file_get_contents($ruta)))) {
                $estado['cambiadas'][] = $nombre;
            }
            unset($aplicadas[$nombre]);
        }
        $estado['desconocidas'] = array_keys($aplicadas);
        return $estado;
    }

    /**
     * Aplica en orden las migraciones que faltan. Se detiene si alguna ya aplicada fue cambiada.
     * MySQL confirma solo cada ALTER/CREATE: si una migracion falla a mitad, queda a medias y se corrige a mano.
     * @param callable(string): void $avisar recibe cada linea de avance
     * @return string[] las que aplico
     */
    public function aplicarPendientes(callable $avisar): array
    {
        $this->prepararTabla();
        $estado = $this->estado();
        if ($estado['cambiadas'] !== []) {
            throw new RuntimeException('Estas migraciones ya se aplicaron y despues alguien cambio el archivo: '
                . implode(', ', $estado['cambiadas']) . '. No cambies una migracion aplicada: deja el archivo como estaba'
                . ' y pon el cambio en una migracion nueva.');
        }
        $archivos = self::archivos($this->carpeta);
        $hechas = [];
        foreach ($estado['pendientes'] as $nombre) {
            $contenido = (string) file_get_contents($archivos[$nombre]);
            $avisar('Aplicando ' . $nombre . '...');
            foreach (self::sentencias($contenido) as $sentencia) {
                $this->db->exec($sentencia);
            }
            $this->db->prepare('INSERT INTO migracion_aplicada (nombre, fecha, checksum) VALUES (:nombre, NOW(), :huella)')
                ->execute(['nombre' => $nombre, 'huella' => self::huella($contenido)]);
            $hechas[] = $nombre;
        }
        return $hechas;
    }

    /**
     * El instalador de Laragon (backend/database/instalar_laragon.sql): los 12 scripts de la base,
     * la tabla migracion_aplicada y cada migracion con su registro. Lo arma generar_instalador.php
     * y las pruebas unitarias revisan que el archivo subido sea igual a este.
     */
    public static function instalador(?string $carpetaBd = null): string
    {
        $carpeta = $carpetaBd ?? dirname(__DIR__) . '/database';
        $leer = static fn(string $ruta): string => rtrim(str_replace("\r", '', (string) file_get_contents($ruta)), "\n");
        $migraciones = self::archivos($carpeta);

        $sql = "/* ========================================================\n"
            . "   BASE DE DATOS COMPLETA PARA LARAGON - MINIMARKET MASS\n"
            . "   Ejecuta este unico archivo en HeidiSQL / phpMyAdmin. BORRA la base\n"
            . "   minimarket_mass y la crea otra vez con los datos de ejemplo.\n"
            . "   Para una base que ya tiene datos: php backend/scripts/migrar.php\n"
            . "\n"
            . "   NO SE EDITA A MANO: lo arma backend/scripts/generar_instalador.php\n"
            . "   con los 12 scripts de backend/database/ (" . implode(', ', self::BASE) . ")\n"
            . "   y las migraciones de backend/database/migraciones/ ("
            . ($migraciones === [] ? 'ninguna' : implode(', ', array_keys($migraciones))) . ").\n"
            . "   ======================================================== */\n\n";
        foreach (self::BASE as $script) {
            $sql .= '-- >>> INICIO DE database/' . $script . ".sql <<<\n" . $leer($carpeta . '/' . $script . '.sql') . "\n\n\n";
        }
        $sql .= "-- >>> INICIO DE database/migracion_aplicada.sql <<<\n" . $leer($carpeta . '/migracion_aplicada.sql') . "\n\n\n";
        foreach ($migraciones as $nombre => $ruta) {
            $contenido = (string) file_get_contents($ruta);
            $sql .= '-- >>> INICIO DE database/migraciones/' . $nombre . " <<<\n" . $leer($ruta) . "\n"
                . "INSERT INTO migracion_aplicada (nombre, fecha, checksum) VALUES ('" . $nombre . "', NOW(), '"
                . self::huella($contenido) . "');\n\n\n";
        }
        return rtrim($sql, "\n") . "\n";
    }
}
