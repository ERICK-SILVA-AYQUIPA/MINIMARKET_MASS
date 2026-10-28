<?php
declare(strict_types=1);

require_once __DIR__ . '/ReglaNegocioException.php';

/**
 * La foto de un producto: public/img/productos/{id}.jpg. No va en la base: si el archivo no esta,
 * la pantalla dibuja la ilustracion de su categoria (icono y color), asi todo producto se ve igual de ordenado.
 * La foto subida nunca se guarda tal cual: se vuelve a dibujar con GD como JPEG de hasta 600 px,
 * lo que quita cualquier contenido escondido en el archivo original.
 */
final class FotoProducto
{
    public const MAXIMO_BYTES = 2_097_152;   // 2 MB
    public const LADO = 600;                 // px del lado mas largo

    // Palabra de la categoria => [icono de public/iconos.svg, tono de color 1..8]
    private const CATEGORIAS = [
        'lacte'    => ['lacteo', 1],
        'abarrot'  => ['abarrote', 2],
        'bebid'    => ['bebida', 3],
        'fruta'    => ['fruta', 4],
        'verdur'   => ['fruta', 4],
        'limpie'   => ['limpieza', 5],
        'desayun'  => ['desayuno', 6],
        'snack'    => ['snack', 7],
        'golosin'  => ['snack', 7],
        'cuidado'  => ['cuidado', 8],
        'embutid'  => ['embutido', 6],
        'huevo'    => ['embutido', 6],
        'panad'    => ['desayuno', 2],
    ];

    public function __construct(private string $carpeta = __DIR__ . '/../public/img/productos')
    {
    }

    // "/img/productos/8.jpg?v=..." o null si el producto no tiene foto
    public function url(int $idProducto): ?string
    {
        $ruta = $this->ruta($idProducto);
        return is_file($ruta) ? '/img/productos/' . $idProducto . '.jpg?v=' . filemtime($ruta) : null;
    }

    /**
     * Lo que necesita la vista para dibujar el producto: su foto o, si no tiene, el icono y el color de su categoria.
     * @return array{url: ?string, icono: string, tono: int, categoria: string}
     */
    public function datos(int $idProducto, string $categoria): array
    {
        return ['url' => $this->url($idProducto), 'icono' => self::icono($categoria), 'tono' => self::tono($categoria), 'categoria' => $categoria];
    }

    /**
     * Guarda la foto (JPEG, PNG, WebP o GIF) ya achicada. Lanza ReglaNegocioException si no es una imagen valida.
     */
    public function guardar(int $idProducto, string $contenido): void
    {
        if ($contenido === '' || strlen($contenido) > self::MAXIMO_BYTES) {
            throw new ReglaNegocioException('La foto debe pesar hasta 2 MB.');
        }
        $info = @getimagesizefromstring($contenido);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)
            || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40_000_000) {
            throw new ReglaNegocioException('Sube una foto JPG, PNG o WebP.');
        }
        $original = @imagecreatefromstring($contenido);
        if ($original === false) {
            throw new ReglaNegocioException('No se pudo leer la foto. Prueba con otra.');
        }

        $escala = min(1, self::LADO / max($info[0], $info[1]));
        $ancho = max(1, (int) round($info[0] * $escala));
        $alto = max(1, (int) round($info[1] * $escala));
        $lienzo = imagecreatetruecolor($ancho, $alto);
        // Fondo blanco: las partes transparentes de un PNG no salen negras en el JPEG
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopyresampled($lienzo, $original, 0, 0, 0, 0, $ancho, $alto, $info[0], $info[1]);
        imagedestroy($original);

        if (!is_dir($this->carpeta) && !mkdir($this->carpeta, 0755, true) && !is_dir($this->carpeta)) {
            throw new RuntimeException('No se pudo crear la carpeta de fotos.');
        }
        // Se escribe en un temporal y se cambia de nombre: nadie ve una foto a medias
        $temporal = $this->ruta($idProducto) . '.tmp';
        $ok = imagejpeg($lienzo, $temporal, 85);
        imagedestroy($lienzo);
        if (!$ok || !rename($temporal, $this->ruta($idProducto))) {
            @unlink($temporal);
            throw new RuntimeException('No se pudo guardar la foto.');
        }
    }

    public function borrar(int $idProducto): bool
    {
        $ruta = $this->ruta($idProducto);
        return is_file($ruta) && unlink($ruta);
    }

    // Icono de public/iconos.svg que dibuja la categoria (sin foto)
    public static function icono(string $categoria): string
    {
        return self::buscar($categoria)[0] ?? 'catalogo';
    }

    // Tono de color 1..8 de la categoria: las categorias nuevas reciben uno fijo segun su nombre
    public static function tono(string $categoria): int
    {
        return self::buscar($categoria)[1] ?? (crc32(mb_strtolower($categoria)) % 8) + 1;
    }

    /** @return array{0: string, 1: int}|array{} */
    private static function buscar(string $categoria): array
    {
        $nombre = mb_strtolower($categoria);
        foreach (self::CATEGORIAS as $palabra => $dibujo) {
            if (str_contains($nombre, $palabra)) {
                return $dibujo;
            }
        }
        return [];
    }

    private function ruta(int $idProducto): string
    {
        return $this->carpeta . '/' . $idProducto . '.jpg';
    }
}
