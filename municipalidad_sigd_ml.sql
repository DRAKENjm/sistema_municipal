-- ============================================================
-- phpMyAdmin SQL Dump
-- ============================================================
-- Sistema: Municipalidad Provincial de Yauli
-- Gestión Documental y Selección de Personal con Machine Learning
-- Base de datos: municipalidad_sigd_ml
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30


-- NOTA: Este script eliminará y recreará todas las tablas
--       para asegurar una instalación limpia.
-- ============================================================
-- USUARIOS DE PRUEBA INCLUIDOS:
-- ============================================================
-- admin        | Admin123@  | Administrador del sistema
-- mesapartes   | 12345678   | Mesa de Partes
-- resp.area    | 12345678   | Responsable de Área
-- rrhh         | 12345678   | Recursos Humanos
-- JORGE        | 12345678   | Jefe General / Director
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================
-- BASE DE DATOS
-- ============================================================

-- Eliminar la base de datos si existe (solo para instalación limpia)
-- ADVERTENCIA: Descomentar la siguiente línea solo si desea borrar toda la BD
-- DROP DATABASE IF EXISTS `municipalidad_sigd_ml`;

CREATE DATABASE IF NOT EXISTS `municipalidad_sigd_ml`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `municipalidad_sigd_ml`;

-- ============================================================
-- TABLA: roles
-- Roles del sistema: Administrador, Mesa de Partes, Resp. de Área, RRHH
-- ============================================================
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id_rol`      INT(11)      NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(50)  NOT NULL,
  `descripcion` VARCHAR(150) DEFAULT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `uq_rol_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: areas
