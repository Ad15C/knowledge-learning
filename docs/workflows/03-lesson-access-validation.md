# Workflow 03 — Lesson access & validation

## Objectif

Permettre à un utilisateur d’accéder à une leçon seulement s’il l’a achetée, puis de la marquer comme complétée.

## Scénario fonctionnel final

- visiteur non connecté :
  - voit le catalogue
  - ne voit pas le contenu de la leçon

- utilisateur connecté non payé :
  - voit le catalogue
  - peut acheter
  - ne peut pas ouvrir la leçon

- utilisateur connecté payé :
  - peut ouvrir la leçon
  - peut la valider

---

## Affichage d’une leçon

Route :

- `GET /lesson/{id}`

Étapes :

1. chargement via `LessonRepository::findVisibleLesson(id)`
   - leçon active
   - cursus actif
   - thème actif

2. utilisateur connecté requis
   - `LessonController` est protégé par `#[IsGranted('ROLE_USER')]`

3. vérification du paywall
   - `LessonAccessService::userCanAccessLesson(user, lesson)`

4. si accès refusé
   - flash danger
   - redirection vers `cursus_show`

5. si accès accordé
   - chargement de la map `userHasCompleted`
   - chargement de la certification de type `lesson`
   - rendu `lesson/show.html.twig`

---

## Validation d’une leçon

Route :

- `POST /lesson/{id}/complete`

Contrôles :

- leçon visible
- utilisateur connecté
- token CSRF valide
- accès payé obligatoire

Action :

- `LessonValidatedService::validateLesson(user, lesson)`

Effets :

- validation créée ou mise à jour
- certification de leçon si nécessaire
- certification de cursus si cursus terminé
- certification de thème si thème terminé

Redirection :

- retour vers `lesson_show`
- flash success