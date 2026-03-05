# Workflows métier

## 1) Visibilité catalogue (Learning)

### Objectif

Définir ce qui est visible publiquement (côté catalogue) indépendamment de l’achat.

### Important : “visible dans le catalogue” ≠ “accessible gratuitement”

- La visibilité catalogue sert à **lister/afficher** thèmes/cursus/leçons dans le site.
- L’accès au contenu d’une leçon est protégé par un **paywall** (voir section 3).

### Helpers côté entités (lisibilité)

Ces méthodes sont des helpers simples (actif + parent actif) :

- `Theme::isActive()` : visibilité globale d’un thème.
- `Cursus::isVisibleInCatalog()` :
  - `cursus.isActive = true`
  - ET `theme.isActive = true`
- `Lesson::isVisibleInCatalog()` :
  - `lesson.isActive = true`
  - ET `lesson.cursus.isVisibleInCatalog() = true`

⚠️ Ces helpers ne garantissent pas la règle “au moins une leçon active”.

### Règles complètes de visibilité catalogue (Repositories)

Les règles “au moins un enfant actif” sont implémentées en requêtes (Doctrine) :

- **Theme visible** si :
  - `Theme.isActive = true`
  - ET il existe au moins 1 `Cursus.isActive = true`
  - ET ce cursus possède au moins 1 `Lesson.isActive = true`
  - Implémenté dans :
    - `ThemeRepository::findVisibleThemesWithFilters()`
    - `ThemeRepository::findVisibleTheme()`

- **Cursus visible** si :
  - `Cursus.isActive = true`
  - ET `Theme.isActive = true`
  - ET il possède au moins 1 `Lesson.isActive = true`
  - Implémenté dans :
    - `CursusRepository::findVisibleByTheme()`
    - `CursusRepository::findVisibleWithVisibleLessons()`

- **Lesson visible/trouvable** si :
  - `Lesson.isActive = true`
  - ET `Cursus.isActive = true`
  - ET `Theme.isActive = true`
  - Implémenté dans :
    - `LessonRepository::findVisibleLesson()`

---

## 2) Authentification & statut de compte (Users)

### Accès routes

- `/login` : public
- `/register` : public
- `/logout` : public
- `/dashboard` : `ROLE_USER`
- `/admin/*` : `ROLE_ADMIN`

### Connexion (form_login)

1. L’utilisateur accède au formulaire login.
2. Soumission des identifiants.
3. Le provider charge `User` via `email`.
4. Avant finalisation, `UserChecker::checkPreAuth()` applique :
   - blocage si compte archivé
   - blocage si email non vérifié
5. Succès :
   - redirection vers `user_dashboard`
   - remember-me : 1 semaine

---

## 3) Learning : consulter une leçon + paywall

### Routes

- `GET /lesson/{id}` → `lesson_show`
- `POST /lesson/{id}/complete` → `lesson_complete`

### Pré-requis

Le `LessonController` est protégé par :

- `#[IsGranted('ROLE_USER')]`

Donc :

- utilisateur non connecté → aucune page leçon accessible.

### Paywall (règle serveur centrale)

L’accès réel à une leçon (contenu + validation) est centralisé dans :

- `LessonAccessService::userCanAccessLesson(User $user, Lesson $lesson): bool`

Règle :

- Admin : accès total
- Sinon : accès UNIQUEMENT si achat **PAYÉ** :
  - achat de la leçon (PurchaseItem.lesson)
  - OU achat du cursus (PurchaseItem.cursus)
  - et `Purchase.status = Purchase::STATUS_PAID`

Conséquence :

- user connecté mais non payé → redirection + message
- user payé → accès au contenu + possibilité de valider

---

## 4) Learning : valider une leçon (completion + certifs)

### Route

- `POST /lesson/{id}/complete` → `lesson_complete`

### Sécurités

1. `ROLE_USER`
2. CSRF token :
   - id : `lesson_complete_{lessonId}`
3. Paywall :
   - vérification via `LessonAccessService::userCanAccessLesson()`
   - si refus : flash danger + redirection (ex : `cart_show`)

### Action

- `LessonValidatedService::validateLesson($user, $lesson)`

Effets :

- crée/met à jour `LessonValidated`
- crée certifications `lesson`, `theme`, `cursus` selon conditions

---

## 5) E-commerce : panier (Purchase en base)

Routes principales :

- `GET /cart` → `cart_show`
- `POST /cart/add/lesson/{id}` → `cart_add_lesson`
- `POST /cart/add/cursus/{id}` → `cart_add_cursus`
- `POST /cart/remove/{type}/{id}` → `cart_remove`
- `POST /cart/pay` → `cart_pay`
- `GET /cart/success/{orderNumber}` → `cart_success`

Paiement simulé :

- `purchase->calculateTotal()`
- `purchase->markPaid()`
- flush
- redirect `cart_success`

---

## 6) Support : contact utilisateur & traitement admin

- côté user : création de `Contact`
- côté admin : lecture / marquage lu / marquage traité
- toutes les actions admin sensibles utilisent CSRF

---

## 7) PDF : téléchargement d’un certificat

- admin : `GET /admin/certifications/{id}/download`
- rendu Twig → Dompdf → PDF