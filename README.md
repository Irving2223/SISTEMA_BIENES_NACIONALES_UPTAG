# Sistema de Información para el INTI – Departamento de Atención al Campesino

Este proyecto es un **sistema de información web** desarrollado para el **Instituto Nacional de Tierras (INTI), Oficina Regional Falcón**, con el propósito de mejorar la gestión administrativa de solicitudes en el Departamento de Atención al Campesino.

El sistema permite registrar solicitantes (personas naturales, jurídicas y colectivos), gestionar solicitudes relacionadas con trámites agrarios, administrar predios y procedimientos, y realizar un seguimiento de los estatus de cada solicitud.

---

## 🚀 Características principales

* Registro de solicitantes (naturales, jurídicos y colectivos).
* Gestión de solicitudes con número único y control de estatus.
* Administración de predios con ubicación detallada (municipio, parroquia y sector).
* Seguimiento del historial de cambios de estatus.
* Módulo de usuarios con roles (Administrador y Usuario).
* Bitácora de acciones para trazabilidad.
* Validación de seguridad en formularios y cifrado de contraseñas.

---

## 📋 Requisitos previos

* **Servidor local o en producción con soporte PHP 8+**
* **XAMPP** (recomendado para entorno local)
* **MySQL/MariaDB** como motor de base de datos
* **Navegador web actualizado** (Chrome, Firefox, Edge, etc.)
* Hardware mínimo:

  * 4 GB de RAM
  * Procesador Intel i3 o equivalente
  * 500 MB de espacio en disco para sistema y BD

---

## ⚙️ Instalación

1. Clonar o descargar este repositorio en tu servidor local (ejemplo: `htdocs` en XAMPP).
2. Importar el archivo `bd_inti.sql` en MySQL usando phpMyAdmin o línea de comandos.
3. Configurar las credenciales de conexión en el archivo `conexion.php`.
4. Iniciar el servidor Apache y MySQL desde XAMPP.
5. Acceder al sistema desde el navegador en:

   ```
   http://localhost/SISTEMA INTI EDIT
   ```

---

## 🔑 Acceso inicial

* Usuario: **12345678**
* Contraseña: **Sistema21$**

*(la el insert del usuario administrador esta abajo de la BD como un comentario.)*

---

## 📚 Metodología aplicada

El sistema fue desarrollado siguiendo la **metodología de Kendall & Kendall (2015)** y los principios de la **Investigación Acción Participativa (IAP)**, asegurando un enfoque práctico, participativo y ajustado a las necesidades del INTI.

---

## 👥 Autores

* Brayan Javier Pirona Silva
* Irving Jesús Coello Alcalá
* Richard Alejandro Molina Nuñez
* Dixon Jacob Veliz Gallardo

Tutor: **Lariana Camacho**
Validador: **Carlos Noguera**



## Script de los municipios, parroquias y sectores del estado falcon

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM `sectores`;
DELETE FROM `parroquias`;
DELETE FROM `municipios`;

ALTER TABLE `municipios` AUTO_INCREMENT = 1;
ALTER TABLE `parroquias` AUTO_INCREMENT = 1;
ALTER TABLE `sectores` AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Poblar tabla de municipios
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (1, 'Miranda');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (2, 'Acosta');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (3, 'Bolívar');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (4, 'Buchivacoa');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (5, 'Cacique Manaure');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (6, 'Carirubana');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (7, 'Colina');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (8, 'Dabajuro');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (9, 'Democracia');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (10, 'Falcón');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (11, 'Federación');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (12, 'Jacura');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (13, 'Los Taques');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (14, 'Mauroa');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (15, 'Monseñor Iturriza');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (16, 'Palma Sola');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (17, 'Petit');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (18, 'Píritu');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (19, 'San Francisco');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (20, 'Silva');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (21, 'Sucre');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (22, 'Tocópero');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (23, 'Unión');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (24, 'Urumaco');
INSERT INTO `municipios` (`id_municipio`, `nombre_municipio`) VALUES (25, 'Zamora');

-- Poblar tabla de parroquias
-- Municipio Miranda (1) - 7 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (1, 1, 'Guzmán Guillermo');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (2, 1, 'Mitare');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (3, 1, 'Río Seco');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (4, 1, 'Sabaneta');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (5, 1, 'San Antonio');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (6, 1, 'San Gabriel');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (7, 1, 'Santa Ana');

-- Municipio Acosta (2) - 4 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (8, 2, 'Capadare');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (9, 2, 'La Pastora');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (10, 2, 'Libertador');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (11, 2, 'San Juan de los Cayos');

-- Municipio Bolívar (3) - 3 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (12, 3, 'Aracua');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (13, 3, 'La Peña');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (14, 3, 'San Luis');

-- Municipio Buchivacoa (4) - 7 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (15, 4, 'Bariro');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (16, 4, 'Borojó');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (17, 4, 'Capatárida');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (18, 4, 'Guajiro');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (19, 4, 'Seque');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (20, 4, 'Valle de Eroa');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (21, 4, 'Zazárida');

-- Municipio Cacique Manaure (5) - 1 parroquia
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (22, 5, 'Cacique Manaure (Yaracal)');

-- Municipio Carirubana (6) - 4 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (23, 6, 'Norte');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (24, 6, 'Carirubana');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (25, 6, 'Santa Ana');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (26, 6, 'Urbana Punta Cardón');

