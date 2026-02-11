-- phpMyAdmin SQL Dump
-- Base de datos limpia y corregida
-- Fecha de creación: 10-02-2026

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Estructura de tablas en orden correcto (respetando dependencias)
-- --------------------------------------------------------

-- 1. Tabla usuarios (sin dependencias)
CREATE TABLE `usuarios` (
  `cedula` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `rol` enum('Administrador','Usuario') NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cedula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insertar usuario administrador genérico
-- Usuario: admin / Contraseña: Admin123!
INSERT INTO `usuarios` VALUES
('12345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'Sistema', 'admin@sistema.com', 'Administrador', 1, NULL, NOW(), NOW());

-- --------------------------------------------------------

-- 2. Tabla categorias (auto-referenciada)
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `categoria_padre_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categoria_codigo` (`codigo`),
  KEY `categoria_padre_id` (`categoria_padre_id`),
  CONSTRAINT `categorias_ibfk_1` FOREIGN KEY (`categoria_padre_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categorias` VALUES
(1, 'Mobiliario y Equipo de Oficina', 'MOB-001', 'Mobiliario general para oficinas', NULL, 1, NOW(), NOW());

-- --------------------------------------------------------

-- 3. Tabla estatus (sin dependencias)
CREATE TABLE `estatus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `permite_movimiento` tinyint(1) DEFAULT 1,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_estatus_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `estatus` VALUES
(1, 'Activo', 'Bien incorporado y operativo', 1, 1, NOW()),
(2, 'En Uso', 'Bien asignado y en uso normal', 1, 1, NOW()),
(3, 'En Reparacion', 'Bien temporalmente fuera de servicio', 0, 1, NOW()),
(4, 'Desincorporado', 'Bien dado de baja del inventario', 0, 1, NOW()),
(5, 'Extraviado', 'Bien no localizado', 0, 1, NOW()),
(6, 'En Proceso de Desincorporacion', 'Bien en tramite de baja', 0, 1, NOW());

-- --------------------------------------------------------

-- 4. Tabla dependencias (sin dependencias)
CREATE TABLE `dependencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `tipo` enum('Administrativa','Academica','PNF','Laboratorio','Otra') DEFAULT 'Otra',
  `responsable_nombre` varchar(150) DEFAULT NULL,
  `responsable_cedula` varchar(20) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `estado` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dependencia_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dependencias` VALUES
(1, 'RECTORÍA - UPTAG', '01', 'Administrativa', NULL, NULL, NULL, NULL, NULL, 1, NOW(), NOW()),
(2, 'VICERECTORADO ACADÉMICO', '02', 'Academica', NULL, NULL, NULL, NULL, NULL, 1, NOW(), NOW()),
(3, 'OFICINA DE GESTIÓN ADMINISTRATIVA (OGA)', '03', 'Administrativa', NULL, NULL, NULL, NULL, NULL, 1, NOW(), NOW());

-- --------------------------------------------------------

-- 5. Tabla ubicaciones (depende de dependencias)
CREATE TABLE `ubicaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dependencia_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `responsable` varchar(200) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dependencia_id` (`dependencia_id`),
  CONSTRAINT `ubicaciones_ibfk_1` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ubicaciones` VALUES
(1, 1, 'OFICINA DEL RECTOR', '0101', NULL, NULL, NULL, 1, NOW(), NOW()),
(2, 2, 'VICERECTORADO ACADEMICO JEFATURA', '0201', NULL, NULL, NULL, 1, NOW(), NOW()),
(3, 3, 'OFICINA DE GESTION ADM.', '0301', NULL, NULL, NULL, 1, NOW(), NOW());

-- --------------------------------------------------------

-- 6. Tabla proveedores (sin dependencias)
CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `razon_social` varchar(200) NOT NULL,
  `rif` varchar(20) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contacto_nombre` varchar(150) DEFAULT NULL,
  `tipo_proveedor` enum('Oficina de Compras','Ingreso por parte de la Universidad') NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_proveedor_rif` (`rif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- 7. Tabla donaciones (sin dependencias)
CREATE TABLE `donaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donante_nombre` varchar(200) NOT NULL,
  `donante_rif` varchar(20) DEFAULT NULL,
  `donante_cedula` varchar(20) DEFAULT NULL,
  `donante_direccion` varchar(255) DEFAULT NULL,
  `donante_telefono` varchar(50) DEFAULT NULL,
  `donante_email` varchar(100) DEFAULT NULL,
  `fecha_donacion` date NOT NULL,
  `tipo_donante` enum('Persona Natural','Persona Juridica','Institucion') NOT NULL,
  `documento_soporte` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- 8. Tabla adquisiciones (depende de proveedores)
CREATE TABLE `adquisiciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_adquisicion` enum('Compra','Ingreso Propio','Traspaso') NOT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `numero_factura` varchar(50) DEFAULT NULL,
  `numero_orden_compra` varchar(50) DEFAULT NULL,
  `fecha_adquisicion` date NOT NULL,
  `monto_total` decimal(18,2) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `documento_soporte` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `proveedor_id` (`proveedor_id`),
  CONSTRAINT `adquisiciones_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- 9. Tabla responsables (depende de dependencias y ubicaciones)
CREATE TABLE `responsables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cedula` varchar(20) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `dependencia_id` int(11) DEFAULT NULL,
  `ubicacion_id` int(11) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_responsable_cedula` (`cedula`),
  KEY `dependencia_id` (`dependencia_id`),
  KEY `ubicacion_id` (`ubicacion_id`),
  CONSTRAINT `fk_responsables_ubicacion` FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `responsables_ibfk_1` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- 10. Tabla bienes (depende de categorias, ubicaciones, adquisiciones, donaciones, estatus)
CREATE TABLE `bienes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_bien_nacional` varchar(50) NOT NULL,
  `codigo_anterior` varchar(50) DEFAULT NULL,
  `categoria_id` int(11) NOT NULL,
  `ubicacion_id` int(11) DEFAULT NULL,
  `adquisicion_id` int(11) DEFAULT NULL,
  `donacion_id` int(11) DEFAULT NULL,
  `descripcion` text NOT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `serial` varchar(100) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `dimensiones` varchar(100) DEFAULT NULL,
  `valor_adquisicion` decimal(18,2) DEFAULT NULL,
  `valor_actual` decimal(18,2) DEFAULT NULL,
  `vida_util_anos` int(11) DEFAULT NULL,
  `estatus_id` int(11) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_incorporacion` date NOT NULL,
  `fecha_desincorporacion` date DEFAULT NULL,
  `motivo_desincorporacion` text DEFAULT NULL,
  `documento_desincorporacion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codigo_bien` (`codigo_bien_nacional`),
  KEY `categoria_id` (`categoria_id`),
  KEY `adquisicion_id` (`adquisicion_id`),
  KEY `donacion_id` (`donacion_id`),
  KEY `estatus_id` (`estatus_id`),
  KEY `idx_ubicacion_id` (`ubicacion_id`),
  CONSTRAINT `bienes_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`),
  CONSTRAINT `bienes_ibfk_2` FOREIGN KEY (`adquisicion_id`) REFERENCES `adquisiciones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bienes_ibfk_3` FOREIGN KEY (`donacion_id`) REFERENCES `donaciones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bienes_ibfk_4` FOREIGN KEY (`estatus_id`) REFERENCES `estatus` (`id`),
  CONSTRAINT `fk_bienes_ubicacion` FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- 11. Tabla movimientos (depende de bienes, ubicaciones, responsables, usuarios)
CREATE TABLE `movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bien_id` int(11) NOT NULL,
  `tipo_movimiento` enum('Incorporacion','Traslado','Desincorporacion','Asignacion','Reparacion','Devolucion') NOT NULL,
  `ubicacion_origen_id` int(11) DEFAULT NULL,
  `ubicacion_destino_id` int(11) DEFAULT NULL,
  `responsable_origen_id` int(11) DEFAULT NULL,
  `responsable_destino_id` int(11) DEFAULT NULL,
  `fecha_movimiento` date NOT NULL,
  `razon` text NOT NULL,
  `numero_documento` varchar(50) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `usuario_registro` varchar(20) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bien_id` (`bien_id`),
  KEY `ubicacion_origen_id` (`ubicacion_origen_id`),
  KEY `ubicacion_destino_id` (`ubicacion_destino_id`),
  KEY `responsable_origen_id` (`responsable_origen_id`),
  KEY `responsable_destino_id` (`responsable_destino_id`),
  KEY `usuario_registro` (`usuario_registro`),
  CONSTRAINT `movimientos_ibfk_1` FOREIGN KEY (`bien_id`) REFERENCES `bienes` (`id`),
  CONSTRAINT `movimientos_ibfk_2` FOREIGN KEY (`ubicacion_origen_id`) REFERENCES `ubicaciones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `movimientos_ibfk_3` FOREIGN KEY (`ubicacion_destino_id`) REFERENCES `ubicaciones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `movimientos_ibfk_4` FOREIGN KEY (`responsable_origen_id`) REFERENCES `responsables` (`id`) ON DELETE SET NULL,
  CONSTRAINT `movimientos_ibfk_5` FOREIGN KEY (`responsable_destino_id`) REFERENCES `responsables` (`id`) ON DELETE SET NULL,
  CONSTRAINT `movimientos_ibfk_6` FOREIGN KEY (`usuario_registro`) REFERENCES `usuarios` (`cedula`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- 12. Tabla control_perceptivo (depende de bienes, ubicaciones, responsables)
CREATE TABLE `control_perceptivo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bien_id` int(11) NOT NULL,
  `fecha_control` date NOT NULL,
  `ubicacion_fisica_verificada_id` int(11) DEFAULT NULL,
  `responsable_verificado_id` int(11) DEFAULT NULL,
  `condicion` enum('Excelente','Bueno','Regular','Malo','No Localizado') NOT NULL,
  `observaciones` text DEFAULT NULL,
  `verificador_nombre` varchar(150) DEFAULT NULL,
  `verificador_cedula` varchar(20) DEFAULT NULL,
  `firma_digital` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bien_id` (`bien_id`),
  KEY `ubicacion_fisica_verificada_id` (`ubicacion_fisica_verificada_id`),
  KEY `responsable_verificado_id` (`responsable_verificado_id`),
  CONSTRAINT `control_perceptivo_ibfk_1` FOREIGN KEY (`bien_id`) REFERENCES `bienes` (`id`),
  CONSTRAINT `control_perceptivo_ibfk_2` FOREIGN KEY (`ubicacion_fisica_verificada_id`) REFERENCES `ubicaciones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `control_perceptivo_ibfk_3` FOREIGN KEY (`responsable_verificado_id`) REFERENCES `responsables` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- 13. Tabla auditoria (depende de usuarios)
CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tabla_afectada` varchar(50) NOT NULL,
  `accion` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `usuario_cedula` varchar(20) DEFAULT NULL,
  `datos_anteriores` varchar(2000) DEFAULT NULL,
  `datos_nuevos` varchar(2000) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `fecha_accion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_cedula` (`usuario_cedula`),
  CONSTRAINT `auditoria_ibfk_1` FOREIGN KEY (`usuario_cedula`) REFERENCES `usuarios` (`cedula`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;