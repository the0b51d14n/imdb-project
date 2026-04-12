# Supinfo.TV

Plateforme de vente de films et séries en ligne — projet académique SUPINFO.  
Données fournies par l'API [TMDB](https://www.themoviedb.org/).

---

## Stack technique

| Couche | Technologie |
|---|---|
| Backend | PHP 8.3 (natif, sans framework) |
| Base de données | MySQL 8.0 |
| Serveur web | Nginx 1.25 |
| Conteneurisation | Docker + Docker Compose |
| API externe | TMDB (The Movie Database) |
| CSS/JS | Vanilla, GSAP 3, DM Sans |

---

## Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) ≥ 4.x
- [Git](https://git-scm.com/)
- Clé API TMDB gratuite → [themoviedb.org/settings/api](https://www.themoviedb.org/settings/api)

---

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/votre-org/supinfotv.git
cd supinfotv
```

### 2. Configurer l'environnement

```bash
cp .env.example .env
```

Éditez `.env` et renseignez **au minimum** :

```dotenv
TMDB_API_KEY=votre_clé_tmdb_ici
DB_PASS=un_mot_de_passe_fort
APP_SECRET=une_chaine_aléatoire_32_chars
PRICE_HMAC_SECRET=une_autre_chaine_aléatoire
CSRF_SECRET=encore_une_chaine_aléatoire
APP_URL=http://localhost
```

Pour générer des secrets aléatoires :
```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

### 3. Lancer les conteneurs

```bash
docker compose up -d
```

Le schéma SQL (`backend/Database.sql`) est chargé automatiquement au premier démarrage.

### 4. Appliquer le patch de sécurité

```bash
docker compose exec mysql mysql -u supinfotv_user -p supinfotv < backend/Database_patch_auth.sql
```

### 5. Accéder à l'application

Ouvrez [http://localhost](http://localhost)

---

## Démarrage rapide (commandes utiles)

```bash
# Démarrer
docker compose up -d

# Arrêter
docker compose down

# Voir les logs
docker compose logs -f

# Logs PHP uniquement
docker compose logs -f php

# Shell PHP
docker compose exec php sh

# Shell MySQL
docker compose exec mysql mysql -u supinfotv_user -p supinfotv

# Redémarrer Nginx (après modif config)
docker compose restart nginx
```

---

## Structure du projet

```
supinfotv/
├── .env.example              # Template de configuration (pas de secrets)
├── .github/
│   └── workflows/
│       └── security.yml      # CI/CD — audit sécurité automatique
├── backend/
│   ├── config/
│   │   ├── database.php      # Connexion PDO MySQL (singleton)
│   │   ├── mail.php          # Config SMTP
│   │   ├── security.php      # Headers HTTP + constantes sécurité
│   │   └── tmdb.php          # Alias → frontend/config/tmdb.php
│   ├── pages/
│   │   ├── cart.php          # Panier (authentifié)
│   │   ├── director.php      # Filmographie d'un réalisateur
│   │   ├── forgot-password.php
│   │   ├── login.php         # Handler POST connexion + inscription
│   │   ├── logout.php
│   │   ├── movie-detail.php
│   │   ├── movies.php
│   │   ├── profile.php
│   │   ├── register.php
│   │   ├── resend-verification.php
│   │   ├── reset-password.php
│   │   ├── search.php
│   │   └── verify-email.php
│   ├── partials/             # Alias vers frontend/partials/
│   ├── services/
│   │   ├── auth.php          # Authentification complète
│   │   ├── cart.php          # Panier (DB)
│   │   ├── csrf.php          # Protection CSRF
│   │   ├── mailer.php        # Envoi e-mail SMTP natif
│   │   ├── orders.php        # Commandes
│   │   └── tmdb-service.php  # Alias → frontend/services/
│   ├── Database.sql          # Schéma principal
│   └── Database_patch_auth.sql # Patch tables auth renforcées
├── docker/
│   ├── mysql/my.cnf
│   ├── nginx/default.conf
│   └── php/
│       ├── Dockerfile
│       └── php.ini
├── frontend/
│   ├── assets/
│   │   ├── css/              # app.css + composants + pages
│   │   └── js/               # Composants et pages JS
│   ├── config/
│   │   └── tmdb.php          # Constantes TMDB + calcul prix HMAC
│   ├── pages/                # Pages visiteur (sans auth requise)
│   ├── partials/             # head, navbar, footer, loader, movie-card
│   └── services/
│       └── tmdb-service.php  # Appels API TMDB
├── index.php                 # Page d'accueil
├── pre-commit                # Hook Git de sécurité
└── docker-compose.yml
```

---

## Sécurité

### Mesures implémentées

| Vecteur | Protection |
|---|---|
| Injection SQL | PDO avec requêtes préparées exclusivement |
| XSS | `htmlspecialchars()` systématique en sortie |
| CSRF | Token HMAC par session, vérifié sur chaque POST |
| Brute force | Rate limiting par IP (5 tentatives / 15 min) |
| Manipulation de prix | Signature HMAC SHA-256 côté serveur |
| Sessions | HttpOnly, SameSite=Lax, régénération ID, expiration |
| Mots de passe | Bcrypt cost=12, rehash automatique |
| Headers HTTP | CSP, X-Frame-Options, HSTS (prod), etc. |
| Secrets | Exclusivement via `.env`, jamais dans le code |
| Timing attacks | Délais constants sur login/reset (anti user-enumeration) |
| Fichiers sensibles | Nginx bloque `.env`, `.git`, `.sql.gz`, `.key`… |

### Hook pre-commit

Installe le hook pour bloquer les commits dangereux avant qu'ils partent :

```bash
cp pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

Il vérifie automatiquement :
- Présence de fichiers `.env` ou clés dans les fichiers stagés
- Patterns de secrets dans le code (API keys, mots de passe hardcodés)
- Syntaxe PHP de tous les fichiers modifiés
- Présence de `var_dump()` / `console.log()` oubliés
- Fichiers > 1 MB

### CI/CD GitHub Actions

Le workflow `.github/workflows/security.yml` s'exécute à chaque push/PR et lance :
- **Gitleaks** — scan de secrets dans tout l'historique git
- **Lint PHP** — vérification syntaxe de tous les `.php`
- **Scan patterns** — détection de fonctions dangereuses (`eval`, `exec`…)
- **Sensitive files** — vérification absence de fichiers sensibles
- **Docker config** — validation du `docker-compose.yml`

---

## Variables d'environnement

| Variable | Description | Exemple |
|---|---|---|
| `APP_ENV` | Environnement | `development` / `production` |
| `APP_DEBUG` | Affichage erreurs | `true` / `false` |
| `APP_SECRET` | Secret application | Chaîne aléatoire 32+ chars |
| `APP_URL` | URL publique | `http://localhost` |
| `DB_HOST` | Hôte MySQL | `mysql` (Docker) |
| `DB_PORT` | Port MySQL | `3306` |
| `DB_NAME` | Nom de la base | `supinfotv` |
| `DB_USER` | Utilisateur MySQL | `supinfotv_user` |
| `DB_PASS` | Mot de passe MySQL | Chaîne forte |
| `TMDB_API_KEY` | Clé API TMDB | Depuis themoviedb.org |
| `PRICE_HMAC_SECRET` | Secret signature prix | 64 chars hex |
| `CSRF_SECRET` | Secret tokens CSRF | 64 chars hex |
| `RATE_LIMIT_MAX_ATTEMPTS` | Tentatives max login | `5` |
| `RATE_LIMIT_DECAY_MINUTES` | Fenêtre rate limit | `15` |
| `MAIL_HOST` | Serveur SMTP | `smtp.mailtrap.io` |
| `MAIL_PORT` | Port SMTP | `587` |
| `MAIL_USER` | Utilisateur SMTP | — |
| `MAIL_PASS` | Mot de passe SMTP | — |
| `MAIL_FROM` | Expéditeur | `noreply@supinfo.tv` |
| `MAIL_FROM_NAME` | Nom expéditeur | `Supinfo.TV` |

---

## Fonctionnalités

- **Catalogue** — films tendance, sorties récentes, navigation par genre
- **Recherche** — par titre ou réalisateur
- **Fiche film** — synopsis, casting, bande-annonce YouTube, recommandations
- **Page réalisateur** — filmographie complète
- **Panier** — ajout/suppression, persistance en base
- **Commandes** — checkout simulé, historique dans le profil
- **Authentification** — inscription, connexion, déconnexion animée
- **Vérification e-mail** — lien token 24h
- **Reset mot de passe** — lien token 1h
- **Profil** — films achetés, changement de mot de passe
- **Recommandations** — basées sur le dernier achat (via TMDB)

---

## Développement

### Tester l'envoi d'e-mails en local

Utilisez [Mailtrap](https://mailtrap.io/) (plan gratuit) :

```dotenv
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USER=votre_user_mailtrap
MAIL_PASS=votre_pass_mailtrap
```

Tous les e-mails seront interceptés dans la boîte Mailtrap, sans jamais atteindre de vrais destinataires.

### Passer en production

1. Mettre `APP_ENV=production` et `APP_DEBUG=false` dans `.env`
2. Ajouter `APP_URL=https://votre-domaine.com`
3. Configurer HTTPS dans Nginx et activer HSTS
4. Mettre `verify_peer=true` dans `mailer.php`

---

## Licence

Projet académique — SUPINFO International University.  
Les données sont fournies par [TMDB](https://www.themoviedb.org/) sous leur [conditions d'utilisation](https://www.themoviedb.org/documentation/api/terms-of-use).

> *This product uses the TMDB API but is not endorsed or certified by TMDB.*
