-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- TABLAS SIN DEPENDENCIAS (NIVEL 0)
-- ============================================

-- Estructura de tabla para la tabla `usuarios`
CREATE TABLE `usuarios` (
  `cedula` varchar(12) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL DEFAULT '',
  `clave_usuario` varchar(255) NOT NULL,
  `rol` enum('Administrador','Usuario') DEFAULT 'Usuario',
  `activo` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cedula`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Estructura de tabla para la tabla `municipios`
CREATE TABLE `municipios` (
  `id_municipio` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_municipio` varchar(100) NOT NULL,
  PRIMARY KEY (`id_municipio`),
  UNIQUE KEY `nombre_municipio` (`nombre_municipio`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Estructura de tabla para la tabla `representantes`
CREATE TABLE `representantes` (
  `id_representante` int(15) NOT NULL,
  `primer_nombre` varchar(50) NOT NULL,
  `segundo_nombre` varchar(50) DEFAULT NULL,
  `primer_apellido` varchar(50) NOT NULL,
  `segundo_apellido` varchar(50) DEFAULT NULL,
  `sexo` enum('M','F') NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `direccion` text NOT NULL,
  `email` varchar(100) NOT NULL,
  `profesion` varchar(100) NOT NULL,
  `tipo` enum('apoderado','representante legal') NOT NULL,
  `activo` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_representante`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Estructura de tabla para la tabla `tipo_procedimiento`
CREATE TABLE `tipo_procedimiento` (
  `id_procedimiento` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_procedimiento` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  PRIMARY KEY (`id_procedimiento`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLAS NIVEL 1 (Dependen de municipios)
-- ============================================

-- Estructura de tabla para la tabla `parroquias`
CREATE TABLE `parroquias` (
  `id_parroquia` int(11) NOT NULL AUTO_INCREMENT,
  `id_municipio` int(11) NOT NULL,
  `nombre_parroquia` varchar(100) NOT NULL,
  PRIMARY KEY (`id_parroquia`),
  KEY `id_municipio` (`id_municipio`),
  CONSTRAINT `parroquias_ibfk_1` FOREIGN KEY (`id_municipio`) REFERENCES `municipios` (`id_municipio`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Estructura de tabla para la tabla `bitacora`
CREATE TABLE `bitacora` (
  `id_bitacora` int(11) NOT NULL AUTO_INCREMENT,
  `cedula_usuario` varchar(12) NOT NULL,
  `accion` enum('Registro','Edicion','Consulta') NOT NULL,
  `tabla_afectada` varchar(64) NOT NULL,
  `registro_afectado` varchar(64) NOT NULL,
  `detalle` text NOT NULL,
  `fecha_accion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_bitacora`),
  KEY `cedula_usuario` (`cedula_usuario`),
  CONSTRAINT `bitacora_ibfk_1` FOREIGN KEY (`cedula_usuario`) REFERENCES `usuarios` (`cedula`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=534 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLAS NIVEL 2 (Dependen de parroquias)
-- ============================================

-- Estructura de tabla para la tabla `sectores`
CREATE TABLE `sectores` (
  `id_sector` int(11) NOT NULL AUTO_INCREMENT,
  `id_parroquia` int(11) NOT NULL,
  `nombre_sector` varchar(100) NOT NULL,
  PRIMARY KEY (`id_sector`),
  KEY `id_parroquia` (`id_parroquia`),
  CONSTRAINT `sectores_ibfk_1` FOREIGN KEY (`id_parroquia`) REFERENCES `parroquias` (`id_parroquia`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLAS NIVEL 3 (Dependen de representantes)
-- ============================================

-- Estructura de tabla para la tabla `personas_naturales`
CREATE TABLE `personas_naturales` (
  `cedula` varchar(12) NOT NULL,
  `primer_nombre` varchar(50) NOT NULL,
  `segundo_nombre` varchar(50) DEFAULT NULL,
  `primer_apellido` varchar(50) NOT NULL,
  `segundo_apellido` varchar(50) DEFAULT NULL,
  `sexo` enum('M','F') NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `direccion_habitacion` text NOT NULL,
  `estado_civil` enum('Soltero','Casado','Viudo','Divorciado','Concubinato') NOT NULL,
  `numero_hijos` int(11) NOT NULL DEFAULT 0,
  `grado_instruccion` enum('Sin_nivel','Primaria','Secundaria','Tecnico','Universitario','Postgrado','Otro') NOT NULL,
  `sabe_leer` enum('Si','No') NOT NULL DEFAULT 'Si',
  `posee_ayuda_economica` enum('Si','No') NOT NULL DEFAULT 'No',
  `trabaja_actualmente` enum('Si','No') NOT NULL DEFAULT 'Si',
  `pertenece_comuna` enum('Si','No') NOT NULL DEFAULT 'No',
  `enfermedades` text DEFAULT NULL,
  `id_representante` int(15) DEFAULT NULL,
  `tipo_representacion` enum('Representante_Legal','Apoderado') DEFAULT NULL,
  `activo` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cedula`),
  KEY `idx_personas_naturales_representante` (`id_representante`),
  CONSTRAINT `personas_naturales_ibfk_1` FOREIGN KEY (`id_representante`) REFERENCES `representantes` (`id_representante`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Estructura de tabla para la tabla `personas_juridicas`
CREATE TABLE `personas_juridicas` (
  `rif` varchar(20) NOT NULL,
  `razon_social` varchar(255) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `direccion_habitacion` text NOT NULL,
  `estado_civil` enum('Soltero','Casado','Viudo','Divorciado','Concubinato') NOT NULL,
  `numero_hijos` int(11) NOT NULL DEFAULT 0,
  `grado_instruccion` enum('Sin_nivel','Primaria','Secundaria','Tecnico','Universitario','Postgrado','Otro') NOT NULL,
  `sabe_leer` enum('Si','No') NOT NULL DEFAULT 'Si',
  `posee_ayuda_economica` enum('Si','No') NOT NULL DEFAULT 'No',
  `trabaja_actualmente` enum('Si','No') NOT NULL DEFAULT 'Si',
  `pertenece_comuna` enum('Si','No') NOT NULL DEFAULT 'No',
  `enfermedades` text DEFAULT NULL,
  `id_representante` int(15) DEFAULT NULL,
  `tipo_representacion` enum('Representante_Legal','Apoderado') DEFAULT NULL,
  `activo` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rif`),
  KEY `idx_personas_juridicas_representante` (`id_representante`),
  CONSTRAINT `personas_juridicas_ibfk_1` FOREIGN KEY (`id_representante`) REFERENCES `representantes` (`id_representante`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Estructura de tabla para la tabla `colectivos`
CREATE TABLE `colectivos` (
  `rif_o_ci_referente` varchar(20) NOT NULL,
  `nombre_colectivo` varchar(255) NOT NULL,
  `numero_integrantes` int(11) NOT NULL DEFAULT 0,
  `telefono` varchar(20) NOT NULL,
  `direccion_habitacion` text NOT NULL,
  `activo` smallint(6) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rif_o_ci_referente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Estructura de tabla para la tabla `predios`
CREATE TABLE `predios` (
  `id_predio` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_predio` varchar(255) NOT NULL,
  `id_municipio` int(11) NOT NULL,
  `id_parroquia` int(11) NOT NULL,
  `id_sector` int(11) NOT NULL,
  `superficie_ha` varchar(1000) NOT NULL,
  `linderos` text NOT NULL,
  `direccion` text NOT NULL,
  `lindero_norte` text DEFAULT NULL,
  `lindero_sur` text DEFAULT NULL,
  `lindero_este` text DEFAULT NULL,
  `lindero_oeste` text DEFAULT NULL,
  PRIMARY KEY (`id_predio`),
  KEY `idx_predios_municipio` (`id_municipio`),
  KEY `id_parroquia` (`id_parroquia`),
  KEY `id_sector` (`id_sector`),
  CONSTRAINT `predios_ibfk_1` FOREIGN KEY (`id_municipio`) REFERENCES `municipios` (`id_municipio`) ON UPDATE CASCADE,
  CONSTRAINT `predios_ibfk_2` FOREIGN KEY (`id_parroquia`) REFERENCES `parroquias` (`id_parroquia`) ON UPDATE CASCADE,
  CONSTRAINT `predios_ibfk_3` FOREIGN KEY (`id_sector`) REFERENCES `sectores` (`id_sector`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLAS NIVEL 4 (Dependen de colectivos)
-- ============================================

-- Estructura de tabla para la tabla `colectivo_integrantes`
CREATE TABLE `colectivo_integrantes` (
  `id_integrante` int(11) NOT NULL AUTO_INCREMENT,
  `rif_o_ci_colectivo` varchar(20) NOT NULL,
  `cedula` varchar(12) NOT NULL,
  `primer_nombre` varchar(50) NOT NULL,
  `segundo_nombre` varchar(50) DEFAULT NULL,
  `primer_apellido` varchar(50) NOT NULL,
  `segundo_apellido` varchar(50) DEFAULT NULL,
  `sexo` enum('M','F') NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `direccion_habitacion` text NOT NULL,
  `estado_civil` enum('Soltero','Casado','Viudo','Divorciado','Concubinato') NOT NULL,
  `numero_hijos` int(11) NOT NULL DEFAULT 0,
  `grado_instruccion` enum('Sin_nivel','Primaria','Secundaria','Tecnico','Universitario','Postgrado','Otro') NOT NULL,
  `sabe_leer` enum('Si','No') NOT NULL DEFAULT 'Si',
  `posee_ayuda_economica` enum('Si','No') NOT NULL DEFAULT 'No',
  `trabaja_actualmente` enum('Si','No') NOT NULL DEFAULT 'Si',
  `pertenece_comuna` enum('Si','No') NOT NULL DEFAULT 'No',
  `enfermedades` text DEFAULT NULL,
  `es_referente` smallint(6) NOT NULL DEFAULT 0,
  `cargo_en_colectivo` varchar(100) NOT NULL COMMENT 'Presidente, Tesorero, etc.',
  `fecha_ingreso` date NOT NULL,
  `activo` smallint(6) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_integrante`),
  KEY `idx_colectivo_integrantes_colectivo` (`rif_o_ci_colectivo`),
  KEY `idx_colectivo_integrantes_cedula` (`cedula`),
  KEY `idx_colectivo_integrantes_referente` (`es_referente`),
  CONSTRAINT `colectivo_integrantes_ibfk_1` FOREIGN KEY (`rif_o_ci_colectivo`) REFERENCES `colectivos` (`rif_o_ci_referente`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLAS NIVEL 5 (Dependen de múltiples tablas)
-- ============================================

-- Estructura de tabla para la tabla `solicitudes`
CREATE TABLE `solicitudes` (
  `id_solicitud` int(11) NOT NULL AUTO_INCREMENT,
  `numero_solicitud` varchar(30) NOT NULL,
  `fecha_solicitud` date NOT NULL,
  `tipo_solicitante` enum('N','J','C') NOT NULL COMMENT 'N=Natural, J=Juridico, C=Colectivo',
  `cedula_solicitante_n` varchar(12) DEFAULT NULL COMMENT 'CI si es Persona Natural',
  `rif_solicitante_j` varchar(20) DEFAULT NULL COMMENT 'RIF si es Persona Juridica',
  `rif_ci_solicitante_c` varchar(20) DEFAULT NULL COMMENT 'RIF/CI si es Colectivo',
  `id_procedimiento` int(11) NOT NULL,
  `id_predio` int(11) NOT NULL,
  `rubros_a_producir` text NOT NULL COMMENT 'Rubros que planea producir el solicitante',
  `estatus` enum('Por_Inspeccion','En_Ejecucion','En_INTI_Central','Aprobado') NOT NULL DEFAULT 'Por_Inspeccion',
  `fecha_cambio_estatus` datetime NOT NULL DEFAULT current_timestamp(),
  `observaciones` text DEFAULT NULL,
  `creado_por` varchar(12) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_solicitud`),
  UNIQUE KEY `numero_solicitud` (`numero_solicitud`),
  KEY `idx_solicitudes_estatus` (`estatus`),
  KEY `idx_solicitudes_fecha` (`fecha_solicitud`),
  KEY `idx_solicitudes_numero` (`numero_solicitud`),
  KEY `idx_solicitudes_tipo` (`tipo_solicitante`),
  KEY `cedula_solicitante_n` (`cedula_solicitante_n`),
  KEY `rif_solicitante_j` (`rif_solicitante_j`),
  KEY `rif_ci_solicitante_c` (`rif_ci_solicitante_c`),
  KEY `id_procedimiento` (`id_procedimiento`),
  KEY `id_predio` (`id_predio`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `solicitudes_ibfk_1` FOREIGN KEY (`cedula_solicitante_n`) REFERENCES `personas_naturales` (`cedula`) ON UPDATE CASCADE,
  CONSTRAINT `solicitudes_ibfk_2` FOREIGN KEY (`rif_solicitante_j`) REFERENCES `personas_juridicas` (`rif`) ON UPDATE CASCADE,
  CONSTRAINT `solicitudes_ibfk_3` FOREIGN KEY (`rif_ci_solicitante_c`) REFERENCES `colectivos` (`rif_o_ci_referente`) ON UPDATE CASCADE,
  CONSTRAINT `solicitudes_ibfk_4` FOREIGN KEY (`id_procedimiento`) REFERENCES `tipo_procedimiento` (`id_procedimiento`) ON UPDATE CASCADE,
  CONSTRAINT `solicitudes_ibfk_5` FOREIGN KEY (`id_predio`) REFERENCES `predios` (`id_predio`) ON UPDATE CASCADE,
  CONSTRAINT `solicitudes_ibfk_6` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`cedula`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLAS NIVEL 6 (Dependen de solicitudes)
-- ============================================

-- Estructura de tabla para la tabla `solicitud_historial_estatus`
CREATE TABLE `solicitud_historial_estatus` (
  `id_historial` int(11) NOT NULL AUTO_INCREMENT,
  `id_solicitud` int(11) NOT NULL,
  `estatus_anterior` varchar(50) NOT NULL COMMENT 'Estatus anterior (texto libre)',
  `estatus_nuevo` varchar(50) NOT NULL COMMENT 'Estatus nuevo (texto libre)',
  `fecha_cambio` datetime NOT NULL DEFAULT current_timestamp(),
  `cedula_usuario` varchar(12) NOT NULL,
  `comentario` text DEFAULT NULL,
  PRIMARY KEY (`id_historial`),
  KEY `id_solicitud` (`id_solicitud`),
  KEY `cedula_usuario` (`cedula_usuario`),
  CONSTRAINT `solicitud_historial_estatus_ibfk_1` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON UPDATE CASCADE,
  CONSTRAINT `solicitud_historial_estatus_ibfk_2` FOREIGN KEY (`cedula_usuario`) REFERENCES `usuarios` (`cedula`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;


