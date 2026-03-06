# Base de données

## Vue d’ensemble

La base est gérée via Doctrine ORM + migrations.

Domaines :

- Learning
- E-commerce
- Support
- Identité

---

## User

Champs :

- `id`
- `email`
- `roles`
- `password`
- `firstName`
- `lastName`
- `isVerified`
- `verificationToken`
- `verificationTokenExpiresAt`
- `createdAt`
- `archivedAt`

Relations :

- OneToMany `lessonValidated`
- OneToMany `purchases`
- OneToMany `certifications`
- ManyToMany `completedLessons`

---

## Theme

Champs :

- `id`
- `name`
- `description` nullable
- `image` nullable
- `createdAt` (`datetime_immutable`)
- `isActive`

Relations :

- OneToMany `cursus`

Notes :

- `createdAt` est initialisé en `DateTimeImmutable`
- les cursus sont ordonnés par nom

---

## Cursus

Champs :

- `id`
- `name`
- `price` (`decimal`, manipulé côté PHP comme `string`)
- `description` nullable
- `image` nullable
- `isActive`

Relations :

- ManyToOne `theme`
- OneToMany `lessons`

Notes :

- `price` est stocké et manipulé de manière cohérente avec Doctrine `decimal`
- les leçons sont ordonnées via `OrderBy(['title' => 'ASC'])`

Helper :

- `isVisibleInCatalog()` :
  - cursus actif
  - thème actif

---

## Lesson

Champs :

- `id`
- `title`
- `price` (`decimal`, manipulé côté PHP comme `string`)
- `fiche` nullable
- `videoUrl` nullable
- `image` nullable
- `isActive`

Relations :

- ManyToOne `cursus`

Notes :

- `price` est conservé comme `string` pour rester cohérent avec Doctrine `decimal`

Helper :

- `isVisibleInCatalog()` :
  - leçon active
  - cursus visible

---

## LessonValidated

Champs :

- `id`
- `validatedAt`
- `completed`
- `user`
- `lesson`
- `purchaseItem` nullable

Contraintes :

- unicité fonctionnelle `(user, lesson)`

---

## Certification

Champs :

- `id`
- `issuedAt`
- `certificateCode`
- `type`

Relations :

- ManyToOne `user`
- ManyToOne `lesson` nullable
- ManyToOne `cursus` nullable
- ManyToOne `theme` nullable

Types :

- `lesson`
- `cursus`
- `theme`

---

## Purchase

Champs :

- `id`
- `orderNumber`
- `status`
- `total`
- `createdAt`
- `paidAt`

Relations :

- ManyToOne `user`
- OneToMany `items`

Statuts :

- `cart`
- `pending`
- `paid`
- `canceled`

---

## PurchaseItem

Champs :

- `id`
- `quantity`
- `unitPrice`

Relations :

- ManyToOne `purchase`
- ManyToOne `lesson` nullable
- ManyToOne `cursus` nullable

Règle :

- un item représente soit une leçon, soit un cursus

Recommandation :

- garantir applicativement la cohérence `(lesson XOR cursus)`

---

## Contact

Champs :

- `id`
- `fullname`
- `email`
- `subject`
- `message`
- `sentAt`
- `readAt`
- `handled`
- `handledAt`