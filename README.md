# Sistema de Gestión de Bienes Nacionales - UPTAG

Este proyecto es un **sistema de información web** desarrollado para la **Universidad Politécnica Territorial de阿里斯 Falcón "José Wenceslao López" (UPTAG)**, específicamente para la **Oficina de Bienes Nacionales**.

El sistema permite gestionar el registro, control y seguimiento de todos los activos muebles de la institución universitaria, desde su ingreso hasta su disposición final.

---

## 🚀 Características principales

- **Registro de Bienes**: Alta de bienes con código único de Bien Nacional
- **Control de Inventario**: Seguimiento físico y legal de cada equipo
- **Gestión de Movimientos**: Registro de traslados entre dependencias
- **Desincorporación de Bienes**: Proceso para bienes que cumplen su vida útil
- **Reportes**: Generación de inventarios, movimientos y ubicaciones en PDF
- **Búsqueda Avanzada**: Filtrado por código, descripción, categoría, estatus y más

---

## 📋 Requisitos previos

- **Servidor local o en producción con soporte PHP 8+**
- **XAMPP** (recomendado para entorno local)
- **MySQL/MariaDB** como motor de base de datos
- **Navegador web actualizado** (Chrome, Firefox, Edge, etc.)
- **Librería DOMPDF** incluida para generación de PDFs

---

## ⚙️ Instalación

1. Clonar o descargar este repositorio en tu servidor local (ejemplo: `htdocs` en XAMPP).
2. Importar el archivo de base de datos `bienes_nacionales_uptag.sql` en MySQL usando phpMyAdmin o línea de comandos.
3. Configurar las credenciales de conexión en el archivo `conexion.php`.
4. Iniciar el servidor Apache y MySQL desde XAMPP.
5. Acceder al sistema desde el navegador en:

   ```
   http://localhost/SISTEMA DE HUMBERTO
   ```

---

## 🔑 Acceso inicial

- **Usuario**: El primer usuario debe ser creado directamente en la base de datos
- **La base de datos debe contener**:
  - Tabla `bienes` con la estructura de activos
  - Tabla `categorias` con las categorías de bienes
  - Tabla `estatus` con los estados posibles de un bien
  - Tabla `ubicaciones` con las sedes y PNF
  - Tabla `usuarios` con los usuarios del sistema

---

## 📂 Estructura de la Base de Datos

### Tabla Principal: `bienes`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | int | Identificador único |
| codigo_bien_nacional | varchar(50) | Código único del bien (único) |
| codigo_anterior | varchar(50) | Código anterior si existe |
| categoria_id | int | FK a categorías |
| adquisicion_id | int | FK a adquisiciones |
| donacion_id | int | FK a donaciones |
| descripcion | text | Descripción del bien |
| marca | varchar(100) | Marca del bien |
| modelo | varchar(100) | Modelo del bien |
| serial | varchar(100) | Número de serie |
| color | varchar(50) | Color del bien |
| dimensiones | varchar(100) | Dimensiones |
| valor_adquisicion | decimal(18,2) | Valor de compra |
| valor_actual | decimal(18,2) | Valor actual depreciado |
| vida_util_anos | int | Años de vida útil |
| estatus_id | int | FK a estatus |
| observaciones | text | Observaciones adicionales |
| fecha_incorporacion | date | Fecha de ingreso |
| fecha_desincorporacion | date | Fecha de baja (si aplica) |
| motivo_desincorporacion | text | Motivo de baja |
| documento_desincorporacion | varchar(255) | Documento de respaldo |
| activo | tinyint(1) | Si el bien está activo |
| fecha_creacion | timestamp | Fecha de registro |
| fecha_actualizacion | timestamp | Última modificación |

---

## 👥 Autores

- **Sistema INTI Original**: Brayan Javier Pirona Silva, Irving Jesús Coello Alcalá, Richard Alejandro Molina Nuñez, Dixon Jacob Veliz Gallardo
- **Adaptación Bienes Nacionales**: Sistema actualizado para UPTAG

---

## 📝 Licencia

Este proyecto es de uso institucional para la Universidad Politécnica Territorial de阿里斯 Falcón.

---

## 📌 Notas Importantes

1. El sistema maneja **colores naranja (#ff6600)** en toda la interfaz
2. Los reportes se generan usando la librería **DOMPDF** incluida
3. La conexión a BD se configura en `conexion.php`
4. El sistema verifica la autenticación de usuarios en cada página protegida
