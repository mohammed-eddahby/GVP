# GVP — Gestion des Visites Préventives

Application complète (PHP 8 / MySQL / XAMPP) de gestion des visites préventives,
avec module d'analytics Python (Pandas, SQLAlchemy, Plotly, Matplotlib).

## 1. Installation sous XAMPP (Windows)

1. Copiez le dossier `pro_ges/` dans `C:\xampp\htdocs\`.
2. Démarrez **Apache** et **MySQL** depuis le panneau de contrôle XAMPP.
3. Ouvrez **phpMyAdmin** (http://localhost/phpmyadmin), onglet **Importer**,
   puis importez le fichier `pro_ges/database.sql` (crée la base `gvp_db`,
   toutes les tables, les clés étrangères et les données de test).
   - `schema.sql` contient uniquement la structure (sans les données), utile
     pour repartir d'une base vide.
4. Ouvrez votre navigateur sur : **http://localhost/pro_ges/login.php**

Aucune configuration supplémentaire n'est nécessaire : `config/database.php`
est déjà réglé pour XAMPP (`host=localhost`, `user=root`, `password=''`,
`database=gvp_db`).

## 2. Comptes de démonstration

Tous les comptes utilisent le mot de passe : **Password123!**

| Rôle            | Email                   |
|-----------------|-------------------------|
| Administrateur  | admin@gvp.ma            |
| Superviseur     | superviseur@gvp.ma      |
| Technicien      | karim.amrani@gvp.ma     |
| Technicien      | nadia.fassi@gvp.ma      |
| Technicien      | omar.tazi@gvp.ma        |

## 3. Rôles et permissions (RBAC)

| Fonctionnalité         | Administrateur | Superviseur | Technicien            |
|-------------------------|:---:|:---:|:---:|
| Gestion des utilisateurs| ✅ | ❌ | ❌ |
| Gestion des clients     | ✅ | ✅ | ❌ |
| Gestion des sites       | ✅ | ✅ | ❌ |
| Créer / assigner visites| ✅ | ✅ | ❌ |
| Voir / mettre à jour ses visites | ✅ | ✅ | ✅ (les siennes uniquement) |
| Créer un rapport        | ✅ | ❌ | ✅ (sur ses visites) |
| Valider / rejeter un rapport | ✅ | ✅ | ❌ |
| Dashboard Analytics      | ✅ | ✅ | ❌ |

La logique RBAC centralisée se trouve dans `includes/auth.php`
(fonctions `can()`, `requireRole()`, `requireLogin()`).

## 4. Structure du projet

```
pro_ges/
├── config/
│   └── database.php          # Connexion PDO MySQL (XAMPP)
├── includes/
│   ├── auth.php               # Session, RBAC, CSRF, journalisation
│   ├── header.php              # Sidebar + topbar communs
│   └── footer.php
├── modules/
│   ├── utilisateurs/          # CRUD utilisateurs (admin)
│   ├── clients/                # CRUD clients
│   ├── sites/                  # CRUD sites
│   ├── visites/                # CRUD visites
│   └── rapports/               # CRUD rapports + validation
├── python_analytics/
│   ├── config.py                # Connexion SQLAlchemy / PyMySQL
│   ├── extract_data.py          # Extraction depuis gvp_db
│   ├── clean_data.py            # Nettoyage pandas
│   ├── kpi_calculator.py        # Calcul des KPI
│   ├── visualizations.py        # Graphiques Plotly (HTML) + Matplotlib (PNG)
│   ├── generate_analytics.py    # Script principal (orchestration)
│   └── requirements.txt
├── assets/
│   ├── css/style.css
│   ├── js/script.js
│   └── analytics/               # Graphiques générés par Python (HTML/PNG)
├── dashboard.php
├── dashboard_analytics.php     # KPI cards + graphiques
├── login.php / login_process.php / logout.php
├── database.sql                 # Schéma complet + données de test
└── schema.sql                    # Schéma seul (sans données)
```

## 5. Module Python Analytics

### Installation

```bash
cd pro_ges/python_analytics
pip install -r requirements.txt
```

### Exécution manuelle

```bash
python generate_analytics.py
```

Le script :
1. Extrait les données de `gvp_db` via SQLAlchemy + PyMySQL.
2. Nettoie les données avec pandas.
3. Calcule les KPI : taux de visites réalisées, visites par technicien,
   visites par mois, top clients, taux de rapports validés.
4. Génère les graphiques dans `assets/analytics/` (Plotly HTML interactifs
   + Matplotlib PNG).
5. Enregistre les KPI dans la table `analytics_kpi`.

### Depuis l'application

Un bouton **« Actualiser les analytics »** est disponible sur
`dashboard_analytics.php` (visible aux administrateurs et superviseurs).
Il relance automatiquement `generate_analytics.py` via `exec()` côté PHP
(nécessite que `python` soit accessible dans le PATH système de XAMPP).

Si Python n'a pas encore été exécuté, le dashboard reste fonctionnel :
les KPI et graphiques de secours sont calculés directement en SQL/PHP.

## 6. Sécurité mise en place

- Mots de passe hachés avec `password_hash()` (bcrypt) / vérifiés avec `password_verify()`.
- Requêtes préparées PDO (`bindValue`/paramètres nommés) contre les injections SQL.
- Protection CSRF sur tous les formulaires POST (`csrfField()` / `csrfCheck()`).
- RBAC centralisé (`can()`, `requireRole()`).
- Régénération de l'ID de session à la connexion (`session_regenerate_id`).
- Journalisation de toutes les actions sensibles dans `journal_activite`.
- Échappement systématique des sorties (`htmlspecialchars`).

## 7. Points vérifiés (tests effectués)

- Import complet de `database.sql` sur MySQL/MariaDB : ✅ toutes les tables et FK créées sans erreur.
- Connexion / déconnexion (admin, superviseur, technicien) : ✅
- RBAC : accès refusé (403) pour un technicien sur les pages réservées : ✅
- CRUD complet testé en conditions réelles (création d'un client via HTTP POST + vérification en base) : ✅
- Script `generate_analytics.py` exécuté de bout en bout : KPI calculés, graphiques générés, écriture en base : ✅
- `dashboard_analytics.php` affichant les graphiques générés (iframes Plotly + images Matplotlib) : ✅
- Lint PHP (`php -l`) sur tous les fichiers du projet : ✅ aucune erreur de syntaxe.
