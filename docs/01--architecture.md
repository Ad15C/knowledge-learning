# Architecture

## Vue d’ensemble

Knowledge Learning est une application Symfony 6.4 organisée en 3 domaines :

- **Learning** : thèmes, cursus, leçons, progression, validations, certifications
- **E-commerce** : panier, achats, items achetés (leçon ou cursus)
- **Support** : messages de contact (suivi admin : lu / traité)

## Organisation du code

- `src/Controller/` : controllers front (parcours utilisateur)
- `src/Controller/Admin/` : controllers admin (dashboard + gestion)
- `src/Entity/` : entités Doctrine (User, Theme, Cursus, Lesson, Purchase…)
- `src/Repository/` : lecture DB et requêtes custom
- `src/Service/` : logique métier
- `src/Form/` : FormTypes (front + admin)
- `src/Security/` : règles sécurité (UserChecker)
- `src/Twig/` : extensions Twig (helpers d’affichage)
- `src/Command/` : commandes CLI
- `src/DataFixtures/` : fixtures

## Routing

Les routes sont déclarées via **attributs PHP**.  
`config/routes` pointe sur `src/Controller/` en `type: attribute`.

Cela permet :

- routes proches du code (controllers)
- refactoring plus simple

---

## Domaine E-commerce : panier et achats

### CartService

Fichier : `src/Service/CartService.php`

Responsabilité :

- calculer le nombre d’items dans le panier de l’utilisateur connecté

Implémentation :

- récupère l’utilisateur via `Security`
- récupère le `Purchase` en statut `Purchase::STATUS_CART`
- retourne `purchase->getItems()->count()`

Implications :

- le panier est stocké **en base** via une entité `Purchase` en statut `cart`
- on distingue panier vs achats “finalisés” via `Purchase.status`

### PurchaseItemRepository (requêtes utiles)

Fichier : `src/Repository/PurchaseItemRepository.php`

Méthodes utiles :

- `findByUserAndStatus(User $user, string $status)` : retrouver les items par statut de `Purchase` (`cart`, `paid`…)
- `findByUserAndCursus(User $user, Cursus $cursus)` : vérifier si un cursus a été acheté
- `findLessonsPurchasedByUser(User $user)` : retourne les leçons achetées **et payées**

---

## Domaine Learning : validation et certifications

### LessonValidatedService

Fichier : `src/Service/LessonValidatedService.php`

Responsabilité :

- valider une leçon pour un utilisateur
- créer automatiquement des certifications quand les conditions sont remplies

#### `validateLesson(User $user, Lesson $lesson, ?PurchaseItem $purchaseItem = null)`

Étapes :

1. Cherche une validation existante (`LessonValidatedRepository::findOneBy(user, lesson)`).
2. Si elle existe :
   - si elle n’est pas `completed`, la marque comme complétée et flush
   - retourne l’existante
3. Sinon :
   - crée un nouvel objet `LessonValidated`
   - associe `user`, `lesson` et (optionnel) `purchaseItem`
   - marque completed
   - persist + flush
4. Crée une certification de type **lesson** (si pas déjà existante)
5. Si toutes les leçons d’un **thème** sont validées :
   - crée une certification type **theme**
6. Si toutes les leçons d’un **cursus** sont validées :
   - crée une certification type **cursus**

#### Certification (types)

Types utilisés :

- `lesson`
- `theme`
- `cursus`

Code certificat :

- `uniqid('KL-')`

Date :

- `issuedAt: DateTimeImmutable`

### LessonValidatedRepository

Fichier : `src/Repository/LessonValidatedRepository.php`

Méthode clé :

- `hasCompletedTheme(User $user, Theme $theme): bool`
  - compare le nombre de validations dans le thème vs le nombre total de leçons dans ce thème

Autre méthode utile :

- `findValidatedLessonsForUser(User $user)`
  - renvoie l’historique des validations (avec joins cursus + thème)

---

## Twig Extensions

### PurchaseExtension

- `purchase_status_label(status)` : libellé humain (cart/pending/paid/canceled)
- `purchase_status_class(status)` : classes CSS `badge ...`

### PurchaseItemsExtension

- `purchase_items_count(purchase)` : nombre d’items
- `purchase_items_quantity(purchase)` : somme des quantités

---

## Templates

Dossier `templates/` structuré par feature :

- `themes/`, `cursus/`, `lesson/` : learning
- `cart/` : e-commerce
- `contact/` : support
- `registration/`, `security/`, `user/` : compte
- `admin/`, `dashboard/` : back-office
- `base.html.twig`, `macros.html.twig` : layout + helpers