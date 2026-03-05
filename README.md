# Knowledge Learning

![Symfony](https://img.shields.io/badge/Symfony-6.4-black)
![PHP](https://img.shields.io/badge/PHP-8.1+-blue)
![Doctrine](https://img.shields.io/badge/Doctrine-ORM-orange)
![MySQL](https://img.shields.io/badge/MySQL-8-blue)
![Tests](https://img.shields.io/badge/Tests-PHPUnit-green)

## Description

**Knowledge Learning** est une plateforme d’apprentissage en ligne développée avec **Symfony 6.4**.

L’application permet aux utilisateurs de suivre des contenus pédagogiques structurés en **thèmes, cursus et leçons**, de suivre leur progression, d’acheter du contenu et d’obtenir des **certifications PDF**.

Une interface d’administration permet de gérer :

* les contenus pédagogiques
* les utilisateurs
* les achats
* les messages envoyés via le formulaire de contact

---

# Fonctionnalités

## Gestion des utilisateurs

* inscription
* connexion sécurisée
* vérification email
* modification du profil
* changement de mot de passe
* archivage de compte

## Plateforme d'apprentissage

* gestion des **thèmes**
* gestion des **cursus**
* gestion des **leçons**
* suivi de progression utilisateur
* validation des leçons

## E-commerce

* panier
* achat de leçons ou de cursus
* historique des achats
* gestion des éléments achetés

## Certifications

* validation des cursus
* génération automatique de **certificats PDF**

## Support

* formulaire de **contact utilisateur**
* consultation des messages côté administration
* marquage des messages comme lus
* suivi du traitement des demandes

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

L'application est organisée autour de **trois domaines fonctionnels** :

* **Learning** — gestion du contenu pédagogique
* **E-commerce** — gestion des achats
* **Support** — gestion des messages utilisateurs

La documentation détaillée est disponible dans le dossier :

docs/

---

# Installation

## Cloner le projet

git clone <repository-url>
cd knowledge-learning

---

# Installer les dépendances

composer install

---

# Configuration de l'environnement

Créer un fichier `.env.local` :

APP_ENV=dev
APP_SECRET=change_this_secret

DATABASE_URL="mysql://user:password@127.0.0.1:3306/knowledge_learning?serverVersion=8.0&charset=utf8mb4"

---

# Base de données

Créer la base :

php bin/console doctrine:database:create

Exécuter les migrations :

php bin/console doctrine:migrations:migrate

---

# Charger les données de test

php bin/console doctrine:fixtures:load

---

# Lancer l'application

Avec **Symfony CLI**

symfony serve

Ou avec **PHP built-in server**

php -S 127.0.0.1:8000 -t public

Application disponible sur :

http://127.0.0.1:8000

---

# Tests

Lancer les tests :

php bin/phpunit

Le projet inclut :

* tests unitaires
* tests fonctionnels
* tests d’intégration
* tests de workflow utilisateur

---

# Commandes personnalisées

Créer un administrateur

php bin/console app:create-admin

Réinitialiser les utilisateurs

php bin/console app:reset-users

Supprimer un utilisateur de test

php bin/console app:delete-test-user

---

# Structure du projet

assets/        CSS et JS
config/        configuration Symfony
migrations/    migrations Doctrine
public/        point d'entrée de l'application
src/           code source
templates/     templates Twig
tests/         tests PHPUnit
translations/  traductions
var/           cache et logs

---

# Documentation

Une documentation plus détaillée est disponible dans le dossier :

docs/

Elle couvre :

* architecture détaillée
* workflows métier
* sécurité
* déploiement
* décisions d’architecture

---

# Licence

Projet propriétaire.
