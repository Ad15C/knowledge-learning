
---

# Dossier `workflows/`

## `workflows/workflow-01-registration-email-verification.md`

```md
# Workflow 01 — Inscription & vérification email

## Objectif

Créer un utilisateur, puis activer son compte via un token de vérification.

## Étapes

1. L’utilisateur va sur `/register`.
2. Soumission du formulaire `RegistrationFormType`.
3. Création du user :
   - password hashé
   - `verificationToken` généré
   - `verificationTokenExpiresAt` = +1 jour
   - `isVerified = false`
4. Redirection vers `/login`.

## Vérification du token

Route : `GET /verify-email?token=...`

- token manquant / introuvable / expiré → flash error + redirect login
- token valide :
  - `isVerified = true`
  - token supprimé
  - expiration supprimée
  - flash success
  - redirect login

## Sécurité

`UserChecker` bloque :

- utilisateurs non vérifiés
- utilisateurs archivés