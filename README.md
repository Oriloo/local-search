# Local Search Engine

Un moteur de recherche local avancé avec crawler web intelligent et interface d'administration moderne.

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

## 🚀 Fonctionnalités

- **Recherche Avancée** : Moteur full-text avec scoring de pertinence et synonymes
- **Crawler Intelligent** : Indexation automatique avec respect robots.txt
- **Interface Moderne** : Administration responsive et intuitive
- **Architecture MVC** : Code propre et maintenable
- **Sécurité Renforcée** : Protection CSRF, validation, sanitisation
- **API REST** : Intégration facile avec vos applications

## 📋 Prérequis

- PHP 7.4+ (extensions : PDO, cURL, DOM, mbstring, JSON)
- MySQL 5.7+ ou MariaDB 10.2+
- Composer
- Serveur web (Apache/Nginx)

## ⚡ Installation Rapide

```bash
# 1. Cloner le projet
git clone https://github.com/Oriloo/local-search.git
cd local-search

# 2. Installer les dépendances
composer install

# 3. Configuration
cp .env.example .env
# Éditer .env avec vos paramètres

# 4. Base de données
mysql -u root -p < config/database.sql

# 5. Serveur de développement
php -S localhost:8000 -t public/
```

Accédez à : http://localhost:8000

## 📖 Documentation

- [📘 Guide d'Installation Détaillé](docs/INSTALLATION.md)
- [📗 Documentation Complète](docs/README.md)
- [🔧 API Reference](docs/API.md)

## 🏗️ Architecture

```
local-search/
├── public/           # Point d'entrée web
│   ├── index.php    # Interface de recherche
│   ├── admin/       # Interface d'administration
│   └── assets/      # CSS, JS, images
├── src/             # Code source MVC
│   ├── Config/      # Configuration et base de données
│   ├── Models/      # Modèles de données
│   ├── Services/    # Services métier
│   ├── Controllers/ # Contrôleurs
│   └── Utils/       # Utilitaires
├── api/             # Points d'entrée API
├── config/          # Fichiers de configuration
├── docs/            # Documentation
└── tests/           # Tests unitaires
```

## 🎯 Utilisation

### Interface de Recherche

1. **Recherche Simple** : Tapez votre requête et appuyez sur Entrée
2. **Filtres Avancés** : Utilisez les menus déroulants pour filtrer par projet, type, etc.
3. **Synonymes** : Cochez la case pour inclure les synonymes
4. **Phrase Exacte** : Utilisez les guillemets pour une recherche exacte

### Administration

1. **Créer un Projet** : Définissez les domaines à indexer
2. **Ajouter des Sites** : Spécifiez les URLs de départ
3. **Lancer le Crawling** : Indexez automatiquement le contenu
4. **Surveiller** : Consultez les statistiques en temps réel

### API

```javascript
// Recherche via API
fetch('/api/search', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        query: 'votre recherche',
        project_id: 1,
        include_synonyms: true
    })
})
.then(response => response.json())
.then(data => console.log(data.results));
```

## 🔧 Configuration

Principales variables d'environnement :

```env
# Application
APP_NAME="Moteur de Recherche Local"
APP_ENV=production
APP_URL=https://votre-domaine.com

# Base de données
DB_HOST=localhost
DB_DATABASE=local_search
DB_USERNAME=votre_utilisateur
DB_PASSWORD=votre_mot_de_passe

# Crawling
MAX_CRAWL_DEPTH=3
CRAWL_DELAY=1
RESULTS_PER_PAGE=20
```

## 🛡️ Sécurité

- Protection CSRF automatique
- Validation et sanitisation des entrées
- Headers de sécurité (HSTS, XSS Protection, etc.)
- Support HTTPS natif
- Gestion sécurisée des sessions

## 🔄 Mise à Jour

```bash
# Sauvegarde
mysqldump -u user -p local_search > backup.sql

# Mise à jour du code
git pull origin main
composer install --no-dev

# Migration base de données (si nécessaire)
mysql -u user -p local_search < config/migrations/latest.sql
```

## 🤝 Contribution

Les contributions sont bienvenues ! Voir [CONTRIBUTING.md](CONTRIBUTING.md) pour les guidelines.

1. Fork le projet
2. Créez votre branche (`git checkout -b feature/AmazingFeature`)
3. Committez vos changements (`git commit -m 'Add AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrez une Pull Request

## 📄 Licence

Ce projet est sous licence MIT. Voir [LICENSE](LICENSE) pour plus de détails.

## 🆘 Support

- **Issues** : [GitHub Issues](https://github.com/Oriloo/local-search/issues)
- **Discussions** : [GitHub Discussions](https://github.com/Oriloo/local-search/discussions)
- **Wiki** : [Documentation Wiki](https://github.com/Oriloo/local-search/wiki)

## 🙏 Remerciements

- [Composer](https://getcomposer.org/) - Gestionnaire de dépendances
- [MySQL](https://mysql.com/) - Base de données
- Communauté PHP pour les outils et bibliothèques

---

<p align="center">
  <strong>Développé avec ❤️ pour la communauté</strong>
</p>