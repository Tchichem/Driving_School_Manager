# Driving School Manager

![alt text](https://github.com/Tchichem/Driving_School_Manager/blob/main/app_screenshots/user_ss1.png "User managing students screenshot")
![alt text](https://github.com/Tchichem/Driving_School_Manager/blob/main/app_screenshots/user_ss2.png "User managing courses")

Application web de gestion interne pour une auto-école, développée avec Symfony 8.1 et SQL Server.

## Fonctionnalités

- **Élèves** — inscription, suivi des résultats code/conduite, recherche et tri
- **Moniteurs** — gestion des moniteurs et de leur disponibilité
- **Véhicules & Modèles** — gestion du parc automobile
- **Rendez-vous** — prise de rendez-vous via calendrier interactif (FullCalendar), détection des conflits d'horaires
- **Statistiques** — réussites et élèves en difficulté de l'année en cours
- **Authentification** — système de connexion avec deux rôles (Admin / Utilisateur)
- **Gestion des utilisateurs** — CRUD complet réservé à l'administrateur
- **Mentions légales** — conformité RGPD

## Stack technique

- PHP 8.4+
- Symfony 8.1
- SQL Server (driver pdo_sqlsrv)
- Doctrine ORM
- KnpPaginatorBundle
- FullCalendar 6
- Twig

## Prérequis

- PHP 8.4 ou supérieur
- SQL Server avec les drivers PHP officiels Microsoft
- Composer
- Symfony CLI

## Installation

**1. Cloner le projet**
```bash
git clone https://github.com/Tchichem/Driving_School_Manager.git
cd nom-repo
```

**2. Installer les dépendances**
```bash
composer install
```

**3. Installer les drivers PHP SQL Server**

Télécharger les DLL correspondant à votre version PHP :
https://learn.microsoft.com/fr-fr/sql/connect/php/download-drivers-php-sql-server

Vérifier votre version PHP et le mode Thread Safety :
```bash
php -v
php -i | findstr "Thread"
```

Ajouter dans `php.ini` (adapter le numéro de version) :
```ini
extension=php_sqlsrv_84_ts_x64
extension=php_pdo_sqlsrv_84_ts_x64
```

Vérifier l'installation :
```bash
php -m | findstr sqlsrv
```

**4. Configurer la connexion base de données**

Modifier `config/packages/doctrine.yaml` :
```yaml
doctrine:
    dbal:
        driver: pdo_sqlsrv
        host: "localhost\\NOM_DE_VOTRE_INSTANCE"
        dbname: NOM_DE_VOTRE_BASE
        user: ""
        charset: UTF-8
        options:
            TrustServerCertificate: 1
```

Pour une authentification SQL Server avec login/mot de passe, renseigner `user` et `password`. Pour l'authentification Windows, laisser `user` vide.

**5. Importer la base de données**

Exécuter le script SQL fourni `ScriptBDD.sql` à la racine du projet dans SQL Server Management Studio.

**6. Créer les comptes utilisateurs**

Hasher un mot de passe :
```bash
php bin/console security:hash-password
```

Puis insérer les comptes dans SQL Server Management Studio :
```sql
INSERT INTO [user] (email, roles, password) VALUES
('admin@ael.fr', '["ROLE_ADMIN"]', 'VOTRE_HASH'),
('user@ael.fr', '["ROLE_USER"]', 'VOTRE_HASH');
```

**7. Lancer le serveur**
```bash
symfony server:start
```

L'application est accessible sur `https://localhost:8000`.

## Rôles

| Rôle | Droits |
|---|---|
| `ROLE_USER` | Créer des élèves, moniteurs, véhicules et rendez-vous. Modifier les statuts (code, conduite, activité, état). |
| `ROLE_ADMIN` | Accès complet — modifier et supprimer toutes les données, gérer les utilisateurs. |

![alt text](https://github.com/Tchichem/Driving_School_Manager/blob/main/app_screenshots/admin_ss1.png "Admin managing users")

## Auteur

Hichem — Formation Concepteur Développeur d'Applications (CDA) — AFPA Nice 2026