-- Municipio Colina (7) - 5 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (27, 7, 'La Vela de Coro');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (28, 7, 'Acurigua');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (29, 7, 'Guaibacoa');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (30, 7, 'Las Calderas');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (31, 7, 'Mataruca');

-- Municipio Dabajuro (8) - 1 parroquia
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (32, 8, 'Dabajuro');

-- Municipio Democracia (9) - 5 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (33, 9, 'Agua Clara');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (34, 9, 'Avaria');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (35, 9, 'Pedregal');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (36, 9, 'Piedra Grande');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (37, 9, 'Purureche');

-- Municipio Falcón (10) - 9 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (38, 10, 'Adaure');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (39, 10, 'Adícora');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (40, 10, 'Baraived');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (41, 10, 'Buena Vista');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (42, 10, 'Jadacaquiva');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (43, 10, 'El Vínculo');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (44, 10, 'El Hato');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (45, 10, 'Moruy');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (46, 10, 'Pueblo Nuevo');

-- Municipio Federación (11) - 5 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (47, 11, 'Agua Larga');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (48, 11, 'Churuguara');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (49, 11, 'El Paují');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (50, 11, 'Independencia');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (51, 11, 'Mapararí');

-- Municipio Jacura (12) - 3 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (52, 12, 'Agua Linda');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (53, 12, 'Araurima');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (54, 12, 'Jacura');

-- Municipio Los Taques (13) - 2 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (55, 13, 'Los Taques');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (56, 13, 'Judibana');

-- Municipio Mauroa (14) - 3 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (57, 14, 'Mene de Mauroa');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (58, 14, 'San Félix');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (59, 14, 'Casigua');

-- Municipio Monseñor Iturriza (15) - 3 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (60, 15, 'Boca del Tocuyo');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (61, 15, 'Chichiriviche');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (62, 15, 'Tocuyo de la Costa');

-- Municipio Palma Sola (16) - 1 parroquia
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (63, 16, 'Palmasola');

-- Municipio Petit (17) - 3 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (64, 17, 'Cabure');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (65, 17, 'Colina');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (66, 17, 'Curimagua');

-- Municipio Píritu (18) - 2 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (67, 18, 'San José de la Costa');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (68, 18, 'Píritu');

-- Municipio San Francisco (19) - 1 parroquia
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (69, 19, 'Capital San Francisco Mirimire');

-- Municipio Silva (20) - 2 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (70, 20, 'Tucacas');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (71, 20, 'Boca de Aroa');

-- Municipio Sucre (21) - 2 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (72, 21, 'Sucre');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (73, 21, 'Pecaya');

-- Municipio Tocópero (22) - 1 parroquia
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (74, 22, 'Tocópero');

-- Municipio Unión (23) - 3 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (75, 23, 'El Charal');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (76, 23, 'Las Vegas del Tuy');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (77, 23, 'Santa Cruz de Bucaral');

-- Municipio Urumaco (24) - 2 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (78, 24, 'Bruzual');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (79, 24, 'Urumaco');

-- Municipio Zamora (25) - 5 parroquias
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (80, 25, 'Puerto Cumarebo');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (81, 25, 'La Ciénaga');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (82, 25, 'La Soledad');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (83, 25, 'Pueblo Cumarebo');
INSERT INTO `parroquias` (`id_parroquia`, `id_municipio`, `nombre_parroquia`) VALUES (84, 25, 'Zazárida');

--- ESTE ES EL USUARIO ADMINISTRADOR POR DEFECTO (CI: 12345678, CLAVE: Sistema21$)
INSERT INTO `usuarios` VALUES("12345678", "Administrador", "Sistema", "usuario@test.com", "$2y$10$/VJh.HT0EC8ZsWNsTvLKFutO6ieStGooqZQfVsUk5h3SujFoViaqu", "Administrador", "1", "2025-09-29 22:00:55");


INSERT INTO `tipo_procedimiento` (`id_procedimiento`, `nombre_procedimiento`, `descripcion`) VALUES
(1, 'Adjudicación / Regularización de Tierras', 'Cuando un campesino, colectivo o persona jurídica solicita la entrega formal de la tierra o la regularización de su tenencia.'),
(2, 'Renuncia', 'El solicitante cede voluntariamente los derechos sobre la tierra adjudicada.'),
(3, 'Desistimiento', 'El solicitante retira una solicitud en curso antes de que culmine el proceso.'),
(4, 'Revocatoria de Oficio', 'El INTI inicia un proceso para quitar la adjudicación por incumplimiento o irregularidad.'),
(5, 'Garantía de Permanencia', 'Se solicita para mantener el derecho de uso de la tierra asignada, especialmente en casos de conflictos o revisiones.'),
(6, 'Denuncia de Tierra Ociosa o Improductiva', 'Solicitud para que el INTI evalúe un terreno que no está cumpliendo con su función productiva.'),
(7, 'Reimpresión de Documentos', 'Cuando el solicitante extravía o requiere nuevamente el título, carta agraria u otro documento oficial emitido por el INTI.'),
(8, 'Aprovechamiento de Recursos Naturales', 'Permiso especial para actividades vinculadas a los recursos existentes en los predios (agua, bosques, pastos, etc.).'),
(9, 'Otro', 'Espacio abierto para trámites especiales que no encajen en las categorías anteriores.');


