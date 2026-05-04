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
  estat VARCHAR(30) NOT NULL DEFAULT 'pendent_assignar',
  tecnic_assignat VARCHAR(80) NULL,
  data_tancament TIMESTAMP NULL DEFAULT NULL
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

-- Dades inicials (només primer cop). Si el teu entorn ja tenia volum, mira `php/tecnic_schema.php`.
INSERT INTO TECNIC (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROL_EMPLOYEE)
VALUES ('Responsable', 'Tècnic', 'responsable@local', 'responsable', '000000000', 'ENCARGADO');

INSERT INTO TECNIC (FIRST_NAME, LAST_NAME, EMAIL, PASSWORD, PHONE_NUMBER, ROL_EMPLOYEE)
VALUES ('Tècnic', '1', 'tecnic1@local', 'tecnic', '000000001', 'TECNICO');




-- Afegim algunes dades inicials a la taula cases
INSERT INTO cases (name) VALUES ('Casa Milà');
INSERT INTO cases (name) VALUES ('Casa Batlló');
INSERT INTO cases (name) VALUES ('Casa Gaudí');