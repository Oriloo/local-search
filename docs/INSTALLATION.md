# Installation Guide - Local Search Engine

Guide d'installation détaillé pour Local Search Engine.

## Prérequis Système

### Serveur Web
- Apache 2.4+ avec mod_rewrite
- OU Nginx 1.18+
- OU serveur web compatible PHP

### PHP
- **Version** : 7.4 ou supérieur (8.0+ recommandé)
- **Extensions requises** :
  - `pdo_mysql` : Connexion base de données
  - `curl` : Crawler web
  - `dom` : Parsing HTML
  - `mbstring` : Support UTF-8
  - `json` : Manipulation JSON
  - `openssl` : Connexions HTTPS
  - `fileinfo` : Détection types MIME

### Base de Données
- MySQL 5.7+ ou MariaDB 10.2+
- Minimum 1GB d'espace disque libre
- Utilisateur avec privilèges CREATE, ALTER, INSERT, UPDATE, DELETE, SELECT

### Outils
- Composer (gestionnaire de dépendances PHP)
- Git (optionnel, pour récupérer le code)

## Installation Étape par Étape

### Étape 1 : Préparation de l'Environnement

#### 1.1 Vérification PHP

```bash
php -v
php -m | grep -E "(pdo_mysql|curl|dom|mbstring|json)"
```

#### 1.2 Installation Composer

```bash
# Téléchargement
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Vérification
composer --version
```

### Étape 2 : Récupération du Code

#### Option A : Clone Git

```bash
git clone https://github.com/Oriloo/local-search.git
cd local-search
```

#### Option B : Téléchargement ZIP

```bash
wget https://github.com/Oriloo/local-search/archive/main.zip
unzip main.zip
cd local-search-main
```

### Étape 3 : Installation des Dépendances

```bash
composer install --no-dev --optimize-autoloader
```

### Étape 4 : Configuration de la Base de Données

#### 4.1 Création de la Base

```sql
-- Connexion MySQL
mysql -u root -p

-- Création base de données
CREATE DATABASE local_search CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Création utilisateur (optionnel mais recommandé)
CREATE USER 'localsearch'@'localhost' IDENTIFIED BY 'mot_de_passe_fort';
GRANT ALL PRIVILEGES ON local_search.* TO 'localsearch'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 4.2 Import du Schéma

```bash
mysql -u localsearch -p local_search < config/database.sql
```

### Étape 5 : Configuration de l'Application

#### 5.1 Fichier de Configuration

```bash
cp .env.example .env
```

#### 5.2 Édition des Paramètres

Éditez `.env` :

```env
# Application
APP_NAME="Moteur de Recherche Local"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.com

# Base de données
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=local_search
DB_USERNAME=localsearch
DB_PASSWORD=mot_de_passe_fort

# Crawling
MAX_CRAWL_DEPTH=3
USER_AGENT="LocalSearch/1.0"
CRAWL_DELAY=1
MAX_CONTENT_LENGTH=1000000
MAX_FILE_SIZE=50000000

# Recherche
RESULTS_PER_PAGE=20

# Sécurité
SESSION_LIFETIME=1440

# Cache
CACHE_ENABLED=true
CACHE_TTL=3600

# Logs
LOG_LEVEL=info
LOG_FILE=logs/app.log
```

### Étape 6 : Configuration du Serveur Web

#### Option A : Apache

Créez `/etc/apache2/sites-available/local-search.conf` :

```apache
<VirtualHost *:80>
    ServerName local-search.example.com
    DocumentRoot /var/www/local-search/public
    
    <Directory /var/www/local-search/public>
        AllowOverride All
        Require all granted
        
        # Règles de réécriture
        RewriteEngine On
        
        # Redirection HTTPS
        RewriteCond %{HTTPS} off
        RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    </Directory>
</VirtualHost>

<VirtualHost *:443>
    ServerName local-search.example.com
    DocumentRoot /var/www/local-search/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    <Directory /var/www/local-search/public>
        AllowOverride All
        Require all granted
        
        # Headers de sécurité
        Header always set X-Content-Type-Options nosniff
        Header always set X-Frame-Options DENY
        Header always set X-XSS-Protection "1; mode=block"
        Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    </Directory>
    
    # Logs
    ErrorLog ${APACHE_LOG_DIR}/local-search_error.log
    CustomLog ${APACHE_LOG_DIR}/local-search_access.log combined
