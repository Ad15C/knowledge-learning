# Base de données

## Vue d’ensemble

La base est gérée via Doctrine ORM + Migrations, sur MySQL 8.

Domaines :

- Learning : Theme, Cursus, Lesson, LessonValidated, Certification
- E-commerce : Purchase, PurchaseItem
- Support : Contact
- Identité : User

---

## Entités

### User (`user`)

Champs :

- `id` (PK)
- `email` (unique)
- `roles` (array)
- `password`
- `firstName`, `lastName`
- `isVerified`
- `verificationToken` (nullable)
- `verificationTokenExpiresAt` (nullable)
- `createdAt` (nullable)
- `archivedAt` (nullable)

Relations :

- OneToMany `lessonValidated`
- OneToMany `purchases`
- OneToMany `certifications`

---

### Theme

Champs :

- `id`
- `name`
- `description` (nullable)
- `image` (nullable)
- `createdAt`
- `isActive`

Relations :

- OneToMany `cursus`

---

### Cursus

Champs :

- `id`
- `name`
- `price`
- `description` (nullable)
- `image` (nullable)
- `isActive`

Relations :

- ManyToOne `theme` (non nullable)
- OneToMany `lessons`

Helper catalogue :

- `isVisibleInCatalog()` ⇔ cursus actif ET theme actif  
  (la règle “au moins une leçon active” est gérée par requête Repository côté front)

---

### Lesson

Champs :

- `id`
- `title`
- `price`
- `fiche` (nullable)
- `videoUrl` (nullable)
- `image` (nullable)
- `isActive`

Relations :

- ManyToOne `cursus` (non nullable)

Helper catalogue :

- `isVisibleInCatalog()` ⇔ leçon active ET cursus visible dans le catalogue  
  (le paywall est géré par `LessonAccessService`)

---

### LessonValidated (`lesson_validated`)

Champs :

- `id`
- `validatedAt` (datetime_immutable)
- `completed` (bool)
- `user_id` (FK)
- `lesson_id` (FK)
- `purchase_item_id` (FK nullable)

Contraintes :

- UniqueConstraint (`user_id`, `lesson_id`)

---

### Certification

Champs :

- `id`
- `issuedAt` (datetime_immutable)
- `certificateCode`
- `type`

Relations :

- ManyToOne `user` (non nullable)
- ManyToOne `cursus` (nullable)
- ManyToOne `theme` (nullable)
- ManyToOne `lesson` (nullable)

---

### Purchase

Statuts :

- `cart`, `pending`, `paid`, `canceled`

Champs :

- `id`
- `orderNumber` (unique)
- `status`
- `total`
- `createdAt`
- `paidAt` (nullable)

Relations :

- ManyToOne `user` (non nullable)
- OneToMany `items` (cascade persist+remove, orphanRemoval)

---

### PurchaseItem

Champs :

- `id`
- `quantity`
- `unitPrice`

Relations :

- ManyToOne `purchase` (non nullable)
- ManyToOne `lesson` (nullable)
- ManyToOne `cursus` (nullable)

Règle :

- `getTotal()` = `unitPrice * quantity`

Recommandation cohérence :

- ajouter une contrainte (applicative ou DB) garantissant :
  - **(lesson XOR cursus)** : exactement un des deux champs non null.

---

### Contact

Champs :

- `id`
- `fullname`, `email`, `subject`, `message`
- `sentAt`
- `readAt` (nullable)
- `handled` (bool)
- `handledAt` (nullable)

Statuts :

- `unread` / `read` / `handled`