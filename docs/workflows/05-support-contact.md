# Workflow 05 — Support-contact (user → admin)

## Côté utilisateur

- formulaire `ContactFormType`
- persist `Contact`
- tentative d’envoi email (Mailer)

## Côté admin

- `GET /admin/contact/` (filtres)
- `GET /admin/contact/{id}` (auto mark read)
- `POST` : read / unread / handled (CSRF)