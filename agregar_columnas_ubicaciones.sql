-- Agregar columnas para responsables a la tabla ubicaciones
ALTER TABLE `ubicaciones` 
ADD COLUMN `responsable` VARCHAR(200) DEFAULT NULL AFTER `descripcion`;

ALTER TABLE `ubicaciones` 
ADD COLUMN `telefono` VARCHAR(50) DEFAULT NULL AFTER `responsable`;

ALTER TABLE `ubicaciones` 
ADD COLUMN `email` VARCHAR(100) DEFAULT NULL AFTER `telefono`;
