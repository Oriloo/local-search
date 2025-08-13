// Debug au début
console.log('🕷️ Chargement crawl.js...');

class CrawlManager {
    constructor() {
        this.apiBase = '../api/crawl_api.php';
        console.log('🔧 CrawlManager initialisé avec API:', this.apiBase);
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadCrawlStatus();
        console.log('✅ CrawlManager prêt');
    }

    bindEvents() {
        // Boutons crawler
        document.querySelectorAll('.btn-crawl').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const siteId = e.target.dataset.siteId;
                console.log('🔥 Bouton crawl cliqué pour site:', siteId);
                if (siteId) {
                    this.startCrawl(siteId);
                }
            });
        });

        // Refresh automatique du statut
        this.statusInterval = setInterval(() => {
            this.loadCrawlStatus();
        }, 15000);

        console.log('📡 Events bindés, auto-refresh activé');
    }

    async startCrawl(siteId, maxPages = 50) {
        console.log(`🚀 Démarrage crawling site ${siteId} (max: ${maxPages} pages)`);

        const btn = document.querySelector(`[data-site-id="${siteId}"]`);
        if (!btn) {
            console.error('❌ Bouton non trouvé pour site:', siteId);
            return;
        }

        const originalText = btn.textContent;

        try {
            // UI feedback
            btn.textContent = '🕷️ Crawling...';
            btn.disabled = true;
            btn.classList.add('loading');

            this.showNotification('🚀 Démarrage du crawling...', 'info');

            console.log('📡 Envoi requête à:', this.apiBase + '?action=start_crawl');

            const response = await fetch(this.apiBase + '?action=start_crawl', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    site_id: parseInt(siteId),
                    max_pages: maxPages
                })
            });

            console.log('📨 Réponse reçue:', response.status, response.statusText);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            console.log('📋 Résultat:', result);

            if (result.success) {
                this.showNotification('✅ Crawling terminé avec succès !', 'success');
                this.showCrawlResults(result.data);
                this.loadCrawlStatus();
            } else {
                this.showNotification('❌ Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
            }

        } catch (error) {
            console.error('💥 Erreur crawling:', error);
            this.showNotification('🔌 Erreur réseau: ' + error.message, 'error');
        } finally {
            // Restaurer le bouton
            btn.textContent = originalText;
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    }

    async loadCrawlStatus() {
        try {
            const response = await fetch(this.apiBase + '?action=status');

            if (!response.ok) {
                console.warn('⚠️ Erreur chargement statut:', response.status);
                return;
            }

            const result = await response.json();

            if (result.success && result.data) {
                this.updateStatusDisplay(result.data);
                console.log('📊 Statuts mis à jour:', result.data.length, 'sites');
            }
        } catch (error) {
            console.warn('⚠️ Erreur chargement statut:', error);
        }
    }

    updateStatusDisplay(sites) {
        if (!Array.isArray(sites)) return;

        sites.forEach(site => {
            // Mettre à jour le statut
            const statusElement = document.querySelector(`#status-${site.id}`);
            if (statusElement) {
                statusElement.innerHTML = this.getStatusBadge(site.status);
            }

            // Mettre à jour le nombre de documents
            const docsElement = document.querySelector(`#docs-${site.id}`);
            if (docsElement) {
                docsElement.textContent = site.documents_count || 0;
            }

            // Mettre à jour la queue
            const queueElement = document.querySelector(`#queue-${site.id}`);
            if (queueElement) {
                const pending = site.queue_pending || 0;
                const processing = site.queue_processing || 0;
                const total = pending + processing;
                queueElement.textContent = total > 0 ? `Queue: ${total}` : '';
            }
        });
    }

    getStatusBadge(status) {
        const badges = {
            'active': '<span class="status active">Actif</span>',
            'processing': '<span class="status processing">En cours</span>',
            'error': '<span class="status error">Erreur</span>',
            'blocked': '<span class="status blocked">Bloqué</span>'
        };
        return badges[status] || '<span class="status unknown">Inconnu</span>';
    }

    showCrawlResults(data) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'block';

        modal.innerHTML = `
            <div class="modal-content">
                <span class="close" onclick="this.closest('.modal').remove()">&times;</span>
                <h2>📊 Résultats du crawling</h2>
                <div class="crawl-results">
                    <div class="result-stats">
                        <div class="stat-item">
                            <strong>${data.urls_successful || 0}</strong>
                            <span>URLs réussies</span>
                        </div>
                        <div class="stat-item">
                            <strong>${data.urls_failed || 0}</strong>
                            <span>URLs échouées</span>
                        </div>
                        <div class="stat-item">
                            <strong>${data.urls_discovered || 0}</strong>
                            <span>URLs découvertes</span>
                        </div>
                    </div>
                    
                    ${data.errors && data.errors.length > 0 ? `
                        <div class="errors-section">
                            <h3>⚠️ Erreurs rencontrées:</h3>
                            <ul>
                                ${data.errors.slice(0, 10).map(error => `<li>${this.escapeHtml(error)}</li>`).join('')}
                                ${data.errors.length > 10 ? `<li><em>... et ${data.errors.length - 10} autres erreurs</em></li>` : ''}
                            </ul>
                        </div>
                    ` : ''}
                    
                    <div class="timing">
                        <p><strong>⏰ Démarré:</strong> ${data.started_at || 'N/A'}</p>
                        <p><strong>✅ Terminé:</strong> ${data.completed_at || 'N/A'}</p>
                        ${data.started_at && data.completed_at ? `
                            <p><strong>⏱️ Durée:</strong> ${this.calculateDuration(data.started_at, data.completed_at)}</p>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Fermer en cliquant à l'extérieur
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    calculateDuration(start, end) {
        const startTime = new Date(start);
        const endTime = new Date(end);
        const diff = endTime - startTime;

        const seconds = Math.floor(diff / 1000) % 60;
        const minutes = Math.floor(diff / (1000 * 60)) % 60;
        const hours = Math.floor(diff / (1000 * 60 * 60));

        if (hours > 0) {
            return `${hours}h ${minutes}m ${seconds}s`;
        } else if (minutes > 0) {
            return `${minutes}m ${seconds}s`;
        } else {
            return `${seconds}s`;
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showNotification(message, type = 'info') {
        // Utiliser la fonction globale si elle existe
        if (window.showNotification) {
            window.showNotification(message, type);
            return;
        }

        // Sinon, créer notre propre notification
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;

        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 6px;
            color: white;
            font-weight: 600;
            z-index: 10000;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideInRight 0.3s ease-out;
        `;

        if (type === 'success') {
            notification.style.background = '#27ae60';
        } else if (type === 'error') {
            notification.style.background = '#e74c3c';
        } else {
            notification.style.background = '#3498db';
        }

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    destroy() {
        if (this.statusInterval) {
            clearInterval(this.statusInterval);
        }
    }
}

// Initialiser quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
    console.log('🌐 DOM prêt, initialisation CrawlManager...');
    window.crawlManager = new CrawlManager();

    // Debug global
    window.testCrawl = (siteId) => {
        console.log('🧪 Test crawl pour site:', siteId);
        if (window.crawlManager) {
            window.crawlManager.startCrawl(siteId, 5);
        }
    };
});
