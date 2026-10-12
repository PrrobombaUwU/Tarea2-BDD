DROP DATABASE IF EXISTS saludusm;

SET default_storage_engine = 'InnoDB';
SET FOREIGN_KEY_CHECKS = 1;

CREATE DATABASE saludusm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE saludusm;

CREATE TABLE prevision (
    Id_prevision    INT     AUTO_INCREMENT NOT NULL,
    nombre          VARCHAR(50)     NOT NULL,
    CONSTRAINT pk_prevision PRIMARY KEY (Id_prevision)
    
);


CREATE TABLE especialidad (
    id_especialidad  INT             AUTO_INCREMENT NOT NULL,
    nombre           VARCHAR(100)    NOT NULL,
    CONSTRAINT pk_especialidad PRIMARY KEY (id_especialidad)
);


CREATE TABLE estado_cita (
    Id_Estado       INT     AUTO_INCREMENT NOT NULL,
    estado          VARCHAR(50)     NOT NULL,
    CONSTRAINT pk_estado_cita PRIMARY KEY (Id_Estado)
);


CREATE TABLE diagnostico (
    codigo_d        VARCHAR(10)     NOT NULL,   
    descripcion     VARCHAR(255)    NOT NULL,
    CONSTRAINT pk_diagnostico PRIMARY KEY (codigo_d)
);


CREATE TABLE centro_medico (
    codigo_centro   VARCHAR(20)     NOT NULL,
    nombre          VARCHAR(150)    NOT NULL,
    comuna          VARCHAR(80)     NOT NULL,
    region          VARCHAR(80)     NOT NULL,
    CONSTRAINT pk_centro_medico PRIMARY KEY (codigo_centro)
);


CREATE TABLE medico (
    rut_medico              VARCHAR(12)     NOT NULL,  
    nombre_completo         VARCHAR(150)    NOT NULL,
    email_institucional     VARCHAR(120)    NOT NULL,
    contraseña				VARCHAR(20)		NOT NULL,
    CONSTRAINT pk_medico PRIMARY KEY (rut_medico)
);


CREATE TABLE paciente (
    rut_paciente        VARCHAR(12)     NOT NULL,
    nombre_completo     VARCHAR(150)    NOT NULL,
    fecha_nacimiento    DATE            NOT NULL,
    sexo                CHAR(1)         NOT NULL,
    telefono_contacto   VARCHAR(20)     NULL,
    comuna_residencia   VARCHAR(80)     NULL,
    id_prevision                INT     NULL,
    contraseña			VARCHAR(20) 	NOT NULL,
    
    CONSTRAINT pk_paciente PRIMARY KEY (rut_paciente),
    CONSTRAINT ck_paciente_sexo CHECK (sexo IN ('M', 'F', 'O')),
    
    CONSTRAINT fk_paciente_prevision
        FOREIGN KEY (id_prevision)
        REFERENCES prevision (Id_prevision)
        ON UPDATE CASCADE
        ON DELETE SET NULL
);


CREATE TABLE medico_especialidad (
    rut_medico          VARCHAR(12)     NOT NULL,
    id_especialidad     INT             NOT NULL,
    
    CONSTRAINT pk_medico_especialidad PRIMARY KEY (rut_medico, id_especialidad),
    
    CONSTRAINT fk_me_medico
        FOREIGN KEY (rut_medico)
        REFERENCES medico (rut_medico)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
        
    CONSTRAINT fk_me_especialidad
        FOREIGN KEY (id_especialidad)
        REFERENCES especialidad (id_especialidad)   
        ON UPDATE CASCADE
        ON DELETE CASCADE
);


CREATE TABLE medico_centro (
    rut_medico          VARCHAR(12)     NOT NULL,
    codigo_centro       VARCHAR(20)     NOT NULL,
    
    CONSTRAINT pk_medico_centro PRIMARY KEY (rut_medico, codigo_centro),
    
    CONSTRAINT fk_mc_medico
        FOREIGN KEY (rut_medico)                   
        REFERENCES medico (rut_medico)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
        
    CONSTRAINT fk_mc_centro
        FOREIGN KEY (codigo_centro)                
        REFERENCES centro_medico (codigo_centro)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);


CREATE TABLE cita (
    id_cita            INT             AUTO_INCREMENT NOT NULL,
    fecha_hora         DATETIME        NOT NULL,
    rut_paciente       VARCHAR(12)     NOT NULL,
    rut_medico         VARCHAR(12)     NULL,
    codigo_centro      VARCHAR(20)     NOT NULL,
    id_especialidad    INT             NOT NULL,
    id_estado          INT     		   NULL,
    
    CONSTRAINT pk_cita PRIMARY KEY (id_cita),
    
    CONSTRAINT fk_cita_paciente
        FOREIGN KEY (rut_paciente)
        REFERENCES paciente (rut_paciente)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
        
    CONSTRAINT fk_cita_medico
        FOREIGN KEY (rut_medico)
        REFERENCES medico (rut_medico)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
        
    CONSTRAINT fk_cita_centro
        FOREIGN KEY (codigo_centro)
        REFERENCES centro_medico (codigo_centro)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
        
    CONSTRAINT fk_cita_especialidad
        FOREIGN KEY (id_especialidad)
        REFERENCES especialidad (id_especialidad)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
        
    CONSTRAINT fk_cita_estado
        FOREIGN KEY (id_estado)
        REFERENCES estado_cita (Id_Estado)
        ON UPDATE CASCADE
        ON DELETE NO ACTION
);


CREATE TABLE atencion (
    id_atencion         INT              AUTO_INCREMENT NOT NULL,
    motivo_consulta     TEXT  			 NULL,
    obs_clinicas        TEXT  			 NULL,
    id_cita				INT	  			 NOT NULL,
    fecha 				DATE			 NOT NULL,
    codigo_centro 		VARCHAR(20)  	 NOT NULL,
    rut_paciente 		VARCHAR(12)		 NOT NULL,
    rut_medico			VARCHAR(12) 	 NOT NULL,
    
    CONSTRAINT pk_atencion PRIMARY KEY (id_atencion),
    
    CONSTRAINT fk_id_cita
		FOREIGN KEY (id_cita)
        REFERENCES cita (id_cita)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
        
	CONSTRAINT fk_codigo_centro
		FOREIGN KEY (codigo_centro)
        REFERENCES centro_medico (codigo_centro)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
        
	CONSTRAINT fk_rut_paciente
		FOREIGN KEY (rut_paciente)
        REFERENCES paciente (rut_paciente)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
        
	CONSTRAINT fk_rut_medico
		FOREIGN KEY (rut_medico)
        REFERENCES medico (rut_medico)
        ON UPDATE CASCADE
        ON DELETE CASCADE
        -- SUPUESTO
);



