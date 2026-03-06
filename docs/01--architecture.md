# Architecture

## Vue d’ensemble

Knowledge Learning est une application Symfony 6.4 organisée en 3 domaines :

- **Learning** : thèmes, cursus, leçons, progression, validations, certifications
- **E-commerce** : panier, achats, items achetés (leçon ou cursus)
- **Support** : messages de contact (suivi admin : lu / traité)

## Organisation du code

- `src/Controller/` : contrôleurs front
- `src/Controller/Admin/` : contrôleurs admin
- `src/Entity/` : entités Doctrine
- `src/Repository/` : requêtes personnalisées
- `src/Service/` : logique métier
- `src/Form/` : formulaires Symfony
- `src/Security/` : sécurité applicative
- `src/Twig/` : extensions Twig
- `src/Command/` : commandes CLI
- `src/DataFixtures/` : fixtures de test/démo

## Routing

Les routes sont déclarées via attributs PHP.  
`config/routes` pointe vers `src/Controller/` avec `type: attribute`.

---

## Domaine Learning

### Entités principales

- `Theme`
- `Cursus`
- `Lesson`
- `LessonValidated`
- `Certification`

### Visibilité catalogue

La visibilité du catalogue repose sur deux niveaux :

#### 1. Helpers sur les entités

Ils servent à exprimer la lisibilité métier simple :

- `Theme::isActive()`
- `Cursus::isVisibleInCatalog()`
- `Lesson::isVisibleInCatalog()`

Ces helpers vérifient l’état actif de l’objet et de ses parents.

#### 2. Règles complètes en repository

Les règles plus riches sont gérées au niveau Doctrine :

- `ThemeRepository::findVisibleThemesWithFilters()`
- `ThemeRepository::findVisibleTheme()`
- `CursusRepository::findVisibleByTheme()`
- `CursusRepository::findVisibleWithVisibleLessons()`
- `LessonRepository::findVisibleLesson()`
- `LessonRepository::findVisibleByCursus()`

Cela permet notamment de garantir qu’un cursus visible possède au moins une leçon active.

---

## Domaine Learning : accès payant

### LessonAccessService

Fichier : `src/Service/LessonAccessService.php`

Responsabilité :

- déterminer si un utilisateur a le droit d’ouvrir une leçon
- construire la map des leçons accessibles dans un cursus

Règles :

- `ROLE_ADMIN` : accès total
- achat d’une leçon payé : accès à cette leçon
- achat d’un cursus payé : accès à toutes les leçons du cursus

Méthodes principales :

- `userCanAccessLesson(User $user, Lesson $lesson): bool`
- `getAccessibleLessonMapForCursus(User $user, Cursus $cursus): array<int,bool>`

Important :

- l’accès est accordé uniquement si `Purchase.status = paid`
- la visibilité catalogue n’accorde jamais l’accès au contenu

---

## Domaine Learning : validation et certifications

### LessonValidatedService

Fichier : `src/Service/LessonValidatedService.php`

Responsabilité :

- marquer une leçon comme complétée
- créer les certifications nécessaires

Comportement :

- si une validation existe déjà, elle est réutilisée
- si elle existe mais n’est pas complétée, elle est marquée complétée
- si elle n’existe pas, elle est créée
- une certification de type `lesson` est créée si besoin
- une certification de type `cursus` est créée si toutes les leçons du cursus sont validées
- une certification de type `theme` est créée si toutes les leçons du thème sont validées

Le service a été simplifié et nettoyé pour réduire les flush inutiles tout en conservant la logique métier.

---

## Contrôleurs front

### CursusController

Responsabilité :

- afficher la page catalogue d’un cursus visible
- exposer au template :
  - `userHasAccess`
  - `userHasCompleted`

Cas d’usage :

- visiteur : voit le catalogue
- connecté : voit aussi quelles leçons sont accessibles
- connecté + progression : voit quelles leçons sont déjà validées

### LessonController

Responsabilité :

- afficher une leçon visible si l’utilisateur a payé
- permettre de marquer la leçon comme complétée

Protection :

- contrôleur protégé par `#[IsGranted('ROLE_USER')]`
- accès réel vérifié via `LessonAccessService`
- POST de validation protégé par CSRF

---

## Domaine E-commerce

### CartService

Responsabilité :

- calculer le nombre d’items dans le panier de l’utilisateur connecté

Implémentation :

- récupération du `Purchase` en statut `cart`
- retour du nombre d’items du panier

### PurchaseItemRepository

Méthodes utiles :

- `findByUserAndStatus(User $user, string $status)`
- `findByUserAndCursus(User $user, Cursus $cursus)`
- `findLessonsPurchasedByUser(User $user)`

---

## Twig Extensions

### PurchaseExtension

- `purchase_status_label(status)` : label métier
- `purchase_status_class(status)` : classes CSS des badges

### PurchaseItemsExtension

- `purchase_items_count(purchase)`
- `purchase_items_quantity(purchase)`

---

## Templates

Dossier `templates/` structuré par domaine :

- `themes/`
- `cursus/`
- `lesson/`
- `cart/`
- `contact/`
- `registration/`
- `security/`
- `user/`
- `admin/`
- `dashboard/`

Templates Learning finaux :

- `templates/cursus/show.html.twig` : catalogue d’un cursus
- `templates/lesson/show.html.twig` : contenu d’une leçon
- `templates/lesson/validated.html.twig` : page optionnelle de confirmation, si utilisée par une route dédiée