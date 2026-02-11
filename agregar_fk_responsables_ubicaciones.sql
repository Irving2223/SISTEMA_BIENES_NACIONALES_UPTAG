-- Agregar columna ubicacion_id a la tabla responsables
ALTER TABLE `responsables` 
ADD COLUMN `ubicacion_id` INT(11) DEFAULT NULL AFTER `dependencia_id`;

-- Crear índice para la nueva columna (necesario para la clave foránea)
ALTER TABLE `responsables` 
ADD INDEX `ubicacion_id` (`ubicacion_id`);

-- Crear la restricción de clave foránea
ALTER TABLE `responsables` 
ADD CONSTRAINT `fk_responsables_ubicacion` 
FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones` (`id`) ON DELETE SET NULL;
