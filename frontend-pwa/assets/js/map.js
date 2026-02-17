const MapManager = {
    map: null,
    markers: [],
    userMarker: null,

    init(elementId) {
        if (this.map) return; // Already initialized

        // Default center (Sao Paulo generic or event location)
        this.map = L.map(elementId).setView([-23.5505, -46.6333], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(this.map);

        // Try to locate user
        this.locateUser();
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
        this.clearMarkers();

        const icons = {
            hospital: this.createIcon('red'),
            farmacia: this.createIcon('green'),
            emergencia: this.createIcon('orange')
        };

        locations.forEach(loc => {
            if (!loc.lat || !loc.lng) return;

            const type = loc.category || 'hospital';
            const icon = icons[type] || icons.hospital;

            const marker = L.marker([loc.lat, loc.lng], { icon: icon })
                .bindPopup(`<b>${loc.name}</b><br>${loc.address}<br><button onclick="app.router.navigate('detalhe', {id: ${loc.id}})">Ver Detalhes</button>`);

            marker.addTo(this.map);
            this.markers.push(marker);
        });
    },

    createIcon(color) {
        // Simple colorful markers using standard Leaflet styling or custom images
        // For MVP, we use default L.Icon but we could customize color via CSS filters or custom images
        // Here we just return default for simplicity, but ideally we'd have custom icons
        return new L.Icon.Default();
    }
};
