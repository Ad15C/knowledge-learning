# Knowledge Learning

![Symfony](https://img.shields.io/badge/Symfony-6.4-black)
![PHP](https://img.shields.io/badge/PHP-8.1+-blue)
![Doctrine](https://img.shields.io/badge/Doctrine-ORM-orange)
![MySQL](https://img.shields.io/badge/MySQL-8-blue)
![Tests](https://img.shields.io/badge/Tests-PHPUnit-green)

## Description

**Knowledge Learning** est une application web développée avec **Symfony 6.4** permettant de gérer une plateforme d'apprentissage en ligne.

Les utilisateurs peuvent :

* explorer des **thèmes et cursus**
* suivre des **leçons**
* valider leur progression
* acheter du contenu pédagogique
* obtenir des **certifications**
* **contacter les administrateurs via un formulaire de message**

Les administrateurs disposent d’un **dashboard d’administration** permettant de gérer le contenu pédagogique, les utilisateurs, les achats et les messages.

---

# Fonctionnalités

## Utilisateurs

* inscription
* connexion sécurisée
* vérification email
* modification du profil
* changement de mot de passe
* archivage des comptes

## Contenu pédagogique

* gestion des **thèmes**
* gestion des **cursus**
* gestion des **leçons**
* progression utilisateur
* validation des leçons

## Achat de contenu

* panier
* validation d'achat
* historique des achats
* gestion des éléments achetés (leçons ou cursus)

## Certifications

* validation de cursus
* génération de **certificats PDF**

## Messagerie / Contact

* les utilisateurs peuvent **envoyer un message aux administrateurs**
* les administrateurs peuvent **consulter les messages**
* les administrateurs peuvent **marquer les messages comme lus**
* les administrateurs peuvent **traiter les demandes**

---

# Stack technique

## Backend

* PHP **8.1+**
* **Symfony 6.4**
* **Doctrine ORM**
* **Doctrine Migrations**

## Base de données

* **MySQL 8**

## Frontend

* **Twig**
* **CSS**
* **Stimulus**
* **Symfony AssetMapper**

## Tests

* **PHPUnit**
* **Symfony BrowserKit**
* **Liip Test Fixtures**

## Autres outils

* **Dompdf** (génération de certificats PDF)

---

# Architecture de l'application

L'application est structurée en **trois domaines principaux** :

* Learning (contenu pédagogique)
* E-commerce (achats)
* Support (messagerie)

---

# Domaine Learning

```mermaid
erDiagram
  THEME ||--o{ CURSUS : contains
  CURSUS }|--|| THEME : belongs_to

  CURSUS ||--o{ LESSON : contains
  LESSON }|--|| CURSUS : belongs_to

  USER ||--o{ LESSON_VALIDATED : validates
  LESSON_VALIDATED }|--|| USER : user

  LESSON_VALIDATED }|--|| LESSON : lesson
  LESSON_VALIDATED }o--|| PURCHASE_ITEM : purchase_item

  USER }o--o{ LESSON : completed_lessons

  USER ||--o{ CERTIFICATION : earns
  CERTIFICATION }|--|| USER : user
  CERTIFICATION }o--|| CURSUS : cursus
  CERTIFICATION }o--|| THEME : theme
  CERTIFICATION }o--|| LESSON : lesson
```

---

# Domaine E-commerce

```mermaid
erDiagram
  USER ||--o{ PURCHASE : places
  PURCHASE }|--|| USER : user

  PURCHASE ||--o{ PURCHASE_ITEM : contains
  PURCHASE_ITEM }|--|| PURCHASE : purchase

  PURCHASE_ITEM }o--|| LESSON : lesson
  PURCHASE_ITEM }o--|| CURSUS : cursus
```

---

# Domaine Support (Contact)

```mermaid
erDiagram
  CONTACT {
    int id
    string fullname
    string email
    string subject
    text message
    datetime sentAt
    datetime readAt
    bool handled
    datetime handledAt
  }
```

---

# Installation

## 1. Cloner le projet

```bash
git clone <repository-url>
cd knowledge-learning
```

---

# Installer les dépendances

```bash
composer install
```

---

# Configuration de l'environnement

Créer un fichier `.env.local` :

```env
APP_ENV=dev
APP_SECRET=change_this_secret

DATABASE_URL="mysql://user:password@127.0.0.1:3306/knowledge_learning?serverVersion=8.0&charset=utf8mb4"
```

---

# Base de données

Créer la base :

```bash
php bin/console doctrine:database:create
```

Exécuter les migrations :

```bash
php bin/console doctrine:migrations:migrate
```

---

# Charger les données de test

```bash
php bin/console doctrine:fixtures:load
```

---

# Lancer l'application

Avec **Symfony CLI**

```bash
symfony serve
```

Ou avec **PHP built-in server**

```bash
php -S 127.0.0.1:8000 -t public
```

Application disponible sur :

```
http://127.0.0.1:8000
```

---

# Assets frontend

Installer l'importmap :

```bash
php bin/console importmap:install
```

Nettoyer le cache :

```bash
php bin/console cache:clear
```

---

# Tests

Lancer les tests :

```bash
php bin/phpunit
```

Le projet inclut :

* tests unitaires
* tests fonctionnels
* tests d'intégration
* tests de workflow utilisateur

---

# Commandes personnalisées

Créer un administrateur

```bash
php bin/console app:create-admin
```

Réinitialiser les utilisateurs

```bash
php bin/console app:reset-users
```

Supprimer un utilisateur de test

```bash
php bin/console app:delete-test-user
```

---

# Structure du projet

```
assets/        # CSS et JS
config/        # configuration Symfony
migrations/    # migrations Doctrine
public/        # point d'entrée de l'application
src/           # code source
templates/     # templates Twig
tests/         # tests PHPUnit
translations/  # fichiers de traduction
var/           # cache et logs
```

---

# Licence

Projet propriétaire.
