# Resum del projecte: GRUP8 - Gestor d'Incidències

Data: 15/05/2026

## Visió general

Aplicació web per gestionar incidències d'un institut amb diferents rols:
- ADMIN
- RESPONSABLE
- TECNIC
- PROFESSOR

Emmagatzematge:
- Incidències i dades del sistema: MySQL (taula `incidencies`, `worklogs`, `USUARI`, `TECNIC`...).
- Accés/telemetria: MongoDB (`access_logs`) per registrar accessos i generar estadístiques.

Tecnologies principals:
- PHP + Apache (contenidor `web`).
- MySQL (contenidor `db`).
- MongoDB opcional (contenidor `mongo`, per analytics).
- Docker Compose per l'entorn de desenvolupament.

## Estructura del projecte (resum)

- `php/` : codi principal de l'aplicació web.
  - `incidencies/` : helpers, esquema, logger, CRUD d'incidències.
  - `auth/` : login, register, verify, logout.
  - `admin/`, `professor/`, `tecnic/`, `responsable/` : pantalles segons rol.
  - `js/`, `css/`, `img/` : actius públics.
- `images/` : Dockerfiles i entrypoint per la imatge PHP.
- `db_init/` : scripts SQL d'inicialització (executats al crear el contenidor MySQL).
- `.env` : configuració env local (no pujar a producció sense seguretat).

## Com funciona el logging (punt destacat)

Originalment el logger gravava exclusivament a MongoDB (`access_logs`). Això provocava problemes en entorns on no està disponible `ext-mongodb` o on no es puja `vendor/` (ex. desplegament via FTP/FileZilla).

Canvis realitzats per fer-ho robust:
1. `php/incidencies/logger.php` ahora intenta en aquest ordre:
   - MongoDB (si està disponible)
   - MySQL (`access_logs`), creada si cal
   - Fitxer local `php/storage/access_logs.jsonl`

Això garanteix que els logs quedin persistents encara que l'entorn de producció no tingui Mongo.

## Punts importants i recomanacions

- En producció, és preferible tenir MongoDB i la extensió `ext-mongodb` si vols estadístiques en temps real i anàlisi.
- Si desplegues via FTP (FileZilla) assegura't de pujar el directori `php/storage` o comprova permisos perquè l'app pugui escriure-hi.
- No oblideu mantenir `php/vendor/` o executar `composer install` al servidor si voleu fer servir la llibreria oficial de MongoDB.
- Les credencials i URIs han d'estar en variables d'entorn (`.env`) i NO pujar-les al repositori.

## Com generar l'entorn local

1. Copia `.env.example` a `.env` i ajusta variables.
2. `docker compose up -d --build`
3. Accedeix a `http://localhost:8080` (web) i `http://localhost:8081` (Adminer)

## Rutes/claus del backend

- `index.php` - Landing
- `auth/login.php`, `auth/register.php`, `auth/logout.php`, `auth/verify.php`
- `incidencies/crear.php`, `incidencies/llistar.php`, `incidencies/detall_incidencia.php`
- `admin/admin.php`, `admin/admin_stats.php` (API JSON per a les estadístiques)

## Canvis tècnics rellevants (resum)

- Logger amb fallback (Mongo -> MySQL -> JSONL)
- `admin_stats.php` fa fallback a MySQL per retornar estadístiques quan Mongo falla
- `php/storage/.htaccess` afegit per protegir logs del públic
- Comentaris d'encapçalament afegits a fitxers clau per a documentació ràpida

## Revisions i proves fetes

- Validació sintàctica PHP (`php -l`) en fitxers modificats dins del contenidor.
- Prova E2E: aturar `mongo`, fer una petició HTTP i verificar que s'ha inserit a `access_logs` en MySQL.

---

Per a qualsevol apartat que vulguis ampliar (diagrama d'arquitectura, exemples d'API, o un manual d'instal·lació detallat), digues-me quina profunditat vols i ho afegeixo al PDF.
