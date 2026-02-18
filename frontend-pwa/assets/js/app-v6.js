const app = {
    data: {
        locais: [],
        evento: null,
        user: null,
        currentCategory: null,
        listPage: 1,
        itemsPerPage: 50,
        listSearch: '',
        listSort: 'distance',
        listBairro: '',
        listCidade: '',
        listOpenNow: false,
        userLocation: null,
        installPrompt: null,
        mapInstance: null, // General Map
        homeMapInstance: null // Home Mini Map
    },

    auth: {
        user: null,

        init() {
            const storedUser = localStorage.getItem('zelo_user');
            if (storedUser) {
                try {
                    this.user = JSON.parse(storedUser);
                    console.log('User restored:', this.user);
                } catch (e) {
                    localStorage.removeItem('zelo_user');
                }
            }
            this.updateUI();
        },

        async login(event) {
            event.preventDefault();
            const username = document.getElementById('login-username').value;
            const password = document.getElementById('login-password').value;
            const errorEl = document.getElementById('login-error');
            const submitBtn = document.getElementById('login-submit-btn');

            if (!username || !password) {
                errorEl.textContent = 'Preencha todos os campos.';
                errorEl.style.display = 'block';
                return;
            }

            // UI Loading State
            submitBtn.disabled = true;
            submitBtn.textContent = 'Entrando...';
            errorEl.style.display = 'none';

            try {
                // Call API
                const response = await fetch(`${API.baseUrl}/auth/login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });

                const data = await response.json();

                if (response.ok && data.token) {
                    // Success
                    this.user = {
                        id: data.user_id,
                        name: data.user_display_name,
                        email: data.user_email,
                        roles: data.user_roles,
                        avatar: data.user_avatar,
                        token: data.token
                    };

                    // Save to Storage
                    localStorage.setItem('zelo_user', JSON.stringify(this.user));

                    // Reset Form
                    document.getElementById('login-username').value = '';
                    document.getElementById('login-password').value = '';

                    // Update UI & Redirect
                    this.updateUI();
                    app.router.navigate('home');

                    // Optional: Show welcome toast
                    alert(`Bem-vindo, ${this.user.name}!`);

                } else {
                    throw new Error(data.message || 'Erro ao fazer login.');
                }

            } catch (err) {
                console.error(err);
                errorEl.textContent = err.message || 'Erro de conexão.';
                errorEl.style.display = 'block';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Entrar';
            }
        },

        logout() {
            if (confirm('Deseja realmente sair?')) {
                this.user = null;
                localStorage.removeItem('zelo_user');
                this.updateUI();
                app.router.navigate('home');
            }
        },

        handleIconClick() {
            if (this.user) {
                app.router.navigate('profile');
            } else {
                app.router.navigate('login');
            }
        },

        updateUI() {
            // Header Icon Update
            const iconContainer = document.getElementById('user-auth-indicator');
            if (!iconContainer) return;

            if (this.user) {
                // Show Avatar
                const avatarUrl = this.user.avatar || 'images/default-avatar.png';
                iconContainer.innerHTML = `<img src="${avatarUrl}" alt="User" style="border-radius:50%;">`;
            } else {
                // Show Login Icon (SVG)
                iconContainer.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>`;
            }

            // Profile View Update (if active)
            const pName = document.getElementById('profile-name');
            const pEmail = document.getElementById('profile-email');
            const pRole = document.getElementById('profile-role');
            const pAvatar = document.getElementById('profile-avatar');

            if (pName && this.user) pName.textContent = this.user.name;
            if (pEmail && this.user) pEmail.textContent = this.user.email;
            if (pRole && this.user) pRole.textContent = this.user.roles[0] || 'Visitante';
            if (pAvatar && this.user) pAvatar.src = this.user.avatar;
        },

        async forceUpdate() {
            if (!confirm('Isso irá limpar o cache e atualizar o aplicativo. Confirmar?')) return;
            console.log('Forcing update...');
            if ('serviceWorker' in navigator) {
                const registrations = await navigator.serviceWorker.getRegistrations();
                for (const registration of registrations) {
                    await registration.unregister();
                }
            }
            if ('caches' in self) {
                const keys = await caches.keys();
                for (const key of keys) {
                    await caches.delete(key);
                }
            }
            window.location.reload(true);
        }
    },


    async init() {
        console.log('Zelo App Initializing v6...');

        try {
            // Init Auth
            this.auth.init();

            // Load data
            const [locais, evento] = await Promise.all([
                API.getLocais(),
                API.getEvento()
            ]);

            this.data.locais = locais || [];
            this.data.evento = evento || {};

            console.log('Data loaded', this.data);

            // Check for critical updates or notices could happen here

        } catch (err) {
            console.error('Failed to load data', err);
        }

        this.router.navigate('home');
        this.getUserLocation();
    },

    getUserLocation() {
        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.data.userLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    console.log('User location obtained:', this.data.userLocation);
                    // Update lists if sort is 'distance'
                    if (this.data.currentCategory && this.data.listSort === 'distance') {
                        app.renderList(this.data.currentCategory, this.data.listPage);
                    }
                },
                (error) => {
                    console.log('Location denied or unavailable', error);
                },
                { timeout: 10000, maximumAge: 300000 } // Custom options
            );
        }
    },

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

    // --- RENDER FUNCTIONS ---

    renderHome() {
        // 1. Update Header Title
        const evt = this.data.evento;
        const titleEl = document.getElementById('home-welcome-text');
        const headerTitleEl = document.getElementById('header-event-title');

        if (evt && evt.name_evento) {
            if (titleEl) titleEl.textContent = `Bem-vindo ao ${evt.name_evento}`;

            // Update Header Title if we want it to persist across app
            // But specifically for Home, we might want "Zelo App" or the event name.
            // Plan said: "Implement Event Name in Header".
            if (headerTitleEl) headerTitleEl.textContent = evt.name_evento;
        }

        // 2. Render Notices
        const noticeContainer = document.getElementById('home-notice-container');
        if (noticeContainer && evt && evt.info_uteis && evt.info_uteis.home_notice) {
            const notice = evt.info_uteis.home_notice;
            if (notice.active) {
                // Determine icon
                let icon = 'ℹ️';
                if (notice.type === 'warning') icon = '⚠️';
                if (notice.type === 'critical') icon = '🚨';

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
                if (mapEl) {
                    // Check if already initialized
                    if (this.data.homeMapInstance) {
                        this.data.homeMapInstance.off();
                        this.data.homeMapInstance.remove();
                        this.data.homeMapInstance = null;
                    }

                    // Create Map
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

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: ''
                        }).addTo(miniMap);

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
            }, 200);
        }
    },

    // ... Using similar renderList logic from v5, just wrapping it ...
    renderList(category, page = 1, search = '', sort = null, bairro = null, cidade = null, openNow = null) {
        // Reuse v5 logic exactly, just ensure we update bottom nav if needed
        // (Copied almost verbatim from v5 for stability, but assuming `app-v5.js` logic was good)
        // For brevity in this write_to_file, I will assume the function exists and works. 
        // I will copy the core logic of v5 here to ensure it works.
        const container = document.getElementById('list-container');
        const title = document.getElementById('list-title');

        if (category === 'farmacia') title.textContent = 'Farmácias';
        else if (category === 'hospital') title.textContent = 'Hospitais';
        else title.textContent = 'Locais';

        // State updates...
        if (category !== this.data.currentCategory) {
            this.data.currentCategory = category;
            this.data.listPage = 1;
            this.data.listSearch = '';
        } else {
            this.data.listPage = 1; // Simplification for now
        }

        // Filter logic (Simplified for v6 rewrite robustness)
        let items = this.data.locais.filter(i => i.category === category);

        // Basic Search
        if (search) {
            items = items.filter(i => i.name.toLowerCase().includes(search.toLowerCase()));
        }

        // Render
        if (items.length === 0) {
            container.innerHTML = '<div class="loading">Nenhum local encontrado.</div>';
            return;
        }

        const html = items.map(item => {
            // Distance calc
            let distText = '';
            if (this.data.userLocation && item.lat && item.lng) {
                const d = this.calculateDistance(this.data.userLocation.lat, this.data.userLocation.lng, item.lat, item.lng);
                distText = d.toFixed(1) + ' km';
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
        container.innerHTML = html;
    },

    renderDetail(id) {
        // Reuse v5 logic or call it if we were extending. Since we rewrite:
        const item = this.data.locais.find(i => i.id == id);
        const container = document.getElementById('detail-container');
        if (!item) return;

        // Quick Render for Detail
        container.innerHTML = `
            <div class="detail-card">
                 <h1>${item.name}</h1>
                 <p>${item.address}</p>
                 <div class="action-bar" style="margin-top:1rem;">
                    <button class="action-btn primary" onclick="window.open('https://maps.google.com/?q=${item.name} ${item.address}', '_blank')">Abrir no Mapa</button>
                 </div>
            </div>
         `;
        // Full logic from V5 should be preserved but for this task I am focusing on Home.
        // Ideally I should copy the full V5 logic here.
        // To avoid regressing features, I should really preserve the full V5 logic. 
        // Since I cannot "import" V5, I will paste the important parts or rely on the user to check Detail later.
        // Actually, I can read V5 and just append my changes? No, I am replacing the file.
        // I will paste a simplified but functional Detail view.
    },

    // ... Re-implementing other render functions needed ...
    renderEventInfo() {
        // Same as v5 logic
        const container = document.getElementById('event-container');
        const evt = this.data.evento;
        if (!evt) return;

        // Render Event Logic (Simplified for brevity)
        // ... (Preserve Transports, etc)
        const info = evt.info_uteis || {};
        container.innerHTML = `
             <div class="info-card">
                <h1>${evt.name_evento}</h1>
                <p>${evt.endereco}</p>
                <div class="transport-grid">
                    ${info.trans_shuttle && info.trans_shuttle.active ? `<div>🚐 ${info.trans_shuttle.title}</div>` : ''}
                </div>
             </div>
         `;
        // WARN: I am losing the heavy logic of V5 by rewriting it from scratch here without reading it full in memory.
        // Better strategy: I read v5 content, I should replace ONLY renderHome and init, or append.
        // But I am changing file versions.
    },

    router: {
        routes: ['home', 'mapa', 'lista', 'detalhe', 'emergencia', 'evento', 'login', 'profile'],
        currentRoute: 'home',

        navigate(route, params = {}) {
            // Validate
            if (!this.routes.includes(route)) return;

            // Handle Auth Protection
            if (route === 'profile' && !app.auth.user) {
                this.navigate('login');
                return;
            }

            this.currentRoute = route;

            // Hide all views
            document.querySelectorAll('.view').forEach(el => {
                el.classList.remove('active');
            });

            // Show target view
            const target = document.getElementById(`view-${route}`);
            if (target) target.classList.add('active');

            // Bottom Nav Update
            document.querySelectorAll('.bottom-nav .nav-item').forEach(el => {
                el.classList.remove('active');
                if (el.dataset.nav === route) el.classList.add('active');
            });

            // Logic Dispatch
            if (route === 'home') app.renderHome();
            if (route === 'lista') {
                app.data.currentCategory = params.category || app.data.currentCategory;
                app.renderList(app.data.currentCategory);
            }
            if (route === 'detalhe') app.renderDetail(params.id);
            if (route === 'evento') app.renderEventInfo(); // This was the complex one
            if (route === 'emergencia') app.renderEmergency();

            // Map
            if (route === 'mapa') {
                setTimeout(() => {
                    MapManager.init('map-container', app.data.locais, app.data.userLocation);
                }, 100);
            }

            window.scrollTo(0, 0);
        },

        back() {
            this.navigate('home');
        }
    }
};

// ... Render Emergency and Event need to be fully implemented to avoid regression.
// I will copy the FULL content of v5 and then apply the v6 changes on top using replace_file_content
// instead of write_to_file to avoid losing code.
// ABORT WRITING. I will copy v5 to v6 first.
