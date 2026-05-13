# Projecte transversal (1DAM)

Aplicació web de gestió d'incidències per a un institut, amb panell d'administració, registre/login d'usuaris, incidències (MySQL) i registre d'accessos (MongoDB).

**Integrants (1DAM)**
- Àlex Bermúdez
- Paula Paz
- Asier Pozo

**Nom del projecte**
- GRUP8-GESTOR-D'INCIDÉNCIES

**Petita descripció**
- Sistema d'incidències amb diferents rols (ADMIN/PROFESSOR/TECNIC/RESPONSABLE). Les incidències es guarden a MySQL i els accessos a pàgines es guarden a MongoDB (col·lecció `access_logs`).

**Enllaços**
- Gestor de tasques: (pendent d'actualitzar)
- Prototip gràfic: (pendent d'actualitzar)
- Producció: http://g8.dam.inspedralbes.cat/

**Estat**
- En desenvolupament.

## Desenvolupament local (Docker Compose)

Requisits: Docker + Docker Compose.

1) Crea un `.env` a l'arrel.
2) Arrenca:

`docker compose up -d --build`

Serveis:
- Web: `http://localhost:8080`
- Adminer (MySQL): `http://localhost:8081`

MongoDB en local:
- El `docker-compose.yaml` inclou un servei `mongo` i la variable `MONGODB_URI` (exemple): `mongodb://mongo:27017/incidencies`.

## Producció
### Producció sense Docker (hosting tipus Hestia/Apache)

Si en producció s'eliminen `.git` i `.github`, el desplegament s'ha de fer manualment.

Checklist:
- Assegura't que **el servidor té l'extensió PHP `mongodb` (ext-mongodb)** activada.
- Assegura't que **existeix `php/vendor/`** en producció (o executa Composer al servidor) perquè `mongodb/mongodb` funcioni.
- Configura un `.env` (idealment fora de `public_html`) amb:
	- `MONGODB_URI` = URI real de MongoDB Atlas (no `mongodb://mongo:27017/...`)
	- `MONGODB_DB` (opcional)
	- `MYSQL_HOST`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DATABASE` (segons el teu hosting)

El desplegament automàtic queda configurat amb GitHub Actions a `.github/workflows/deploy-prod.yml`.
git p
