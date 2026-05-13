# Projecte transversal (1DAM)

Aplicació web de gestió d'incidències per a un institut, amb panell d'administració, registre/login d'usuaris, incidències (MySQL) i registre d'accessos (MongoDB).

**Integrants (1DAM)**
- Àlex Bermúdez
- Paula Paz
- Asier Pozo

**Nom del projecte**
- (pendent d'actualitzar)

**Petita descripció**
- Sistema d'incidències amb diferents rols (ADMIN/PROFESSOR/TECNIC/RESPONSABLE). Les incidències es guarden a MySQL i els accessos a pàgines es guarden a MongoDB (col·lecció `access_logs`).

**Enllaços**
- Gestor de tasques: (pendent d'actualitzar)
- Prototip gràfic: (pendent d'actualitzar)
- Producció: (pendent d'actualitzar)

**Estat**
- En desenvolupament.

## Desenvolupament local (Docker Compose)

Requisits: Docker + Docker Compose.

1) Crea un `.env` a l'arrel (pots copiar `.env.example`).
2) Arrenca:

`docker compose up -d --build`

Serveis:
- Web: `http://localhost:8080`
- Adminer (MySQL): `http://localhost:8081`

MongoDB en local:
- El `docker-compose.yaml` inclou un servei `mongo` i la variable `MONGODB_URI` (exemple): `mongodb://mongo:27017/incidencies`.

## Producció

Hi ha 2 formes habituals de desplegar (trieu-ne una):

### Opció A — Producció amb Docker (recomanat)

1) Defineix variables d'entorn a un `.env` al servidor (mateixa carpeta que `docker-compose.prod.yml`).
2) Executa:

`docker compose -f docker-compose.prod.yml up -d --build --remove-orphans`

Variables mínimes recomanades:
- `MYSQL_ROOT_PASSWORD`, `MYSQL_USER`, `MYSQL_PASSWORD`
- `MONGODB_URI` (Atlas)
- `MONGODB_DB` (opcional, si la URI no inclou `/<db>`)

### Opció B — Producció sense Docker (hosting tipus Hestia/Apache)

Si en producció s'eliminen `.git` i `.github`, el desplegament s'ha de fer manualment.

Checklist:
- Assegura't que **el servidor té l'extensió PHP `mongodb` (ext-mongodb)** activada.
- Assegura't que **existeix `php/vendor/`** en producció (o executa Composer al servidor) perquè `mongodb/mongodb` funcioni.
- Configura un `.env` (idealment fora de `public_html`) amb:
	- `MONGODB_URI` = URI real de MongoDB Atlas (no `mongodb://mongo:27017/...`)
	- `MONGODB_DB` (opcional)
	- `MYSQL_HOST`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DATABASE` (segons el teu hosting)

Verificació ràpida:
- Obre el JSON d'estadístiques d'admin (a la ruta d'admin) i comprova que `mongoOk=true`. Si surt `errors.mongo`, el missatge indica exactament què falta (URI, DB, extensió, etc.).

## CI/CD de producció (GitHub Actions, només si NO s'elimina `.github`)

El desplegament automàtic queda configurat amb GitHub Actions a `.github/workflows/deploy-prod.yml`.

Secrets necessaris a GitHub:
- `PROD_SSH_HOST`
- `PROD_SSH_USER`
- `PROD_SSH_KEY`
- `PROD_SSH_PORT` (opcional, per defecte `22`)
- `PROD_DEPLOY_PATH`
- `PROD_VAR2`
- `PROD_MYSQL_ROOT_PASSWORD`
- `PROD_MYSQL_USER`
- `PROD_MYSQL_PASSWORD`
- `PROD_MONGODB_URI`
- `PROD_MONGODB_DB` (opcional, si la URI no inclou `/<db>`)
