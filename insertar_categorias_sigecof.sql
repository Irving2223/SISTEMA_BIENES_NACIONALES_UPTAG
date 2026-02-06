-- Script para modificar la tabla categorias y agregar Cuenta Presupuestaria
-- Ejecutar en la base de datos bienes_nacionales_uptag

-- 1. Agregar columna cuenta_presupuestaria si no existe
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS cuenta_presupuestaria VARCHAR(50) DEFAULT NULL COMMENT 'Código de cuenta presupuestaria';

-- 2. Agregar columna denominacion si no existe (para usar en lugar de nombre)
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS denominacion VARCHAR(255) DEFAULT NULL COMMENT 'Denominación de la categoría';

-- 3. Actualizar denominacion con los valores actuales de nombre
UPDATE categorias SET denominacion = nombre WHERE denominacion IS NULL;

-- 4. Ejemplos de categorías SIGECOF para insertar
INSERT INTO categorias (id, codigo, denominacion, descripcion, cuenta_presupuestaria, activo, creado_en) VALUES
('01-01-01-01', '01-01-01-01', 'MOBILIARIO Y EQUIPO DE OFICINA', 'Mobiliario y equipo de oficina en general', '4.01.01.01.001', 1, NOW()),
('01-01-01-02', '01-01-01-02', 'EQUIPO DE CÓMPUTO', 'Computadoras, laptops, periféricos y accesorios', '4.01.01.01.002', 1, NOW()),
('01-01-01-03', '01-01-01-03', 'MOBILIARIO Y EQUIPO EDUCACIONAL', 'Mobiliario y equipos para uso educacional', '4.01.01.01.003', 1, NOW()),
('01-01-01-04', '01-01-01-04', 'EQUIPO DE COMUNICACIONES', 'Equipos de telefonía, radio y comunicaciones', '4.01.01.01.004', 1, NOW()),
('01-01-01-05', '01-01-01-05', 'MAQUINARIA Y HERRAMIENTAS', 'Maquinaria en general y herramientas', '4.01.01.01.005', 1, NOW()),
('01-01-01-06', '01-01-01-06', 'VEHÍCULOS', 'Vehículos motorizados de todo tipo', '4.01.01.01.006', 1, NOW()),
('01-01-01-07', '01-01-01-07', 'EQUIPO MÉDICO Y DE LABORATORIO', 'Equipos médicos, odontológicos y de laboratorio', '4.01.01.01.007', 1, NOW()),
('01-01-01-08', '01-01-01-08', 'SEMOVIENTES', 'Animales de trabajo y producción', '4.01.01.01.008', 1, NOW()),
('01-01-01-09', '01-01-01-09', 'HIERRAMIENTAS Y REPUESTOS', 'Herramientas menores y repuestos en general', '4.01.01.01.009', 1, NOW()),
('01-01-01-10', '01-01-01-10', 'OTROS BIENES DE CONSUMO', 'Otros bienes de consumo no clasificados', '4.01.01.01.010', 1, NOW());

-- Verificar los datos
SELECT id AS CODIGO, denominacion AS DENOMINACION, descripcion AS DESCRIPCION, cuenta_presupuestaria AS CUENTA_PRESUPUESTARIA, activo AS ESTATUS FROM categorias ORDER BY id;
