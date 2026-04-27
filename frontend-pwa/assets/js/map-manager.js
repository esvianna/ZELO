const MapManager = {
    map: null,
    markers: [],
    userMarker: null,
    categoryMeta: {},
    legendControl: null,
    allLocations: [],
    activeCategories: null,

    init(elementId) {
        if (this.map) return; // Already initialized

        // Default center (Event location or generic)
        let center = [-23.5505, -46.6333];
        if (app.data.evento && app.data.evento.coordenadas && app.data.evento.coordenadas.lat) {
            center = [app.data.evento.coordenadas.lat, app.data.evento.coordenadas.lng];
        }

        this.map = L.map(elementId).setView(center, 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(this.map);

        // Try to locate user (but map starts centered on event)
        this.locateUser();
    },

    setCategoryMeta(categoryMeta) {
        this.categoryMeta = categoryMeta || {};
    },

    normalizeHexColor(color, fallback = '#3B82F6') {
        if (typeof color !== 'string') return fallback;
        const value = color.trim();
        return /^#[0-9A-Fa-f]{6}$/.test(value) ? value.toUpperCase() : fallback;
    },

    locateUser() {
        this.map.locate({ setView: true, maxZoom: 16 });

        this.map.on('locationfound', (e) => {
            const radius = e.accuracy / 2;

            if (this.userMarker) {
                this.map.removeLayer(this.userMarker);
            }

            this.userMarker = L.marker(e.latlng).addTo(this.map)
                .bindPopup("Você está aqui").openPopup();

            L.circle(e.latlng, radius).addTo(this.map);
        });

        this.map.on('locationerror', (e) => {
            console.warn("Location access denied or failed", e.message);
        });
    },

    clearMarkers() {
        this.markers.forEach(marker => this.map.removeLayer(marker));
        this.markers = [];
    },

    addMarkers(locations) {
        this.allLocations = Array.isArray(locations) ? locations : [];
        this.ensureCategoryFilters();
        this.refreshMarkers();
    },

    ensureCategoryFilters() {
        const categories = {};
        this.allLocations.forEach(loc => {
            if (!loc || !loc.lat || !loc.lng) return;
            const slug = loc.category || 'hospital';
            categories[slug] = true;
        });

        if (!this.activeCategories) {
            this.activeCategories = {};
            Object.keys(categories).forEach(slug => {
                this.activeCategories[slug] = true;
            });
            return;
        }

        const next = {};
        Object.keys(categories).forEach(slug => {
            next[slug] = this.activeCategories[slug] !== false;
        });
        this.activeCategories = next;
    },

    isCategoryVisible(slug) {
        if (!this.activeCategories) return true;
        return this.activeCategories[slug] !== false;
    },

    toggleCategory(slug) {
        if (!this.activeCategories || !Object.prototype.hasOwnProperty.call(this.activeCategories, slug)) return;
        this.activeCategories[slug] = !this.activeCategories[slug];
        this.refreshMarkers();
    },

    showAllCategories() {
        if (!this.activeCategories) return;
        Object.keys(this.activeCategories).forEach(slug => {
            this.activeCategories[slug] = true;
        });
        this.refreshMarkers();
    },

    refreshMarkers() {
        this.clearMarkers();
        const legendTotalCount = {};
        const legendVisibleCount = {};

        // Add Event Marker (Center)
        const eventData = app.data.evento;
        if (eventData && eventData.coordenadas && eventData.coordenadas.lat) {
            let eventIcon;

            if (eventData.logo) {
                eventIcon = L.icon({
                    iconUrl: eventData.logo,
                    iconSize: [50, 50], // Adjust size as needed
                    iconAnchor: [25, 25],
                    popupAnchor: [0, -25],
                    className: 'event-marker-logo' // For CSS circular styling
                });
            } else {
                eventIcon = this.createIcon('#2563EB');
            }

            const eventMarker = L.marker([eventData.coordenadas.lat, eventData.coordenadas.lng], { icon: eventIcon })
                .bindPopup(`<b>${eventData.name_evento || 'Evento'}</b><br>${eventData.endereco || ''}`)
                .addTo(this.map);

            this.markers.push(eventMarker);
        }

        this.allLocations.forEach(loc => {
            if (!loc.lat || !loc.lng) return;

            const type = loc.category || 'hospital';
            legendTotalCount[type] = (legendTotalCount[type] || 0) + 1;
            if (!this.isCategoryVisible(type)) return;

            const meta = this.categoryMeta[type] || this.categoryMeta.default || {};
            const color = this.normalizeHexColor(meta.color, '#3B82F6');
            const icon = this.createIcon(color);
            legendVisibleCount[type] = (legendVisibleCount[type] || 0) + 1;

            const marker = L.marker([loc.lat, loc.lng], { icon: icon })
                .bindPopup(`<b>${loc.name}</b><br>${loc.address}<br><button onclick="app.router.navigate('detalhe', {id: ${loc.id}})">Ver Detalhes</button>`);

            marker.addTo(this.map);
            this.markers.push(marker);
        });

        this.renderLegend(legendTotalCount, legendVisibleCount);
    },

    createIcon(color) {
        const safeColor = this.normalizeHexColor(color, '#3B82F6');
        return L.divIcon({
            className: 'zelo-map-marker-wrap',
            html: `<span class="zelo-map-marker-pin" style="background:${safeColor};"></span>`,
            iconSize: [20, 20],
            iconAnchor: [10, 20],
            popupAnchor: [0, -18]
        });
    },

    renderLegend(legendTotalCount, legendVisibleCount) {
        if (!this.map) return;

        if (this.legendControl) {
            this.map.removeControl(this.legendControl);
            this.legendControl = null;
        }

        this.legendControl = L.control({ position: 'topright' });
        this.legendControl.onAdd = () => {
            const container = L.DomUtil.create('div', 'zelo-map-legend');
            const entries = Object.entries(legendTotalCount || {}).sort((a, b) => b[1] - a[1]);
            const bodyHtml = entries.map(([slug, count]) => {
                const meta = this.categoryMeta[slug] || this.categoryMeta.default || {};
                const label = meta.label || slug;
                const color = this.normalizeHexColor(meta.color, '#3B82F6');
                const visible = this.isCategoryVisible(slug);
                const shownCount = legendVisibleCount[slug] || 0;
                return `<div class="zelo-map-legend-item">
                    <span class="zelo-map-legend-dot" style="background:${color};"></span>
                    <span class="zelo-map-legend-label">${label}</span>
                    <span class="zelo-map-legend-count">${shownCount}/${count}</span>
                    <button type="button" class="zelo-map-legend-visibility ${visible ? '' : 'is-off'}" data-slug="${slug}" title="${visible ? 'Ocultar categoria' : 'Mostrar categoria'}">${visible ? '👁' : '🚫'}</button>
                </div>`;
            }).join('');

            container.innerHTML = `
                <button type="button" class="zelo-map-legend-toggle">Legenda</button>
                <div class="zelo-map-legend-body">
                    <button type="button" class="zelo-map-legend-show-all">Mostrar todas</button>
                    ${bodyHtml || '<div class="zelo-map-legend-empty">Sem locais</div>'}
                </div>
            `;

            const toggle = container.querySelector('.zelo-map-legend-toggle');
            toggle.addEventListener('click', () => {
                container.classList.toggle('is-collapsed');
            });
            const showAll = container.querySelector('.zelo-map-legend-show-all');
            if (showAll) {
                showAll.addEventListener('click', () => this.showAllCategories());
            }
            container.querySelectorAll('.zelo-map-legend-visibility').forEach(button => {
                button.addEventListener('click', () => {
                    const slug = button.getAttribute('data-slug');
                    if (!slug) return;
                    this.toggleCategory(slug);
                });
            });
            container.classList.add('is-collapsed');
            L.DomEvent.disableClickPropagation(container);
            L.DomEvent.disableScrollPropagation(container);
            return container;
        };

        this.legendControl.addTo(this.map);
    }
};
