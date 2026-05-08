# MongoDB (Atlas) - Connexió des de PHP

Aquest projecte està dockeritzat. La connexió a MongoDB es fa mitjançant:
- **Extensió PHP**: `mongodb` (PECL) instal·lada al contenidor `web`
- **Llibreria PHP (Composer)**: `mongodb/mongodb`
- **Variable d'entorn**: `MONGODB_URI`

## 1) Configurar MongoDB Atlas

1. Crea un compte a Atlas i un **Project**.
2. Crea un **Cluster** (M0 gratuït serveix per desenvolupament).
3. **Database Access** → crea un usuari (username/password).
4. **Network Access** → afegeix una IP:
   - Per desenvolupament ràpid: `0.0.0.0/0` (NO recomanat a producció).
   - Millor: la teva IP pública actual.
5. **Connect** → **Drivers** → copia el connection string `mongodb+srv://...`.

Exemple (NO real):

`mongodb+srv://user:pass@cluster0.xxxxx.mongodb.net/incidencies?retryWrites=true&w=majority&appName=Projecte`

## 2) Afegir variables d'entorn

Copia [.env.example](../.env.example) a `.env` i omple:

- `MONGODB_URI`
   - Dev (local): `mongodb://mongo:27017/incidencies`
   - Prod (Atlas): `mongodb+srv://.../incidencies?...`

## 3) Construir i aixecar els contenidors

Com que hem afegit dependències al contenidor PHP, cal reconstruir:

- `docker compose build web`
- `docker compose up -d`

## 4) Instal·lar la llibreria PHP (Composer)

La dependència està declarada a [php/composer.json](../php/composer.json).
Instal·la-la dins del contenidor (quedarà a `php/vendor/` perquè és un volum):

- `docker compose exec web composer install`

## 5) Provar la connexió

Executa l'script de test:

- `docker compose exec web php /var/www/html/test_mongo.php`

Si tot va bé, veuràs un `Ping result` i un `Write/read OK`.

## Fitxers importants

- [images/Dockerfile_php](../images/Dockerfile_php): instal·la `ext-mongodb` i `composer`
- [docker-compose.yaml](../docker-compose.yaml): passa `MONGODB_URI` al contenidor
- [php/incidencies/mongo_connexio.php](../php/incidencies/mongo_connexio.php): helper per obtenir `MongoDB\Database`
- [php/test_mongo.php](../php/test_mongo.php): test de connexió
