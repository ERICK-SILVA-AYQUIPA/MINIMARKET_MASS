<?php
declare(strict_types=1);

/**
 * Datos reales de un codigo de barras que aun no esta en el catalogo, desde las bases publicas y gratuitas
 * Open Food Facts (alimentos y bebidas; ~2 900 productos del Peru) y Open Products Facts (limpieza, cuidado personal).
 * Llena el formulario de Nuevo producto (nombre, marca y categoria); el administrador lo revisa y pone el precio.
 * Lo mismo hace la app al escanear un codigo nuevo (Sugerencia.kt y Categorias.kt): las palabras son las mismas.
 * Sin internet o si nadie lo conoce, devuelve null y se escribe a mano: nunca frena el registro.
 */
final class SugerenciaPublica
{
    private const BASES = ['world.openfoodfacts.org', 'world.openproductsfacts.org'];

    // Categoria del catalogo (sin tildes, en minusculas) => palabras que la delatan (castellano e ingles de Open Food Facts)
    private const PISTAS = [
        'lacteos' => ['leche', 'yogur', 'yogurt', 'queso', 'mantequilla', 'manjar', 'dairies', 'dairy', 'milks', 'cheeses', 'yogurts', 'butters'],
        'bebidas' => ['gaseosa', 'agua', 'jugo', 'nectar', 'refresco', 'cerveza', 'bebida', 'rehidratante', 'energizante',
            'beverages', 'sodas', 'waters', 'juices', 'nectars', 'beers', 'soft drinks', 'energy drinks'],
        'desayuno' => ['cafe', 'avena', 'cereal', 'mermelada', 'infusion', 'manzanilla', 'anis', 'cocoa', 'chocolate en polvo',
            'coffees', 'breakfast', 'teas', 'herbal teas', 'oat', 'jams', 'cocoa and its products'],
        'snacks y golosinas' => ['galleta', 'chocolate', 'caramelo', 'chupetin', 'papas fritas', 'papitas', 'chifle', 'snack',
            'chicle', 'gomitas', 'turron', 'biscuits', 'cookies', 'candies', 'confectioneries', 'chips', 'snacks', 'chocolates', 'crackers'],
        'cuidado personal' => ['jabon', 'shampoo', 'champu', 'acondicionador', 'pasta dental', 'crema dental', 'cepillo dental',
            'desodorante', 'papel higienico', 'toalla higienica', 'panal', 'afeitar', 'colonia',
            'soaps', 'shampoos', 'toothpastes', 'deodorants', 'toilet paper', 'hygiene', 'body care'],
        'embutidos y huevos' => ['jamonada', 'jamon', 'hot dog', 'salchicha', 'chorizo', 'mortadela', 'huevo', 'tocino',
            'sausages', 'hams', 'eggs', 'cold cuts', 'prepared meats'],
        'limpieza' => ['detergente', 'lejia', 'lavavajilla', 'limpiador', 'desinfectante', 'suavizante', 'quitamanchas',
            'esponja', 'escoba', 'ambientador', 'insecticida', 'cleaning', 'detergents', 'dishwashing', 'bleach', 'household'],
        'frutas y verduras' => ['fruta', 'verdura', 'papa', 'platano', 'cebolla', 'tomate', 'limon', 'manzana',
            'fresh fruits', 'fresh vegetables', 'fruits', 'vegetables'],
        'abarrotes' => ['arroz', 'azucar', 'aceite', 'fideo', 'tallarin', 'spaghetti', 'harina', 'sal', 'atun', 'conserva',
            'menestra', 'lenteja', 'frejol', 'pallar', 'garbanzo', 'salsa', 'mayonesa', 'ketchup', 'vinagre', 'sillao', 'sazonador',
            'condimento', 'rices', 'pastas', 'vegetable oils', 'sugars', 'flours', 'canned', 'legumes', 'sauces', 'condiments', 'salts'],
    ];

    /** @var callable(string): ?string */
    private $descargar;

    /** @param ?callable(string): ?string $descargar la URL -> el cuerpo (o null); en las pruebas, una respuesta fija */
    public function __construct(?callable $descargar = null)
    {
        $this->descargar = $descargar ?? self::descargarDeInternet(...);
    }

    /**
     * @return ?array{nombre: string, marca: string, categorias: string[]}
     */
    public function buscar(string $codigo): ?array
    {
        if (preg_match('/^[0-9]{8,14}$/', $codigo) !== 1) {
            return null;
        }
        foreach (self::BASES as $base) {
            $cuerpo = ($this->descargar)('https://' . $base . '/api/v2/product/' . $codigo
                . '.json?fields=product_name,product_name_es,brands,quantity,categories_tags');
            $sugerencia = $cuerpo !== null ? self::desdeRespuesta($cuerpo) : null;
            if ($sugerencia !== null) {
                return $sugerencia;
            }
        }
        return null;
    }

