/**
 * Gestionnaire de prévisualisation des médias
 * Auteur: Oriloo
 * Date: 2025-08-13
 */

class MediaPreview {
    constructor() {
        this.currentModal = null;
        this.mediaItems = [];
        this.currentIndex = 0;
        this.supportedImageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        this.supportedVideoTypes = ['mp4', 'webm', 'ogv', 'mov', 'avi'];

        this.init();
    }

    init() {
        this.createModal();
        this.bindEvents();
        this.processMediaResults();
        console.log('📺 MediaPreview initialisé');
    }

    /**
     * Crée la modal de prévisualisation
     */
    createModal() {
        const modal = document.createElement('div');
        modal.className = 'media-modal';
        modal.id = 'mediaModal';

        modal.innerHTML = `
            <div class="media-modal-content">
                <div class="modal-controls">
                    <button class="modal-btn" id="downloadBtn" title="Télécharger">💾</button>
                    <button class="modal-btn" id="shareBtn" title="Partager">🔗</button>
                    <button class="modal-btn" id="closeModalBtn" title="Fermer">✖️</button>
                </div>
                
                <div class="media-display" id="mediaDisplay">
                    <!-- Contenu média injecté ici -->
                </div>
                
                <div class="media-details">
                    <h3 id="mediaTitle">Titre du média</h3>
                    <div class="media-meta-grid" id="mediaMeta">
                        <!-- Métadonnées injectées ici -->
                    </div>
                </div>
                
                <button class="modal-nav prev" id="prevBtn" title="Précédent">❮</button>
                <button class="modal-nav next" id="nextBtn" title="Suivant">❯</button>
            </div>
        `;

        document.body.appendChild(modal);
        this.modal = modal;

        // Event listeners pour la modal
        document.getElementById('closeModalBtn').addEventListener('click', () => this.closeModal());
        document.getElementById('downloadBtn').addEventListener('click', () => this.downloadMedia());
        document.getElementById('shareBtn').addEventListener('click', () => this.shareMedia());
        document.getElementById('prevBtn').addEventListener('click', () => this.previousMedia());
        document.getElementById('nextBtn').addEventListener('click', () => this.nextMedia());

        // Fermer avec Escape ou clic à l'extérieur
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.closeModal();
        });

        document.addEventListener('keydown', (e) => {
            if (this.modal.classList.contains('active')) {
                switch(e.key) {
                    case 'Escape':
                        this.closeModal();
                        break;
                    case 'ArrowLeft':
                        this.previousMedia();
                        break;
                    case 'ArrowRight':
                        this.nextMedia();
                        break;
                }
            }
        });
    }

    /**
     * Lie les événements
     */
    bindEvents() {
        // Observer pour les nouveaux résultats (recherche dynamique)
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.addedNodes.length) {
                    this.processMediaResults();
                }
            });
        });

        const resultsContainer = document.querySelector('.results');
        if (resultsContainer) {
            observer.observe(resultsContainer, { childList: true, subtree: true });
        }
    }

    /**
     * Traite les résultats pour identifier et améliorer les médias
     */
    processMediaResults() {
        const resultItems = document.querySelectorAll('.result-item');

        resultItems.forEach((item, index) => {
            if (item.dataset.processed === 'true') return;

            const contentType = this.getContentType(item);
            const url = this.getMediaUrl(item);

            if (contentType && url && (this.isImage(url) || this.isVideo(url))) {
                this.enhanceMediaResult(item, contentType, url, index);
                item.dataset.processed = 'true';
            }
        });
    }

    /**
     * Améliore un résultat avec prévisualisation média
     */
    enhanceMediaResult(resultItem, contentType, url, index) {
        const isImage = this.isImage(url);
        const isVideo = this.isVideo(url);

        if (!isImage && !isVideo) return;

        // Ajouter la classe has-media
        resultItem.classList.add('has-media');

        // Créer la structure du contenu
        const resultContent = resultItem.querySelector('.result-title').parentElement;
        const contentWrapper = document.createElement('div');
        contentWrapper.className = 'result-content';

        // Déplacer le contenu existant
        while (resultContent.firstChild) {
            contentWrapper.appendChild(resultContent.firstChild);
        }

        // Créer la vignette
        const thumbnail = this.createThumbnail(url, isImage, isVideo, index);

        // Assembler le nouveau layout
        resultContent.appendChild(thumbnail);
        resultContent.appendChild(contentWrapper);

        // Enregistrer dans la liste des médias
        this.mediaItems.push({
            url: url,
            type: isImage ? 'image' : 'video',
            title: this.extractTitle(resultItem),
            description: this.extractDescription(resultItem),
            domain: this.extractDomain(resultItem),
            index: index
        });
    }

    /**
     * Crée une vignette pour le média
     */
    createThumbnail(url, isImage, isVideo, index) {
        const thumbnail = document.createElement('div');
        thumbnail.className = 'media-thumbnail loading';
        thumbnail.dataset.index = index;

        // Badge de type
        const badge = document.createElement('div');
        badge.className = `media-badge ${isImage ? 'image' : 'video'}`;
        badge.textContent = isImage ? 'IMG' : 'VID';
        thumbnail.appendChild(badge);

        if (isImage) {
            this.loadImageThumbnail(thumbnail, url, index);
        } else if (isVideo) {
            this.loadVideoThumbnail(thumbnail, url, index);
        }

        // Event listener pour ouvrir la modal
        thumbnail.addEventListener('click', () => {
            this.openModal(index);
        });

        return thumbnail;
    }

    /**
     * Charge une vignette d'image
     */
    loadImageThumbnail(thumbnail, url, index) {
        const img = new Image();

        img.onload = () => {
            thumbnail.classList.remove('loading');
            thumbnail.style.backgroundImage = `url(${url})`;
            thumbnail.style.backgroundSize = 'cover';
            thumbnail.style.backgroundPosition = 'center';

            // Informations du fichier
            this.addMediaInfo(thumbnail, {
                dimensions: `${img.naturalWidth}×${img.naturalHeight}`,
                size: 'Chargement...'
            });

            // Obtenir la taille du fichier
            this.getFileSize(url).then(size => {
                this.updateMediaInfo(thumbnail, { size });
            });
        };

        img.onerror = () => {
            thumbnail.classList.remove('loading');
            thumbnail.classList.add('error');
        };

        img.src = url;
    }

    /**
     * Charge une vignette de vidéo
     */
    loadVideoThumbnail(thumbnail, url, index) {
        const video = document.createElement('video');
        video.muted = true;
        video.preload = 'metadata';

        video.onloadedmetadata = () => {
            thumbnail.classList.remove('loading');

            // Créer une capture d'écran de la première frame
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            video.currentTime = 1; // Aller à la seconde 1
        };

        video.onseeked = () => {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0);

            const thumbnailUrl = canvas.toDataURL('image/jpeg', 0.7);
            thumbnail.style.backgroundImage = `url(${thumbnailUrl})`;
            thumbnail.style.backgroundSize = 'cover';
            thumbnail.style.backgroundPosition = 'center';

            // Ajouter l'overlay avec bouton play
            const overlay = document.createElement('div');
            overlay.className = 'media-overlay';

            const playButton = document.createElement('button');
            playButton.className = 'play-button';
            playButton.innerHTML = '▶️';

            overlay.appendChild(playButton);
            thumbnail.appendChild(overlay);

            // Informations de la vidéo
            this.addMediaInfo(thumbnail, {
                duration: this.formatDuration(video.duration),
                dimensions: `${video.videoWidth}×${video.videoHeight}`
            });
        };

        video.onerror = () => {
            thumbnail.classList.remove('loading');
            thumbnail.classList.add('error');
        };

        video.src = url;
    }

    /**
     * Ajoute les informations du média
     */
    addMediaInfo(thumbnail, info) {
        const mediaInfo = document.createElement('div');
        mediaInfo.className = 'media-info';

        if (info.dimensions) {
            const dimensions = document.createElement('span');
            dimensions.className = 'dimensions';
            dimensions.textContent = info.dimensions;
            mediaInfo.appendChild(dimensions);
        }

        if (info.size) {
            const size = document.createElement('span');
            size.className = 'file-size';
            size.textContent = info.size;
            mediaInfo.appendChild(size);
        }

        if (info.duration) {
            const duration = document.createElement('span');
            duration.className = 'duration';
            duration.textContent = info.duration;
            mediaInfo.appendChild(duration);
        }

        thumbnail.appendChild(mediaInfo);
    }

    /**
     * Met à jour les informations du média
     */
    updateMediaInfo(thumbnail, newInfo) {
        const mediaInfo = thumbnail.querySelector('.media-info');
        if (!mediaInfo) return;

        if (newInfo.size) {
            const sizeElement = mediaInfo.querySelector('.file-size');
            if (sizeElement) {
                sizeElement.textContent = newInfo.size;
            }
        }
    }

    /**
     * Ouvre la modal pour un média spécifique
     */
    openModal(index) {
        if (!this.mediaItems[index]) return;

        this.currentIndex = index;
        const media = this.mediaItems[index];

        // Mettre à jour le contenu de la modal
        this.updateModalContent(media);

        // Afficher la modal
        this.modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Mettre à jour la navigation
        this.updateNavigation();
    }

    /**
     * Met à jour le contenu de la modal
     */
    updateModalContent(media) {
        const mediaDisplay = document.getElementById('mediaDisplay');
        const mediaTitle = document.getElementById('mediaTitle');
        const mediaMeta = document.getElementById('mediaMeta');

        // Titre
        mediaTitle.textContent = media.title || 'Média sans titre';

        // Contenu média
        mediaDisplay.innerHTML = '';

        if (media.type === 'image') {
            const img = document.createElement('img');
            img.src = media.url;
            img.alt = media.title || 'Image';
            img.style.maxWidth = '100%';
            img.style.maxHeight = '80vh';
            mediaDisplay.appendChild(img);
        } else if (media.type === 'video') {
            const video = document.createElement('video');
            video.src = media.url;
            video.controls = true;
            video.style.maxWidth = '100%';
            video.style.maxHeight = '80vh';
            mediaDisplay.appendChild(video);
        }

        // Métadonnées
        this.updateMetadata(mediaMeta, media);
    }

    /**
     * Met à jour les métadonnées
     */
    updateMetadata(container, media) {
        container.innerHTML = '';

        const metadata = [
            { label: 'Type', value: media.type === 'image' ? 'Image' : 'Vidéo' },
            { label: 'URL', value: media.url },
            { label: 'Domaine', value: media.domain || 'Inconnu' },
            { label: 'Description', value: media.description || 'Aucune description' }
        ];

        metadata.forEach(item => {
            const metaItem = document.createElement('div');
            metaItem.className = 'meta-item';

            const label = document.createElement('div');
            label.className = 'meta-label';
            label.textContent = item.label;

            const value = document.createElement('div');
            value.className = 'meta-value';
            value.textContent = item.value;

            if (item.label === 'URL') {
                value.style.wordBreak = 'break-all';
                value.style.fontSize = '0.8em';
            }

            metaItem.appendChild(label);
            metaItem.appendChild(value);
            container.appendChild(metaItem);
        });
    }

    /**
     * Met à jour la navigation
     */
    updateNavigation() {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        prevBtn.style.display = this.currentIndex > 0 ? 'block' : 'none';
        nextBtn.style.display = this.currentIndex < this.mediaItems.length - 1 ? 'block' : 'none';
    }

    /**
     * Média précédent
     */
    previousMedia() {
        if (this.currentIndex > 0) {
            this.openModal(this.currentIndex - 1);
        }
    }

    /**
     * Média suivant
     */
    nextMedia() {
        if (this.currentIndex < this.mediaItems.length - 1) {
            this.openModal(this.currentIndex + 1);
        }
    }

    /**
     * Ferme la modal
     */
    closeModal() {
        this.modal.classList.remove('active');
        document.body.style.overflow = '';

        // Pause des vidéos
        const videos = this.modal.querySelectorAll('video');
        videos.forEach(video => {
            video.pause();
        });
    }

    /**
     * Télécharge le média actuel
     */
    downloadMedia() {
        if (!this.mediaItems[this.currentIndex]) return;

        const media = this.mediaItems[this.currentIndex];
        const link = document.createElement('a');
        link.href = media.url;
        link.download = this.getFilenameFromUrl(media.url);
        link.click();
    }

    /**
     * Partage le média actuel
     */
    async shareMedia() {
        if (!this.mediaItems[this.currentIndex]) return;

        const media = this.mediaItems[this.currentIndex];

        if (navigator.share) {
            try {
                await navigator.share({
                    title: media.title || 'Média partagé',
                    text: media.description || 'Découvrez ce média',
                    url: media.url
                });
            } catch (err) {
                console.log('Erreur partage:', err);
                this.copyToClipboard(media.url);
            }
        } else {
            this.copyToClipboard(media.url);
        }
    }

    /**
     * Copie dans le presse-papiers
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showNotification('URL copiée dans le presse-papiers !', 'success');
        } catch (err) {
            console.error('Erreur copie:', err);
            this.showNotification('Impossible de copier l\'URL', 'error');
        }
    }

    /**
     * Utilitaires
     */

    getContentType(resultItem) {
        const typeElement = resultItem.querySelector('.content-type');
        return typeElement ? typeElement.textContent.toLowerCase() : null;
    }

    getMediaUrl(resultItem) {
        const urlElement = resultItem.querySelector('.result-url');
        return urlElement ? urlElement.textContent.trim() : null;
    }

    isImage(url) {
        const extension = this.getFileExtension(url);
        return this.supportedImageTypes.includes(extension);
    }

    isVideo(url) {
        const extension = this.getFileExtension(url);
        return this.supportedVideoTypes.includes(extension);
    }

    getFileExtension(url) {
        try {
            const pathname = new URL(url).pathname;
            return pathname.split('.').pop().toLowerCase();
        } catch {
            return '';
        }
    }

    getFilenameFromUrl(url) {
        try {
            const pathname = new URL(url).pathname;
            return pathname.split('/').pop() || 'media';
        } catch {
            return 'media';
        }
    }

    extractTitle(resultItem) {
        const titleElement = resultItem.querySelector('.result-title a');
        return titleElement ? titleElement.textContent.trim() : 'Sans titre';
    }

    extractDescription(resultItem) {
        const descElement = resultItem.querySelector('.result-snippet, .highlighted-snippet');
        return descElement ? descElement.textContent.trim() : '';
    }

    extractDomain(resultItem) {
        const urlElement = resultItem.querySelector('.result-url');
        if (!urlElement) return '';

        try {
            return new URL(urlElement.textContent.trim()).hostname;
        } catch {
            return '';
        }
    }

    formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);

        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        } else {
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }
    }

    async getFileSize(url) {
        try {
            const response = await fetch(url, { method: 'HEAD' });
            const contentLength = response.headers.get('content-length');

            if (contentLength) {
                const bytes = parseInt(contentLength);
                return this.formatFileSize(bytes);
            }
        } catch (err) {
            console.warn('Impossible de récupérer la taille du fichier:', err);
        }

        return 'Taille inconnue';
    }

    formatFileSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }

        return `${size.toFixed(1)} ${units[unitIndex]}`;
    }

    showNotification(message, type = 'info') {
        // Utiliser la fonction globale si elle existe
        if (window.showNotification) {
            window.showNotification(message, type);
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }
}

// Initialisation automatique
document.addEventListener('DOMContentLoaded', () => {
    window.mediaPreview = new MediaPreview();
});
