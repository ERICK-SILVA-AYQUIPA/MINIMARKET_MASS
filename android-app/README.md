# Mass Tienda · app Android

App para el celular de la tienda: consultar productos con la cámara, ver el inventario, registrar entradas de mercadería, vender en caja y revisar el historial de ventas.

La app **no tiene base de datos propia ni se conecta a MySQL**. Habla solo con el backend PHP del sistema web, por la API `/api/v1` (ver [`docs/api_movil.md`](../docs/api_movil.md)). Por eso una venta hecha en el celular aparece al instante en la web y al revés: las reglas (stock, precios, turnos de caja, permisos) las aplica el mismo servidor.

## Qué hace cada rol

| Rol | En la app |
|---|---|
| Cajero | Vende con el escáner (abre su turno si no tiene), consulta precios y stock, ve sus ventas |
| Almacenero | Registra entradas de mercadería, ve el inventario y las fichas de producto |
| Administrador | Inventario, historial de ventas de su tienda y **anulación** (por el mismo proceso que la web, nunca se borra una venta) |

El menú de inicio solo muestra lo que el rol puede hacer, y el servidor vuelve a revisar cada permiso.

Sobre los pagos: la app **registra** el método (efectivo, tarjeta o Sip) igual que la web. No cobra con tarjeta ni con billetera: el cajero cobra con el POS o la app de Sip y anota la autorización u operación.

## Tecnología

Kotlin, Jetpack Compose (Material 3), MVVM con `ViewModel` y `StateFlow`, CameraX y ML Kit para los códigos (EAN-13, EAN-8, UPC-A, UPC-E y Code 128), Retrofit y OkHttp, y `EncryptedSharedPreferences` para guardar la sesión. Android 8.0 (API 26) o más nuevo.

## Antes de empezar: el backend

1. Levantar la base y la web como dice el README principal (`docker compose up -d`).
2. Si la base se creó **antes** de esta versión, falta crear las 2 tablas de la API:
   ```powershell
   docker exec -i minimarket_mass_db mysql -u root -p minimarket_mass < backend/database/api_movil.sql
   ```
   (en Laragon, ejecutar `backend/database/api_movil.sql` en HeidiSQL). Un contenedor nuevo ya la corre solo.
3. Encender el servidor PHP:
   - Emulador: `php -S localhost:8000 -t frontend/public`
   - Celular real en el mismo Wi-Fi: `php -S 0.0.0.0:8000 -t frontend/public` y usar la IP de la PC (por ejemplo `http://192.168.1.50:8000/`). Puede que haya que permitir el puerto 8000 en el firewall de Windows.
4. Probar en el navegador: `http://localhost:8000/api/v1/salud` debe responder `{"ok":true,...}`.

## Compilar el APK de prueba (debug)

Con Android Studio (Ladybug o más nuevo, con el JDK 17 que trae):

1. **File → Open** y elegir la carpeta `android-app` (no la raíz del repositorio).
2. Esperar a que Gradle descargue las dependencias.
3. **Build → Build App Bundle(s) / APK(s) → Build APK(s)**.

O desde la terminal, dentro de `android-app`:

```powershell
.\gradlew.bat assembleDebug          # Windows
./gradlew assembleDebug              # Linux o macOS
./gradlew testDebugUnitTest          # pruebas del carrito
```

El APK queda en `android-app/app/build/outputs/apk/debug/app-debug.apk` (se crea al compilar; no está en GitHub).

La dirección del servidor que trae el APK de prueba es `http://10.0.2.2:8000/` (la PC vista desde el emulador). Para un celular real se cambia al compilar, o desde la pantalla de ingreso de la app con el botón **Cambiar** junto a la dirección del servidor (prueba la conexión antes de guardar):

```powershell
./gradlew assembleDebug -PmassApiUrlDebug=http://192.168.1.50:8000/
```

Solo el APK de prueba acepta `http://`. Así se puede probar en la red de la tienda, pero no se debe usar fuera de ella.

## Instalar

- Con cable: activar la depuración USB en el celular y correr `adb install -r app/build/outputs/apk/debug/app-debug.apk`.
- Sin cable: copiar el `.apk` al celular, abrirlo y permitir "instalar apps de origen desconocido" para el administrador de archivos.

La primera vez que se escanea, la app pide permiso para la cámara. Si se niega, se puede escribir el código o buscar por nombre.

## APK de lanzamiento (release)

El APK final **solo** habla con un servidor HTTPS (el de `docs/despliegue.md`; nginx y Apache ya envían `/api/` a `frontend/public/api/index.php`).

1. Crear la llave de firma una sola vez y guardarla fuera del repositorio:
   ```powershell
   keytool -genkeypair -v -keystore mass-tienda.jks -alias mass -keyalg RSA -keysize 2048 -validity 10000
   ```
2. Crear `android-app/keystore.properties` (está en `.gitignore`, nunca se sube):
   ```properties
   storeFile=../mass-tienda.jks
   storePassword=...
   keyAlias=mass
   keyPassword=...
   ```
3. Compilar con la dirección real:
   ```powershell
   ./gradlew assembleRelease -PmassApiUrl=https://mi-dominio.pe/
   ```
   Queda en `app/build/outputs/apk/release/app-release.apk`. Si la dirección no empieza con `https://`, la compilación se detiene.

Si se pierde la llave, las actualizaciones ya no se pueden instalar encima: hay que desinstalar y volver a instalar.

## Seguridad

- La app nunca recibe datos de MySQL: ni usuario, ni clave, ni host. Solo la dirección del servidor web.
- Se entra con DNI y clave, igual que la web, con el mismo bloqueo por intentos fallidos. El servidor entrega un token que vence a las 12 horas; en la base solo se guarda su SHA-256.
- El token y el perfil se guardan cifrados con `EncryptedSharedPreferences`. La app no guarda copias de seguridad (`allowBackup=false`).
- Ventas y entradas llevan una `Idempotency-Key`: si se cae la señal justo al cobrar, reintentar no crea una segunda venta.
- Antes de cobrar, la app manda el total que vio. Si el precio cambió en la web, el servidor responde 409 y la app pide revisar el carrito.
- Los registros de red (solo en el APK de prueba) ocultan la cabecera `Authorization`.

## Estructura

```
app/src/main/java/pe/mass/minimarket/
  data/          API (Retrofit + DTOs), configuración cifrada y Repositorio
  dominio/       Dinero, validaciones y Carrito (lógica pura, con pruebas)
  ui/            pantallas Compose por módulo: login, inicio, escaner, inventario, entrada, venta, historial
  MainActivity   navegación entre pantallas
```