CREATE TABLE atencion_diagnostico (
    id_atencion      INT             NOT NULL,
    codigo_d         VARCHAR(10)     NOT NULL,
    
    CONSTRAINT pk_atencion_diagnostico PRIMARY KEY (id_atencion, codigo_d),
    
    CONSTRAINT fk_ad_atencion
        FOREIGN KEY (id_atencion)
        REFERENCES atencion (id_atencion)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
        
    CONSTRAINT fk_ad_diagnostico
        FOREIGN KEY (codigo_d)
        REFERENCES diagnostico (codigo_d)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);


CREATE TABLE receta (
    id_receta           INT             AUTO_INCREMENT NOT NULL,
    id_atencion         INT             NOT NULL,
    medicamento         VARCHAR(150)    NOT NULL,
    dosis               VARCHAR(100)    NULL,
    Dias_tratamiento    INT             NULL,
    
    CONSTRAINT pk_receta PRIMARY KEY (id_receta),
    
    CONSTRAINT fk_receta_atencion
        FOREIGN KEY (id_atencion)
        REFERENCES atencion (id_atencion)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE administrador (
	id_admin	INT					AUTO_INCREMENT NOT NULL,
    nombre		VARCHAR(50)			NOT NULL,
    contraseña	VARCHAR(20)			NOT NULL,
    email		VARCHAR(50)			NOT NULL,
    
	CONSTRAINT pk_admin PRIMARY KEY (id_admin)
);

DELIMITER //
CREATE PROCEDURE ingresarUsuario(IN rut VARCHAR(12), IN nombre VARCHAR(150), IN nacimiento DATE, IN sexo CHAR(1), IN telefono VARCHAR(20), IN comuna VARCHAR(80), IN prevision INT, IN contraseña VARCHAR(20) )
BEGIN
	INSERT INTO paciente(rut_paciente, nombre_completo, fecha_nacimiento, sexo, telefono_contacto, comuna_residencia, id_prevision, contraseña) VALUES(rut, nombre, nacimiento, sexo, telefono, comuna, prevision, contraseña);
END//
DELIMITER ;

DELIMITER //
CREATE FUNCTION porcentajeInasistencia(total_citas INT, total_inasistencias INT) RETURNS decimal(10,2)
    NO SQL
    DETERMINISTIC
BEGIN
	IF total_citas IS NULL OR total_citas = 0 THEN
        RETURN 0.00;
    END IF;

	RETURN ROUND((total_inasistencias / total_citas) * 100, 2);
END //

DELIMITER ;

DELIMITER //
CREATE 
    ALGORITHM = UNDEFINED 
    SQL SECURITY DEFINER
VIEW `saludusm`.`vista_citas_resumen` AS
    SELECT 
        `c`.`id_cita` AS `id_cita`,
        `c`.`fecha_hora` AS `fecha_hora`,
        `c`.`rut_paciente` AS `rut_paciente`,
        `p`.`nombre_completo` AS `paciente`,
        `p`.`id_prevision` AS `id_prevision`,
        `prev`.`nombre` AS `prevision`,
        `c`.`rut_medico` AS `rut_medico`,
        `m`.`nombre_completo` AS `medico`,
        `c`.`id_especialidad` AS `id_especialidad`,
        `esp`.`nombre` AS `especialidad`,
        `c`.`codigo_centro` AS `codigo_centro`,
        `cm`.`nombre` AS `centro_medico`,
        `cm`.`region` AS `region`,
        `c`.`id_estado` AS `id_estado`,
        `ec`.`estado` AS `estado`
    FROM
        ((((((`saludusm`.`cita` `c`
        JOIN `saludusm`.`paciente` `p` ON ((`c`.`rut_paciente` = `p`.`rut_paciente`)))
        LEFT JOIN `saludusm`.`medico` `m` ON ((`c`.`rut_medico` = `m`.`rut_medico`)))
        JOIN `saludusm`.`especialidad` `esp` ON ((`c`.`id_especialidad` = `esp`.`id_especialidad`)))
        JOIN `saludusm`.`centro_medico` `cm` ON ((`c`.`codigo_centro` = `cm`.`codigo_centro`)))
        LEFT JOIN `saludusm`.`estado_cita` `ec` ON ((`c`.`id_estado` = `ec`.`Id_Estado`)))
        LEFT JOIN `saludusm`.`prevision` `prev` ON ((`p`.`id_prevision` = `prev`.`Id_prevision`))); //
        
DELIMITER ;
        
DELIMITER //

CREATE TRIGGER trigger_validar_cita_tiempo_repetida
BEFORE INSERT ON cita
FOR EACH ROW
BEGIN
	    IF EXISTS (SELECT 1 FROM cita WHERE cita.rut_paciente = NEW.rut_paciente and cita.fecha_hora = NEW.fecha_hora AND cita.id_estado <> 5) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya tienes una cita agendada para ese dia y hora.';
    END IF;
END//

DELIMITER ;

USE saludusm;

-- centro_medico
-- prevision
-- estado cita
-- paciente
-- medico
-- medico_centro
-- especialidad
-- medico_especialidad
-- diagnostico
-- cita
-- atencion
-- receta
-- atencion_diagnostico

-- =========================================================
-- . 0. ADMINISTRADOR
-- =========================================================
INSERT INTO administrador (nombre,contraseña,email) VALUES
	('admin','12345678','admin@admin.cl');

-- =========================================================
-- 1. INSERCIÓN: CENTROS MÉDICOS
-- =========================================================
INSERT INTO centro_medico (codigo_centro, nombre, comuna, region) VALUES
    ('C001', 'Centro Médico Santiago Centro', 'Santiago', 'Región Metropolitana'),
    ('C002', 'Centro Médico Viña del Mar', 'Viña del Mar', 'Valparaíso'),
    ('C003', 'Centro Médico Concepción', 'Concepción', 'Biobío'),
    ('C004', 'Centro Médico La Serena', 'La Serena', 'Coquimbo');

-- =========================================================
-- 2. INSERCION: ESTADO CITA
-- =========================================================
INSERT INTO estado_cita (estado) VALUES
	('Reservada'), ('Confirmada'), ('Atendida'), ('No asistio'), ('Cancelada');

-- =========================================================
-- 3. INSERCIÓN: PREVISION
-- =========================================================
INSERT INTO prevision (nombre) VALUES
	('fonasa'), ('isapre'), ('particular');
    
-- =========================================================
-- 4. INSERCIÓN: PACIENTES
-- =========================================================
INSERT INTO paciente (rut_paciente, nombre_completo, fecha_nacimiento, sexo, telefono_contacto, comuna_residencia, id_prevision, contraseña) VALUES
    ('20123456-1', 'Ana María Soto', '1985-03-15', 'F', '912345678', 'Santiago', 1, '1234abcd'),
    ('20234567-2', 'Carlos Alberto Rivas', '1990-07-22', 'M', '923456789', 'Viña del Mar', 2, '1234abcd'),
    ('20345678-3', 'Elena Pérez', '1975-11-02', 'F', NULL, 'Concepción', 3, '1234abcd'),
    ('20456789-4', 'Roberto Gómez', '1988-05-12', 'M', '934567890', 'La Serena', 1, '1234abcd'),
    ('20567890-5', 'Paula Fernández', '1995-09-28', 'F', '945678901', 'Santiago', 2, '1234abcd'),
    ('20678901-6', 'Javier Martínez', '1980-12-01', 'M', '956789012', 'Viña del Mar', 1, '1234abcd'),
    ('20789012-7', 'Laura Díaz', '2000-02-14', 'F', '967890123', 'Concepción', 2, '1234abcd'),
    ('20890123-8', 'Diego Torres', '1992-06-18', 'M', '978901234', 'La Serena', 3, '1234abcd'),
    ('20901234-9', 'Andrea López', '1970-10-25', 'F', '989012345', 'Santiago', 1, '1234abcd'),
    ('21012345-0', 'Fernando Castro', '1983-04-08', 'M', '990123456', 'Viña del Mar', 2, '1234abcd'),
    ('21123456-1', 'Camila Rojas', '1998-08-19', 'F', '901234567', 'Concepción', 1, '1234abcd'),
    ('21234567-2', 'Pedro Herrera', '1978-01-30', 'M', NULL, 'La Serena', 2, '1234abcd'),
    ('21345678-3', 'Sofía Morales', '1993-12-05', 'F', '912345670', 'Santiago', 3, '1234abcd'),
    ('21456789-4', 'Mauricio Ramírez', '1987-09-17', 'M', '923456701', 'Viña del Mar', 1, '1234abcd'),
    ('21567890-5', 'Natalia Sánchez', '2002-07-11', 'F', '934567812', 'Concepción', 2, '1234abcd'),
    ('21678901-6', 'Alejandro Reyes', '1972-02-28', 'M', '945678923', 'La Serena', 1, '1234abcd'),
    ('21789012-7', 'Verónica Flores', '1996-11-09', 'F', '956789034', 'Santiago', 2, '1234abcd'),
    ('21890123-8', 'Oscar Mendoza', '1981-06-25', 'M', '967890145', 'Viña del Mar', 3, '1234abcd'),
    ('21901234-9', 'Katherine Silva', '1991-04-13', 'F', '978901256', 'Concepción', 1, '1234abcd'),
    ('22012345-0', 'Ricardo Núñez', '1976-08-02', 'M', '989012367', 'La Serena', 2, '1234abcd'),
    ('22123456-1', 'Diana Pizarro', '1984-10-19', 'F', '990123478', 'Santiago', 1, '1234abcd'),
    ('22234567-2', 'Gabriel Salazar', '1999-03-07', 'M', NULL, 'Viña del Mar', 2, '1234abcd'),
    ('22345678-3', 'Cecilia Vergara', '1973-12-31', 'F', '901234589', 'Concepción', 3, '1234abcd'),
    ('22456789-4', 'Esteban Guzmán', '1989-05-21', 'M', '912345690', 'La Serena', 1, '1234abcd'),
    ('22567890-5', 'Raquel Paredes', '1994-09-14', 'F', '923456701', 'Santiago', 2, '1234abcd'),
    ('22678901-6', 'Héctor Acuña', '1977-11-06', 'M', '934567812', 'Viña del Mar', 1, '1234abcd'),
    ('22789012-7', 'Carmen Carrasco', '2001-01-20', 'F', '945678923', 'Concepción', 2, '1234abcd'),
    ('22890123-8', 'Jorge Bravo', '1986-07-16', 'M', '956789034', 'La Serena', 3, '1234abcd'),
    ('22901234-9', 'Patricia Velásquez', '1992-02-10', 'F', '967890145', 'Santiago', 1, '1234abcd'),
    ('23012345-0', 'Luis Durán', '1979-08-29', 'M', '978901256', 'Viña del Mar', 2, '1234abcd'),
    ('23123456-1', 'Isabel Cáceres', '1988-12-12', 'F', '989012367', 'Concepción', 1, '1234abcd'),
    ('23234567-2', 'Marcelo Espinoza', '1997-05-04', 'M', '990123478', 'La Serena', 2, '1234abcd'),
    ('23345678-3', 'Gloria Fuentes', '1974-09-27', 'F', NULL, 'Santiago', 3, '1234abcd'),
    ('23456789-4', 'Felipe Muñoz', '1982-01-15', 'M', '901234590', 'Viña del Mar', 1, '1234abcd'),
    ('23567890-5', 'Daniela Ávila', '1990-06-08', 'F', '912345601', 'Concepción', 2, '1234abcd'),
    ('23678901-6', 'Eduardo Serrano', '1985-03-23', 'M', '923456712', 'La Serena', 1, '1234abcd'),
    ('23789012-7', 'Pilar Espinoza', '1993-10-31', 'F', '934567823', 'Santiago', 2, '1234abcd'),
    ('23890123-8', 'Cristóbal Pino', '1978-04-17', 'M', '945678934', 'Viña del Mar', 3, '1234abcd'),
    ('23901234-9', 'Macarena Guerrero', '1995-07-26', 'F', '956789045', 'Concepción', 1, '1234abcd'),
    ('24012345-0', 'Tomás Olivares', '1980-11-11', 'M', '967890156', 'La Serena', 2, '1234abcd');
    
-- =========================================================
-- 5. INSERCIÓN: MÉDICOS
-- =========================================================
INSERT INTO medico (rut_medico, nombre_completo, email_institucional, contraseña) VALUES
    ('11111111-1', 'Dr. Juan Pérez', 'juan.perez@saludusm.cl', '1234abcd'),
    ('22222222-2', 'Dra. María González', 'maria.gonzalez@saludusm.cl', '1234abcd'),
    ('33333333-3', 'Dr. Carlos Rodríguez', 'carlos.rodriguez@saludusm.cl', '1234abcd'),
    ('44444444-4', 'Dra. Ana Martínez', 'ana.martinez@saludusm.cl', '1234abcd'),
    ('55555555-5', 'Dr. Luis Fernández', 'luis.fernandez@saludusm.cl', '1234abcd'),
    ('66666666-6', 'Dra. Patricia López', 'patricia.lopez@saludusm.cl', '1234abcd'),
    ('77777777-7', 'Dr. Miguel Sánchez', 'miguel.sanchez@saludusm.cl', '1234abcd'),
    ('88888888-8', 'Dra. Claudia Díaz', 'claudia.diaz@saludusm.cl', '1234abcd'),
    ('99999999-9', 'Dr. Andrés Torres', 'andres.torres@saludusm.cl', '1234abcd'),
    ('10101010-1', 'Dra. Verónica Ramírez', 'veronica.ramirez@saludusm.cl', '1234abcd'),
    ('12121212-1', 'Dr. Pablo Morales', 'pablo.morales@saludusm.cl', '1234abcd'),
    ('13131313-1', 'Dra. Sofía Rojas', 'sofia.rojas@saludusm.cl', '1234abcd'),
    ('14141414-1', 'Dr. Diego Herrera', 'diego.herrera@saludusm.cl', '1234abcd'),
    ('15151515-1', 'Dra. Laura Castro', 'laura.castro@saludusm.cl', '1234abcd'),
    ('16161616-1', 'Dr. Ricardo Soto', 'ricardo.soto@saludusm.cl', '1234abcd');
    
-- =========================================================
-- 6. INSERCIÓN: CENTROS POR MÉDICO
-- =========================================================
INSERT INTO medico_centro (rut_medico, codigo_centro) VALUES
    ('11111111-1', 'C001'), ('11111111-1', 'C002'),
    ('22222222-2', 'C001'), ('22222222-2', 'C003'),
    ('33333333-3', 'C002'), ('33333333-3', 'C004'),
    ('44444444-4', 'C001'), ('44444444-4', 'C003'),
    ('55555555-5', 'C002'),
    ('66666666-6', 'C003'), ('66666666-6', 'C004'),
    ('77777777-7', 'C001'), ('77777777-7', 'C004'),
    ('88888888-8', 'C002'), ('88888888-8', 'C003'),
    ('99999999-9', 'C001'), ('99999999-9', 'C004'),
    ('10101010-1', 'C002'),
    ('12121212-1', 'C003'), ('12121212-1', 'C004'),
    ('13131313-1', 'C001'), ('13131313-1', 'C002'),
    ('14141414-1', 'C003'),
    ('15151515-1', 'C004'),
    ('16161616-1', 'C001'), ('16161616-1', 'C002');
    
-- =========================================================
-- 7. INSERCIÓN: ESPECIALIDADES
-- =========================================================
INSERT INTO especialidad (nombre) VALUES
	('Cardiologia'), ('Pediatria'), ('Traumatologia'), ('Medicina General'),
    ('Dermatologia'), ('Otorrinolaringologia'), ('Oftamologia'), ('Kinesiologia');
    
-- =========================================================
-- 8. INSERCIÓN: ESPECIALIDADES POR MÉDICO
-- =========================================================
INSERT INTO medico_especialidad (rut_medico, id_especialidad) VALUES
    ('11111111-1', 1), ('11111111-1', 2), ('11111111-1', 3),
    ('22222222-2', 1), ('22222222-2', 4),
    ('33333333-3', 2), ('33333333-3', 5),
    ('44444444-4', 3), ('44444444-4', 6),
    ('55555555-5', 1), ('55555555-5', 7),
    ('66666666-6', 2), ('66666666-6', 8),
    ('77777777-7', 3), ('77777777-7', 4),
    ('88888888-8', 1), ('88888888-8', 5), ('88888888-8', 6),
    ('99999999-9', 2), ('99999999-9', 7),
    ('10101010-1', 3), ('10101010-1', 8),
    ('12121212-1', 1), ('12121212-1', 4), ('12121212-1', 5),
    ('13131313-1', 2), ('13131313-1', 6),
    ('14141414-1', 3), ('14141414-1', 7),
    ('15151515-1', 1), ('15151515-1', 8),
    ('16161616-1', 2), ('16161616-1', 3), ('16161616-1', 5);
    
-- =========================================================
-- 9. INSERCION: Diagnostico
-- =========================================================
INSERT INTO diagnostico (codigo_d, descripcion) VALUES
	('I10', 'Hipertensión esencial (primaria)'), ('E11', 'Diabetes mellitus tipo 2'), ('J00', 'Rinofaringitis aguda (resfriado común)'),
    ('J18.9', 'Neumonía, no especificada'), ('J45.9', 'Asma, no especificada'), ('K29.7', 'Gastritis, no especificada'), ('K35.8', 'Apendicitis aguda'),
	('N39.0', 'Infección de vías urinarias, sitio no especificado'),
	('M54.5', 'Lumbago no especificado'),
	('F32.9', 'Episodio depresivo, no especificado'),
    ('S80.0', 'Contusion en la rodilla');
    
-- =========================================================
-- 10. GENERACIÓN DE CITAS
-- =========================================================
INSERT INTO cita (fecha_hora, rut_paciente, rut_medico, codigo_centro, id_especialidad, id_estado) VALUES
	('2026-09-01 12:30:00', '20123456-1', '22222222-2', 'C001', 4 , 3),
	('2026-09-02 15:30:00', '21890123-8', '10101010-1', 'C002', 3 , 3),
    ('2026-10-01 17:35:00', '21789012-7', '77777777-7', 'C004', 4, 3),
    ('2026-10-25 10:00:00', '22345678-3', '55555555-5', 'C002', 1, 3),
    ('2026-11-04 12:00:00', '24012345-0', '12121212-1', 'C003', 4, 3),
    ('2026-11-11 09:30:00', '21123456-1', '22222222-2', 'C001', 4, 3),
    ('2026-12-01 10:30:00', '23567890-5', '13131313-1', 'C001', 6, 1),
    ('2026-12-11 15:30:00', '21234567-2', '15151515-1', 'C004', 8, 2),
    ('2026-09-15 12:00:00', '22234567-2', '44444444-4', 'C001', 6, 4),
    ('2026-10-05 13:00:00', '20789012-7', '99999999-9', 'C004', 7, 5),
    ('2026-10-06 13:00:00', '20789012-7', '11111111-1', 'C001', 1, 3),
	('2026-09-03 09:00:00', '20123456-1', '11111111-1', 'C001', 1, 3),
	('2026-09-03 09:30:00', '20234567-2', '11111111-1', 'C001', 2, 3),
	('2026-09-03 10:00:00', '20345678-3', '11111111-1', 'C001', 3, 3),
	('2026-09-03 09:00:00', '20456789-4', '33333333-3', 'C002', 2, 3),
	('2026-09-03 09:30:00', '20567890-5', '33333333-3', 'C002', 5, 3),
	('2026-09-03 10:00:00', '20678901-6', '33333333-3', 'C002', 2, 3),
	('2026-09-03 10:30:00', '20789012-7', '44444444-4', 'C003', 3, 3),
	('2026-09-03 11:00:00', '20890123-8', '44444444-4', 'C003', 6, 3),
	('2026-09-03 11:30:00', '20901234-9', '44444444-4', 'C003', 3, 3),
	('2026-09-03 14:00:00', '21012345-0', '66666666-6', 'C004', 2, 3),
	('2026-09-03 14:30:00', '21123456-1', '66666666-6', 'C004', 8, 3),
	('2026-09-03 15:00:00', '21234567-2', '66666666-6', 'C004', 2, 3),
	('2026-09-03 15:30:00', '21345678-3', '77777777-7', 'C001', 3, 3),
	('2026-09-03 16:00:00', '21456789-4', '77777777-7', 'C001', 4, 3),
	('2026-09-04 09:00:00', '21567890-5', '88888888-8', 'C002', 1, 3),
	('2026-09-04 09:30:00', '21678901-6', '88888888-8', 'C002', 5, 3),
	('2026-09-04 10:00:00', '21789012-7', '88888888-8', 'C002', 6, 3),
	('2026-09-04 09:00:00', '21890123-8', '99999999-9', 'C001', 2, 3),
	('2026-09-04 09:30:00', '21901234-9', '99999999-9', 'C001', 7, 3),
	('2026-09-04 10:00:00', '22012345-0', '99999999-9', 'C001', 2, 3),
	('2026-09-04 11:00:00', '22123456-1', '12121212-1', 'C004', 1, 3),
	('2026-09-04 11:30:00', '22234567-2', '12121212-1', 'C004', 4, 3),
	('2026-09-04 12:00:00', '22345678-3', '12121212-1', 'C004', 5, 3),
	('2026-09-04 14:00:00', '22456789-4', '13131313-1', 'C002', 2, 3),
	('2026-09-04 14:30:00', '22567890-5', '13131313-1', 'C002', 6, 3),
	('2026-09-04 15:00:00', '22678901-6', '13131313-1', 'C002', 2, 3),
	('2026-09-04 15:30:00', '22789012-7', '14141414-1', 'C003', 3, 3),
	('2026-09-04 16:00:00', '22890123-8', '14141414-1', 'C003', 7, 3),
	('2026-09-04 16:30:00', '22901234-9', '14141414-1', 'C003', 3, 3),
	('2026-09-07 09:00:00', '23012345-0', '15151515-1', 'C004', 1, 3),
	('2026-09-07 09:30:00', '23123456-1', '15151515-1', 'C004', 8, 3),
	('2026-09-07 10:00:00', '23234567-2', '15151515-1', 'C004', 1, 3),
	('2026-09-07 09:00:00', '23345678-3', '16161616-1', 'C001', 2, 3),
	('2026-09-07 09:30:00', '23456789-4', '16161616-1', 'C001', 3, 3),
	('2026-09-07 10:00:00', '23567890-5', '16161616-1', 'C001', 5, 3),
	('2026-09-07 11:00:00', '23678901-6', '22222222-2', 'C003', 1, 3),
	('2026-09-07 11:30:00', '23789012-7', '22222222-2', 'C003', 4, 3),
	('2026-09-07 12:00:00', '23890123-8', '22222222-2', 'C003', 1, 3),
	('2026-09-07 14:00:00', '23901234-9', '33333333-3', 'C004', 2, 3),
	('2026-09-07 14:30:00', '24012345-0', '33333333-3', 'C004', 5, 3),
	('2026-09-07 15:00:00', '20123456-1', '33333333-3', 'C004', 2, 3),
	('2026-09-07 15:30:00', '20234567-2', '55555555-5', 'C002', 1, 3),
	('2026-09-07 16:00:00', '20345678-3', '55555555-5', 'C002', 7, 3),
	('2026-09-07 16:30:00', '20456789-4', '55555555-5', 'C002', 1, 3),
	('2026-09-08 09:00:00', '20567890-5', '77777777-7', 'C004', 3, 3),
	('2026-09-08 09:30:00', '20678901-6', '77777777-7', 'C004', 4, 3),
	('2026-09-08 10:00:00', '20789012-7', '77777777-7', 'C004', 3, 3),
	('2026-09-08 10:30:00', '20890123-8', '10101010-1', 'C002', 3, 3),
	('2026-09-08 11:00:00', '20901234-9', '10101010-1', 'C002', 8, 3),
	('2026-09-08 11:30:00', '21012345-0', '10101010-1', 'C002', 3, 3),
	('2026-09-08 14:00:00', '21123456-1', '11111111-1', 'C002', 1, 3),
	('2026-09-08 14:30:00', '21234567-2', '11111111-1', 'C002', 2, 3),
	('2026-09-08 15:00:00', '21345678-3', '11111111-1', 'C002', 3, 3),
	('2026-09-08 15:30:00', '21456789-4', '44444444-4', 'C001', 3, 3),
	('2026-09-09 09:00:00', '21567890-5', '44444444-4', 'C001', 6, 4),
	('2026-09-09 09:30:00', '21678901-6', '44444444-4', 'C001', 3, 4),
	('2026-09-09 10:00:00', '21789012-7', '22222222-2', 'C001', 1, 4),
	('2026-09-09 10:30:00', '21890123-8', '22222222-2', 'C001', 4, 4),
	('2026-09-09 11:00:00', '21901234-9', '33333333-3', 'C002', 2, 4),
	('2026-09-09 11:30:00', '22012345-0', '33333333-3', 'C002', 5, 4),
	('2026-09-09 14:00:00', '22123456-1', '88888888-8', 'C003', 1, 4),
	('2026-09-09 14:30:00', '22234567-2', '88888888-8', 'C003', 5, 4),
	('2026-09-09 15:00:00', '22345678-3', '88888888-8', 'C003', 6, 4),
	('2026-09-09 15:30:00', '22456789-4', '99999999-9', 'C004', 2, 4),
	('2026-09-09 16:00:00', '22567890-5', '99999999-9', 'C004', 7, 4),
	('2026-09-09 16:30:00', '22678901-6', '99999999-9', 'C004', 2, 4),
	('2026-09-09 17:00:00', '22789012-7', '12121212-1', 'C003', 1, 4),
	('2026-09-09 17:30:00', '22890123-8', '12121212-1', 'C003', 4, 4),
	('2026-09-10 09:00:00', '22901234-9', '12121212-1', 'C003', 5, 4),
	('2026-09-10 09:30:00', '23012345-0', '13131313-1', 'C001', 2, 5),
	('2026-09-10 10:00:00', '23123456-1', '13131313-1', 'C001', 6, 5),
	('2026-09-10 10:30:00', '23234567-2', '14141414-1', 'C003', 3, 5),
	('2026-09-10 11:00:00', '23345678-3', '14141414-1', 'C003', 7, 5),
	('2026-09-10 11:30:00', '23456789-4', '15151515-1', 'C004', 1, 5),
	('2026-09-10 14:00:00', '23567890-5', '15151515-1', 'C004', 8, 5),
	('2026-09-10 14:30:00', '23678901-6', '16161616-1', 'C002', 2, 5),
	('2026-09-10 15:00:00', '23789012-7', '16161616-1', 'C002', 3, 5),
	('2026-09-10 15:30:00', '23890123-8', '16161616-1', 'C002', 5, 5),
	('2026-09-10 16:00:00', '23901234-9', '77777777-7', 'C001', 3, 5),
	('2026-09-10 16:30:00', '24012345-0', '77777777-7', 'C001', 4, 5),
	('2026-09-11 09:00:00', '20123456-1', '66666666-6', 'C003', 2, 5),
	('2026-09-11 09:30:00', '20234567-2', '66666666-6', 'C003', 8, 5),
	('2026-09-11 10:00:00', '20345678-3', '55555555-5', 'C002', 1, 5),
	('2026-09-11 10:30:00', '20456789-4', '55555555-5', 'C002', 7, 5),
	('2026-12-02 09:00:00', '20567890-5', '11111111-1', 'C001', 1, 1),
	('2026-12-02 09:30:00', '20678901-6', '11111111-1', 'C001', 2, 1),
	('2026-12-02 10:00:00', '20789012-7', '11111111-1', 'C001', 3, 1),
	('2026-12-02 10:30:00', '20890123-8', '22222222-2', 'C003', 1, 1),
	('2026-12-02 11:00:00', '20901234-9', '22222222-2', 'C003', 4, 1),
	('2026-12-02 11:30:00', '21012345-0', '33333333-3', 'C004', 2, 1),
	('2026-12-02 14:00:00', '21123456-1', '33333333-3', 'C004', 5, 1),
	('2026-12-02 14:30:00', '21234567-2', '44444444-4', 'C003', 3, 1),
	('2026-12-02 15:00:00', '21345678-3', '44444444-4', 'C003', 6, 1),
	('2026-12-02 15:30:00', '21456789-4', '66666666-6', 'C004', 2, 1),
	('2026-12-02 16:00:00', '21567890-5', '66666666-6', 'C004', 8, 1),
	('2026-12-02 16:30:00', '21678901-6', '77777777-7', 'C004', 3, 1),
	('2026-12-02 17:00:00', '21789012-7', '77777777-7', 'C004', 4, 1),
	('2026-12-03 09:00:00', '21890123-8', '88888888-8', 'C002', 1, 1),
	('2026-12-03 09:30:00', '21901234-9', '88888888-8', 'C002', 5, 1),
	('2026-12-03 10:00:00', '22012345-0', '88888888-8', 'C002', 6, 2),
	('2026-12-03 10:30:00', '22123456-1', '99999999-9', 'C001', 2, 2),
	('2026-12-03 11:00:00', '22234567-2', '99999999-9', 'C001', 7, 2),
	('2026-12-03 11:30:00', '22345678-3', '10101010-1', 'C002', 3, 2),
	('2026-12-03 14:00:00', '22456789-4', '10101010-1', 'C002', 8, 2),
	('2026-12-03 14:30:00', '22567890-5', '12121212-1', 'C004', 1, 2),
	('2026-12-03 15:00:00', '22678901-6', '12121212-1', 'C004', 4, 2),
	('2026-12-03 15:30:00', '22789012-7', '12121212-1', 'C004', 5, 2),
	('2026-12-03 16:00:00', '22890123-8', '13131313-1', 'C002', 2, 2),
	('2026-12-03 16:30:00', '22901234-9', '13131313-1', 'C002', 6, 2),
	('2026-12-03 17:00:00', '23012345-0', '14141414-1', 'C003', 3, 2);
    
-- =========================================================
-- 11. ATENCIONES
-- =========================================================
INSERT INTO atencion (motivo_consulta, obs_clinicas, id_cita, fecha, codigo_centro, rut_paciente, rut_medico)
VALUES
    ('Sintomas de resfrio', 'Fiebre sobre 38 grados', 1, '2026-09-01', 'C001', '20123456-1', '22222222-2'),
	('Golpe en la rodilla', 'Dolor de hueso', 2, '2026-09-02', 'C003', '21890123-8', '10101010-1'),
    ('Dolor de cabeza', 'Dolor punzante', 3, '2026-10-01', 'C004', '21789012-7', '77777777-7'),
    ('Problemas de respiracion', 'Presion alta', 4, '2026-10-25', 'C002', '22345678-3', '55555555-5'),
    ('Dolor estomacal', 'Sin observaciones', 5, '2026-11-04', 'C003', '24012345-0', '12121212-1'),
    ('Problemas para orinar', 'Sin observaciones', 6, '2026-11-11', 'C001', '21123456-1', '22222222-2'),
    ('Control cardiovascular', 'Presión arterial bajo control', 12, '2026-09-03', 'C001', '20123456-1', '11111111-1'),
	('Chequeo pediátrico', 'Crecimiento y peso adecuados', 13, '2026-09-03', 'C001', '20234567-2', '11111111-1'),
	('Molestia en tobillo', 'Leve inflamación articular', 14, '2026-09-03', 'C001', '20345678-3', '11111111-1'),
	('Fiebre recurrente', 'Sin foco infeccioso evidente', 15, '2026-09-03', 'C002', '20456789-4', '33333333-3'),
	('Manchas en piel', 'Lesiones eritematosas en dorso', 16, '2026-09-03', 'C002', '20567890-5', '33333333-3'),
	('Tos persistente', 'Vía aérea superior congestiva', 17, '2026-09-03', 'C002', '20678901-6', '33333333-3'),
	('Dolor de hombro', 'Movilidad conservada con dolor', 18, '2026-09-03', 'C003', '20789012-7', '44444444-4'),
	('Molestia auditiva', 'Membrana timpánica íntegra', 19, '2026-09-03', 'C003', '20890123-8', '44444444-4'),
	('Dolor lumbar moderado', 'Contractura paravertebral', 20, '2026-09-03', 'C003', '20901234-9', '44444444-4'),
	('Control de rutina', 'Examen físico dentro de rangos normales', 21, '2026-09-03', 'C004', '21012345-0', '66666666-6'),
	('Dolor articular post-esfuerzo', 'Sin aumento de volumen evidente', 22, '2026-09-03', 'C004', '21123456-1', '66666666-6'),
	('Congestión nasal severa', 'Mucosa congestiva', 23, '2026-09-03', 'C004', '21234567-2', '66666666-6'),
	('Dolor de espalda alta', 'Postura inadecuada en trabajo', 24, '2026-09-03', 'C001', '21345678-3', '77777777-7'),
	('Chequeo médico anual', 'Exámenes de laboratorio pendientes', 25, '2026-09-03', 'C001', '21456789-4', '77777777-7'),
	('Palpitaciones ocasionales', 'Ritmo regular, auscultación normal', 26, '2026-09-04', 'C002', '21567890-5', '88888888-8'),
	('Picazón cutánea generalizada', 'Xerosis cutánea', 27, '2026-09-04', 'C002', '21678901-6', '88888888-8'),
	('Sensación de tapón en oídos', 'Secreción ceruminosa leve', 28, '2026-09-04', 'C002', '21789012-7', '88888888-8'),
	('Fiebre y malestar general', 'Hidratación conservada', 29, '2026-09-04', 'C001', '21890123-8', '99999999-9'),
	('Disminución de agudeza visual', 'Requiere evaluación refractiva', 30, '2026-09-04', 'C001', '21901234-9', '99999999-9'),
	('Control de talla y peso', 'Desarrollo acorde a edad', 31, '2026-09-04', 'C001', '22012345-0', '99999999-9'),
	('Revisión de presión arterial', 'Leve tendencia a la elevación', 32, '2026-09-04', 'C004', '22123456-1', '12121212-1'),
	('Fatiga crónica y decaimiento', 'Sin foco aparente, solicita exámenes', 33, '2026-09-04', 'C004', '22234567-2', '12121212-1'),
	('Alergia cutánea recurrente', 'Pápulas aisladas en antebrazo', 34, '2026-09-04', 'C004', '22345678-3', '12121212-1'),
	('Resfrío común', 'Rinorrea transparente', 35, '2026-09-04', 'C002', '22456789-4', '13131313-1'),
	('Molestia al tragar', 'Faringe hiperémica leve', 36, '2026-09-04', 'C002', '22567890-5', '13131313-1'),
	('Tos matinal persistente', 'Murmullo pulmonar conservado', 37, '2026-09-04', 'C002', '22678901-6', '13131313-1'),
	('Dolor en muñeca', 'Dolor a la palpación estiloides', 38, '2026-09-04', 'C003', '22789012-7', '14141414-1'),
	('Ojo rojo sin dolor', 'Hiperemia conjuntival periférica', 39, '2026-09-04', 'C003', '22890123-8', '14141414-1'),
	('Contractura cervical', 'Rango articular reducido lateral', 40, '2026-09-04', 'C003', '22901234-9', '14141414-1'),
	('Evaluación cardiológica', 'ECG basal dentro de límites', 41, '2026-09-07', 'C004', '23012345-0', '15151515-1'),
	('Rehabilitación muscular de cadera', 'Fuerza conservada, dolor residual', 42, '2026-09-07', 'C004', '23123456-1', '15151515-1'),
	('Control de hipertensión', 'Cifras tensionales en meta', 43, '2026-09-07', 'C004', '23234567-2', '15151515-1'),
	('Dolor estomacal e inapetencia', 'Abdomen blando, depresible', 44, '2026-09-07', 'C001', '23345678-3', '16161616-1'),
	('Esguince de tobillo leve', 'Edema leve en maléolo externo', 45, '2026-09-07', 'C001', '23456789-4', '16161616-1'),
	('Lesión dérmica sospechosa', 'Nevus con bordes regulares', 46, '2026-09-07', 'C001', '23567890-5', '16161616-1'),
	('Hipertensión arterial estadio 1', 'Se ajustan indicaciones no farmacológicas', 47, '2026-09-07', 'C003', '23678901-6', '22222222-2'),
	('Disnea de esfuerzo', 'Sibilancias espiratorias leves', 48, '2026-09-07', 'C003', '23789012-7', '22222222-2'),
	('Chequeo cardiovascular preventivo', 'Perfil lipídico solicitado', 49, '2026-09-07', 'C003', '23890123-8', '22222222-2'),
	('Fiebre alta de inicio brusco', 'Faringe congestiva con exudado', 50, '2026-09-07', 'C004', '23901234-9', '33333333-3'),
	('Dermatitis por contacto', 'Eritema en manos por químicos', 51, '2026-09-07', 'C004', '24012345-0', '33333333-3'),
	('Control de vacuna y desarrollo', 'Curvas antropométricas normales', 52, '2026-09-07', 'C004', '20123456-1', '33333333-3'),
	('Dolor torácico atípico', 'Descartada causa coronaria aguda', 53, '2026-09-07', 'C002', '20234567-2', '55555555-5'),
	('Ardor ocular prolongado', 'Lagrimeo reactivo presente', 54, '2026-09-07', 'C002', '20345678-3', '55555555-5'),
	('Control de arritmia benigna', 'Pulsos simétricos periféricos', 55, '2026-09-07', 'C002', '20456789-4', '55555555-5'),
	('Dolor en rodilla derecha', 'Maniobras meniscales negativas', 56, '2026-09-08', 'C004', '20567890-5', '77777777-7'),
	('Control médico general', 'Parámetros vitales en orden', 57, '2026-09-08', 'C004', '20678901-6', '77777777-7'),
	('Dolor lumbar agudo', 'Lasegue negativo bilateral', 58, '2026-09-08', 'C004', '20789012-7', '77777777-7'),
	('Tendinitis de hombro', 'Dolor al arco de abducción', 59, '2026-09-08', 'C002', '20890123-8', '10101010-1'),
	('Rehabilitación funcional', 'Mejoría en marcha y equilibrio', 60, '2026-09-08', 'C002', '20901234-9', '10101010-1'),
	('Dolor de cadera', 'Rotación interna conservada', 61, '2026-09-08', 'C002', '21012345-0', '10101010-1'),
	('Dislipidemia y riesgo CV', 'Derivado a nutrición', 62, '2026-09-08', 'C002', '21123456-1', '11111111-1'),
	('Rinofaringitis viral', 'Evolución favorable', 63, '2026-09-08', 'C002', '21234567-2', '11111111-1'),
	('Dolor dorsal postraumático', 'Sin signos focales neurológicos', 64, '2026-09-08', 'C002', '21345678-3', '11111111-1'),
	('Traumatismo leve en codo', 'Sin impotencia funcional completa', 65, '2026-09-08', 'C001', '21456789-4', '44444444-4');
-- =========================================================
-- 12. DIAGNÓSTICOS POR ATENCIÓN
-- =========================================================
INSERT INTO atencion_diagnostico(id_atencion, codigo_d) VALUES
	(1,'J00'),
	(2, 'S80.0'),
	(3, 'F32.9'),
	(4, 'J18.9'),
	(5, 'K29.7'),
	(6, 'N39.0'),
	(7, 'I10'), (8, 'J00'), (9, 'S80.0'), (10, 'J00'), (11, 'J00'),
	(12, 'J45.9'), (13, 'M54.5'), (14, 'J00'), (15, 'M54.5'), (16, 'I10'),
	(17, 'M54.5'), (18, 'J00'), (19, 'M54.5'), (20, 'E11'), (21, 'I10'),
	(22, 'J00'), (23, 'J00'), (24, 'J00'), (25, 'J00'), (26, 'E11'),
	(27, 'I10'), (28, 'F32.9'), (29, 'J00'), (30, 'J00'), (31, 'J00'),
	(32, 'J45.9'), (33, 'S80.0'), (34, 'J00'), (35, 'M54.5'), (36, 'I10'),
	(37, 'M54.5'), (38, 'I10'), (39, 'K29.7'), (40, 'S80.0'), (41, 'J00'),
	(42, 'I10'), (43, 'J45.9'), (44, 'I10'), (45, 'J18.9'), (46, 'J00'),
	(47, 'J00'), (48, 'I10'), (49, 'J00'), (50, 'I10'), (51, 'S80.0'),
	(52, 'I10'), (53, 'M54.5'), (54, 'M54.5'), (55, 'M54.5'), (56, 'S80.0'),
	(57, 'I10'), (58, 'J00'), (59, 'M54.5'), (60, 'S80.0');


-- =========================================================
-- 13. RECETAS POR ATENCIÓN
-- =========================================================
INSERT INTO receta(id_atencion, medicamento, dosis, Dias_tratamiento) VALUES
    (1, 'Paracetamol 500 mg', '1 comprimido cada 8 horas', 5),
    (1, 'Ibuprofeno 400 mg', '1 comprimido cada 12 horas', 7),
    (1, 'Amoxicilina 500 mg', '1 cápsula cada 8 horas', 10),
    (2, 'Omeprazol 20 mg', '1 cápsula en ayunas', 14),
    (2, 'Salbutamol inhalador', '2 puff cada 6 horas', 5),
    (2, 'Losartán 50 mg', '1 comprimido al día', 30),
    (3, 'Metformina 850 mg', '1 comprimido cada 12 horas', 30),
    (3, 'Diazepam 5 mg', '1 comprimido al acostarse', 7),
    (3, 'Cetirizina 10 mg', '1 comprimido al día', 5),
    (4, 'Azitromicina 500 mg', '1 comprimido al día por 3 días', 3),
    (4, 'Prednisona 20 mg', 'según indicación', 5),
    (4, 'Enalapril 10 mg', '1 comprimido al día', 30),
    (5, 'Atorvastatina 20 mg', '1 comprimido al día', 30),
    (5, 'Tramadol 50 mg', '1 comprimido cada 8 horas', 4),
    (5, 'Clonazepam 2 mg', 'media tableta cada 12 horas', 10),
    (6, 'Metoclopramida 10 mg', '1 comprimido antes de comidas', 7),
    (6, 'Diclofenaco 50 mg', '1 comprimido cada 8 horas', 5),
    (6, 'Hidroclorotiazida 25 mg', '1 comprimido al día', 30),
    (6, 'Simvastatina 20 mg', '1 comprimido al día', 30),
    (6, 'Morfina 10 mg', 'según dolor, cada 6 horas', 3),
    (7, 'Enalapril 10 mg', '1 comprimido cada 12 horas', 30),
	(8, 'Paracetamol 500 mg', '1 comprimido cada 8 horas', 5),
	(9, 'Ibuprofeno 400 mg', '1 comprimido cada 8 horas por 5 días', 5),
	(10, 'Amoxicilina 500 mg', '1 cápsula cada 8 horas', 7),
	(11, 'Cetirizina 10 mg', '1 comprimido al acostarse', 10),
	(12, 'Salbutamol inhalador', '2 puff cada 6 horas en crisis', 7),
	(13, 'Ketorolaco 10 mg', '1 comprimido cada 8 horas', 3),
	(14, 'Clorfenamina 4 mg', '1 comprimido cada 8 horas', 4),
	(15, 'Ciclobenzaprina 10 mg', '1 comprimido en la noche', 7),
	(16, 'Losartán 50 mg', '1 comprimido al día', 30),
	(17, 'Paracetamol 500 mg', '1 comprimido cada 8 horas según dolor', 5),
	(18, 'Ibuprofeno 400 mg', '1 comprimido cada 12 horas', 4),
	(19, 'Meloxicam 15 mg', '1 comprimido al día', 10),
	(20, 'Metformina 850 mg', '1 comprimido con almuerzo', 30),
	(21, 'Atenolol 50 mg', '1 comprimido en la mañana', 30),
	(22, 'Loratadina 10 mg', '1 comprimido diario', 14),
	(23, 'Paracetamol 500 mg', '1 comprimido cada 8 horas', 3),
	(24, 'Amoxicilina 875 mg', '1 comprimido cada 12 horas', 7),
	(25, 'Gotas lubricantes oculares', '1 gota en cada ojo cada 8 horas', 30),
	(26, 'Glibenclamida 5 mg', '1 comprimido antes del desayuno', 30),
	(27, 'Amlodipino 5 mg', '1 comprimido al día', 30),
	(28, 'Sertralina 50 mg', '1 comprimido cada mañana', 30),
	(29, 'Desloratadina 5 mg', '1 comprimido al día', 10),
	(30, 'Paracetamol 1g', '1 comprimido cada 8 horas', 4),
	(31, 'Ibuprofeno 600 mg', '1 comprimido cada 12 horas', 5),
	(32, 'Budesonida 200 mcg', '1 puff cada 12 horas', 30),
	(33, 'Diclofenaco gel 1%', 'Aplicar localmente cada 8 horas', 7),
	(34, 'Lágrimas artificiales', 'Según necesidad', 30),
	(35, 'Tramadol/Paracetamol 37.5/325 mg', '1 comprimido cada 8 horas', 5),
	(36, 'Carvedilol 12.5 mg', '1 comprimido cada 12 horas', 30),
	(37, 'Naproxeno 550 mg', '1 comprimido cada 12 horas', 5),
	(38, 'Hidroclorotiazida 25 mg', '1 comprimido en ayunas', 30),
	(39, 'Omeprazol 20 mg', '1 cápsula en ayunas', 28),
	(40, 'Paracetamol 500 mg', '1 comprimido cada 8 horas', 5),
	(41, 'Betametasona crema 0.05%', 'Aplicar en zona afectada cada 12 horas', 7),
	(42, 'Enalapril 20 mg', '1 comprimido al día', 30),
	(43, 'Salmeterol/Fluticasona', '1 inhalación cada 12 horas', 30),
	(44, 'Atorvastatina 20 mg', '1 comprimido en la noche', 30),
	(45, 'Azitromicina 500 mg', '1 comprimido diario', 5),
	(46, 'Clotrimazol crema 1%', 'Aplicar 2 veces al día', 14),
	(47, 'Paracetamol 500 mg', '1 comprimido cada 8 horas', 4),
	(48, 'Aspirina 100 mg', '1 comprimido con el almuerzo', 30),
	(49, 'Ciprofloxacino 500 mg', '1 comprimido cada 12 horas', 7),
	(50, 'Losartán 100 mg', '1 comprimido en la mañana', 30),
	(51, 'Ketoprofeno 200 mg', '1 cápsula al día con comida', 5),
	(52, 'Enalapril 10 mg', '1 comprimido cada 12 horas', 30),
	(53, 'Celecoxib 200 mg', '1 cápsula al día', 7),
	(54, 'Ibuprofeno 400 mg', '1 comprimido cada 8 horas', 5),
	(55, 'Paracetamol 1g', '1 comprimido cada 8 horas según molestia', 3),
	(56, 'Ketorolaco trometamol 10 mg', '1 comprimido cada 8 horas', 4),
	(57, 'Metformina 500 mg', '1 comprimido diario', 30),
	(58, 'Antigripal compuesto', '1 cápsula cada 8 horas', 3),
	(59, 'Diclofenaco sódico 50 mg', '1 comprimido cada 12 horas', 5),
	(60, 'Paracetamol 500 mg', '1 comprimido cada 8 horas', 4),
	(7, 'Atorvastatina 10 mg', '1 comprimido en la noche', 30),
	(12, 'Prednisona 20 mg', '1 comprimido por la mañana', 5),
	(20, 'Atorvastatina 40 mg', '1 comprimido nocturno', 30),
	(39, 'Domperidona 10 mg', '1 comprimido 15 min antes de comidas', 10),
	(43, 'Montelukast 10 mg', '1 comprimido al acostarse', 30),
	(45, 'Amoxicilina/Ácido Clavulánico 875/125 mg', '1 comprimido cada 12 horas', 10);


        
        

















