# Workflow 04 — Certifications & PDF

## Génération des certifications

Déclenchée par `LessonValidatedService::validateLesson()` :

- certification `type=lesson` après validation d’une leçon
- certification `type=theme` si toutes les leçons d’un thème sont validées
- certification `type=cursus` si toutes les leçons d’un cursus sont validées

Chaque certification :

- `certificateCode = uniqid('KL-')`
- `issuedAt = now`

## Téléchargement PDF

- user : route dédiée dashboard (si existante dans ton projet)
- admin : `GET /admin/certifications/{id}/download`
- rendu Twig → Dompdf → PDF