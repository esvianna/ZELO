const MapManager = {
    map: null,
    markers: [],
    userMarker: null,

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
                eventIcon = this.createIcon('blue');
            }

            const eventMarker = L.marker([eventData.coordenadas.lat, eventData.coordenadas.lng], { icon: eventIcon })
                .bindPopup(`<b>${eventData.name_evento || 'Evento'}</b><br>${eventData.endereco || ''}`)
                .addTo(this.map);

            this.markers.push(eventMarker);
        }

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
        // Using standard Leaflet colored markers
        return new L.Icon({
            iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-${color}.png`,
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });
    }
};
