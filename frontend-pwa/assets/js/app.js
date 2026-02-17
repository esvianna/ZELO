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
        const item = this.data.locais.find(i => i.id == id);

        if (!item) {
            container.innerHTML = 'Local não encontrado.';
            return;
        }

        // --- Data Parsing ---

        // 1. Website extraction
        let website = null;
        let descriptionClean = item.description || '';
        const urlRegex = /(https?:\/\/[^\s]+)/g;
        const urlMatch = descriptionClean.match(urlRegex);
        if (urlMatch) {
            website = urlMatch[0];
            descriptionClean = descriptionClean.replace(urlRegex, '').replace('Site:', '').trim();
        }

        // 2. Hours Parsing
        const daysMap = {
            'Monday': 'Segunda-feira',
            'Tuesday': 'Terça-feira',
            'Wednesday': 'Quarta-feira',
            'Thursday': 'Quinta-feira',
            'Friday': 'Sexta-feira',
            'Saturday': 'Sábado',
            'Sunday': 'Domingo'
        };

        let hoursHtml = '<div class="text-muted">Horário não disponível</div>';
        let isOpen = false; // logic to determine if open would require complex parsing of time ranges
        let statusBadge = '<span class="badge status closed">Fechado/Indisponível</span>';

        if (item.is_24h) {
            hoursHtml = `
                <div class="schedule-table">
                    <div class="schedule-row"><span class="day">Todos os dias</span><span class="hours">24 Horas</span></div>
                </div>`;
            isOpen = true;
            statusBadge = '<span class="badge status">Aberto Agora</span>';
        } else if (item.hours) {
            // "Monday: 8:00 AM – 6:00 PM; Tuesday: ..."
            const hoursArr = item.hours.split(';').map(h => h.trim());

            // Simple check for "Open Now" (Approximation based on current day/hour would be better but requires robust parsing)
            // For MVP, if it's not 24h, we default to "Ver Horários" or similar unless we parse fully.
            // Let's rely on the visual table for user to decide.
            statusBadge = '<span class="badge status closed">Ver Horários</span>';

            hoursHtml = '<div class="schedule-table">';
            const todayEng = new Date().toLocaleDateString('en-US', { weekday: 'long' });

            hoursHtml += hoursArr.map(hStr => {
                const [dayEng, timeRange] = hStr.split(': ', 2);
                if (!daysMap[dayEng]) return '';

                const isToday = dayEng === todayEng;
                const dayPt = daysMap[dayEng];
                const timeClean = timeRange.replace('Closed', 'Fechado').replace('Open 24 hours', '24 Horas');

                return `
                    <div class="schedule-row ${isToday ? 'today' : ''}">
                        <span class="day">${dayPt}</span>
                        <span class="hours">${timeClean}</span>
                    </div>
                `;
            }).join('');
            hoursHtml += '</div>';
        }

        // 3. Map Link
        const mapLink = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(item.name + ' ' + item.address)}`;

        // --- Render HTML ---

        container.innerHTML = `
            <div class="detail-header-wrapper">
                <div class="breadcrumbs">
                    <span onclick="app.router.back()" style="cursor:pointer">Início</span>
                    <span>/</span>
                    <span onclick="app.router.navigate('lista', {category: '${item.category}'})" style="cursor:pointer; text-transform:capitalize;">${item.category}s</span>
                    <span>/</span>
                    <span>${item.name}</span>
                </div>

                <div class="badge-container">
                    <span class="badge category ${item.category}">${item.category === 'farmacia' ? 'Farmácia' : 'Hospital'}</span>
                    ${statusBadge}
                </div>

                <h1 class="detail-title">${item.name}</h1>
                
                <div class="detail-address">
                    <span class="icon">📍</span>
                    <span>${item.address}</span>
                </div>
            </div>

            <div class="action-bar">
                <a href="${mapLink}" target="_blank" class="action-btn primary">
                    <span>🗺️</span> Como chegar
                </a>
                <a href="tel:${item.phone}" class="action-btn outline">
                    <span>📞</span> Ligar agora
                </a>
            </div>

            <div class="detail-grid">
                <!-- Left Column -->
                <div>
                    <div class="info-card">
                        <div class="card-title">
                            <span>ℹ️</span> Observações para Visitantes
                        </div>
                        <ul class="visitor-notes">
                            <li><span class="icon">✓</span> Equipe fala português disponível (Verificar)</li>
                            <li><span class="icon">✓</span> Pagamento aceito: ${descriptionClean || 'Dinheiro, Cartão'}</li>
                            <li><span class="icon">⚠️</span> Recomendado ligar antes em caso de emergência grave.</li>
                        </ul>
                    </div>

                    <div class="map-preview" onclick="window.open('${mapLink}', '_blank')">
                         <!-- Static map or placeholder -->
                         <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#eee; color:#666;">
                            <img src="https://static-maps.yandex.ru/1.x/?lang=en-US&ll=${item.lng},${item.lat}&z=15&l=map&size=600,300&pt=${item.lng},${item.lat},pm2rdm" 
                                 style="width:100%; height:100%; object-fit:cover;" 
                                 alt="Mapa"
                                 onerror="this.style.display='none'; this.parentNode.innerHTML='Visualizar no Mapa Externo'">
                         </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <div class="info-card">
                        <div class="card-title">
                            <span>🕒</span> Horário de Funcionamento
                        </div>
                        ${hoursHtml}
                    </div>

                    <div class="info-card">
                        <div class="card-title">
                            <span>📋</span> Informações Rápidas
                        </div>
                        <div class="info-list">
                            <div class="info-item">
                                <label>Telefone</label>
                                <a href="tel:${item.phone}">${item.phone}</a>
                            </div>
                            ${website ? `
                            <div class="info-item">
                                <label>Website</label>
                                <a href="${website}" target="_blank">Acessar site oficial</a>
                            </div>` : ''}
                            <div class="info-item">
                                <label>Estacionamento</label>
                                <span>Disponível (Consultar local)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="emergency-banner">
                <div class="content">
                    <div class="emergency-icon">🚨</div>
                    <div>
                        <div class="text-bold">Precisa de ajuda imediata?</div>
                        <div style="font-size:0.9rem; opacity:0.8;">Entre em contato com a linha de emergência.</div>
                    </div>
                </div>
                <button class="emergency-btn" onclick="app.router.navigate('emergencia')">
                    Central Zelo
                </button>
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
