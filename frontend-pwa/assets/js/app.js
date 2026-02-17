const app = {
    data: {
        locais: [],
        evento: null,
        currentCategory: null
    },

    router: {
        currentView: 'home',

        navigate(viewId, params = {}) {
            // Hide current view
            document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));

            // Show new view
            const view = document.getElementById(`view-${viewId}`);
            if (view) {
                view.classList.add('active');
                this.currentView = viewId;

                // Trigger view specific logic
                if (viewId === 'mapa') {
                    setTimeout(() => {
                        MapManager.init('map-container');
                        MapManager.map.invalidateSize(); // Fix leafleft rendering issue in hidden divs
                        MapManager.addMarkers(app.data.locais);
                    }, 100);
                } else if (viewId === 'lista') {
                    app.renderList(params.category);
                } else if (viewId === 'detalhe') {
                    app.renderDetail(params.id);
                } else if (viewId === 'emergencia') {
                    app.renderEmergency();
                } else if (viewId === 'evento') {
                    app.renderEventInfo();
                }
            }
        },

        back() {
            this.navigate('home');
        }
    },

    async init() {
        console.log('Zelo App Initializing...');

        // Mock data loading or real API
        try {
            // Load initial data
            const [locais, evento] = await Promise.all([
                API.getLocais(), // Fetch all initially
                API.getEvento()
            ]);

            this.data.locais = locais || [];
            this.data.evento = evento || {};

            console.log('Data loaded', this.data);

        } catch (err) {
            console.error('Failed to load data', err);
            // Show offline message if needed
        }

        // Handle URL hash if we implement deep linking, for now simple init
        this.router.navigate('home');
    },

    renderList(category) {
        const container = document.getElementById('list-container');
        const title = document.getElementById('list-title');

        let items = this.data.locais;

        if (category) {
            items = items.filter(i => i.category === category);
            title.textContent = category.charAt(0).toUpperCase() + category.slice(1) + 's';
        } else {
            title.textContent = 'Todos os Locais';
        }

        if (items.length === 0) {
            container.innerHTML = '<div class="loading">Nenhum local encontrado.</div>';
            return;
        }

        container.innerHTML = items.map(item => `
            <div class="list-item" onclick="app.router.navigate('detalhe', {id: ${item.id}})">
                <div>
                    <h3>${item.name}</h3>
                    <p>${item.address}</p>
                </div>
                ${item.distance ? `<span class="distance-badge">${item.distance} km</span>` : ''}
            </div>
        `).join('');
    },

    renderDetail(id) {
        const container = document.getElementById('detail-container');
        const item = this.data.locais.find(i => i.id == id); // loose comparison for string/int

        if (!item) {
            container.innerHTML = 'Local não encontrado.';
            return;
        }

        container.innerHTML = `
            <div class="detail-card">
                <h1>${item.name}</h1>
                <p><strong>Categoria:</strong> ${item.category}</p>
                <p><strong>Endereço:</strong> ${item.address}</p>
                <p><strong>Telefone:</strong> <a href="tel:${item.phone}">${item.phone}</a></p>
                <p><strong>Horário:</strong> ${item.hours} ${item.is_24h ? '(24h)' : ''}</p>
                ${item.description ? `<p>${item.description}</p>` : ''}
                
                <div style="margin-top: 2rem; text-align: center;">
                    <a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(item.name + ' ' + item.address)}" target="_blank" class="detail-map-btn">Abrir no Google Maps</a>
                </div>
            </div>
        `;
    },

    renderEmergency() {
        const container = document.getElementById('emergency-container');
        const phones = this.data.evento.telefones_emergencia || [];

        container.innerHTML = phones.map(p => `
            <div class="contact-row">
                <span>${p.nome}</span>
                <a href="tel:${p.numero}" class="call-btn">${p.numero}</a>
            </div>
        `).join('');
    },

    renderEventInfo() {
        const container = document.getElementById('event-container');
        const evt = this.data.evento;

        if (!evt) return;

        container.innerHTML = `
            <div class="detail-card">
                <h1>${evt.name_evento}</h1>
                <p>${evt.endereco}</p>
                
                <h3>Contatos</h3>
                <p>Email: ${evt.contatos.email}</p>
                <p>Site: <a href="${evt.contatos.site}" target="_blank">${evt.contatos.site}</a></p>
            </div>
        `;
    }
};

// Start app
document.addEventListener('DOMContentLoaded', () => {
    app.init();
});
