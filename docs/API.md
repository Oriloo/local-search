# API Documentation - Local Search Engine

Documentation de l'API REST de Local Search Engine.

## Base URL

```
https://votre-domaine.com/api
```

## Authentication

L'API utilise des tokens CSRF pour les opérations de modification. Les requêtes de lecture sont publiques.

## Endpoints

### Recherche

#### POST /search

Effectue une recherche dans les documents indexés.

**Paramètres :**

```json
{
  "query": "string (requis)",
  "project_id": "integer (optionnel)",
  "content_type": "string (optionnel)",
  "site_id": "integer (optionnel)",
  "language": "string (optionnel)",
  "sort": "relevance|date|title (optionnel, défaut: relevance)",
  "page": "integer (optionnel, défaut: 1)",
  "include_synonyms": "boolean (optionnel, défaut: false)",
  "exact_phrase": "boolean (optionnel, défaut: false)"
}
```

**Réponse :**

```json
{
  "success": true,
  "query": "recherche example",
  "total_results": 156,
  "results": [
    {
      "id": 1,
      "title": "Page d'exemple",
      "description": "Description de la page",
      "url": "https://example.com/page",
      "domain": "example.com",
      "project_name": "Projet Test",
      "content_type": "text/html",
      "language": "fr",
      "indexed_at": "2023-12-01 10:30:00",
      "relevance_score": 0.85,
      "highlighted_title": "Page d'<mark>exemple</mark>",
      "highlighted_description": "Description de la page avec <mark>exemple</mark>",
      "snippet": "...contenu avec termes <mark>recherche</mark> mis en évidence..."
    }
  ],
  "facets": {
    "content_types": [
      {"content_type": "text/html", "count": 120},
      {"content_type": "application/pdf", "count": 36}
    ],
    "sites": [
      {"domain": "example.com", "id": 1, "count": 89},
      {"domain": "test.com", "id": 2, "count": 67}
    ],
    "languages": [
      {"language": "fr", "count": 130},
      {"language": "en", "count": 26}
    ]
  },
  "suggestions": [
    {"term": "rechercher", "total_frequency": 45},
    {"term": "recherches", "total_frequency": 23}
  ],
  "search_time": 0.0234,
  "page": 1,
  "total_pages": 8
}
```

#### GET /suggestions

Obtient des suggestions de recherche pour l'autocomplétion.

**Paramètres :**
- `q` (string, requis) : Début du terme recherché
- `project_id` (integer, optionnel) : Limiter à un projet

**Réponse :**

```json
[
  {"term": "recherche", "total_frequency": 156},
  {"term": "rechercher", "total_frequency": 89},
  {"term": "recherches", "total_frequency": 45}
]
```

#### GET /statistics

Obtient les statistiques de recherche.

**Paramètres :**
- `project_id` (integer, optionnel) : Limiter à un projet

**Réponse :**

```json
{
  "documents": {
    "total": 1256,
    "by_type": {
      "text/html": 890,
      "application/pdf": 245,
      "text/plain": 121
    },
    "by_language": {
      "fr": 956,
      "en": 234,
      "es": 66
    }
  },
  "terms": {
    "total_unique_terms": 15634,
    "most_frequent": [
      {"term": "recherche", "total_frequency": 456},
      {"term": "information", "total_frequency": 234}
    ]
  }
}
```

### Administration

#### GET /admin/crawl-stats

Obtient les statistiques de crawling pour un site.

**Paramètres :**
- `site_id` (integer, requis) : ID du site

**Réponse :**

```json
{
  "success": true,
  "stats": {
    "total_documents": 156,
    "by_type": {
      "text/html": 120,
      "application/pdf": 36
    },
    "queue_status": {
      "pending": 12,
      "completed": 144,
      "failed": 3
    }
  }
}
```

#### POST /admin/start-crawl

Lance le crawling d'un site.

**Paramètres :**

```json
{
  "site_id": 1,
  "max_pages": 100
}
```

**Réponse :**

```json
{
  "success": true,
  "message": "Crawling terminé ! 87 pages indexées.",
  "stats": {
    "site_id": 1,
    "urls_discovered": 98,
    "urls_successful": 87,
    "urls_failed": 11,
    "duration": 145,
    "errors": ["Timeout on /page1", "404 on /page2"]
  }
}
```

## Codes d'Erreur

### HTTP Status Codes

- `200` - Succès
- `400` - Requête invalide
- `401` - Non autorisé
- `404` - Ressource non trouvée
- `405` - Méthode non autorisée
- `422` - Entité non traitable (erreur de validation)
- `500` - Erreur serveur interne

### Erreurs d'Application

```json
{
  "error": "Message d'erreur descriptif",
  "code": "ERROR_CODE",
  "details": {
    "field": "Description du problème spécifique"
  }
}
```

**Codes d'erreur courants :**

- `INVALID_QUERY` : Requête de recherche invalide
- `PROJECT_NOT_FOUND` : Projet non trouvé
- `SITE_NOT_FOUND` : Site non trouvé
- `CRAWL_IN_PROGRESS` : Crawling déjà en cours
- `VALIDATION_ERROR` : Erreur de validation des données

## Exemples d'Utilisation

### JavaScript/Fetch

```javascript
// Recherche simple
async function search(query) {
  const response = await fetch('/api/search', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ query })
  });
  
  return await response.json();
}

// Recherche avec filtres
async function advancedSearch(params) {
  const response = await fetch('/api/search', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      query: params.query,
      project_id: params.projectId,
      content_type: params.contentType,
      include_synonyms: true,
      sort: 'relevance'
    })
  });
  
  return await response.json();
}

// Autocomplétion
async function getSuggestions(term) {
  const response = await fetch(`/api/suggestions?q=${encodeURIComponent(term)}`);
  return await response.json();
}
```

### PHP/cURL

```php
function searchAPI($query, $options = []) {
    $data = array_merge(['query' => $query], $options);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://votre-domaine.com/api/search',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    throw new Exception("API Error: HTTP $httpCode");
}

// Utilisation
try {
    $results = searchAPI('mon terme de recherche', [
        'project_id' => 1,
        'include_synonyms' => true
    ]);
    
    foreach ($results['results'] as $result) {
        echo $result['title'] . "\n";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}
```

### Python/Requests

```python
import requests
import json

def search_api(query, **kwargs):
    data = {'query': query, **kwargs}
    
    response = requests.post(
        'https://votre-domaine.com/api/search',
        json=data,
        headers={'Content-Type': 'application/json'}
    )
    
    response.raise_for_status()
    return response.json()

# Utilisation
try:
    results = search_api(
        'terme de recherche',
        project_id=1,
        include_synonyms=True,
        content_type='text/html'
    )
    
    for result in results['results']:
        print(f"{result['title']} - {result['url']}")
        
except requests.RequestException as e:
    print(f"Erreur API: {e}")
```

## Limites et Quotas

- **Taux de requêtes** : 100 requêtes par minute par IP
- **Taille des requêtes** : Maximum 1000 caractères pour le terme de recherche
- **Résultats par page** : Maximum 100 résultats par page
- **Timeout** : 30 secondes pour les requêtes de recherche

## CORS

L'API supporte CORS pour les requêtes cross-origin avec les headers suivants :

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

## Webhooks (Futur)

*Fonctionnalité prévue pour les prochaines versions*

Notifications en temps réel pour :
- Fin de crawling
- Nouvelles indexations
- Erreurs de crawling