# Déploiement

## Variables d’environnement

Minimales :

- `APP_ENV=prod`
- `APP_SECRET=...`
- `DATABASE_URL=...`

Optionnelles selon usage :

- `MAILER_DSN=...`
- `MESSENGER_TRANSPORT_DSN=...`

## Installation en production

Exemple :

```bash
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear
php bin/console cache:warmup