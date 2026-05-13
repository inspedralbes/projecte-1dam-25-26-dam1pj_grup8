# transversals
Esquema mínim de carpetes pels projectes transversals

És obligatori seguir aquesta estructura tot i que la podeu ampliar.

## Atenció
Un cop comenceu heu de canviar aquesta explicació amb la corresponent al vostre projecte (utilitzant markdown)

# Aquest fitxer ha de contenir com a mínim:
 * Nom dels integrants
 * Nom del projecte
 * Petita descripció
 * Adreça del gestor de tasques (taiga, jira, trello...)
 * Adreça del prototip gràfic del projecte (Penpot, figma, moqups...)
 * URL de producció (quan la tingueu)
 * Estat: (explicació d'en quin punt està)

## CI/CD de producció

El desplegament automàtic queda configurat amb GitHub Actions a `.github/workflows/deploy-prod.yml`.

Secrets necessaris a GitHub:

* `PROD_SSH_HOST`
* `PROD_SSH_USER`
* `PROD_SSH_KEY`
* `PROD_SSH_PORT` opcional, per defecte `22`
* `PROD_DEPLOY_PATH`
* `PROD_VAR2`
* `PROD_MYSQL_ROOT_PASSWORD`
* `PROD_MYSQL_USER`
* `PROD_MYSQL_PASSWORD`
* `PROD_MONGODB_URI`
* `PROD_MONGODB_DB` (opcional, si la URI no inclou /<db>)

El workflow fa build de la imatge i, si el push és a `main`, entra per SSH al servidor i executa `docker compose -f docker-compose.prod.yml up -d --build`.
