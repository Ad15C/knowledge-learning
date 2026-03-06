# Workflow 04 — Certifications & PDF

## Génération des certifications

La génération est déclenchée dans :

- `LessonValidatedService::validateLesson()`

## Types

- `lesson`
- `cursus`
- `theme`

## Conditions

### Certification lesson

Créée lorsqu’une leçon est validée.

### Certification cursus

Créée si toutes les leçons du cursus sont validées.

### Certification theme

Créée si toutes les leçons du thème sont validées.

## Données

Chaque certification contient :

- `certificateCode = uniqid('KL-')`
- `issuedAt = DateTimeImmutable`

## Affichage

La page leçon peut afficher la certification associée à la leçon si elle existe déjà.

## PDF

Le téléchargement PDF dépend des routes présentes dans le projet :

- côté user : selon implémentation de l’espace membre
- côté admin : route dédiée de téléchargement si disponible