# Sistema de Gestión de Bienes Nacionales - UPTAG

Este proyecto es un **sistema de información web** desarrollado para la **Universidad Politécnica Territorial de阿里斯 Falcón "José Wenceslao López" (UPTAG)**, específicamente para la **Oficina de Bienes Nacionales**.

El sistema permite gestionar el registro, control y seguimiento de todos los activos muebles de la institución universitaria, desde su ingreso hasta su disposición final.

---

## 🚀 Características Principales

### Gestión de Bienes
- **Registro de Bienes**: Alta de bienes con código único de Bien Nacional
- **Edición de Bienes**: Modificación de datos de bienes existentes
- **Desincorporación de Bienes**: Proceso para bienes que cumplen su vida útil
- **Historial de Movimientos**: Seguimiento completo de cada bien

### Búsqueda Avanzada
- Búsqueda por código, descripción, marca, modelo, serial
- Filtros por estatus, categoría, lugar y dependencia
- Búsqueda recursiva en sub-ubicaciones y sub-dependencias
- Exportación de resultados a PDF

### Reportes
- Inventario general de bienes
- Reporte de movimientos por período
- Reporte por ubicación/departamento
- Reporte de categorías
- Generación en formato PDF

---

## 📋 Requisitos Previos

- **Servidor local o en producción con soporte PHP 8+**
- **XAMPP** (recomendado para entorno local)
- **MySQL/MariaDB** como motor de base de datos
- **Navegador web actualizado** (Chrome, Firefox, Edge, etc.)
- **Librería DOMPDF** incluida para generación de PDFs

---

## ⚙️ Instalación

1. **Clonar o descargar** este repositorio en tu servidor local (ejemplo: `htdocs` en XAMPP)
2. **Importar la base de datos**: Ejecutar el archivo `bd_inti.sql` en MySQL
   ```bash
   mysql -u root -p < bd_inti.sql
   ```
3. **Configurar conexión**: Editar `conexion.php` con tus credenciales
4. **Iniciar servicios**: Apache y MySQL desde XAMPP
5. **Acceder al sistema**:
   ```
   http://localhost/SISTEMA DE HUMBERTO
   ```

---

## 🔑 Credenciales de Acceso

### Usuario por Defecto (desarrollo)
- **Usuario**: admin
- **Contraseña**: admin123

> ⚠️ **Nota**: Cambiar las credenciales en producción

---

## 📂 Estructura del Proyecto

```
SISTEMA DE HUMBERTO/
├── assets/
│   ├── css/          # Estilos del sistema
│   ├── img/          # Imágenes del sistema
│   └── js/           # Librerías JavaScript
├── css/              # Estilos Bootstrap y Material
├── fonts/            # Fuentes Montserrat
├── js/               # jQuery y plugins
├── librerias/       # DOMPDF para PDFs
├── categorias.php   # Gestión de categorías
├── conexion.php     # Conexión a BD
├── header.php       # Cabecera y menú
├── footer.php       # Pie de página
├── home.php         # Panel principal
├── Loggin.php       # Autenticación
├── buscar.php       # Búsqueda avanzada
├── registrar_bien.php    # Registro de bienes
├── editar_bien.php       # Edición de bienes
├── desincorporar_bien.php # Desincorporación
├── registrar_movimiento.php # Movimientos
├── lugares_dependencias.php # Lugares y deps.
├── generar_reporte_*.php   # Reportes PDF
├── reporte_*.php           # Vistas de reportes
├── auditoria_sistema.php   # Auditoría
├── gestion_usuarios.php    # Usuarios
├── configuracion.php       # Configuración
└── salir.php              # Cerrar sesión
```

---

## 📊 Estructura de la Base de Datos

