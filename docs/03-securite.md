# Sécurité

## Authentification

Firewall `main` avec **form_login** :

- `login_path` / `check_path` : `app_login`
- paramètres attendus :
  - username : `_username`
  - password : `_password`
- CSRF activé :
  - `csrf_parameter: _csrf_token`
  - `csrf_token_id: authenticate`
- redirection après login : `user_dashboard`
- logout :
  - route : `app_logout`
  - redirection : `app_login`
- remember-me :
  - lifetime : 604800 (1 semaine)
  - secret : `%kernel.secret%`

> La protection CSRF est activée globalement dans `framework.yaml` (`csrf_protection: true`).

## Provider utilisateur

Provider Doctrine sur `App\Entity\User` via `email`.

## Rôles

- stockés en base : `[]` ou `['ROLE_ADMIN']`
- `ROLE_USER` est ajouté dynamiquement dans `User::getRoles()`

## Access control

- `/login`, `/register`, `/logout` : public
- `/dashboard` : `ROLE_USER`
- `/admin/*` : `ROLE_ADMIN` (+ éventuellement `requires_channel: https`)

## UserChecker

`App\Security\UserChecker` (checkPreAuth) :

- bloque si compte archivé
- bloque si email non vérifié