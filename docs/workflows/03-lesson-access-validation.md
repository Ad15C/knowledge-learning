# Workflow 03 — Lesson-access-validation

## Objectif

Permettre à un utilisateur d’accéder à une leçon seulement s’il l’a achetée, puis de la marquer comme complétée.

## Affichage d’une leçon

Route : `GET /lesson/{id}`

1. Chargement via `LessonRepository::findVisibleLesson(id)` :
   - leçon active
   - cursus actif
   - thème actif
2. Paywall serveur :
   - `LessonAccessService::userCanAccessLesson(user, lesson)`
   - refus → redirection + flash
3. Données utiles :
   - `userHasCompleted[lessonId]` basé sur `LessonValidated`

## Marquer comme complétée

Route : `POST /lesson/{id}/complete`

- CSRF : `lesson_complete_{lessonId}`
- Paywall serveur :
  - `LessonAccessService::userCanAccessLesson(user, lesson)`
- Action :
  - `LessonValidatedService::validateLesson(user, lesson)`