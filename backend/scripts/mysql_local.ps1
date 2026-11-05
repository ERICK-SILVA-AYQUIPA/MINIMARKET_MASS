# Lo usan respaldo.ps1 y restaurar.ps1 (se carga con ". ", no se corre solo). Necesita Leer-Env y $contenedor.
#
# Elige donde esta MySQL y, sin Docker, prepara el mysqldump/mysql de esta PC.
#   RESPALDO_MODO=docker   el contenedor minimarket_mass_db (Docker Desktop)
#   RESPALDO_MODO=local    MySQL de Laragon o instalado en la PC
#   vacio                  docker si el contenedor existe; si no, local
#
# Usuario de MySQL en modo local: RESPALDO_DB_USER / RESPALDO_DB_PASSWORD (si no se ponen, DB_USER y DB_PASSWORD;
# en Laragon el .env ya trae root sin clave) y RESPALDO_DB_HOST / RESPALDO_DB_PORT (si no, DB_HOST y DB_PORT).
# Los programas se buscan en el PATH, en RESPALDO_MYSQL_BIN (una carpeta) o en C:\laragon\bin\mysql\*\bin.
# La clave va en un archivo temporal, nunca en la linea de comandos.

function Modo-Mysql {
    $modo = Leer-Env 'RESPALDO_MODO'
    if ($modo -eq 'docker' -or $modo -eq 'local') { return $modo }
    if ($modo) { throw "RESPALDO_MODO debe ser docker, local o quedar vacio (es: $modo)." }
    if (Get-Command docker -ErrorAction SilentlyContinue) {
        # Solo en esta funcion: en Windows PowerShell 5.1, un programa que escribe en stderr con 'Stop' corta el script
        $ErrorActionPreference = 'Continue'
        docker inspect $contenedor *> $null
        if ($LASTEXITCODE -eq 0) { return 'docker' }
    }
    return 'local'
}

function Buscar-Programa([string]$nombre) {
    $carpeta = Leer-Env 'RESPALDO_MYSQL_BIN'
    if ($carpeta -and (Test-Path (Join-Path $carpeta "$nombre.exe"))) { return (Join-Path $carpeta "$nombre.exe") }
    $enPath = Get-Command $nombre -ErrorAction SilentlyContinue
    if ($enPath) { return $enPath.Source }
    $laragon = Get-ChildItem 'C:\laragon\bin\mysql' -Directory -ErrorAction SilentlyContinue |
        Sort-Object Name -Descending | ForEach-Object { Join-Path $_.FullName "bin\$nombre.exe" } |
        Where-Object { Test-Path $_ } | Select-Object -First 1
    if ($laragon) { return $laragon }
    throw "No hay contenedor de Docker ni $nombre.exe. Inicia MySQL en Laragon o pon en .env RESPALDO_MYSQL_BIN con la carpeta bin de MySQL."
}

# Escribe el archivo de opciones de MySQL y devuelve su ruta. En las comillas, \ y " van escapados.
function Preparar-MysqlLocal([string]$carpeta) {
    $usuario = Leer-Env 'RESPALDO_DB_USER'
    if ($usuario) { $clave = Leer-Env 'RESPALDO_DB_PASSWORD' } else { $usuario = Leer-Env 'DB_USER'; $clave = Leer-Env 'DB_PASSWORD' }
    if (-not $usuario) { $usuario = 'root' }
    $servidor = Leer-Env 'RESPALDO_DB_HOST'
    if (-not $servidor) { $servidor = Leer-Env 'DB_HOST' }
    $puerto = Leer-Env 'RESPALDO_DB_PORT'
    if (-not $puerto) { $puerto = Leer-Env 'DB_PORT' }
    $esc = { param($v) '"' + ($v -replace '\\', '\\' -replace '"', '\"') + '"' }
    $lineas = @('[client]', "user=$(& $esc $usuario)")
    if ($clave) { $lineas += "password=$(& $esc $clave)" }
    if ($servidor) { $lineas += "host=$servidor" }
    if ($puerto) { $lineas += "port=$puerto" }
    $lineas += 'default-character-set=utf8mb4'
    $cnf = Join-Path $carpeta 'mysql.cnf'
    # Sin BOM: MySQL no lee bien un archivo de opciones que empieza con BOM
    [IO.File]::WriteAllLines($cnf, $lineas, (New-Object Text.UTF8Encoding $false))
    $script:usuarioLocal = $usuario
    return $cnf
}

# Verdadero si se puede entrar a MySQL con ese archivo de opciones
function Probar-MysqlLocal([string]$mysql, [string]$cnf) {
    $ErrorActionPreference = 'Continue'
    & $mysql "--defaults-extra-file=$cnf" -e 'SELECT 1' *> $null
    return ($LASTEXITCODE -eq 0)
}
