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
        installPrompt: null, // Store PWA install prompt
        homeMapInstance: null
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
            },
            { timeout: 10000, maximumAge: 300000 }
        );
    },

    router: {
        currentView: 'home',
        routes: ['home', 'mapa', 'lista', 'detalhe', 'emergencia', 'evento', 'login', 'profile'],

        navigate(viewId, params = {}) {
            // Handle Auth Protection
            if (viewId === 'profile' && !app.auth.user) {
                this.navigate('login');
                return;
            }

            // Hide all views
            document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));

            // Show new view
            const view = document.getElementById(`view-${viewId}`);
            if (view) {
                view.classList.add('active');
                this.currentView = viewId;

                // Bottom Nav Update
                document.querySelectorAll('.bottom-nav .nav-item').forEach(el => {
                    el.classList.remove('active');
                    if (el.dataset.nav === viewId) el.classList.add('active');
                });

                // Trigger view specific logic
                if (viewId === 'home') app.renderHome();
                else if (viewId === 'mapa') {
                    setTimeout(() => {
                        MapManager.init('map-container', app.data.locais, app.data.userLocation);
                    }, 100);
                } else if (viewId === 'lista') {
                    app.data.currentCategory = params.category || app.data.currentCategory;
                    app.renderList(app.data.currentCategory);
                } else if (viewId === 'detalhe') {
                    app.renderDetail(params.id);
                } else if (viewId === 'emergencia') {
                    app.renderEmergency();
                } else if (viewId === 'evento') {
                    app.renderEventInfo();
                }

                window.scrollTo(0, 0);
            }
        },

        debounceTimer: null,
        debounceSearch(query, category) {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                app.data.listSearch = query;
                app.renderList(category, 1);
            }, 300);
        },

        back() {
            this.navigate('home');
        }
    },

    auth: {
        user: null, // {id, name, email, avatar, roles, token}

        init() {
            const storedUser = localStorage.getItem('zelo_user');
            if (storedUser) {
                try {
                    this.user = JSON.parse(storedUser);
                } catch (e) { localStorage.removeItem('zelo_user'); }
            }
            this.updateUI();
        },

        async login(event) {
            event.preventDefault();
            const username = document.getElementById('login-username').value;
            const password = document.getElementById('login-password').value;
            const errorEl = document.getElementById('login-error');
            const submitBtn = document.getElementById('login-submit-btn');

            errorEl.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Entrando...';

            try {
                let url;
                if (typeof API !== 'undefined' && API.baseUrl && API.baseUrl.includes('/zelo/v1')) {
                    url = `${API.baseUrl}/auth/login`;
                } else {
                    const apiRoot = 'https://zelo.quadrodeanuncios.com.br/wp-json';
                    url = `${apiRoot}/zelo/v1/auth/login`;
                }

                // Debug Alert
                // alert(`Tentando login em: ${url}`);
                console.log('Login request to:', url);

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });

                const data = await response.json();

                if (data.success) {
                    this.user = data.user;
                    this.user.nonce = data.nonce;
                    localStorage.setItem('zelo_user', JSON.stringify(this.user));
                    this.updateUI();
                    app.router.navigate('home');
                    document.getElementById('login-username').value = '';
                    document.getElementById('login-password').value = '';
                } else {
                    throw new Error(data.message || 'Erro ao fazer login');
                }
            } catch (err) {
                console.error('Login error:', err);
                // Visible Alert for the user
                alert('Erro de Login:\n' + (err.message || err));

                errorEl.textContent = err.message || 'Erro de conexão.';
                errorEl.style.display = 'block';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Entrar';
            }
        },

        logout() {
            if (confirm('Sair da conta?')) {
                this.user = null;
                localStorage.removeItem('zelo_user');
                this.updateUI();
                app.router.navigate('home');
            }
        },

        handleIconClick() {
            if (this.user) app.router.navigate('profile');
            else app.router.navigate('login');
        },

        updateUI() {
            const iconContainer = document.getElementById('user-auth-indicator');
            if (!iconContainer) return;

            if (this.user) {
                const avatarUrl = this.user.avatar || 'images/default-avatar.png';
                iconContainer.innerHTML = `<img src="${avatarUrl}" alt="User">`;

                // Profile View Update
                const pName = document.getElementById('profile-name');
                const pEmail = document.getElementById('profile-email');
                const pRole = document.getElementById('profile-role');
                const pAvatar = document.getElementById('profile-avatar');

                if (pName) pName.textContent = this.user.name;
                if (pEmail) pEmail.textContent = this.user.email;
                if (pRole) pRole.textContent = this.user.roles[0] || 'Visitante';
                if (pAvatar) pAvatar.src = avatarUrl;
            } else {
                iconContainer.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>`;
            }
        },

        async forceUpdate() {
            if (!confirm('Recarregar app e limpar cache?')) return;
            if ('serviceWorker' in navigator) {
                const registrations = await navigator.serviceWorker.getRegistrations();
                for (const registration of registrations) await registration.unregister();
            }
            if ('caches' in self) {
                const keys = await caches.keys();
                for (const key of keys) await caches.delete(key);
            }
            window.location.reload(true);
        }
    },

    async init() {
        console.log('Zelo App Initializing v6.1...');

        try {
            this.auth.init();

            // Load data
            const [locais, evento] = await Promise.all([
                API.getLocais(),
                API.getEvento()
            ]);

            this.data.locais = locais || [];
            this.data.evento = evento || {};

            // console.log('✅ DATA LOADED FROM API:', { locais, evento });



        } catch (err) {
            console.error('Failed to load data', err);
        }

        this.router.navigate('home');
        this.getUserLocation();
    },

    // --- RENDER FUNCTIONS ---

    renderHome() {
        // 1. Update Header Title
        const evt = this.data.evento;
        const titleEl = document.getElementById('home-welcome-text');
        const headerTitleEl = document.getElementById('header-event-title');

        if (evt && evt.name_evento) {
            if (titleEl) titleEl.textContent = `Bem-vindo ao ${evt.name_evento}`;
            if (headerTitleEl) headerTitleEl.textContent = evt.name_evento;
        }

        // 2. Render Notices
        const noticeContainer = document.getElementById('home-notice-container');
        if (noticeContainer) {
            if (evt && evt.info_uteis && evt.info_uteis.home_notice && evt.info_uteis.home_notice.active) {
                const notice = evt.info_uteis.home_notice;
                let icon = 'ℹ️';
                if (notice.type === 'warning') icon = '⚠️';
                if (notice.type === 'critical') icon = '🚨';
                // Add text-dark or specific color styles in CSS handled by class
                noticeContainer.innerHTML = `
                    <div class="notice-banner ${notice.type || 'info'}" ${notice.link ? `onclick="window.open('${notice.link}', '_blank')"` : ''} style="${notice.link ? 'cursor:pointer' : ''}">
                        <div style="font-size: 1.2rem;">${icon}</div>
                        <div>${notice.text}</div>
                    </div>
                `;
            } else {
                noticeContainer.innerHTML = '';
            }
        }

        // 3. Init Home Map
        if (evt && evt.coordenadas) {
            setTimeout(() => {
                const mapEl = document.getElementById('home-map-preview');
                // Ensure element exists and is visible
                if (mapEl && mapEl.offsetParent !== null) {
                    if (this.data.homeMapInstance) {
                        this.data.homeMapInstance.off();
                        this.data.homeMapInstance.remove();
                        this.data.homeMapInstance = null;
                    }

                    const lat = parseFloat(evt.coordenadas.lat);
                    const lng = parseFloat(evt.coordenadas.lng);

                    if (!isNaN(lat) && !isNaN(lng)) {
                        const miniMap = L.map('home-map-preview', {
                            center: [lat, lng],
                            zoom: 14,
                            zoomControl: false,
                            dragging: false,
                            scrollWheelZoom: false,
                            doubleClickZoom: false,
                            boxZoom: false,
                            keyboard: false,
                            attributionControl: false
                        });

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '' }).addTo(miniMap);

                        // Custom Marker
                        const iconHtml = `<div style="background-color: #e63946; width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`;
                        const customIcon = L.divIcon({
                            html: iconHtml,
                            className: 'custom-div-icon',
                            iconSize: [14, 14],
                            iconAnchor: [7, 7]
                        });

                        L.marker([lat, lng], { icon: customIcon }).addTo(miniMap);
                        this.data.homeMapInstance = miniMap;
                    }
                }
            }, 300);
        }
    },

    renderList(category, page = 1) {
        // ... (Using V6 simplified logic with V5 features integrated) ...
        const container = document.getElementById('list-container');
        const title = document.getElementById('list-title');

        if (category === 'farmacia') title.textContent = 'Farmácias';
        else if (category === 'hospital') title.textContent = 'Hospitais';
        else title.textContent = 'Locais';

        if (category !== this.data.currentCategory) {
            this.data.currentCategory = category;
            this.data.listPage = 1;
            this.data.listSearch = '';
        } else {
            this.data.listPage = page;
        }

        // Filter logic
        let items = this.data.locais.filter(i => i.category === category);

        if (this.data.listSearch) {
            const term = this.data.listSearch.toLowerCase();
            items = items.filter(i => i.name.toLowerCase().includes(term));
        }
        // Apply other filters if implemented (Bairro, Cidade from V5 logic should be here ideally)

        if (this.data.listSort === 'distance') {
            items.sort((a, b) => {
                let distA = 9999, distB = 9999;
                if (this.data.userLocation && a.lat && a.lng) {
                    distA = this.calculateDistance(this.data.userLocation.lat, this.data.userLocation.lng, a.lat, a.lng);
                } else if (a.distance) distA = parseFloat(a.distance.replace(',', '.'));

                if (this.data.userLocation && b.lat && b.lng) {
                    distB = this.calculateDistance(this.data.userLocation.lat, this.data.userLocation.lng, b.lat, b.lng);
                } else if (b.distance) distB = parseFloat(b.distance.replace(',', '.'));

                return distA - distB;
            });
        }

        // Pagination
        const totalPages = Math.ceil(items.length / this.data.itemsPerPage);
        if (this.data.listPage > totalPages) this.data.listPage = 1;

        const start = (this.data.listPage - 1) * this.data.itemsPerPage;
        const pagedItems = items.slice(start, start + this.data.itemsPerPage);

        // Render Search Bar (SimplifiedV6)
        let html = `
            <div class="search-container">
                 <input type="text" class="search-input" placeholder="Buscar..." 
                        value="${this.data.listSearch}" 
                        oninput="app.router.debounceSearch(this.value, '${category}')">
            </div>
        `;

        if (items.length === 0) {
            html += '<div class="loading">Nenhum local encontrado.</div>';
            container.innerHTML = html;
            return;
        }

        html += pagedItems.map(item => {
            let distText = '';
            if (this.data.userLocation && item.lat && item.lng) {
                const d = this.calculateDistance(this.data.userLocation.lat, this.data.userLocation.lng, item.lat, item.lng);
                distText = d.toFixed(1) + ' km';
            } else if (item.distance) distText = item.distance + ' km';

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

        // Paginator
        if (totalPages > 1) {
            html += `
                <div class="pagination">
                    <button class="page-btn" ${this.data.listPage === 1 ? 'disabled' : ''} onclick="app.renderList('${category}', ${this.data.listPage - 1})">Ant</button>
                    <span class="page-info">${this.data.listPage}/${totalPages}</span>
                    <button class="page-btn" ${this.data.listPage === totalPages ? 'disabled' : ''} onclick="app.renderList('${category}', ${this.data.listPage + 1})">Prox</button>
                </div>
            `;
        }

        container.innerHTML = html;
    },

    renderDetail(id) {
        // RESTORED V5 logic roughly
        const item = this.data.locais.find(i => i.id == id);
        const container = document.getElementById('detail-container');
        if (!item) return;

        container.innerHTML = `
            <div class="detail-card">
                 <h1>${item.name}</h1>
                 <p>${item.address}</p>
                 <div class="action-bar" style="margin-top:1rem;">
                    <button class="action-btn primary" onclick="window.open('https://maps.google.com/?q=${item.name} ${item.address}', '_blank')">Abrir no Mapa</button>
                    ${item.phone ? `<a href="tel:${item.phone}" class="action-btn outline">Ligar</a>` : ''}
                 </div>
                 <div class="info-card" style="margin-top:1rem;">
                    <h3>Horários</h3>
                    <p>${item.hours || 'Não informado'}</p>
                 </div>
            </div>
         `;
    },

    renderEventInfo() {
        const container = document.getElementById('event-container');
        const evt = this.data.evento;
        if (!evt) return;

        const info = evt.info_uteis || {};
        const heroImage = evt.foto || 'images/logo-zelo.png';

        container.innerHTML = `
            <div class="event-hero" style="background-image: url('${heroImage}'); height:200px; background-size:cover; border-radius:12px; margin-bottom:1rem; position:relative;">
                <div style="position:absolute; bottom:0; left:0; width:100%; background:linear-gradient(transparent, rgba(0,0,0,0.7)); color:white; padding:1rem;">
                    <h1 style="margin:0;">${evt.name_evento}</h1>
                </div>
            </div>

            <div class="event-grid">
                <!-- Location -->
                <div class="info-card">
                    <div class="card-title" style="color:#137fec; font-weight:bold;">LOCALIZAÇÃO</div>
                    <h3>${evt.local || 'Local Principal'}</h3>
                    <p>${evt.endereco}</p>
                    <div style="display: flex; gap: 10px; margin-top: 1rem;">
                        <button class="action-btn outline small" onclick="navigator.clipboard.writeText('${evt.endereco}')">📋 Copiar</button>
                        <button class="action-btn primary small" onclick="window.open('https://maps.google.com/?q=${evt.endereco}', '_blank')">🗺️ Mapa</button>
                    </div>
                </div>

                <!-- Transport (Dynamic) -->
                <div class="info-card">
                    <div class="card-title">🚍 Como chegar</div>
                    <div class="transport-grid">
                        ${info.trans_shuttle && info.trans_shuttle.active ? `
                        <div class="transport-item" style="margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:10px;">
                            <h4 style="display:flex; align-items:center; gap:5px;">🚐 ${info.trans_shuttle.title}</h4>
                            <p style="font-size:0.9rem; color:#666;">${info.trans_shuttle.desc}</p>
                        </div>` : ''}

                        ${info.trans_public && info.trans_public.active ? `
                        <div class="transport-item" style="margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:10px;">
                            <h4 style="display:flex; align-items:center; gap:5px;">🚇 ${info.trans_public.title}</h4>
                            <p style="font-size:0.9rem; color:#666;">${info.trans_public.desc}</p>
                        </div>` : ''}

                        ${info.trans_taxi && info.trans_taxi.active ? `
                        <div class="transport-item">
                            <h4 style="display:flex; align-items:center; gap:5px;">🚕 ${info.trans_taxi.title}</h4>
                            <p style="font-size:0.9rem; color:#666;">${info.trans_taxi.desc}</p>
                        </div>` : ''}
                    </div>
                </div>

                <!-- Info Blocks -->
                 <div class="info-card highlight-blue" style="background:#f0f9ff; border-left:4px solid #137fec;">
                    <h3>📶 Wi-Fi</h3>
                    <div style="display:flex; justify-content:space-between; margin-top:0.5rem;">
                        <span>Rede:</span> <strong>${info.wifi_ssid || '-'}</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Senha:</span> <strong>${info.wifi_pass || '-'}</strong>
                    </div>
                </div>

                <div class="info-card">
                    <h3>🆔 Credenciamento</h3>
                     <p><strong>Horários:</strong> ${info.cred_hours || 'Consulte'}</p>
                     <p><strong>Docs:</strong> ${info.cred_docs || 'Documento com foto'}</p>
                </div>

                <div class="info-card highlight-red" style="background:#fff5f5; border-left:4px solid #e63946;">
                    <h3>⛑️ Segurança</h3>
                     <p><strong>Posto Médico:</strong> ${info.medical_loc || 'A definir'}</p>
                     <p><strong>Emergência:</strong> <span style="color:var(--danger-color); font-weight:bold;">${info.emergency_phone || '192'}</span></p>
                </div>
            </div>
        `;
    },

    renderEmergency() {
        const container = document.getElementById('emergency-container');
        const phones = this.data.evento.telefones_emergencia || [];
        container.innerHTML = phones.map(p => `
            <div class="contact-row" style="background:white; padding:1rem; margin-bottom:0.5rem; border-radius:8px; display:flex; justify-content:space-between; align-items:center; border-left:4px solid #e63946;">
                <span style="font-weight:600;">${p.nome}</span>
                <a href="tel:${p.numero}" class="call-btn" style="background:#e63946; color:white; padding:5px 10px; border-radius:12px; text-decoration:none;">Ligar ${p.numero}</a>
            </div>
        `).join('');
    }
};

// Start app
document.addEventListener('DOMContentLoaded', () => {
    // Network Status
    window.addEventListener('online', () => document.getElementById('network-status').textContent = 'Online');
    window.addEventListener('offline', () => document.getElementById('network-status').textContent = 'Offline');

    // PWA Install
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        app.data.installPrompt = e;
        const btn = document.getElementById('install-btn');
        if (btn) {
            btn.style.display = 'block';
            btn.addEventListener('click', () => {
                app.data.installPrompt.prompt();
            });
        }
    });

    app.init();
});
