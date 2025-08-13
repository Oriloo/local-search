# Local Search Engine

Un moteur de recherche local avancé avec crawler web et interface d'administration complète.

## Description

Local Search est un moteur de recherche autonome conçu pour indexer et rechercher du contenu sur des sites web spécifiques. Il offre des fonctionnalités avancées de crawling, d'indexation et de recherche avec une interface d'administration intuitive.

## Fonctionnalités

### 🔍 Recherche Avancée
- Recherche full-text avec scoring de pertinence
- Support des synonymes et recherche sémantique
- Filtres par projet, type de contenu, site et date
- Suggestions automatiques et autocomplétion
- Facettes de navigation dynamiques

### 🕷️ Crawler Web Intelligent
- Crawling respectueux avec délais configurables
- Support de robots.txt
- Extraction intelligente du contenu
- Gestion des types de fichiers multiples (HTML, PDF, images, vidéos)
- Détection automatique de la langue
- Calcul de scores de qualité du contenu

### 🎛️ Interface d'Administration
- Gestion des projets de crawling
- Configuration des domaines autorisés
- Monitoring en temps réel du crawling
- Statistiques détaillées d'indexation
- Interface responsive et moderne

### 🔧 Architecture Moderne
- Architecture MVC propre avec PSR-4
- Configuration centralisée avec variables d'environnement
- Sécurité renforcée (CSRF, validation, sanitisation)
- Autoloading Composer
- Gestion d'erreurs robuste

## Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7+ ou MariaDB 10.2+
- Extensions PHP requises:
  - PDO MySQL
  - cURL
  - DOM
  - mbstring
  - JSON
- Composer (pour la gestion des dépendances)

## Installation

### 1. Cloner le projet

```bash
git clone https://github.com/Oriloo/local-search.git
cd local-search
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configuration de la base de données

Créer la base de données MySQL :

```sql
CREATE DATABASE local_search CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Importer le schéma :

```bash
mysql -u root -p local_search < config/database.sql
```

### 4. Configuration de l'environnement

Copier le fichier de configuration :

```bash
cp .env.example .env
```

Éditer `.env` avec vos paramètres :

```env
# Application
APP_NAME="Moteur de Recherche Local"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.com

# Base de données
DB_HOST=localhost
DB_DATABASE=local_search
DB_USERNAME=votre_utilisateur
DB_PASSWORD=votre_mot_de_passe

# Crawling
MAX_CRAWL_DEPTH=3
CRAWL_DELAY=1
```

### 5. Configuration du serveur web

#### Apache

Créer un VirtualHost :

```apache
<VirtualHost *:80>
    ServerName local-search.example.com
    DocumentRoot /path/to/local-search/public
    
    <Directory /path/to/local-search/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Redirection HTTPS (recommandé)
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName local-search.example.com
    DocumentRoot /path/to/local-search/public
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    <Directory /path/to/local-search/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

Configuration Nginx :

```nginx
server {
    listen 80;
    server_name local-search.example.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name local-search.example.com;
    root /path/to/local-search/public;
    index index.php;

    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 6. Permissions

Configurer les permissions :

```bash
chmod -R 755 public/
chmod -R 777 logs/
```

## Utilisation

### Interface de Recherche

Accédez à l'interface de recherche via `https://votre-domaine.com/`

- Entrez votre requête dans le champ de recherche
- Utilisez les filtres pour affiner les résultats
- Activez la recherche de synonymes pour une recherche plus large
- Utilisez les guillemets pour une recherche de phrase exacte

### Interface d'Administration

Accédez à l'administration via `https://votre-domaine.com/admin/`

#### Créer un Projet

1. Donnez un nom et une description au projet
2. Spécifiez les domaines autorisés (un par ligne)
3. Configurez la profondeur de crawling et les délais
4. Choisissez de respecter ou non robots.txt

#### Ajouter un Site

1. Sélectionnez le projet approprié
2. Entrez l'URL de base du site à crawler
3. Le domaine sera automatiquement extrait et validé

#### Lancer un Crawling

1. Sélectionnez le site dans la liste
2. Cliquez sur "Crawler" pour démarrer l'indexation
3. Surveillez les statistiques en temps réel

### API de Recherche

L'application expose également une API REST pour l'intégration :

```javascript
// Recherche AJAX
fetch('/api/search', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        query: 'votre recherche',
        project_id: 1,
        content_type: 'text/html',
        include_synonyms: true
    })
})
.then(response => response.json())
.then(data => console.log(data));
```

## Configuration Avancée

### Variables d'Environnement

Consultez `.env.example` pour la liste complète des variables configurables.

### Optimisation des Performances

1. **Index de base de données** : Les index sont automatiquement créés lors de l'installation
2. **Cache** : Activez le cache en définissant `CACHE_ENABLED=true`
3. **Compression** : Configurez la compression gzip sur votre serveur web

### Sécurité

- Utilisez HTTPS en production
- Configurez des mots de passe de base de données forts
- Limitez l'accès à l'interface d'administration
- Surveillez les logs d'erreurs

## Maintenance

### Logs

Les logs sont stockés dans le répertoire `logs/` :
- `app.log` : Logs généraux de l'application
- Configurez la rotation des logs sur votre serveur

### Sauvegarde

Sauvegardez régulièrement :
- Base de données MySQL
- Fichiers de configuration
- Logs si nécessaire

### Mise à jour

1. Sauvegardez vos données
2. Récupérez la dernière version
3. Exécutez `composer install`
4. Vérifiez les migrations de base de données
5. Testez le fonctionnement

## Dépannage

### Problèmes de Crawling

- Vérifiez les permissions réseau
- Contrôlez les paramètres robots.txt
- Augmentez les délais si les sites sont lents

### Problèmes de Base de Données

- Vérifiez les paramètres de connexion
- Contrôlez les permissions utilisateur
- Surveillez l'espace disque

### Problèmes de Performance

- Optimisez les index de base de données
- Augmentez la mémoire PHP si nécessaire
- Utilisez un serveur web performant

## Support

- **Issues** : Reportez les bugs sur GitHub
- **Documentation** : Consultez le wiki du projet
- **Email** : contact@example.com

## Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## Contribuer

Les contributions sont les bienvenues ! Voir `CONTRIBUTING.md` pour les guidelines.

## Changelog

Voir `CHANGELOG.md` pour l'historique des versions.