    /**
     * Lee la respuesta de Open Food Facts: nombre (en castellano si hay) + marca + tamano, como en el empaque.
     * @return ?array{nombre: string, marca: string, categorias: string[]}
     */
    public static function desdeRespuesta(string $cuerpo): ?array
    {
        $raiz = json_decode($cuerpo, true);
        if (!is_array($raiz) || (string) ($raiz['status'] ?? '') !== '1' || !is_array($raiz['product'] ?? null)) {
            return null;
        }
        $p = $raiz['product'];
        $texto = static fn(string $clave): string => is_string($p[$clave] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', $p[$clave])) : '';
        $nombre = $texto('product_name_es') !== '' ? $texto('product_name_es') : $texto('product_name');
        if ($nombre === '') {
            return null;
        }
        // "Gloria, Leche Gloria" -> "Gloria"
        $marca = trim(explode(',', $texto('brands'))[0]);
        $cantidad = $texto('quantity');
        if ($marca !== '' && mb_stripos($nombre, $marca) === false) {
            $nombre .= ' ' . $marca;
        }
        if ($cantidad !== '' && mb_stripos($nombre, $cantidad) === false) {
            $nombre .= ' ' . $cantidad;
        }
        // Algunos nombres vienen en minusculas ("agua san Luis")
        $nombre = mb_strtoupper(mb_substr($nombre, 0, 1)) . mb_substr($nombre, 1);
        $categorias = [];
        foreach (is_array($p['categories_tags'] ?? null) ? $p['categories_tags'] : [] as $etiqueta) {
            if (is_string($etiqueta)) {
                // "en:carbonated-drinks" -> "carbonated drinks"
                $categorias[] = str_replace('-', ' ', (string) preg_replace('/^[a-z]{2}:/', '', $etiqueta));
            }
        }
        return ['nombre' => mb_substr($nombre, 0, 100), 'marca' => mb_substr($marca, 0, 50), 'categorias' => $categorias];
    }

    /**
     * La categoria del catalogo que mejor calza con el nombre y las categorias de internet, o null.
     * El nombre pesa el doble (es lo que se ve en el empaque) y su primera palabra aun mas ("Salsa de tomate").
     * @param string[] $categoriasPublicas
     * @param array<int, string> $catalogo id => nombre
     */
    public static function categoria(string $nombre, array $categoriasPublicas, array $catalogo): ?int
    {
        $enNombre = ' ' . self::normalizar($nombre) . ' ';
        $enInternet = array_map(static fn(string $c): string => ' ' . self::normalizar($c) . ' ', $categoriasPublicas);
        $mejor = null;
        $mejorPuntaje = 0;
        foreach (self::PISTAS as $categoria => $palabras) {
            $id = null;
            foreach ($catalogo as $idCatalogo => $nombreCatalogo) {
                if (self::normalizar($nombreCatalogo) === $categoria) {
                    $id = $idCatalogo;
                    break;
                }
            }
            if ($id === null) {
                continue;
            }
            $puntaje = 0;
            foreach ($palabras as $palabra) {
                // Palabra entera: "sal" no esta en "salsa"; "leche" si esta en "leches"
                $patron = '/ ' . preg_quote($palabra, '/') . '(?:s|es)? /';
                if (preg_match('/^' . substr($patron, 1), $enNombre) === 1) {
                    $puntaje += 3;
                }
                if (preg_match($patron, $enNombre) === 1) {
                    $puntaje += 2;
                }
                foreach ($enInternet as $c) {
                    if (preg_match($patron, $c) === 1) {
                        $puntaje += 1;
                        break;
                    }
                }
            }
            if ($puntaje > $mejorPuntaje) {
                $mejor = $id;
                $mejorPuntaje = $puntaje;
            }
        }
        return $mejor;
    }

    /** Sin tildes, en minusculas y solo letras y numeros separados por un espacio: "Lácteos" -> "lacteos". */
    private static function normalizar(string $texto): string
    {
        $sinTildes = strtr(mb_strtolower($texto), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $sinTildes));
    }

    private static function descargarDeInternet(string $url): ?string
    {
        $contexto = stream_context_create([
            'http' => ['timeout' => 3, 'ignore_errors' => false, 'header' => "User-Agent: MassTienda-Web/1.0\r\n"],
        ]);
        $cuerpo = @file_get_contents($url, false, $contexto);
        return is_string($cuerpo) ? $cuerpo : null;
    }
}