### Tabla Principal: `bienes`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | int | Identificador único |
| codigo_bien_nacional | varchar(50) | Código único del bien |
| codigo_anterior | varchar(50) | Código anterior |
| descripcion | text | Descripción del bien |
| marca | varchar(100) | Marca |
| modelo | varchar(100) | Modelo |
| serial | varchar(100) | Número de serie |
| color | varchar(50) | Color |
| dimensiones | varchar(100) | Dimensiones |
| valor_original | decimal(18,2) | Valor de adquisición |
| valor_actual | decimal(18,2) | Valor depreciado |
| vida_util_anos | int | Vida útil en años |
| estatus_id | int | FK a estatus |
| categoria_id | int | FK a categorías |
| ubicacion_id | int | FK a ubicaciones |
| dependencia_id | int | FK a dependencias |
| observaciones | text | Observaciones |
| fecha_incorporacion | date | Fecha de ingreso |
| activo | tinyint(1) | Si está activo |

### Otras Tablas

- **categorias**: Clasificación de bienes
- **estatus**: Estados posibles (Activo, Desincorporado, etc.)
- **ubicaciones**: Sedes y ubicaciones
- **dependencias**: Departamentos y oficinas
- **movimientos**: Historial de traslados
- **usuarios**: Usuarios del sistema
- **auditoria**: Registro de acciones

---

## 🔐 Seguridad

- **Autenticación**: Sistema de login con verificación de sesión
- **Control de Acceso**: Verificación en cada página protegida
- **Sesiones**: Tiempo de inactividad configurable (10 minutos)
- **Validación**: Validación de formularios en servidor
- **Protección SQL**: Uso de consultas preparadas (prepared statements)

---

## 📝 Uso del Sistema

### 1. Inicio de Sesión
Acceder con las credenciales proporcionadas.

### 2. Registrar un Bien
1. Ir a **Gestión de Bienes** → **Registrar Bien**
2. Completar el formulario con los datos del bien
3. El código de bien nacional se genera automáticamente

### 3. Buscar Bienes
1. Ir a **Búsqueda de Bienes**
2. Ingresar término de búsqueda o usar filtros
3. Opcional: Exportar resultados a PDF

### 4. Registrar Movimiento
1. Ir a **Gestión de Bienes** → **Registrar Movimiento**
2. Seleccionar el bien y tipo de movimiento
3. Completar los datos requeridos

### 5. Desincorporar Bien
1. Ir a **Gestión de Bienes** → **Desincorporar Bien**
2. Buscar el bien por código
3. Completar el motivo y fecha de desincorporación
4. Confirmar la acción

### 6. Generar Reportes
1. Ir a **Reportes** en el menú
2. Seleccionar tipo de reporte
3. Aplicar filtros si es necesario
4. Descargar en PDF

---

## 🛠️ Mantenimiento

### Respaldo de Base de Datos
```bash
mysqldump -u root -p bd_inti > respaldo_$(date +%Y%m%d).sql
```

### Restauración
```bash
mysql -u root -p bd_inti < respaldo_archivo.sql
```

---

## 👥 Equipo de Desarrollo

- **Desarrollo Original**: 
  - Brayan Javier Pirona Silva
  - Irving Jesús Coello Alcalá
  - Richard Alejandro Molina Nuñez
  - Dixon Jacob Veliz Gallardo

- **Universidad**: UPTAG (Universidad Politécnica Territorial de阿里斯 Falcón)

---

## 📝 Licencia

Este proyecto es de uso institucional para la Universidad Politécnica Territorial de阿里斯 Falcón.

---

## 📌 Notas Importantes

1. **Identidad Visual**: El sistema usa colores naranja (#ff6600) en toda la interfaz
2. **Reportes**: Generados con la librería DOMPDF incluida en el proyecto
3. **Conexión**: Configurar en `conexion.php` las credenciales de BD
4. **Autenticación**: Verificación de usuarios en cada página protegida
5. **Compatibilidad**: Optimizado para PHP 8+ y MySQL/MariaDB
6. **Responsive**: Diseño adaptativo para diferentes tamaños de pantalla

---

## 📞 Soporte

Para soporte técnico, contactar al equipo de desarrollo del proyecto.
