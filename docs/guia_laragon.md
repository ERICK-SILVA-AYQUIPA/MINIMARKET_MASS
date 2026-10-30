# Guía Rápida para Usar el Proyecto en Laragon (Windows)

Esta guía es para los integrantes del equipo que trabajan en Windows usando **Laragon**.

---

## 1. Requisitos Previos en Laragon
1. Tener Laragon instalado y abierto.
2. Asegurarse de tener activado **PHP 8.2 o superior**:
   - Clic derecho en Laragon ➔ **PHP** ➔ **Version** ➔ Seleccionar PHP 8.2 o PHP 8.4.
3. Dar clic al botón **"Iniciar todo"** en Laragon (para que MySQL y Apache/Nginx estén corriendo).

---

## 2. Instalar la Base de Datos (1 solo paso)
Hemos creado un único archivo con todo listo: [`backend/database/instalar_laragon.sql`](../backend/database/instalar_laragon.sql).

1. En Laragon, da clic en el botón **"Base de datos"** (se abrirá HeidiSQL).
2. Conéctate con los datos normales de Laragon:
   - Servidor: `127.0.0.1` o `localhost`
   - Usuario: `root`
   - Contraseña: *(vacía / sin contraseña)*
   - Puerto: `3306`
3. En el menú superior de HeidiSQL, ve a:
   **Archivo** ➔ **Cargar archivo SQL...**
4. Selecciona el archivo:
   `backend/database/instalar_laragon.sql`
5. Presiona la tecla **F9** (o el botón azul de Ejecutar).
6. ¡Listo! El archivo creará automáticamente la base de datos `minimarket_mass` con todas sus tablas, datos iniciales, claves de acceso, ingresos y auditoría.

---

## 3. Configuración del `.env`
El repositorio incluye el archivo listo [`.env.laragon`](../.env.laragon):
- Solo tienes que **copiarlo y renombrarlo como `.env`** en la carpeta principal del proyecto.
- Sus datos ya vienen listos:
  ```ini
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_NAME=minimarket_mass
  DB_USER=root
  DB_PASSWORD=
  ```

---

## 4. Iniciar el Sistema (1 solo clic)
Dentro de la carpeta del proyecto en Windows:
- Haz doble clic en el archivo:
  👉 **`iniciar_servidor_laragon.bat`**

Este archivo se encarga de:
1. Verificar tu archivo `.env`.
2. Abrir tu navegador en **`http://localhost:8000`**.
3. Iniciar el servidor local de PHP apuntando a la carpeta correcta (`public`).

---

## 5. Datos de Ingreso de Prueba
Puedes ingresar al sistema con cualquiera de estos usuarios:
- **Administrador (Tienda Cayma):** DNI `12345678` / Clave `Mass2026!`
- **Cajero (Tienda Cayma):** DNI `87654321` / Clave `Mass2026!`
- **Almacenero (Tienda Cayma):** DNI `11223344` / Clave `Mass2026!`
