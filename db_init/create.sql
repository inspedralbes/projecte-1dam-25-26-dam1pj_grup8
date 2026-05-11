-- Aquest script NOMÉS s'executa la primera vegada que es crea el contenidor.
-- Si es vol recrear les taules de nou cal esborrar el contenidor, o bé les dades del contenidor
-- és a dir, 
-- esborrar el contingut de la carpeta db_data 
-- o canviant el nom de la carpeta, però atenció a no pujar-la a git


-- És un exemple d'script per crear una base de dades i una taula
-- i afegir-hi dades inicials

-- Si creem la BBDD aquí podem control·lar la codificació i el collation
-- en canvi en el docker-compose no podem especificar el collation ni la codificació

-- Per assegurar-nes de que la codificació dels caràcters d'aquest script és la correcta
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS persones
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Donem permisos a l'usuari 'usuari' per accedir a la base de dades 'persones'
-- sinó, aquest usuari no podrà veure la base de dades i no podrà accedir a les taules
GRANT ALL PRIVILEGES ON persones.* TO 'usuari'@'%';
FLUSH PRIVILEGES;


-- Després de crear la base de dades, cal seleccionar-la per treballar-hi
USE persones;


CREATE TABLE cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);


-- Taula d'incidències (pantalla "Registrar nova incidència")
-- L'usuari només introdueix departament i descripció curta.
-- La data s'assigna automàticament i l'id és autonumèric.
CREATE TABLE IF NOT EXISTS incidencies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  departament VARCHAR(80) NOT NULL,
  data_incidencia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  descripcio_curta VARCHAR(255) NOT NULL,
  descripcio_llarga TEXT NULL,
  localitzacio ENUM(
    'P1_A1','P1_A2','P1_A3','P1_A4','P1_A5','P1_A6','P1_A7','P1_A8','P1_A9','P1_A10',
    'P2_A1','P2_A2','P2_A3','P2_A4','P2_A5','P2_A6','P2_A7','P2_A8','P2_A9','P2_A10',
    'P3_A1','P3_A2','P3_A3','P3_A4','P3_A5','P3_A6','P3_A7','P3_A8','P3_A9','P3_A10'
  ) NULL,
  prioritat VARCHAR(10) NOT NULL DEFAULT 'mitja',
  estat VARCHAR(30) NOT NULL DEFAULT 'pendent_assignar',
  tecnic_assignat VARCHAR(80) NULL,
  data_inici_tasca TIMESTAMP NULL DEFAULT NULL,
  data_tancament TIMESTAMP NULL DEFAULT NULL
);


-- Taula de Work Logs (per afegir entrades de treball a una incidència)
CREATE TABLE IF NOT EXISTS worklogs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  incident_id INT NOT NULL,
  opened_at DATETIME NOT NULL,
  user VARCHAR(255) NULL,
  hours_spent DECIMAL(6,2) NOT NULL DEFAULT 0,
  description TEXT NOT NULL,
  visible_to_user TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_worklogs_incident (incident_id),
  INDEX idx_worklogs_created (created_at)
);


