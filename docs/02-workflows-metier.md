# Workflows métier

## 1) Visibilité catalogue

### Objectif

Lister les thèmes, cursus et leçons visibles côté front sans confondre cela avec un accès gratuit.

### Principe clé

**Visible dans le catalogue** ≠ **accessible au contenu**

Un objet peut être affiché dans le catalogue sans que son contenu soit consultable.

### Règles

#### Theme visible

- `Theme.isActive = true`
- au moins un cursus actif
- ce cursus possède au moins une leçon active

#### Cursus visible

- `Cursus.isActive = true`
- `Theme.isActive = true`
- au moins une `Lesson.isActive = true`

#### Lesson visible

- `Lesson.isActive = true`
- `Cursus.isActive = true`
- `Theme.isActive = true`

---

## 2) Accès à une leçon

### Objectif

Autoriser l’accès au contenu d’une leçon uniquement après achat payé.

### Règles

#### Visiteur non connecté

- peut voir le catalogue
- ne peut pas ouvrir une leçon

#### Utilisateur connecté non payé

- peut voir le catalogue
- peut acheter une leçon ou un cursus
- ne peut pas ouvrir le contenu

#### Utilisateur connecté payé

- peut ouvrir la leçon
- peut valider la leçon

### Point central

Le contrôle d’accès est centralisé dans :

- `LessonAccessService::userCanAccessLesson()`

Cette règle ne dépend pas du template.

---

## 3) Page cursus

Route :

- `GET /cursus/{id}`

Comportement :

- charge un cursus visible
- si utilisateur connecté :
  - calcule les leçons accessibles
  - calcule les leçons déjà validées
- rend la page catalogue du cursus

Affichage côté template :

- leçon verrouillée
- leçon accessible
- leçon validée

---

## 4) Page leçon

Route :

- `GET /lesson/{id}`

Comportement :

1. recherche la leçon via `LessonRepository::findVisibleLesson()`
2. vérifie que l’utilisateur est connecté
3. vérifie l’accès payant via `LessonAccessService`
4. charge l’état de validation de l’utilisateur
5. charge la certification éventuelle
6. affiche le contenu

---

## 5) Validation d’une leçon

Route :

- `POST /lesson/{id}/complete`

Sécurités :

- `ROLE_USER`
- token CSRF `lesson_complete_{id}`
- accès payant obligatoire

Effets :

- création ou mise à jour de `LessonValidated`
- génération de la certification de leçon si nécessaire
- génération éventuelle de la certification de cursus
- génération éventuelle de la certification de thème

---

## 6) Panier et achat

Routes principales :

- `GET /cart`
- `POST /cart/add/lesson/{id}`
- `POST /cart/add/cursus/{id}`
- `POST /cart/remove/{type}/{id}`
- `POST /cart/pay`
- `GET /cart/success/{orderNumber}`

Statuts métier :

- `cart`
- `pending`
- `paid`
- `canceled`

---

## 7) Certifications

Types de certification :

- `lesson`
- `cursus`
- `theme`

Déclenchement :

- leçon validée → certification lesson
- toutes les leçons d’un cursus validées → certification cursus
- toutes les leçons d’un thème validées → certification theme