</VirtualHost>
```

Activation :

```bash
sudo a2ensite local-search
sudo a2enmod rewrite ssl headers
sudo systemctl restart apache2
```

#### Option B : Nginx

Créez `/etc/nginx/sites-available/local-search` :

```nginx
# Redirection HTTP vers HTTPS
server {
    listen 80;
    server_name local-search.example.com;
    return 301 https://$server_name$request_uri;
}

# Configuration HTTPS
server {
    listen 443 ssl http2;
    server_name local-search.example.com;
    root /var/www/local-search/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;

    # Security Headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP handling
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Assets caching
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Security
    location ~ /\.ht {
        deny all;
    }
    
    location ~ /\.(env|git) {
        deny all;
    }

    # Logs
    access_log /var/log/nginx/local-search_access.log;
    error_log /var/log/nginx/local-search_error.log;
}
```

Activation :

```bash
sudo ln -s /etc/nginx/sites-available/local-search /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Étape 7 : Permissions et Sécurité

#### 7.1 Permissions Fichiers

```bash
# Propriétaire correct
sudo chown -R www-data:www-data /var/www/local-search

# Permissions
find /var/www/local-search -type d -exec chmod 755 {} \;
find /var/www/local-search -type f -exec chmod 644 {} \;

# Répertoires spéciaux
chmod 777 /var/www/local-search/logs
```

#### 7.2 Création des Répertoires

```bash
mkdir -p logs cache tmp
chmod 777 logs cache tmp
```

### Étape 8 : Vérification de l'Installation

#### 8.1 Test de Base

Accédez à `https://votre-domaine.com/` pour vérifier l'interface de recherche.

#### 8.2 Test Administration

Accédez à `https://votre-domaine.com/admin/` pour l'interface d'administration.

#### 8.3 Test de Connectivité Base de Données

Créez un fichier temporaire `test-db.php` :

```php
<?php
require_once 'vendor/autoload.php';
use LocalSearch\Config\Configuration;
use LocalSearch\Config\Database;

try {
    Configuration::load();
    $db = Database::getInstance();
    $result = $db->query("SELECT COUNT(*) as count FROM search_projects");
    echo "✅ Connexion base de données réussie\n";
    echo "Nombre de projets : " . $result[0]['count'] . "\n";
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
```

```bash
php test-db.php
rm test-db.php
```

## Configuration Post-Installation

### SSL/TLS (Production)

#### Avec Let's Encrypt (Certbot)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d local-search.example.com
```

#### Ou pour Nginx

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d local-search.example.com
```

### Surveillance et Logs

#### Rotation des Logs

Créez `/etc/logrotate.d/local-search` :

```
/var/www/local-search/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    copytruncate
}
```

### Sauvegarde Automatique

Script de sauvegarde `/etc/cron.daily/local-search-backup` :

```bash
#!/bin/bash
BACKUP_DIR="/backup/local-search"
DATE=$(date +%Y%m%d_%H%M%S)

# Création répertoire
mkdir -p $BACKUP_DIR

# Sauvegarde base de données
mysqldump -u localsearch -p'mot_de_passe' local_search > $BACKUP_DIR/db_$DATE.sql

# Sauvegarde fichiers
tar -czf $BACKUP_DIR/files_$DATE.tar.gz -C /var/www local-search

# Nettoyage (garde 30 jours)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

## Dépannage

### Problèmes Courants

#### Erreur 500

- Vérifiez les logs Apache/Nginx
- Contrôlez les permissions
- Vérifiez la configuration PHP

#### Base de Données Inaccessible

- Testez la connexion MySQL
- Vérifiez les paramètres `.env`
- Contrôlez les privilèges utilisateur

#### CSS/JS Non Chargés

- Vérifiez les permissions sur `/public/assets/`
- Contrôlez la configuration du serveur web
- Vérifiez les règles de réécriture

### Support

En cas de problème, consultez :
- Logs de l'application : `logs/app.log`
- Logs serveur web : `/var/log/apache2/` ou `/var/log/nginx/`
- Issues GitHub du projet