-- Taula de tècnics (mateixa estructura base que USUARI, però amb ROL_EMPLOYEE)
CREATE TABLE IF NOT EXISTS TECNIC (
  TECNIC_ID INT NOT NULL AUTO_INCREMENT,
  FIRST_NAME VARCHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  LAST_NAME VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  EMAIL VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  PASSWORD CHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  PHONE_NUMBER VARCHAR(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  ROL_EMPLOYEE ENUM('ENCARGADO','TECNICO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TECNICO',
  PRIMARY KEY (TECNIC_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si la taula ja existeix però li falten columnes, les afegim
ALTER TABLE TECNIC ADD COLUMN IF NOT EXISTS FIRST_NAME VARCHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE TECNIC ADD COLUMN IF NOT EXISTS LAST_NAME VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE TECNIC ADD COLUMN IF NOT EXISTS EMAIL VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE TECNIC ADD COLUMN IF NOT EXISTS PASSWORD CHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE TECNIC ADD COLUMN IF NOT EXISTS PHONE_NUMBER VARCHAR(12) COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE TECNIC ADD COLUMN IF NOT EXISTS ROL_EMPLOYEE ENUM('ENCARGADO','TECNICO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TECNICO';

-- Taula d'usuaris/persones (per crear usuaris des de l'admin)
CREATE TABLE IF NOT EXISTS USUARI (
  USUARI_ID INT NOT NULL AUTO_INCREMENT,
  USERNAME VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  FIRST_NAME VARCHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  LAST_NAME VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  EMAIL VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  PASSWORD_HASH VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PHONE_NUMBER VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL,
  DEPARTMENT_ID INT NULL,
  ROLE ENUM('TECNIC','ADMIN','RESPONSABLE','PROFESSOR') COLLATE utf8mb4_unicode_ci NOT NULL,
  IS_VERIFIED TINYINT(1) NOT NULL DEFAULT 0,
  VERIFICATION_TOKEN VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
  TOKEN_EXPIRES_AT DATETIME NULL,
  CREATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UPDATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (USUARI_ID),
  UNIQUE KEY uniq_usuari_email (EMAIL),
  UNIQUE KEY uniq_usuari_username (USERNAME)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Taula de departaments (usada per autenticació / registre)
CREATE TABLE IF NOT EXISTS DEPARTMENT (
  DEPARTMENT_ID INT NOT NULL AUTO_INCREMENT,
  DEPARTMENT_NAME VARCHAR(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  CREATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (DEPARTMENT_ID),
  UNIQUE KEY uniq_department_name (DEPARTMENT_NAME)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO DEPARTMENT (DEPARTMENT_NAME)
SELECT 'General'
WHERE NOT EXISTS (SELECT 1 FROM DEPARTMENT WHERE LOWER(DEPARTMENT_NAME) = 'general');

INSERT INTO DEPARTMENT (DEPARTMENT_NAME)
SELECT 'IT'
WHERE NOT EXISTS (SELECT 1 FROM DEPARTMENT WHERE LOWER(DEPARTMENT_NAME) = 'it');

INSERT INTO DEPARTMENT (DEPARTMENT_NAME)
SELECT 'Support'
WHERE NOT EXISTS (SELECT 1 FROM DEPARTMENT WHERE LOWER(DEPARTMENT_NAME) = 'support');

INSERT INTO DEPARTMENT (DEPARTMENT_NAME)
SELECT 'Finance'
WHERE NOT EXISTS (SELECT 1 FROM DEPARTMENT WHERE LOWER(DEPARTMENT_NAME) = 'finance');

INSERT INTO DEPARTMENT (DEPARTMENT_NAME)
SELECT 'HR'
WHERE NOT EXISTS (SELECT 1 FROM DEPARTMENT WHERE LOWER(DEPARTMENT_NAME) = 'hr');

INSERT INTO DEPARTMENT (DEPARTMENT_NAME)
SELECT 'Sales'
WHERE NOT EXISTS (SELECT 1 FROM DEPARTMENT WHERE LOWER(DEPARTMENT_NAME) = 'sales');

-- Dades inicials (només primer cop). Si el teu entorn ja tenia volum, mira `php/tecnic_schema.php`.
INSERT INTO TECNIC (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROL_EMPLOYEE)
SELECT 'Alex', 'Serra', 'alex.serra@exemple.local', LEFT(REPLACE(UUID(),'-',''), 16), '600000001', 'ENCARGADO'
WHERE NOT EXISTS (SELECT 1 FROM TECNIC WHERE EMAIL = 'alex.serra@exemple.local');

INSERT INTO TECNIC (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROL_EMPLOYEE)
SELECT 'Berta', 'Roca', 'berta.roca@exemple.local', LEFT(REPLACE(UUID(),'-',''), 16), '600000002', 'TECNICO'
WHERE NOT EXISTS (SELECT 1 FROM TECNIC WHERE EMAIL = 'berta.roca@exemple.local');

INSERT INTO TECNIC (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROL_EMPLOYEE)
SELECT 'Carles', 'Pujol', 'carles.pujol@exemple.local', LEFT(REPLACE(UUID(),'-',''), 16), '600000003', 'TECNICO'
WHERE NOT EXISTS (SELECT 1 FROM TECNIC WHERE EMAIL = 'carles.pujol@exemple.local');

INSERT INTO TECNIC (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROL_EMPLOYEE)
SELECT 'Dina', 'Vila', 'dina.vila@exemple.local', LEFT(REPLACE(UUID(),'-',''), 16), '600000004', 'TECNICO'
WHERE NOT EXISTS (SELECT 1 FROM TECNIC WHERE EMAIL = 'dina.vila@exemple.local');




-- Afegim algunes dades inicials a la taula cases
INSERT INTO cases (name) VALUES ('Casa Milà');
INSERT INTO cases (name) VALUES ('Casa Batlló');
INSERT INTO cases (name) VALUES ('Casa Gaudí');