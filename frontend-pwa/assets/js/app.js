const app = {
    data: {
        locais: [],
        evento: null,
        currentCategory: null,
        // Pagination & Filter State
        listPage: 1,
        listSearch: '',
        listSort: 'distance', // 'distance' or 'alpha'
        listBairro: '',
        listCidade: '',
        listOpenNow: false,
        itemsPerPage: 10,
        userLocation: null, // {lat, lng}
    },

    // --- Helpers ---
    calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Radius of the earth in km
        const dLat = this.deg2rad(lat2 - lat1);
        const dLon = this.deg2rad(lon2 - lon1);
        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(this.deg2rad(lat1)) * Math.cos(this.deg2rad(lat2)) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        const d = R * c; // Distance in km
        return d;
    },

    deg2rad(deg) {
        return deg * (Math.PI / 180);
    },

    getUserLocation() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.data.userLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                console.log('User location obtained:', this.data.userLocation);
                // Re-render if current view is list and sorted by distance
                if (this.router.currentView === 'lista' && this.data.listSort === 'distance') {
                    this.renderList(this.data.currentCategory);
                }
            },
            (error) => {
                console.warn('Geolocation error:', error);
            }
        );
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



        debounceTimer: null,
        debounceSearch(query, category) {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                // When searching, reset to page 1
                app.renderList(category, 1, query);

                // Focus management hack: refocs input after render
                setTimeout(() => {
                    const input = document.querySelector('.search-input');
                    if (input) {
                        input.focus();
                        input.setSelectionRange(input.value.length, input.value.length);
                    }
                }, 50);
            }, 300);
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

        // Request location
        this.getUserLocation();
    },

    renderList(category, page = 1, search = '', sort = null, bairro = null, cidade = null, openNow = null) {
        const container = document.getElementById('list-container');
        const title = document.getElementById('list-title');

        // Update State (if params provided)
        if (category !== this.data.currentCategory) {
            this.data.currentCategory = category;
            this.data.listPage = 1;
            this.data.listSearch = '';
            this.data.listSort = 'distance';
            this.data.listBairro = '';
            this.data.listCidade = '';
            this.data.listOpenNow = false;
        } else {
            this.data.listPage = page;
            if (search !== null) this.data.listSearch = search;
            if (sort !== null) this.data.listSort = sort;
            if (bairro !== null) this.data.listBairro = bairro;
            if (cidade !== null) this.data.listCidade = cidade;
            if (openNow !== null) this.data.listOpenNow = openNow;
        }

        let items = this.data.locais;

        // 0. Pre-process items to extract metadata (Bairro, Cidade) if not present
        // This is a naive extraction based on typical "Address - Bairro, Cidade - UF" format
        items.forEach(item => {
            if (!item._bairro || !item._cidade) {
                const parts = item.address ? item.address.split('-') : [];
                if (parts.length >= 3) {
                    item._bairro = parts[parts.length - 3].trim();
                    item._cidade = parts[parts.length - 2].trim().split(',')[0].trim();
                } else if (parts.length === 2) {
                    // Fallback for simpler addresses
                    const subParts = parts[1].split(',');
                    if (subParts.length >= 2) {
                        item._cidade = subParts[0].trim();
                    }
                }
            }
        });

        // 1. Extract Unique Options for Filters (based on current category items)
        const categoryItems = category ? items.filter(i => i.category === category) : items;
        const bairros = [...new Set(categoryItems.map(i => i._bairro).filter(Boolean))].sort();
        const cidades = [...new Set(categoryItems.map(i => i._cidade).filter(Boolean))].sort();

        // 2. Filter Pipeline
        let filtered = categoryItems;

        // Search
        if (this.data.listSearch) {
            const term = this.data.listSearch.toLowerCase();
            filtered = filtered.filter(i =>
                i.name.toLowerCase().includes(term) ||
                (i.address && i.address.toLowerCase().includes(term))
            );
        }

        // Bairro
        if (this.data.listBairro) {
            filtered = filtered.filter(i => i._bairro === this.data.listBairro);
        }

        // Cidade
        if (this.data.listCidade) {
            filtered = filtered.filter(i => i._cidade === this.data.listCidade);
        }

        // Open Now
        if (this.data.listOpenNow) {
            filtered = filtered.filter(i => {
                const now = new Date();
                const currentDay = now.toLocaleDateString('pt-BR', { weekday: 'long' }).toLowerCase();
                const currentHour = now.getHours();
                const currentMin = now.getMinutes();
                const currentTime = currentHour * 60 + currentMin;

                // Simple check: is_24h or basic parsing (reuse parsing logic from renderDetail conceptually)
                if (i.is_24h == '1') return true;
                if (!i.hours) return false; // Assume closed if no hours data

                // Parsing simplified logic here or strictly reuse logic if extracted
                // For MVP, checking is_24h is the most reliable signal we added. 
                // Parsing text hours "Segunda-feira: 08:00 - 18:00" is complex to do accurately without the structured parser.
                // Let's assume for now "Aberto Agora" relies strongly on 24h OR if we implement the parser here.
                // Given user request "Abertas Agora" (plural), let's try to be smart.

                // If we want to support non-24h, we need the parser. 
                // For safety in this step, let's filter by 24h explicitly as it's 100% sure, 
                // and maybe todo: extract parseHours to a helper to use here.
                return i.is_24h == '1';
            });
        }

        // 3. Sort
        if (this.data.listSort === 'distance') {
            filtered.sort((a, b) => {
                let distA, distB;

                // Prefer user location if available
                if (this.data.userLocation && a.lat && a.lng && b.lat && b.lng) {
                    distA = this.calculateDistance(this.data.userLocation.lat, this.data.userLocation.lng, a.lat, a.lng);
                    distB = this.calculateDistance(this.data.userLocation.lat, this.data.userLocation.lng, b.lat, b.lng);
                    // Add temporary property for display (optional, or just rely on sort)
                    a._userDistance = distA;
                    b._userDistance = distB;
                } else {
                    // Fallback to server distance
                    distA = parseFloat((a.distance || '9999').replace(',', '.'));
                    distB = parseFloat((b.distance || '9999').replace(',', '.'));
                }

                return distA - distB;
            });
        } else if (this.data.listSort === 'alpha') {
            filtered.sort((a, b) => a.name.localeCompare(b.name));
        }

        // 4. Pagination
        const totalItems = filtered.length;
        const totalPages = Math.ceil(totalItems / this.data.itemsPerPage);

        if (this.data.listPage > totalPages) this.data.listPage = totalPages || 1;
        if (this.data.listPage < 1) this.data.listPage = 1;

        const start = (this.data.listPage - 1) * this.data.itemsPerPage;
        const end = start + this.data.itemsPerPage;
        const paginatedItems = filtered.slice(start, end);

        // --- Render ---

        let html = `
            <div class="search-container">
                <!-- Text Search -->
                <div style="display:flex; width:100%;">
                     <input type="text" 
                        class="search-input" 
                        placeholder="Buscar por nome..." 
                        value="${this.data.listSearch}"
                        oninput="app.router.debounceSearch(this.value, '${category}')">
                </div>

                <!-- Filters Row -->
                <div class="filter-row">
                    <!-- Sort -->
                    <select class="filter-select" onchange="app.renderList('${category}', 1, null, this.value)">
                        <option value="distance" ${this.data.listSort === 'distance' ? 'selected' : ''}>Perto de mim</option>
                        <option value="alpha" ${this.data.listSort === 'alpha' ? 'selected' : ''}>A-Z</option>
                    </select>

                    <!-- Bairro -->
                    <select class="filter-select" onchange="app.renderList('${category}', 1, null, null, this.value)">
                        <option value="">Bairro</option>
                        ${bairros.map(b => `<option value="${b}" ${this.data.listBairro === b ? 'selected' : ''}>${b}</option>`).join('')}
                    </select>

                     <!-- Cidade -->
                    <select class="filter-select" onchange="app.renderList('${category}', 1, null, null, null, this.value)">
                        <option value="">Cidade</option>
                        ${cidades.map(c => `<option value="${c}" ${this.data.listCidade === c ? 'selected' : ''}>${c}</option>`).join('')}
                    </select>

                    <!-- Open Now Toggle -->
                     <div class="filter-toggle ${this.data.listOpenNow ? 'active' : ''}" 
                          onclick="app.renderList('${category}', 1, null, null, null, null, !${this.data.listOpenNow})">
                        <span>🕒 Aberto Agora</span>
                     </div>
                </div>
            </div>
        `;

        if (totalItems === 0) {
            html += '<div class="loading">Nenhum local encontrado com estes filtros.</div>';

            // Allow clearing filters
            html += `<div style="text-align:center; margin-top:1rem;">
                        <button class="call-btn" onclick="app.renderList('${category}', 1, '', 'distance', '', '', false)">Limpar Filtros</button>
                     </div>`;

            container.innerHTML = html;
            return;
        }

        // List Items
        html += paginatedItems.map(item => {
            // Determine distance text
            let distText = '';
            if (this.data.userLocation && item._userDistance) {
                distText = item._userDistance.toFixed(1) + ' km';
            } else if (item.distance) {
                distText = item.distance + ' km';
            }

            return `
            <div class="list-item" onclick="app.router.navigate('detalhe', {id: ${item.id}})">
                <div>
                    <h3>${item.name}</h3>
                    <p>${item.address}</p>
                </div>
                ${distText ? `<span class="distance-badge">${distText}</span>` : ''}
            </div>
            `;
        }).join('');

        // Pagination Controls
        if (totalPages > 1) {
            html += `
                <div class="pagination">
                    <button class="page-btn" 
                            ${this.data.listPage === 1 ? 'disabled' : ''} 
                            onclick="app.renderList('${category}', ${this.data.listPage - 1})">
                        Anterior
                    </button>
                    
                    <span class="page-info">Página ${this.data.listPage} de ${totalPages}</span>
                    
                    <button class="page-btn active" 
                            ${this.data.listPage === totalPages ? 'disabled' : ''} 
                            onclick="app.renderList('${category}', ${this.data.listPage + 1})">
                        Próximo
                    </button>
                </div>
            `;
        }

        container.innerHTML = html;
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

                    <div class="map-preview" id="detail-map-preview" style="cursor: pointer;">
                        <!-- Map will be injected here -->
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

        // Initialize Mini Map
        if (item.lat && item.lng) {
            setTimeout(() => {
                const mapEl = document.getElementById('detail-map-preview');
                if (mapEl) {
                    const miniMap = L.map('detail-map-preview', {
                        center: [item.lat, item.lng],
                        zoom: 15,
                        zoomControl: false,
                        dragging: false,
                        scrollWheelZoom: false,
                        doubleClickZoom: false,
                        boxZoom: false,
                        keyboard: false,
                        attributionControl: false
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: ''
                    }).addTo(miniMap);

                    // Add colored marker based on category
                    const icon = MapManager.createIcon(item.category === 'farmacia' ? 'green' : (item.category === 'hospital' ? 'red' : 'blue'));
                    L.marker([item.lat, item.lng], { icon: icon }).addTo(miniMap);

                    // Click to open Google Maps
                    mapEl.addEventListener('click', () => {
                        window.open(mapLink, '_blank');
                    });
                }
            }, 100);
        }
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