-- Áreas / unidades orgánicas de la municipalidad
-- ============================================================
DROP TABLE IF EXISTS `areas`;
CREATE TABLE `areas` (
  `id_area`  INT(11)     NOT NULL AUTO_INCREMENT,
  `nombre`   VARCHAR(100) NOT NULL,
  `codigo`   VARCHAR(20)  DEFAULT NULL COMMENT 'Código interno del área',
  `activo`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_area`),
  UNIQUE KEY `uq_area_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: usuarios
-- Usuarios del sistema (personal de la municipalidad)
-- ============================================================
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id_usuario`       INT(11)      NOT NULL AUTO_INCREMENT,
  `id_rol`           INT(11)      NOT NULL,
  `id_area`          INT(11)      DEFAULT NULL COMMENT 'Área a la que pertenece el usuario',
  `dni`              CHAR(8)      NOT NULL,
  `apellido_paterno` VARCHAR(50)  NOT NULL,
  `apellido_materno` VARCHAR(50)  NOT NULL,
  `nombres`          VARCHAR(80)  NOT NULL,
  `correo`           VARCHAR(100) DEFAULT NULL,
  `telefono`         VARCHAR(20)  DEFAULT NULL,
  `usuario`          VARCHAR(50)  NOT NULL,
  `password_hash`    VARCHAR(255) NOT NULL,
  `estado`           TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=activo, 0=inactivo',
  `fecha_registro`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_usuario_dni`     (`dni`),
  UNIQUE KEY `uq_usuario_usuario` (`usuario`),
  KEY `fk_usuarios_rol`  (`id_rol`),
  KEY `fk_usuarios_area` (`id_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: tipos_documento
-- Catálogo de tipos de documento (Oficio, Resolución, Memo, etc.)
-- ============================================================
DROP TABLE IF EXISTS `tipos_documento`;
CREATE TABLE `tipos_documento` (
  `id_tipo_documento` INT(11)     NOT NULL AUTO_INCREMENT,
  `nombre`            VARCHAR(80) NOT NULL,
  `abreviatura`       VARCHAR(10) DEFAULT NULL COMMENT 'Ej: OF, RES, MEM',
  `activo`            TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_tipo_documento`),
  UNIQUE KEY `uq_tipo_doc_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: documentos
-- Documentos administrativos registrados en el sistema
-- ============================================================
DROP TABLE IF EXISTS `documentos`;
CREATE TABLE `documentos` (
  `id_documento`      INT(11)      NOT NULL AUTO_INCREMENT,
  `id_tipo_documento` INT(11)      NOT NULL,
  `numero_documento`  VARCHAR(40)  DEFAULT NULL COMMENT 'Número oficial del documento (ej: OF-001-2026)',
  `asunto`            VARCHAR(200) NOT NULL,
  `descripcion`       TEXT         DEFAULT NULL,
  `fecha_documento`   DATE         DEFAULT NULL,
  `id_area_origen`    INT(11)      DEFAULT NULL,
  `id_area_destino`   INT(11)      DEFAULT NULL,
  `ruta_archivo`      VARCHAR(300) DEFAULT NULL COMMENT 'Ruta del archivo físico adjunto',
  `nombre_archivo`    VARCHAR(255) DEFAULT NULL COMMENT 'Nombre original del archivo subido',
  `estado`            ENUM('REGISTRADO','EN_TRAMITE','DERIVADO','ARCHIVADO','ANULADO')
                                   NOT NULL DEFAULT 'REGISTRADO',
  `prioridad`         ENUM('BAJA','NORMAL','ALTA','URGENTE')
                                   NOT NULL DEFAULT 'NORMAL' COMMENT 'Nivel de prioridad del documento',
  `observaciones`     TEXT         DEFAULT NULL COMMENT 'Observaciones adicionales del trámite',
  `id_usuario`        INT(11)      DEFAULT NULL COMMENT 'Usuario que registró el documento',
  `fecha_registro`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `fecha_modificacion` TIMESTAMP   NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP() COMMENT 'Última modificación',
  PRIMARY KEY (`id_documento`),
  KEY `fk_doc_tipo`         (`id_tipo_documento`),
  KEY `fk_doc_area_origen`  (`id_area_origen`),
  KEY `fk_doc_area_destino` (`id_area_destino`),
  KEY `fk_doc_usuario`      (`id_usuario`),
  KEY `idx_doc_estado`      (`estado`),
  KEY `idx_doc_prioridad`   (`prioridad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: expedientes
-- Expedientes que agrupan varios documentos
-- ============================================================
DROP TABLE IF EXISTS `expedientes`;
CREATE TABLE `expedientes` (
  `id_expediente`      INT(11)      NOT NULL AUTO_INCREMENT,
  `numero_expediente`  VARCHAR(30)  NOT NULL,
  `asunto`             VARCHAR(200) DEFAULT NULL,
  `descripcion`        TEXT         DEFAULT NULL COMMENT 'Descripción detallada del expediente',
  `tipo_expediente`    VARCHAR(50)  DEFAULT NULL COMMENT 'Tipo: Administrativo, Judicial, Contencioso, etc.',
  `id_area`            INT(11)      DEFAULT NULL COMMENT 'Área responsable del expediente',
  `id_usuario`         INT(11)      DEFAULT NULL COMMENT 'Usuario que creó el expediente',
  `estado`             ENUM('ABIERTO','EN_PROCESO','CERRADO','ARCHIVADO')
                                    NOT NULL DEFAULT 'ABIERTO',
  `observaciones`      TEXT         DEFAULT NULL COMMENT 'Observaciones generales',
  `fecha_creacion`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `fecha_cierre`       TIMESTAMP    NULL DEFAULT NULL COMMENT 'Fecha de cierre del expediente',
  `fecha_modificacion` TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id_expediente`),
  UNIQUE KEY `uq_numero_expediente` (`numero_expediente`),
  KEY `fk_exp_area`    (`id_area`),
  KEY `fk_exp_usuario` (`id_usuario`),
  KEY `idx_exp_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: expediente_documento
-- Relación N:M entre expedientes y documentos
-- ============================================================
DROP TABLE IF EXISTS `expediente_documento`;
CREATE TABLE `expediente_documento` (
  `id`             INT(11)   NOT NULL AUTO_INCREMENT,
  `id_expediente`  INT(11)   NOT NULL,
  `id_documento`   INT(11)   NOT NULL,
  `orden`          INT(11)   DEFAULT 1 COMMENT 'Orden del documento dentro del expediente',
  `fecha_vinculo`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exp_doc` (`id_expediente`, `id_documento`),
  KEY `fk_expdoc_expediente` (`id_expediente`),
  KEY `fk_expdoc_documento`  (`id_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: convocatorias
-- Convocatorias de personal publicadas por la municipalidad
-- ============================================================
DROP TABLE IF EXISTS `convocatorias`;
CREATE TABLE `convocatorias` (
  `id_convocatoria`     INT(11)      NOT NULL AUTO_INCREMENT,
  `id_area`             INT(11)      DEFAULT NULL COMMENT 'Área que requiere el personal',
  `codigo_convocatoria` VARCHAR(30)  DEFAULT NULL COMMENT 'Código único de la convocatoria (ej: CONV-001-2026)',
  `titulo`              VARCHAR(200) NOT NULL,
  `descripcion`         TEXT         DEFAULT NULL,
  `requisitos`          TEXT         DEFAULT NULL COMMENT 'Requisitos generales en texto libre',
  `palabras_clave`      TEXT         DEFAULT NULL COMMENT 'Keywords para el modelo ML (separadas por coma)',
  `perfil_requerido`    TEXT         DEFAULT NULL COMMENT 'Descripción estructurada del perfil, usada por ML',
  `vacantes`            INT(11)      DEFAULT 1 COMMENT 'Número de vacantes disponibles',
  `tipo_contrato`       VARCHAR(50)  DEFAULT NULL COMMENT 'CAS, Plazo fijo, Indefinido, etc.',
  `salario_referencial` DECIMAL(10,2) DEFAULT NULL,
  `fecha_inicio`        DATE         DEFAULT NULL,
  `fecha_fin`           DATE         DEFAULT NULL,
  `estado`              ENUM('BORRADOR','ACTIVA','CERRADA','CANCELADA')
                                     NOT NULL DEFAULT 'ACTIVA',
  `publicada`           TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=visible en portal público, 0=oculta',
  `id_usuario`          INT(11)      DEFAULT NULL COMMENT 'Usuario que creó la convocatoria',
  `fecha_registro`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `fecha_modificacion`  TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id_convocatoria`),
  UNIQUE KEY `uq_codigo_convocatoria` (`codigo_convocatoria`),
  KEY `fk_conv_area`    (`id_area`),
  KEY `fk_conv_usuario` (`id_usuario`),
  KEY `idx_conv_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: postulantes
-- Personas externas que se registran y postulan a convocatorias.
-- Tienen su propio acceso (usuario/contraseña) para ver resultados.
-- ============================================================
DROP TABLE IF EXISTS `postulantes`;
CREATE TABLE `postulantes` (
  `id_postulante`    INT(11)      NOT NULL AUTO_INCREMENT,
  `dni`              CHAR(8)      NOT NULL,
  `apellido_paterno` VARCHAR(50)  NOT NULL,
  `apellido_materno` VARCHAR(50)  NOT NULL,
  `nombres`          VARCHAR(80)  NOT NULL,
  `correo`           VARCHAR(100) NOT NULL COMMENT 'Correo obligatorio, se usa como contacto',
  `telefono`         VARCHAR(20)  DEFAULT NULL,
  `celular`          VARCHAR(20)  DEFAULT NULL COMMENT 'Número de celular/WhatsApp',
  `direccion`        VARCHAR(200) DEFAULT NULL,
  `distrito`         VARCHAR(100) DEFAULT NULL COMMENT 'Distrito de residencia',
  `provincia`        VARCHAR(100) DEFAULT NULL COMMENT 'Provincia de residencia',
  `departamento`     VARCHAR(100) DEFAULT NULL COMMENT 'Departamento de residencia',
  `fecha_nacimiento` DATE         DEFAULT NULL,
  `genero`           ENUM('M','F','OTRO') DEFAULT NULL COMMENT 'Género del postulante',
  `profesion`        VARCHAR(100) DEFAULT NULL COMMENT 'Profesión u ocupación principal',
  `usuario`          VARCHAR(50)  NOT NULL COMMENT 'Nombre de usuario para el portal del postulante',
  `password_hash`    VARCHAR(255) NOT NULL COMMENT 'Contraseña hasheada (bcrypt)',
  `estado`           TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=activo, 0=bloqueado',
  `perfil_completo`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=perfil completado, 0=incompleto',
  `foto_perfil`      VARCHAR(300) DEFAULT NULL COMMENT 'Ruta de la foto de perfil (opcional)',
  `fecha_registro`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `fecha_actualizacion` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id_postulante`),
  UNIQUE KEY `uq_postulante_dni`     (`dni`),
  UNIQUE KEY `uq_postulante_usuario` (`usuario`),
  UNIQUE KEY `uq_postulante_correo`  (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: curriculums
-- Archivos de CV subidos por los postulantes
-- ACTUALIZADO: Agregadas columnas para actualización de CV y estados
-- ============================================================
DROP TABLE IF EXISTS `curriculums`;
CREATE TABLE `curriculums` (
  `id_curriculum`      INT(11)      NOT NULL AUTO_INCREMENT,
  `id_postulante`      INT(11)      NOT NULL,
  `id_convocatoria`    INT(11)      NOT NULL,
  `ruta_archivo`       VARCHAR(300) NOT NULL COMMENT 'Ruta interna donde se almacenó el archivo',
  `nombre_archivo`     VARCHAR(255) DEFAULT NULL COMMENT 'Nombre original del archivo (PDF, DOCX)',
  `texto_extraido`     LONGTEXT     DEFAULT NULL COMMENT 'Texto plano extraído por Python para ML',
  `procesado`          TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '0=pendiente de análisis ML, 1=procesado',
  `actualizado`        TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=postulante actualizó su CV una vez',
  `fecha_actualizacion` TIMESTAMP   NULL DEFAULT NULL COMMENT 'Fecha en que se actualizó el CV',
  `estado_revision`    ENUM('PENDIENTE','EN_REVISION','REVISADO') 
                                    NOT NULL DEFAULT 'PENDIENTE' COMMENT 'Estado del proceso de revisión',
  `fecha_carga`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id_curriculum`),
  KEY `fk_cv_postulante`   (`id_postulante`),
  KEY `fk_cv_convocatoria` (`id_convocatoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: evaluaciones_ml
-- Resultados del análisis de ML sobre cada currículo
-- ACTUALIZADO: Agregadas columnas para verificación de RRHH
-- ============================================================
DROP TABLE IF EXISTS `evaluaciones_ml`;
CREATE TABLE `evaluaciones_ml` (
  `id_evaluacion`          INT(11)       NOT NULL AUTO_INCREMENT,
  `id_curriculum`          INT(11)       NOT NULL COMMENT 'CV analizado',
  `id_postulante`          INT(11)       NOT NULL,
  `id_convocatoria`        INT(11)       NOT NULL,
  `puntaje`                DECIMAL(5,2)  NOT NULL DEFAULT 0.00 COMMENT 'Puntaje total (0-100)',
  `porcentaje_coincidencia` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '% de coincidencia con perfil requerido',
  `ranking`                INT(11)       DEFAULT NULL COMMENT 'Posición en el ranking de la convocatoria',
  `modelo_version`         VARCHAR(20)   DEFAULT NULL COMMENT 'Versión del modelo ML usado (ej: v1.0)',
  `detalles_json`          JSON          DEFAULT NULL COMMENT 'Desglose detallado del análisis en formato JSON',
  `observaciones`          TEXT          DEFAULT NULL,
  `revisado_rrhh`          TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 si RRHH revisó el resultado',
  `verificado`             TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 si RRHH verificó y decidió sobre el CV',
  `resultado_verificacion` ENUM('ACEPTADO','RECHAZADO','EN_ESPERA') 
                                         DEFAULT NULL COMMENT 'Decisión final de RRHH',
  `comentario_verificacion` TEXT         DEFAULT NULL COMMENT 'Comentario de RRHH sobre la decisión',
  `fecha_verificacion`     TIMESTAMP     NULL DEFAULT NULL COMMENT 'Fecha de verificación por RRHH',
  `verificado_por`         INT(11)       DEFAULT NULL COMMENT 'ID del usuario RRHH que verificó',
  `fecha_evaluacion`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id_evaluacion`),
  UNIQUE KEY `uq_eval_curriculum` (`id_curriculum`) COMMENT 'Un CV solo se evalúa una vez (se actualiza)',
  KEY `fk_eval_postulante`   (`id_postulante`),
  KEY `fk_eval_convocatoria` (`id_convocatoria`),
  KEY `fk_eval_verificado_por` (`verificado_por`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: bitacora
-- Registro de auditoría de todas las acciones del sistema
-- ============================================================
DROP TABLE IF EXISTS `bitacora`;
CREATE TABLE `bitacora` (
  `id_bitacora`    INT(11)      NOT NULL AUTO_INCREMENT,
  `id_usuario`     INT(11)      DEFAULT NULL,
  `accion`         VARCHAR(100) NOT NULL,
  `tabla_afectada` VARCHAR(60)  DEFAULT NULL,
  `id_registro`    INT(11)      DEFAULT NULL COMMENT 'ID del registro afectado en la tabla',
  `descripcion`    TEXT         DEFAULT NULL,
  `ip_origen`      VARCHAR(45)  DEFAULT NULL COMMENT 'IP del cliente (soporta IPv6)',
  `fecha`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id_bitacora`),
  KEY `fk_bitacora_usuario` (`id_usuario`),
  KEY `idx_bitacora_fecha`  (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- FOREIGN KEYS
-- ============================================================

ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_rol`  FOREIGN KEY (`id_rol`)  REFERENCES `roles`  (`id_rol`),
  ADD CONSTRAINT `fk_usuarios_area` FOREIGN KEY (`id_area`) REFERENCES `areas`  (`id_area`);

ALTER TABLE `documentos`
  ADD CONSTRAINT `fk_doc_tipo`         FOREIGN KEY (`id_tipo_documento`) REFERENCES `tipos_documento` (`id_tipo_documento`),
  ADD CONSTRAINT `fk_doc_area_origen`  FOREIGN KEY (`id_area_origen`)    REFERENCES `areas`           (`id_area`),
  ADD CONSTRAINT `fk_doc_area_destino` FOREIGN KEY (`id_area_destino`)   REFERENCES `areas`           (`id_area`),
  ADD CONSTRAINT `fk_doc_usuario`      FOREIGN KEY (`id_usuario`)        REFERENCES `usuarios`        (`id_usuario`);

ALTER TABLE `expedientes`
  ADD CONSTRAINT `fk_exp_area`    FOREIGN KEY (`id_area`)    REFERENCES `areas`    (`id_area`),
  ADD CONSTRAINT `fk_exp_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

ALTER TABLE `expediente_documento`
  ADD CONSTRAINT `fk_expdoc_expediente` FOREIGN KEY (`id_expediente`) REFERENCES `expedientes` (`id_expediente`),
  ADD CONSTRAINT `fk_expdoc_documento`  FOREIGN KEY (`id_documento`)  REFERENCES `documentos`  (`id_documento`);

ALTER TABLE `convocatorias`
  ADD CONSTRAINT `fk_conv_area`    FOREIGN KEY (`id_area`)    REFERENCES `areas`    (`id_area`),
  ADD CONSTRAINT `fk_conv_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

ALTER TABLE `curriculums`
  ADD CONSTRAINT `fk_cv_postulante`   FOREIGN KEY (`id_postulante`)  REFERENCES `postulantes`   (`id_postulante`),
  ADD CONSTRAINT `fk_cv_convocatoria` FOREIGN KEY (`id_convocatoria`) REFERENCES `convocatorias` (`id_convocatoria`);

ALTER TABLE `evaluaciones_ml`
  ADD CONSTRAINT `fk_eval_curriculum`    FOREIGN KEY (`id_curriculum`)    REFERENCES `curriculums`   (`id_curriculum`),
  ADD CONSTRAINT `fk_eval_postulante`    FOREIGN KEY (`id_postulante`)    REFERENCES `postulantes`   (`id_postulante`),
  ADD CONSTRAINT `fk_eval_convocatoria`  FOREIGN KEY (`id_convocatoria`)  REFERENCES `convocatorias` (`id_convocatoria`),
  ADD CONSTRAINT `fk_eval_verificado_por` FOREIGN KEY (`verificado_por`)  REFERENCES `usuarios`      (`id_usuario`);

ALTER TABLE `bitacora`
  ADD CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

-- ============================================================
-- DATOS SEMILLA
-- ============================================================

-- ============================================================
-- ROLES DEL SISTEMA (5 roles operativos reales)
-- ============================================================
-- ID 1: Administrador  — Control técnico total (bitácora incluida). NO opera documentos ni CVs.
-- ID 2: Mesa de Partes — Registra, clasifica, deriva documentos y expedientes.
-- ID 3: Resp. de Área  — Atiende documentos de su área, genera informes.
-- ID 4: RRHH           — Convocatorias, evaluación ML, selección de personal.
-- ID 5: Jefe General   — Director municipalidad, acceso operativo completo EXCEPTO bitácora.
-- ============================================================
INSERT INTO `roles` (`nombre`, `descripcion`) VALUES
('Administrador',    'Control técnico total: usuarios, áreas, catálogos y bitácora. No opera documentos ni CVs.'),
('Mesa de Partes',   'Recepción documental: registra, clasifica, crea expedientes y deriva documentos a áreas.'),
('Resp. de Área',    'Atiende documentos asignados a su área, cambia estados y gestiona sus expedientes.'),
('RRHH',             'Recursos Humanos: crea convocatorias, valida resultados ML y decide la selección final.'),
('Jefe General',     'Director de la municipalidad: acceso operativo completo excepto bitácora técnica.');

-- ============================================================
-- ÁREAS / UNIDADES ORGÁNICAS
-- Estructura basada en la Ley Orgánica de Municipalidades (Ley 27972)
-- Ordenadas por nivel jerárquico
-- ============================================================

-- ── Órganos de Alta Dirección ──
INSERT INTO `areas` (`nombre`, `codigo`) VALUES
('Alcaldía',                                        'ALC'),
('Gerencia Municipal',                              'GM');

-- ── Órganos de Control y Defensa ──
INSERT INTO `areas` (`nombre`, `codigo`) VALUES
('Órgano de Control Institucional',                 'OCI'),
('Procuraduria Pública Municipal',                  'PPM');

-- ── Órganos de Asesoramiento ──
INSERT INTO `areas` (`nombre`, `codigo`) VALUES
('Gerencia de Asesoría Jurídica',                   'GAJ'),
('Gerencia de Planificación y Presupuesto',         'GPP');

-- ── Órganos de Apoyo ──
INSERT INTO `areas` (`nombre`, `codigo`) VALUES
('Secretaría General / Mesa de Partes',             'SG'),
('Gerencia de Administración y Finanzas',           'GAF'),
('Sub Gerencia de Recursos Humanos',                'SGRH'),
('Sub Gerencia de Logística y Patrimonio',          'SGLP'),
('Sub Gerencia de Contabilidad',                    'SGC'),
('Sub Gerencia de Tesorería',                       'SGT'),
('Sub Gerencia de Tecnologías de la Información',   'SGTI');

-- ── Órganos de Línea ──
INSERT INTO `areas` (`nombre`, `codigo`) VALUES
('Gerencia de Desarrollo Urbano',                   'GDU'),
('Sub Gerencia de Obras Públicas e Infraestructura','SGOPI'),
('Sub Gerencia de Catastro y Habilitaciones Urbanas','SGCHU'),
('Gerencia de Desarrollo Económico Local',          'GDEL'),
('Gerencia de Desarrollo Social',                   'GDS'),
('Sub Gerencia de Programas Sociales',              'SGPS'),
('Sub Gerencia de Educación, Cultura y Deportes',   'SGECD'),
('Gerencia de Servicios Públicos y Medio Ambiente', 'GSPMA'),
('Gerencia de Rentas y Administración Tributaria',  'GRAT'),
('Gerencia de Seguridad Ciudadana',                 'GSC');

-- Tipos de documento
INSERT INTO `tipos_documento` (`nombre`, `abreviatura`) VALUES
('Oficio',                  'OF'),
('Memorándum',              'MEM'),
('Resolución de Alcaldía',  'RA'),
('Resolución Gerencial',    'RG'),
('Informe',                 'INF'),
('Carta',                   'CAR'),
('Solicitud',               'SOL'),
('Proveído',                'PROV'),
('Decreto de Alcaldía',     'DA'),
('Contrato',                'CONT');

-- ============================================================
-- USUARIOS DE EJEMPLO
-- ============================================================
-- Contraseñas:
--   admin      → Admin123@
--   mesapartes → 12345678
--   resp.area  → 12345678
--   rrhh       → 12345678
--
-- Hash bcrypt 'Admin123@' : $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- Hash bcrypt '12345678'  : $2y$10$TKh8H1.PfQ0A0bz.b3uCsuuQ8qG0B2aFJv5rVYelLPVGLDxXNH3Re
-- ============================================================

-- Administrador del Sistema → área TI
INSERT INTO `usuarios`
  (`id_rol`, `id_area`, `dni`, `apellido_paterno`, `apellido_materno`,
   `nombres`, `correo`, `usuario`, `password_hash`, `estado`)
SELECT 1, id_area, '00000001', 'Admin', 'Sistema', 'Administrador',
       'admin@municipalidad.gob.pe', 'admin',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1
FROM areas WHERE codigo = 'SGTI';

-- Mesa de Partes → Secretaría General / Mesa de Partes
INSERT INTO `usuarios`
  (`id_rol`, `id_area`, `dni`, `apellido_paterno`, `apellido_materno`,
   `nombres`, `correo`, `usuario`, `password_hash`, `estado`)
SELECT 2, id_area, '10000002', 'Quispe', 'Mamani', 'Carmen Rosa',
       'mesapartes@municipalidad.gob.pe', 'mesapartes',
       '$2y$10$TKh8H1.PfQ0A0bz.b3uCsuuQ8qG0B2aFJv5rVYelLPVGLDxXNH3Re', 1
FROM areas WHERE codigo = 'SG';

-- Responsable de Área → Gerencia de Desarrollo Social
INSERT INTO `usuarios`
  (`id_rol`, `id_area`, `dni`, `apellido_paterno`, `apellido_materno`,
   `nombres`, `correo`, `usuario`, `password_hash`, `estado`)
SELECT 3, id_area, '10000003', 'Condori', 'Huanca', 'Luis Alberto',
       'gerencia.social@municipalidad.gob.pe', 'resp.area',
       '$2y$10$TKh8H1.PfQ0A0bz.b3uCsuuQ8qG0B2aFJv5rVYelLPVGLDxXNH3Re', 1
FROM areas WHERE codigo = 'GDS';

-- RRHH → Sub Gerencia de Recursos Humanos
INSERT INTO `usuarios`
  (`id_rol`, `id_area`, `dni`, `apellido_paterno`, `apellido_materno`,
   `nombres`, `correo`, `usuario`, `password_hash`, `estado`)
SELECT 4, id_area, '10000004', 'Flores', 'Ccopa', 'Ana María',
       'rrhh@municipalidad.gob.pe', 'rrhh',
       '$2y$10$TKh8H1.PfQ0A0bz.b3uCsuuQ8qG0B2aFJv5rVYelLPVGLDxXNH3Re', 1
FROM areas WHERE codigo = 'SGRH';

-- Jefe General → Gerencia Municipal (Director de la Municipalidad)
-- Contraseña: 12345678
INSERT INTO `usuarios`
  (`id_rol`, `id_area`, `dni`, `apellido_paterno`, `apellido_materno`,
   `nombres`, `correo`, `usuario`, `password_hash`, `estado`)
SELECT 5, id_area, '75143414', 'Santos Mendoza', '', 'Jorge',
       'director@municipalidad.gob.pe', 'JORGE',
       '$2y$10$UQcMwsKNtPQzbK.EUyjmkujwFRJoM.4gU.islV5DzndmSGsZ8TyPu', 1
FROM areas WHERE codigo = 'GM';

COMMIT;

-- Reactivar validación de Foreign Keys
SET FOREIGN_KEY_CHECKS = 1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
-- La base de datos está lista para usarse
-- Recuerde actualizar el archivo config/database.php con:
--   - Nombre de la base de datos: municipalidad_sigd_ml
--   - Usuario de MySQL (por defecto: root)
--   - Contraseña de MySQL (por defecto en XAMPP: vacía)
--   - Host: localhost
-- ============================================================
